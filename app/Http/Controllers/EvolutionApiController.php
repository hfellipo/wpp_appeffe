<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppInstance;
use App\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EvolutionApiController extends Controller
{
    private EvolutionApiService $evolutionApi;

    public function __construct(EvolutionApiService $evolutionApi)
    {
        $this->evolutionApi = $evolutionApi;
    }

    /**
     * Show WhatsApp configuration page.
     */
    public function index(): View
    {
        try {
            $statusData = [];
            $webhook = [];
            $instanceName = 'N/A';
            $configured = false;
            
            try {
                $statusData = $this->evolutionApi->getInstanceStatus();
                $webhook = $this->evolutionApi->getWebhook();
                $instanceName = $this->evolutionApi->getInstanceName();
                
                // Ensure webhook is an array
                if (!is_array($webhook)) {
                    $webhook = [];
                }
                
                // Ensure instanceName is a string
                if (empty($instanceName)) {
                    $instanceName = auth()->check() ? 'secretario_' . auth()->id() : 'secretario_guest';
                }
                
                // Normalize status
                $status = $statusData['status'] ?? 'not_found';
                if ($status === 'not_configured') {
                    $status = 'not_configured';
                    $configured = false;
                } elseif ($status === 'open' || $status === 'connected') {
                    $status = 'open';
                    $configured = !isset($statusData['error']);
                } elseif ($status === 'close' || $status === 'disconnected') {
                    $status = 'close';
                    $configured = !isset($statusData['error']);
                } elseif ($status === 'connecting' || $status === 'qrcode') {
                    $status = 'connecting';
                    $configured = !isset($statusData['error']);
                } else {
                    $configured = !isset($statusData['error']) && $status !== 'not_configured';
                }
            } catch (\Exception $e) {
                \Log::warning('Erro ao obter dados da Evolution API', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $status = 'not_configured';
                $configured = false;
            }
            
            return view('settings.whatsapp', [
                'status' => ['status' => $status],
                'webhook' => $webhook,
                'instanceName' => $instanceName,
                'configured' => $configured,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro crítico ao renderizar página WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return view('settings.whatsapp', [
                'status' => ['status' => 'not_configured'],
                'webhook' => [],
                'instanceName' => 'N/A',
                'configured' => false,
            ]);
        }
    }

    /**
     * Create or connect instance.
     */
    public function connect(Request $request): JsonResponse|RedirectResponse
    {
        // Build marker para confirmar versão em produção
        $connectBuild = 'wa-connect-2026-01-28-02';

        // Get WhatsApp number from request
        $whatsappNumber = $request->input('whatsapp_number');
        
        if (empty($whatsappNumber)) {
            $errorMessage = 'Número do WhatsApp é obrigatório';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => $errorMessage], 400);
            }
            return back()->with('error', $errorMessage);
        }

        // Clean number (only digits)
        $whatsappNumber = preg_replace('/\D/', '', $whatsappNumber);
        
        if (strlen($whatsappNumber) < 10) {
            $errorMessage = 'Número do WhatsApp inválido. Digite o número com código do país';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => $errorMessage], 400);
            }
            return back()->with('error', $errorMessage);
        }

        // Create instance with WhatsApp number
        \Log::info('Evolution API - ANTES de criar instância', [
            'connect_build' => $connectBuild,
            'instanceName' => $whatsappNumber,
            'user_id' => auth()->id(),
            'user_authenticated' => auth()->check(),
        ]);
        
        $result = $this->evolutionApi->createInstance($whatsappNumber);
        
        \Log::info('Evolution API - DEPOIS criar instância (resposta completa)', [
            'instanceName' => $whatsappNumber,
            'response' => $result,
            'response_keys' => array_keys($result),
            'has_error' => isset($result['error']),
            'has_qrcode' => isset($result['qrcode']) || isset($result['base64']),
        ]);
        
        // IMPORTANTE: Mesmo com erro, pode haver QR code na resposta
        // A Evolution API pode retornar erro 400 mas ainda criar a instância e retornar QR code
        $hasQrCode = isset($result['qrcode']) 
            || isset($result['base64']) 
            || isset($result['pairingCode']);
        
        if (isset($result['error']) && !$hasQrCode) {
            // Só retornar erro se não houver QR code para processar
            $errorMessage = $result['error'];
            // Don't duplicate "Erro ao criar instância" if it's already in the message
            if (strpos($errorMessage, 'Erro ao criar instância') === false) {
                $errorMessage = 'Erro ao criar instância: ' . $errorMessage;
            }
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => $errorMessage], 400);
            }
            return back()->with('error', $errorMessage);
        }
        
        // Se tem erro mas também tem QR code, logar aviso mas continuar processando
        if (isset($result['error']) && $hasQrCode) {
            \Log::warning('Evolution API - Erro na resposta mas QR code disponível', [
                'error' => $result['error'],
                'has_qrcode' => true,
            ]);
        }

        // Persist instance name for subsequent requests
        $request->session()->put('whatsapp_instance_name', $whatsappNumber);

        $whatsappInstance = null;
        $dbWarning = null;
        
        \Log::info('Evolution API - Tentando salvar no banco', [
            'authenticated' => auth()->check(),
            'user_id' => auth()->id(),
            'instance_name' => $whatsappNumber,
        ]);
        
        if (auth()->check()) {
            try {
                // Extrair dados da resposta da Evolution API conforme estrutura fornecida
                // instance_name vem de instance.instanceName na resposta
                $instanceNameFromResponse = $result['instance']['instanceName'] ?? $whatsappNumber;
                
                // instance_token é o hash retornado na resposta (não vem dentro de instance)
                $instanceToken = $result['hash'] ?? null;
                
                // Extrair outros dados relevantes
                $instanceId = $result['instance']['instanceId'] ?? null;
                $integration = $result['instance']['integration'] ?? null;
                $statusFromResponse = $result['instance']['status'] ?? 'connecting';
                
                \Log::info('Evolution API - Dados extraídos da resposta', [
                    'instance_name_response' => $instanceNameFromResponse,
                    'instance_name_original' => $whatsappNumber,
                    'instance_token' => $instanceToken,
                    'instance_id' => $instanceId,
                    'integration' => $integration,
                    'status_from_response' => $statusFromResponse,
                ]);
                
                // Normalizar status
                $normalizedStatus = 'connecting';
                if ($statusFromResponse === 'open' || $statusFromResponse === 'connected') {
                    $normalizedStatus = 'open';
                } elseif ($statusFromResponse === 'close' || $statusFromResponse === 'disconnected') {
                    $normalizedStatus = 'close';
                } elseif ($statusFromResponse === 'connecting' || $statusFromResponse === 'qrcode') {
                    $normalizedStatus = 'connecting';
                }
                
                $whatsappInstance = WhatsAppInstance::updateOrCreate(
                    ['instance_name' => $instanceNameFromResponse],
                    [
                        'user_id' => auth()->id(),
                        'whatsapp_number' => $whatsappNumber,
                        'instance_name' => $instanceNameFromResponse, // Usar o nome da resposta
                        'instance_token' => $instanceToken, // hash da resposta
                        'status' => $normalizedStatus,
                        'metadata' => [
                            'created_via' => 'connect',
                            'create_response' => $result,
                            'instance_id' => $instanceId,
                            'integration' => $integration,
                            'hash' => $instanceToken,
                            'instance_data' => $result['instance'] ?? null,
                        ],
                    ]
                );
                
                \Log::info('Evolution API - Instância salva com sucesso no banco', [
                    'id' => $whatsappInstance->id,
                    'instance_name' => $whatsappInstance->instance_name,
                    'instance_token' => $whatsappInstance->instance_token ? 'definido (' . strlen($whatsappInstance->instance_token) . ' chars)' : 'não definido',
                    'status' => $whatsappInstance->status,
                ]);
            } catch (\Exception $e) {
                $dbWarning = 'Não foi possível salvar a instância no banco de dados.';
                \Log::error('Erro ao salvar instância WhatsApp no banco', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'instance_name' => $whatsappNumber,
                ]);
            }
        } else {
            $dbWarning = 'Usuário não autenticado. Instância não foi salva no banco.';
            \Log::warning('Evolution API - Usuário não autenticado', [
                'session_id' => session()->getId(),
            ]);
        }

        // QR Code: prioridade TOTAL para o campo do JSON de criação:
        // $result['qrcode']['base64'] (como vem da Evolution API no create instance)
        //
        // - Se vier data:image/... => usar como imagem
        // - Se vier "2@..." => isso é pairing code (não é imagem)
        $qrcode = null;
        $pairingCode = null;
        $qrText = null;

        $base64FromCreate = $result['qrcode']['base64'] ?? null;
        if (is_string($base64FromCreate) && $base64FromCreate !== '') {
            if (str_starts_with($base64FromCreate, 'data:image')) {
                $qrcode = ['base64' => $base64FromCreate];
            } elseif (preg_match('/^\d+@/', $base64FromCreate)) {
                $pairingCode = explode(',', $base64FromCreate)[0];
            }
        }

        // Também capturar o texto cru do QR (para gerar via biblioteca no frontend quando não vier imagem)
        if (isset($result['qrcode']['code']) && is_string($result['qrcode']['code']) && $result['qrcode']['code'] !== '') {
            $qrText = $result['qrcode']['code'];
        }

        // Fallback: se não veio nada em qrcode.base64, usar o extrator para cobrir outros formatos
        if ($qrcode === null && $pairingCode === null) {
            $extracted = $this->extractQrCodeAndPairingCode($result);
            $qrcode = $extracted['qrcode'];
            $pairingCode = $extracted['pairingCode'];
            $qrText = $qrText ?? ($extracted['qrText'] ?? null);
        }
        
        \Log::info('Evolution API - Resultado da extração', [
            'has_qrcode_image' => $qrcode !== null,
            'has_pairing_code' => $pairingCode !== null,
            'pairing_code_value' => $pairingCode,
        ]);

        // IMPORTANTE: Aguardar um pouco para a instância estar pronta
        sleep(2);

        // Check status da instância correta (não depender de session/última instância)
        $statusData = $this->evolutionApi->getInstanceStatusForInstance($whatsappNumber);
        $status = $statusData['status'] ?? 'not_found';
        
        // Normalize status
        if ($status === 'open' || $status === 'connected') {
            $status = 'open';
        } elseif ($status === 'close' || $status === 'disconnected') {
            $status = 'close';
        } elseif ($status === 'connecting' || $status === 'qrcode') {
            $status = 'connecting';
        }

        // Get QR code if connecting (fallback if not returned on create or if not valid)
        if (($status === 'connecting' || $status === 'not_found') && $qrcode === null) {
            \Log::info('Evolution API - Tentando obter QR Code via /connect', [
                'instance_name' => $whatsappNumber,
                'current_status' => $status,
            ]);
            
            $qrcodeResult = $this->evolutionApi->getQrCode();
            
            \Log::info('Evolution API - Resposta do getQrCode', [
                'result_keys' => array_keys($qrcodeResult),
                'result' => $qrcodeResult,
            ]);
            
            // Use helper function to extract QR code (como no exemplo PHP)
            $extracted = $this->extractQrCodeAndPairingCode($qrcodeResult);
            
            // Only update if we got something new
            if ($extracted['qrcode'] !== null) {
                $qrcode = $extracted['qrcode']['base64'];
            }
            if ($extracted['pairingCode'] !== null) {
                $pairingCode = $extracted['pairingCode'];
            }
        }

        // NOTA: Webhook será configurado separadamente após a instância estar conectada
        // Use o endpoint /settings/whatsapp/webhook para configurar o webhook manualmente
        // Não configuramos webhook durante a criação para evitar erros e seguir o fluxo:
        // 1. Criar instância
        // 2. Conectar WhatsApp (mostrar QR code)
        // 3. Ativar instância (quando conectar)
        // 4. Configurar webhook (separadamente, após estar conectado)
        $webhookWarning = null;
        if ($whatsappInstance) {
            $metadata = is_array($whatsappInstance->metadata) ? $whatsappInstance->metadata : [];
            // ⚠️ IMPORTANTE: qrcode.base64 é ENORME - campo metadata é LONGTEXT (suporta)
            // NÃO truncar ao salvar - passar base64 COMPLETO
            if ($qrcode && isset($qrcode['base64'])) {
                $metadata['qrcode_base64'] = $qrcode['base64']; // Base64 completo, sem truncar
            }
            $metadata['status_response'] = $statusData;
            $metadata['created_at'] = now()->toIso8601String();
            
            try {
                $whatsappInstance->update([
                    'status' => $status,
                    'connected_at' => $status === 'open' ? now() : $whatsappInstance->connected_at,
                    'disconnected_at' => $status === 'close' ? now() : $whatsappInstance->disconnected_at,
                    'metadata' => $metadata,
                ]);
            } catch (\Exception $e) {
                $dbWarning = $dbWarning ?: 'Não foi possível atualizar o status no banco de dados.';
                \Log::error('Erro ao atualizar status da instância no banco', [
                    'error' => $e->getMessage(),
                    'instance_name' => $whatsappNumber,
                ]);
            }
        }

        // IMPORTANTE (PROD):
        // A Evolution pode retornar AO MESMO TEMPO:
        // - qrcode.base64 (imagem válida data:image/png;base64,iVBORw0K...)
        // - qrcode.code (conteúdo bruto / "2@..." usado para pareamento)
        //
        // Para o "momento de parear", queremos retornar SOMENTE o base64 quando ele existir.
        // Então: se temos imagem em qrcode.base64, ignoramos pairingCode.
        if ($qrcode !== null && isset($qrcode['base64']) && is_string($qrcode['base64']) && str_starts_with($qrcode['base64'], 'data:image')) {
            if ($pairingCode !== null) {
                \Log::info('Imagem de QR disponível; ignorando pairingCode e retornando apenas qrcode.base64');
            }
            $pairingCode = null;
        } elseif ($pairingCode !== null) {
            // Se NÃO há imagem, aí sim mantemos o pairingCode e zeramos qrcode
            $qrcode = null;
            \Log::info('Pairing code detectado e sem imagem de QR; garantindo que qrcode seja null');
        }
        
        // Adicionar warning se houve erro na criação mas QR code está disponível
        $creationWarning = null;
        if (isset($result['error']) && ($qrcode !== null || $pairingCode !== null)) {
            $creationWarning = 'Aviso: ' . $result['error'] . ' (mas a instância foi criada e o QR code está disponível)';
        }
        
        $responseData = [
            'success' => true,
            'message' => 'Instância criada com sucesso! Escaneie o QR Code para conectar.',
            'build' => $connectBuild,
            'status' => $status,
            'qrcode' => $qrcode, // Será null se há pairing code
            'pairingCode' => $pairingCode,
            // Se não houver imagem, podemos retornar o texto cru para gerar QR via biblioteca no frontend
            'qrText' => $qrcode === null ? $qrText : null,
            'instanceName' => $whatsappNumber,
            'note' => 'Configure o webhook separadamente após conectar o WhatsApp',
            'db_warning' => $dbWarning,
            'creation_warning' => $creationWarning,
        ];
        
        // Adicionar webhook_warning apenas se houver algum problema específico
        if ($webhookWarning) {
            $responseData['webhook_warning'] = $webhookWarning;
        }
        
        \Log::info('Evolution API - Resposta final para o frontend', [
            'has_qrcode' => $qrcode !== null,
            'has_pairing_code' => $pairingCode !== null,
            'status' => $status,
            'has_creation_warning' => $creationWarning !== null,
            'response_keys' => array_keys($responseData),
        ]);
        
        return response()->json($responseData);
    }

    /**
     * Extract QR code and pairing code from Evolution API response.
     * Based on Postman Collection v2.3:
     * - Create Instance returns: qrcode.base64
     * - Instance Connect returns: base64 (direct)
     * 
     * @param array $result Response from Evolution API
     * @return array ['qrcode' => array|null, 'pairingCode' => string|null, 'qrText' => string|null]
     */
    private function extractQrCodeAndPairingCode(array $result): array
    {
        $qrcode = null;
        $pairingCode = null;
        $qrText = null;
        
        \Log::info('Evolution API - Processando resposta para extrair QR code', [
            'result_keys' => array_keys($result),
            'has_qrcode_key' => isset($result['qrcode']),
            'has_base64_key' => isset($result['base64']),
            'qrcode_type' => isset($result['qrcode']) ? gettype($result['qrcode']) : 'not set',
        ]);
        
        // Extract pairing code if available (priority)
        if (isset($result['pairingCode'])) {
            $pairingCode = $result['pairingCode'];
            \Log::info('Pairing code encontrado em result[pairingCode]', ['code' => $pairingCode]);
        } elseif (isset($result['pairing_code'])) {
            $pairingCode = $result['pairing_code'];
            \Log::info('Pairing code encontrado em result[pairing_code]', ['code' => $pairingCode]);
        }

        // QR text (conteúdo bruto do QR) — útil para gerar QR via biblioteca no frontend quando base64 não vier.
        if (isset($result['qrcode']['code']) && is_string($result['qrcode']['code']) && $result['qrcode']['code'] !== '') {
            $qrText = $result['qrcode']['code'];
        } elseif (isset($result['code']) && is_string($result['code']) && $result['code'] !== '') {
            $qrText = $result['code'];
        }
        
        // Try to extract QR code image (conforme Postman Collection v2.3)
        // 1. Check qrcode.base64 (Create Instance format)
        // ⚠️ IMPORTANTE: qrcode.base64 é ENORME (milhares de caracteres)
        // NÃO truncar, NÃO usar substr(), NÃO salvar em VARCHAR curto
        // ✅ Usar qrcode.base64 (correto) - NÃO usar qrcode.code (errado para imagem)
        if (isset($result['qrcode']['base64'])) {
            $base64Value = $result['qrcode']['base64'];
            
            \Log::info('Evolution API - Processando qrcode.base64', [
                'is_string' => is_string($base64Value),
                'length' => is_string($base64Value) ? strlen($base64Value) : 0,
                'starts_with_data_image' => is_string($base64Value) && strpos($base64Value, 'data:image') === 0,
                'first_50_chars' => is_string($base64Value) ? substr($base64Value, 0, 50) : 'not_string',
            ]);
            
            if (is_string($base64Value)) {
                // Check if it's a pairing code
                if (preg_match('/^\d+@/', $base64Value)) {
                    $pairingCode = explode(',', $base64Value)[0];
                    \Log::info('qrcode.base64 detectado como pairing code', ['code' => $pairingCode]);
                } elseif (strpos($base64Value, 'data:image') === 0) {
                    // Se for data URI, checar se o payload (após vírgula) é pairing code (ex: data:image...,2@...)
                    $commaIndex = strpos($base64Value, ',');
                    if ($commaIndex !== false) {
                        $payload = substr($base64Value, $commaIndex + 1);
                        if (preg_match('/^\d+@/', $payload)) {
                            $pairingCode = explode(',', $payload)[0];
                            \Log::info('qrcode.base64 (data URI) contém pairing code no payload', ['code' => $pairingCode]);
                        }
                    }
                } elseif ($this->isValidBase64Image($base64Value)) {
                    // ✅ Passar base64 COMPLETO (sem truncar)
                    $qrcode = ['base64' => $base64Value];
                    \Log::info('qrcode.base64 é imagem válida (formato Create Instance)', [
                        'length' => strlen($base64Value), // Log apenas o tamanho, não o conteúdo
                    ]);
                } else {
                    \Log::warning('qrcode.base64 não passou na validação isValidBase64Image', [
                        'length' => strlen($base64Value),
                        'starts_with_data_image' => strpos($base64Value, 'data:image') === 0,
                    ]);
                }
            }
        }
        // 1b. Check qrcode.code (raw QR content / pairing code)
        if ($pairingCode === null && isset($result['qrcode']['code']) && is_string($result['qrcode']['code'])) {
            if (preg_match('/^\d+@/', $result['qrcode']['code'])) {
                $pairingCode = explode(',', $result['qrcode']['code'])[0];
                \Log::info('Pairing code encontrado em qrcode[code]', ['code' => $pairingCode]);
            }
        }
        // 2. Check base64 (direct - Instance Connect format)
        elseif (isset($result['base64'])) {
            $base64Value = $result['base64'];
            
            if (is_string($base64Value)) {
                if (preg_match('/^\d+@/', $base64Value)) {
                    $pairingCode = explode(',', $base64Value)[0];
                    \Log::info('base64 direto detectado como pairing code', ['code' => $pairingCode]);
                } elseif ($this->isValidBase64Image($base64Value)) {
                    $qrcode = ['base64' => $base64Value];
                    \Log::info('base64 direto é imagem válida (formato Instance Connect)');
                }
            }
        }
        // 3. Check qrcode as string
        elseif (isset($result['qrcode']) && is_string($result['qrcode'])) {
            $qrcodeValue = $result['qrcode'];
            
            if (preg_match('/^\d+@/', $qrcodeValue)) {
                $pairingCode = explode(',', $qrcodeValue)[0];
                \Log::info('qrcode string detectado como pairing code', ['code' => $pairingCode]);
            } elseif ($this->isValidBase64Image($qrcodeValue)) {
                $qrcode = ['base64' => $qrcodeValue];
                \Log::info('qrcode string é imagem válida');
            }
        }
        // 4. Check qrcode.pairingCode
        elseif (isset($result['qrcode']['pairingCode'])) {
            $pairingCode = $result['qrcode']['pairingCode'];
            \Log::info('Pairing code encontrado em qrcode[pairingCode]', ['code' => $pairingCode]);
        }
        // 5. Check qrcode as array (full structure)
        elseif (isset($result['qrcode']) && is_array($result['qrcode'])) {
            // If it's already a structured array, use it
            if (isset($result['qrcode']['base64']) && $this->isValidBase64Image($result['qrcode']['base64'])) {
                $qrcode = $result['qrcode'];
                \Log::info('QR code array completo usado');
            }
        }
        
        return [
            'qrcode' => $qrcode,
            'pairingCode' => $pairingCode,
            'qrText' => $qrText,
        ];
    }

    /**
     * Get webhook URL correctly formatted for production.
     * Detects if server requires /public in URL and adjusts accordingly.
     */
    public function getWebhookUrl(): string
    {
        // Get base URL from config or env
        $appUrl = config('app.url');
        
        if (empty($appUrl) || $appUrl === 'http://localhost') {
            // Fallback: try to get from request
            $appUrl = request()->getSchemeAndHttpHost();
            \Log::warning('APP_URL não configurado ou é localhost, usando URL da requisição', [
                'config_url' => config('app.url'),
                'request_url' => $appUrl,
            ]);
        }
        
        // Remove trailing slash
        $appUrl = rtrim($appUrl, '/');
        
        // IMPORTANTE: Detectar se o servidor requer /public na URL
        // Opção 1: Verificar variável de ambiente (mais confiável e explícito)
        $forcePublic = env('WEBHOOK_REQUIRES_PUBLIC', false);
        
        // Opção 2: Verificar a URL completa da requisição atual
        $currentFullUrl = request()->fullUrl();
        $currentPath = request()->getPathInfo();
        $requestUri = request()->getRequestUri();
        
        // Detectar se a requisição atual tem /public
        // Isso indica que o servidor redireciona para /public
        $hasPublicInRequest = strpos($currentFullUrl, '/public') !== false || 
                              strpos($requestUri, '/public') !== false;
        
        // Se APP_URL já tem /public, manter
        $hasPublicInAppUrl = strpos($appUrl, '/public') !== false;
        
        // Decidir se precisa de /public
        $needsPublic = $forcePublic || $hasPublicInRequest || $hasPublicInAppUrl;
        
        // Se não tem /public mas detectamos que precisa, adicionar
        if ($needsPublic && !$hasPublicInAppUrl) {
            $appUrl .= '/public';
            \Log::info('Servidor requer /public na URL, adicionando', [
                'url_antes' => preg_replace('#/public/?$#', '', $appUrl),
                'url_depois' => $appUrl,
                'force_public_env' => $forcePublic,
                'has_public_in_request' => $hasPublicInRequest,
                'has_public_in_app_url' => $hasPublicInAppUrl,
            ]);
        }
        
        // Se tem /public mas não precisa, remover
        if (!$needsPublic && $hasPublicInAppUrl) {
            $appUrl = preg_replace('#/public/?$#', '', $appUrl);
            \Log::info('Removendo /public da URL (não necessário)', ['url' => $appUrl]);
        }
        
        // Generate route URL (absolute: false para não incluir domínio)
        $routePath = route('evolution.webhook', [], false);
        
        // Remover barra final se existir (será adicionada pelo WebhookResource conforme exemplo)
        $routePath = rtrim($routePath, '/');
        
        // Combine
        $webhookUrl = $appUrl . $routePath;
        
        // NOTA: A barra final "/" será adicionada pelo WebhookResource::set() conforme o exemplo fornecido
        // O exemplo PHP adiciona "/" no final: if (substr($webhookUrl, -1) !== '/') { $webhookUrl .= '/'; }
        
        \Log::info('URL do webhook gerada', [
            'app_url_config' => config('app.url'),
            'app_url_final' => $appUrl,
            'route_path' => $routePath,
            'webhook_url' => $webhookUrl,
            'needs_public' => $needsPublic,
            'current_full_url' => $currentFullUrl,
            'current_path' => $currentPath,
        ]);
        
        return $webhookUrl;
    }

    /**
     * Validate webhook URL and log warnings if needed.
     */
    private function validateWebhookUrl(string $url): void
    {
        $parsed = parse_url($url);
        
        \Log::info('Validação da URL do webhook', [
            'url' => $url,
            'scheme' => $parsed['scheme'] ?? null,
            'host' => $parsed['host'] ?? null,
            'path' => $parsed['path'] ?? null,
            'has_public' => strpos($url, '/public') !== false,
        ]);
        
        // Check if URL contains /public (may cause issues in production)
        if (strpos($url, '/public') !== false) {
            \Log::warning('URL do webhook contém /public - pode causar problemas em produção', [
                'url' => $url,
                'sugestao' => 'Configure APP_URL no .env sem /public',
            ]);
        }
        
        // Check if it's HTTPS (recommended)
        if (isset($parsed['scheme']) && $parsed['scheme'] !== 'https') {
            \Log::warning('URL do webhook não usa HTTPS - pode causar problemas', [
                'url' => $url,
            ]);
        }
        
        // Check if host is localhost/127.0.0.1
        if ($this->isLocalWebhookUrl($url)) {
            \Log::error('URL do webhook é local - Evolution API não conseguirá acessar', [
                'url' => $url,
            ]);
        }
    }

    private function isLocalWebhookUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        return in_array($host, ['127.0.0.1', 'localhost'], true);
    }

    private function isValidBase64Image(string $base64): bool
    {
        // Se começa com data:image, validar o conteúdo APÓS a vírgula.
        // ⚠️ Caso comum de bug: "data:image/png;base64,2@...." (pairing code) — NÃO é imagem.
        if (strpos($base64, 'data:image') === 0) {
            $commaIndex = strpos($base64, ',');
            if ($commaIndex === false || $commaIndex >= strlen($base64) - 1) {
                return false;
            }

            $payload = substr($base64, $commaIndex + 1);

            // Se o payload começa com dígito@, é pairing code (não imagem)
            if (preg_match('/^\d+@/', $payload)) {
                return false;
            }

            // Validar base64 do payload (sem o prefixo data:image)
            if (strlen($payload) < 1000) {
                return false;
            }

            $decoded = base64_decode($payload, true);
            if ($decoded === false) {
                return false;
            }

            $header = substr($decoded, 0, 8);
            if ($header === "\x89PNG\r\n\x1a\n") {
                return true;
            }
            if (substr($header, 0, 3) === "\xFF\xD8\xFF") {
                return true;
            }

            return false;
        }
        
        // IMPORTANTE: Rejeitar pairing codes explicitamente
        // Pairing codes começam com dígito@ (ex: 2@cOz1XwPq...)
        if (preg_match('/^\d+@/', $base64)) {
            return false;
        }
        
        // Se contém vírgulas, provavelmente é pairing code com múltiplos códigos
        if (strpos($base64, ',') !== false && preg_match('/^\d+@/', $base64)) {
            return false;
        }
        
        // Check if it looks like base64 (should be much longer for an image)
        // A QR code image typically has 5000+ characters
        if (strlen($base64) < 1000) {
            return false;
        }
        
        // Check if it's valid base64
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return false;
        }
        
        // Check if it's a valid image by checking PNG/JPEG magic numbers
        $header = substr($decoded, 0, 8);
        
        // PNG magic number
        if ($header === "\x89PNG\r\n\x1a\n") {
            return true;
        }
        
        // JPEG magic number
        if (substr($header, 0, 3) === "\xFF\xD8\xFF") {
            return true;
        }
        
        return false;
    }

    private function extractInstanceToken(array $result): ?string
    {
        $token = $result['instance_token']
            ?? $result['instanceToken']
            ?? $result['token']
            ?? $result['apikey']
            ?? null;

        if (!is_string($token) || $token === '') {
            $nested = $result['instance'] ?? $result['data'] ?? $result['response'] ?? null;
            if (is_array($nested)) {
                $token = $nested['instance_token']
                    ?? $nested['instanceToken']
                    ?? $nested['token']
                    ?? $nested['apikey']
                    ?? $nested['hash']
                    ?? null;
            }
        }

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Get QR Code.
     */
    public function qrcode(): JsonResponse
    {
        $result = $this->evolutionApi->getQrCode();
        
        \Log::info('Evolution API - Endpoint /qrcode resultado', [
            'result_keys' => array_keys($result),
            'result' => $result,
        ]);
        
        // Use helper function to extract QR code (como no exemplo PHP)
        $extracted = $this->extractQrCodeAndPairingCode($result);
        
        // Mesma regra do connect():
        // Se temos imagem em qrcode.base64, retornamos SOMENTE o base64 (ignoramos pairingCode).
        if ($extracted['qrcode'] !== null && isset($extracted['qrcode']['base64']) && is_string($extracted['qrcode']['base64']) && str_starts_with($extracted['qrcode']['base64'], 'data:image')) {
            if ($extracted['pairingCode'] !== null) {
                \Log::info('Imagem de QR disponível no /qrcode; ignorando pairingCode e retornando apenas qrcode.base64');
            }
            $extracted['pairingCode'] = null;
        } elseif ($extracted['pairingCode'] !== null) {
            $extracted['qrcode'] = null;
            \Log::info('Pairing code detectado no /qrcode e sem imagem; garantindo que qrcode seja null');
        }
        
        $response = [];
        
        if ($extracted['qrcode'] !== null) {
            $response['qrcode'] = $extracted['qrcode'];
        }
        
        if ($extracted['pairingCode'] !== null) {
            $response['pairingCode'] = $extracted['pairingCode'];
        }

        if (!empty($extracted['qrText']) && $extracted['qrcode'] === null) {
            $response['qrText'] = $extracted['qrText'];
        }
        
        // Se nada foi extraído, NÃO devolver o $result cru (em prod ele pode vir "data:image...,2@..."
        // e isso quebra o <img> no browser com ERR_INVALID_URL).
        if (empty($response)) {
            $pairingFromBase64 = null;
            if (isset($result['qrcode']['base64']) && is_string($result['qrcode']['base64']) && str_starts_with($result['qrcode']['base64'], 'data:image')) {
                $comma = strpos($result['qrcode']['base64'], ',');
                if ($comma !== false) {
                    $payload = substr($result['qrcode']['base64'], $comma + 1);
                    if (preg_match('/^\d+@/', $payload)) {
                        $pairingFromBase64 = explode(',', $payload)[0];
                    }
                }
            }

            $pairingFromCode = null;
            if (isset($result['qrcode']['code']) && is_string($result['qrcode']['code']) && preg_match('/^\d+@/', $result['qrcode']['code'])) {
                $pairingFromCode = explode(',', $result['qrcode']['code'])[0];
            }

            $pairing = $pairingFromBase64 ?? $pairingFromCode;
            if ($pairing) {
                return response()->json(['pairingCode' => $pairing]);
            }

            return response()->json([]);
        }
        
        return response()->json($response);
    }

    /**
     * Get instance status.
     */
    public function status(): JsonResponse
    {
        $statusData = $this->evolutionApi->getInstanceStatus();
        
        // Normalize status
        $status = $statusData['status'] ?? 'not_found';
        if ($status === 'open' || $status === 'connected') {
            $status = 'open';
        } elseif ($status === 'close' || $status === 'disconnected') {
            $status = 'close';
        } elseif ($status === 'connecting' || $status === 'qrcode') {
            $status = 'connecting';
        }
        
        return response()->json(['status' => $status, 'data' => $statusData]);
    }

    /**
     * Logout instance.
     */
    public function logout(): RedirectResponse
    {
        $result = $this->evolutionApi->logoutInstance();
        
        if (isset($result['error'])) {
            return back()->with('error', 'Erro ao desconectar: ' . $result['error']);
        }

        return back()->with('success', 'WhatsApp desconectado com sucesso!');
    }

    /**
     * Delete instance.
     */
    public function delete(): RedirectResponse
    {
        $result = $this->evolutionApi->deleteInstance();
        
        if (isset($result['error'])) {
            return back()->with('error', 'Erro ao deletar instância: ' . $result['error']);
        }

        return back()->with('success', 'Instância deletada com sucesso!');
    }

    /**
     * Configure webhook.
     */
    /**
     * Configure webhook separately (after instance is created and connected).
     * This method is called separately from instance creation to avoid errors.
     */
    public function configureWebhook(Request $request): RedirectResponse
    {
        $request->validate([
            'url' => 'nullable|url', // Opcional: usa URL padrão se não fornecida
            'instance_name' => 'nullable|string',
            'events' => 'nullable|array',
            'webhook_base64' => 'nullable|boolean',
        ]);

        // Se URL não fornecida, usar URL padrão
        $url = $request->input('url');
        if (empty($url)) {
            $url = $this->getWebhookUrl();
        }

        // Garantir que é uma URL absoluta válida
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            \Log::error('URL do webhook inválida', ['url' => $url]);
            $url = $this->getWebhookUrl();
        }

        // Validar URL antes de configurar
        $this->validateWebhookUrl($url);
        
        // Eventos padrão conforme exemplo fornecido
        $events = $request->input('events', []);
        if (empty($events)) {
            $events = [
                'APPLICATION_STARTUP',
                'QRCODE_UPDATED',
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'CONNECTION_UPDATE'
            ];
        }

        $webhookBase64 = $request->boolean('webhook_base64', false);

        // Definir explicitamente a instância alvo do webhook (evita usar instância errada)
        $instanceName = $request->input('instance_name');
        if (empty($instanceName)) {
            // Fallback: usa a instância atual resolvida (session/DB)
            $instanceName = $this->evolutionApi->getInstanceName();
        }
        $instanceName = preg_replace('/\s+/', '', (string) $instanceName);

        // TRAVA DE FLUXO: webhook só depois de conectar (status open)
        $statusData = $this->evolutionApi->getInstanceStatusForInstance($instanceName);
        $status = $statusData['status'] ?? 'unknown';
        if (!in_array($status, ['open', 'connected'], true)) {
            \Log::warning('Tentativa de configurar webhook antes de conectar WhatsApp', [
                'instance_name' => $instanceName,
                'status' => $status,
                'status_data' => $statusData,
            ]);
            return back()->with('error', 'Conecte o WhatsApp primeiro (status precisa estar OPEN). Depois configure o webhook.');
        }
        
        // Gerar diagnóstico
        $diagnostics = $this->diagnoseWebhook($url);
        \Log::info('Configurando webhook separadamente (após criação da instância)', [
            'instance_name' => $instanceName,
            'url' => $url,
            'events' => $events,
            'events_count' => count($events),
            'webhook_base64' => $webhookBase64,
            'diagnostics' => $diagnostics,
        ]);

        $result = $this->evolutionApi->setWebhookForInstance($instanceName, $url, $events, $webhookBase64);
        
        if (isset($result['error'])) {
            \Log::error('Erro ao configurar webhook manualmente', [
                'error' => $result['error'],
                'instance_name' => $instanceName,
                'url' => $url,
                'events' => $events,
                'diagnostics' => $diagnostics,
            ]);
            return back()->with('error', 'Erro ao configurar webhook: ' . $result['error']);
        }

        // Salvar no banco de dados
        if (auth()->check()) {
            WhatsAppInstance::updateOrCreate(
                ['instance_name' => $instanceName],
                [
                    'user_id' => auth()->id(),
                    'webhook_url' => $url,
                    'webhook_events' => $events,
                    'webhook_base64' => $webhookBase64,
                ]
            );
            
            \Log::info('Webhook configurado e salvo no banco de dados', [
                'instance_name' => $instanceName,
                'url' => $url,
            ]);
        }

        return back()->with('success', 'Webhook configurado com sucesso!');
    }

    /**
     * Diagnose webhook configuration and accessibility.
     */
    private function diagnoseWebhook(string $url): array
    {
        $diagnostics = [
            'url' => $url,
            'is_valid_url' => filter_var($url, FILTER_VALIDATE_URL) !== false,
            'is_local' => $this->isLocalWebhookUrl($url),
            'has_public' => strpos($url, '/public') !== false,
            'is_https' => strpos($url, 'https://') === 0,
            'app_url' => config('app.url'),
            'route_path' => route('evolution.webhook', [], false),
            'expected_url' => $this->getWebhookUrl(),
        ];

        $parsed = parse_url($url);
        if ($parsed) {
            $diagnostics['parsed'] = [
                'scheme' => $parsed['scheme'] ?? null,
                'host' => $parsed['host'] ?? null,
                'port' => $parsed['port'] ?? null,
                'path' => $parsed['path'] ?? null,
            ];
        }

        // Check if URL matches expected format
        $expectedUrl = $this->getWebhookUrl();
        $diagnostics['matches_expected'] = $url === $expectedUrl;
        
        if (!$diagnostics['matches_expected']) {
            $diagnostics['warning'] = 'URL não corresponde à URL esperada. Verifique APP_URL no .env';
        }

        return $diagnostics;
    }

    /**
     * Handle webhook from Evolution API.
     */
    public function webhook(Request $request): JsonResponse
    {
        // Log webhook data for debugging
        \Log::info('Evolution API Webhook - RECEBIDO', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'data' => $request->all(),
            'headers' => $request->headers->all(),
            'raw_body' => $request->getContent(),
        ]);

        // Handle different event types
        $event = $request->input('event');
        $data = $request->input('data');

        switch ($event) {
            case 'qrcode.updated':
            case 'QRCODE_UPDATED':
                // QR Code was updated
                \Log::info('Webhook: QR Code atualizado', ['data' => $data]);
                break;
            case 'connection.update':
            case 'CONNECTION_UPDATE':
                // Connection status changed
                \Log::info('Webhook: Status de conexão atualizado', ['data' => $data]);
                break;
            case 'messages.upsert':
            case 'MESSAGES_UPSERT':
                // New message received
                $this->handleIncomingMessage($data);
                break;
            case 'messages.update':
            case 'MESSAGES_UPDATE':
                // Message status updated (sent, delivered, read)
                $this->handleMessageUpdate($data);
                break;
            default:
                // Handle other events
                \Log::info('Webhook: Evento não tratado', [
                    'event' => $event,
                    'data' => $data,
                ]);
                break;
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Test webhook endpoint - to verify it's accessible (GET).
     */
    public function testWebhook(Request $request): JsonResponse
    {
        $webhookUrl = $this->getWebhookUrl();
        
        return response()->json([
            'status' => 'ok',
            'message' => 'Webhook endpoint is accessible',
            'timestamp' => now()->toIso8601String(),
            'current_url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'webhook_url_for_evolution_api' => $webhookUrl,
            'note' => 'Use POST method to /webhook/evolution (not /test)',
            'test_post_command' => "curl -X POST {$webhookUrl} -H 'Content-Type: application/json' -d '{\"test\":\"data\"}'",
        ]);
    }

    /**
     * Test webhook endpoint with POST - simulates Evolution API request.
     */
    public function testWebhookPost(Request $request): JsonResponse
    {
        \Log::info('Test Webhook POST - Simulando Evolution API', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'data' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Webhook POST endpoint is working!',
            'timestamp' => now()->toIso8601String(),
            'received_data' => $request->all(),
            'webhook_url_for_evolution_api' => $this->getWebhookUrl(),
            'note' => 'Se você recebeu esta resposta, o webhook está funcionando corretamente!',
        ]);
    }

    /**
     * Handle incoming message.
     */
    private function handleIncomingMessage(array $data): void
    {
        // Process incoming message
        // You can save to database, trigger actions, etc.
        \Log::info('Incoming message', $data);
    }

    /**
     * Handle message status update.
     */
    private function handleMessageUpdate(array $data): void
    {
        // Process message status update
        \Log::info('Message status updated', $data);
    }
}
