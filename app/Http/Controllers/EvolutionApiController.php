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
            'has_qrcode' => isset($result['qrcode']) || isset($result['base64']) || isset($result['code']),
        ]);
        
        // IMPORTANTE: Mesmo com erro, pode haver QR code na resposta
        // A Evolution API pode retornar erro 400 mas ainda criar a instância e retornar QR code
        $hasQrCode = isset($result['qrcode']) 
            || isset($result['base64']) 
            || isset($result['code'])
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
                $instanceToken = $this->extractInstanceToken($result);
                
                \Log::info('Evolution API - Token extraído', [
                    'token' => $instanceToken,
                    'token_length' => $instanceToken ? strlen($instanceToken) : 0,
                ]);
                
                $whatsappInstance = WhatsAppInstance::updateOrCreate(
                    ['instance_name' => $whatsappNumber],
                    [
                        'user_id' => auth()->id(),
                        'whatsapp_number' => $whatsappNumber,
                        'instance_token' => $instanceToken,
                        'status' => 'connecting',
                        'metadata' => [
                            'created_via' => 'connect',
                            'create_response' => $result,
                        ],
                    ]
                );
                
                \Log::info('Evolution API - Instância salva com sucesso no banco', [
                    'id' => $whatsappInstance->id,
                    'instance_name' => $whatsappInstance->instance_name,
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

        // Extract QR code and pairing code using helper method (como no exemplo PHP)
        $extracted = $this->extractQrCodeAndPairingCode($result);
        $qrcode = $extracted['qrcode'];
        $pairingCode = $extracted['pairingCode'];
        
        \Log::info('Evolution API - Resultado da extração', [
            'has_qrcode_image' => $qrcode !== null,
            'has_pairing_code' => $pairingCode !== null,
            'pairing_code_value' => $pairingCode,
        ]);

        // IMPORTANTE: Aguardar um pouco para a instância estar pronta
        sleep(2);

        // Check status primeiro para garantir que a instância existe
        $statusData = $this->evolutionApi->getInstanceStatus();
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
                $qrcode = $extracted['qrcode'];
            }
            if ($extracted['pairingCode'] !== null) {
                $pairingCode = $extracted['pairingCode'];
            }
        }

        // AGORA configurar webhook (DEPOIS de obter QR code e verificar status)
        // A instância já deve estar mais estável neste ponto
        $webhookUrlInput = $request->input('webhook_url');
        
        // Se não foi fornecida, gerar URL correta do webhook
        if (empty($webhookUrlInput)) {
            $webhookUrl = $this->getWebhookUrl();
        } else {
            $webhookUrl = $webhookUrlInput;
        }
        
        // Garantir que é uma URL absoluta válida
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            \Log::error('URL do webhook inválida', ['url' => $webhookUrl]);
            $webhookUrl = $this->getWebhookUrl();
        }
        
        // Verificar se a URL é acessível publicamente
        $this->validateWebhookUrl($webhookUrl);
        
        $events = $request->input('events', []);
        $webhookBase64 = $request->boolean('webhook_base64', false);
        
        // If no events selected, use default important events
        if (empty($events)) {
            $events = ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'QRCODE_UPDATED', 'CONNECTION_UPDATE'];
        }

        // Configure webhook (agora que a instância está mais estável)
        $webhookResult = null;
        $webhookWarning = null;
        
        // Verificar se a instância existe antes de configurar webhook
        if ($status === 'not_found') {
            \Log::warning('Instância não encontrada, pulando configuração do webhook', [
                'instance_name' => $whatsappNumber,
                'status' => $status,
            ]);
            $webhookWarning = 'Instância ainda não encontrada. O webhook será configurado quando a instância estiver pronta.';
        } elseif ($this->isLocalWebhookUrl($webhookUrl)) {
            $webhookWarning = 'Webhook configurado com URL local. A Evolution API não consegue acessar 127.0.0.1/localhost.';
            \Log::warning('Webhook URL é local', ['url' => $webhookUrl]);
            $webhookResult = ['error' => 'URL local não suportada'];
        } else {
            // Aguardar mais um pouco para garantir que a instância está totalmente pronta
            sleep(2);
            
            $maxRetries = 3;
            $retryDelay = 3; // seconds
            
            \Log::info('Configurando webhook APÓS obter QR code e verificar status', [
                'url' => $webhookUrl,
                'instance_name' => $whatsappNumber,
                'events' => $events,
                'status_atual' => $status,
                'has_qrcode' => $qrcode !== null,
                'has_pairing_code' => $pairingCode !== null,
            ]);
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                \Log::info("Tentativa {$attempt} de {$maxRetries} para configurar webhook", [
                    'url' => $webhookUrl,
                    'instance_name' => $whatsappNumber,
                    'events' => $events,
                    'status' => $status,
                ]);
                
                $webhookResult = $this->evolutionApi->setWebhook($webhookUrl, $events, $webhookBase64);
                
                \Log::info("Resposta do webhook (tentativa {$attempt})", [
                    'has_error' => isset($webhookResult['error']),
                    'error' => $webhookResult['error'] ?? null,
                    'result_keys' => array_keys($webhookResult),
                    'full_result' => $webhookResult,
                ]);
                
                if (!isset($webhookResult['error'])) {
                    \Log::info('✓ Webhook configurado com sucesso!', [
                        'url' => $webhookUrl,
                        'events_count' => count($events),
                        'attempt' => $attempt,
                    ]);
                    break;
                }
                
                // Se o erro é 404, a instância pode não estar pronta ainda
                if (isset($webhookResult['error']) && strpos($webhookResult['error'], '404') !== false) {
                    \Log::warning("Instância pode não estar pronta ainda (404), aguardando mais tempo...", [
                        'attempt' => $attempt,
                    ]);
                    sleep($retryDelay + 2);
                }
                
                if ($attempt < $maxRetries) {
                    \Log::warning("Erro ao configurar webhook, tentando novamente em {$retryDelay}s", [
                        'error' => $webhookResult['error'],
                        'attempt' => $attempt,
                    ]);
                    sleep($retryDelay);
                }
            }
            
            if (isset($webhookResult['error'])) {
                \Log::error('✗ Erro ao configurar webhook após todas as tentativas', [
                    'error' => $webhookResult['error'],
                    'url' => $webhookUrl,
                    'events' => $events,
                    'attempts' => $maxRetries,
                    'status' => $status,
                ]);
                
                $errorMsg = $webhookResult['error'];
                if (strpos($errorMsg, '400') !== false || strpos($errorMsg, 'Bad Request') !== false) {
                    $webhookWarning = 'Webhook não configurado (Bad Request). Verifique os logs para mais detalhes. Você pode configurá-lo manualmente depois na página de configurações.';
                } else {
                    $webhookWarning = 'Webhook não configurado: ' . $errorMsg . '. Você pode configurá-lo manualmente depois na página de configurações.';
                }
            }
        }

        // Salvar webhook no banco se configurado com sucesso
        if ($whatsappInstance && !isset($webhookResult['error'])) {
            try {
                $whatsappInstance->update([
                    'webhook_url' => $webhookUrl,
                    'webhook_events' => $events,
                    'webhook_base64' => $webhookBase64,
                ]);
            } catch (\Exception $e) {
                $dbWarning = $dbWarning ?: 'Não foi possível salvar o webhook no banco de dados.';
                \Log::error('Erro ao salvar webhook no banco', [
                    'error' => $e->getMessage(),
                    'instance_name' => $whatsappNumber,
                ]);
            }
        }

        if ($whatsappInstance) {
            $metadata = is_array($whatsappInstance->metadata) ? $whatsappInstance->metadata : [];
            if ($qrcode && isset($qrcode['base64'])) {
                $metadata['qrcode_base64'] = $qrcode['base64'];
            }
            $metadata['status_response'] = $statusData;
            if (!isset($webhookResult['error'])) {
                $metadata['webhook_response'] = $webhookResult;
            } else {
                $metadata['webhook_error'] = $webhookResult['error'];
            }
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

        // IMPORTANTE: Se há pairing code, garantir que qrcode seja null
        // Isso evita que o frontend tente usar pairing code como imagem
        if ($pairingCode !== null) {
            $qrcode = null;
            \Log::info('Pairing code detectado, garantindo que qrcode seja null');
        }
        
        // Adicionar warning se houve erro na criação mas QR code está disponível
        $creationWarning = null;
        if (isset($result['error']) && ($qrcode !== null || $pairingCode !== null)) {
            $creationWarning = 'Aviso: ' . $result['error'] . ' (mas a instância foi criada e o QR code está disponível)';
        }
        
        $responseData = [
            'success' => true,
            'message' => 'Instância criada com sucesso! Escaneie o QR Code para conectar.',
            'status' => $status,
            'qrcode' => $qrcode, // Será null se há pairing code
            'pairingCode' => $pairingCode,
            'instanceName' => $whatsappNumber,
            'webhook_warning' => $webhookWarning,
            'db_warning' => $dbWarning,
            'creation_warning' => $creationWarning,
        ];
        
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
     * @return array ['qrcode' => array|null, 'pairingCode' => string|null]
     */
    private function extractQrCodeAndPairingCode(array $result): array
    {
        $qrcode = null;
        $pairingCode = null;
        
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
        
        // Try to extract QR code image (conforme Postman Collection v2.3)
        // 1. Check qrcode.base64 (Create Instance format)
        if (isset($result['qrcode']['base64'])) {
            $base64Value = $result['qrcode']['base64'];
            
            if (is_string($base64Value)) {
                // Check if it's a pairing code
                if (preg_match('/^\d+@/', $base64Value)) {
                    $pairingCode = explode(',', $base64Value)[0];
                    \Log::info('qrcode.base64 detectado como pairing code', ['code' => $pairingCode]);
                } elseif ($this->isValidBase64Image($base64Value)) {
                    $qrcode = ['base64' => $base64Value];
                    \Log::info('qrcode.base64 é imagem válida (formato Create Instance)');
                }
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
        
        // Combine
        $webhookUrl = $appUrl . $routePath;
        
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
        // Check if it's already a data URI
        if (strpos($base64, 'data:image') === 0) {
            return true;
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
        
        // IMPORTANTE: Se há pairing code, garantir que qrcode seja null
        if ($extracted['pairingCode'] !== null) {
            $extracted['qrcode'] = null;
            \Log::info('Pairing code detectado no /qrcode, garantindo que qrcode seja null');
        }
        
        $response = [];
        
        if ($extracted['qrcode'] !== null) {
            $response['qrcode'] = $extracted['qrcode'];
        }
        
        if ($extracted['pairingCode'] !== null) {
            $response['pairingCode'] = $extracted['pairingCode'];
        }
        
        // If no valid data was extracted, return the original result
        if (empty($response)) {
            return response()->json($result);
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
    public function configureWebhook(Request $request): RedirectResponse
    {
        $request->validate([
            'url' => 'required|url',
            'events' => 'nullable|array',
            'webhook_base64' => 'nullable|boolean',
        ]);

        $url = $request->input('url');
        $events = $request->input('events', []);
        $webhookBase64 = $request->boolean('webhook_base64', false);

        // Validar URL antes de configurar
        $this->validateWebhookUrl($url);
        
        // Gerar diagnóstico
        $diagnostics = $this->diagnoseWebhook($url);
        \Log::info('Diagnóstico do webhook antes de configurar', $diagnostics);

        $result = $this->evolutionApi->setWebhook($url, $events, $webhookBase64);
        
        if (isset($result['error'])) {
            \Log::error('Erro ao configurar webhook manualmente', [
                'error' => $result['error'],
                'url' => $url,
                'diagnostics' => $diagnostics,
            ]);
            return back()->with('error', 'Erro ao configurar webhook: ' . $result['error']);
        }

        if (auth()->check()) {
            $instanceName = $this->evolutionApi->getInstanceName();
            WhatsAppInstance::updateOrCreate(
                ['instance_name' => $instanceName],
                [
                    'user_id' => auth()->id(),
                    'status' => 'connecting',
                    'webhook_url' => $url,
                    'webhook_events' => $events,
                    'webhook_base64' => $webhookBase64,
                ]
            );
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
