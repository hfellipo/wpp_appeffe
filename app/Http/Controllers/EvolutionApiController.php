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

        // Try to extract QR code from creation response (faster UX)
        $qrcode = null;
        $pairingCode = null;
        
        \Log::info('Evolution API - Processando resposta de criação', [
            'result_keys' => array_keys($result),
            'has_qrcode_key' => isset($result['qrcode']),
            'qrcode_type' => isset($result['qrcode']) ? gettype($result['qrcode']) : 'not set',
            'qrcode_value_sample' => isset($result['qrcode']) ? (is_string($result['qrcode']) ? substr($result['qrcode'], 0, 100) : json_encode($result['qrcode'])) : null,
        ]);
        
        // Extract pairing code if available
        if (isset($result['pairingCode'])) {
            $pairingCode = $result['pairingCode'];
            \Log::info('Pairing code encontrado em result[pairingCode]', ['code' => $pairingCode]);
        } elseif (isset($result['pairing_code'])) {
            $pairingCode = $result['pairing_code'];
            \Log::info('Pairing code encontrado em result[pairing_code]', ['code' => $pairingCode]);
        }
        
        // Try to extract QR code image
        if (isset($result['qrcode'])) {
            if (is_string($result['qrcode'])) {
                \Log::info('QR code é string', [
                    'length' => strlen($result['qrcode']),
                    'starts_with' => substr($result['qrcode'], 0, 20),
                    'is_valid_image' => $this->isValidBase64Image($result['qrcode']),
                ]);
                
                // Check if it starts with digit@ (pairing code pattern)
                if (preg_match('/^\d+@/', $result['qrcode'])) {
                    $pairingCode = $result['qrcode'];
                    \Log::info('QR code detectado como pairing code (formato: número@...)', ['code' => $pairingCode]);
                }
                // Check if it's a valid base64 image
                elseif ($this->isValidBase64Image($result['qrcode'])) {
                    $qrcode = ['base64' => $result['qrcode']];
                    \Log::info('QR code detectado como imagem base64 válida');
                }
            } elseif (is_array($result['qrcode'])) {
                \Log::info('QR code é array', ['keys' => array_keys($result['qrcode'])]);
                
                // Check if base64 field contains valid image
                if (isset($result['qrcode']['base64'])) {
                    $base64Value = $result['qrcode']['base64'];
                    
                    // Check if it's a pairing code (starts with digit@)
                    if (is_string($base64Value) && preg_match('/^\d+@/', $base64Value)) {
                        $pairingCode = $base64Value;
                        \Log::info('QR code base64 detectado como pairing code (formato: número@...)', [
                            'code' => $pairingCode,
                            'length' => strlen($pairingCode),
                        ]);
                    }
                    // Check if it contains commas (pairing code format: 2@...,code1,code2,code3)
                    elseif (is_string($base64Value) && strpos($base64Value, ',') !== false && preg_match('/^\d+@/', $base64Value)) {
                        // Extract just the first part (before first comma)
                        $pairingCode = explode(',', $base64Value)[0];
                        \Log::info('QR code base64 detectado como pairing code com múltiplos códigos', [
                            'code' => $pairingCode,
                            'full_value' => substr($base64Value, 0, 100),
                        ]);
                    }
                    // Check if it's a valid base64 image
                    elseif ($this->isValidBase64Image($base64Value)) {
                        $qrcode = $result['qrcode'];
                        \Log::info('QR code base64 é imagem válida');
                    } else {
                        \Log::warning('QR code base64 não é imagem válida nem pairing code reconhecido', [
                            'value_sample' => substr($base64Value, 0, 100),
                            'length' => strlen($base64Value),
                        ]);
                    }
                }
                
                if (isset($result['qrcode']['pairingCode'])) {
                    $pairingCode = $result['qrcode']['pairingCode'];
                    \Log::info('Pairing code encontrado em qrcode[pairingCode]', ['code' => $pairingCode]);
                }
            }
        } elseif (isset($result['base64'])) {
            \Log::info('Verificando result[base64]', [
                'length' => strlen($result['base64']),
                'starts_with' => substr($result['base64'], 0, 20),
            ]);
            
            if (preg_match('/^\d+@/', $result['base64'])) {
                $pairingCode = $result['base64'];
                \Log::info('result[base64] detectado como pairing code', ['code' => $pairingCode]);
            } elseif ($this->isValidBase64Image($result['base64'])) {
                $qrcode = ['base64' => $result['base64']];
                \Log::info('result[base64] é imagem válida');
            }
        }
        
        \Log::info('Evolution API - Resultado da extração', [
            'has_qrcode_image' => $qrcode !== null,
            'has_pairing_code' => $pairingCode !== null,
            'pairing_code_value' => $pairingCode,
        ]);

        // Wait a moment for instance to be ready
        sleep(2);

        // Configure webhook (use default URL if not provided, or use provided values)
        $webhookUrl = $request->input('webhook_url') ?: route('evolution.webhook');
        $events = $request->input('events', []);
        $webhookBase64 = $request->boolean('webhook_base64', false);

        // If no events selected, use default important events
        if (empty($events)) {
            $events = ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'QRCODE_UPDATED', 'CONNECTION_UPDATE'];
        }

        // Always configure webhook
        $webhookResult = $this->evolutionApi->setWebhook($webhookUrl, $events, $webhookBase64);
        $webhookWarning = null;
        
        if (isset($webhookResult['error'])) {
            \Log::warning('Erro ao configurar webhook durante conexão', [
                'error' => $webhookResult['error'],
                'url' => $webhookUrl,
                'events' => $events,
            ]);
            $webhookWarning = 'Webhook não configurado: ' . $webhookResult['error'];
        } elseif ($this->isLocalWebhookUrl($webhookUrl)) {
            $webhookWarning = 'Webhook configurado com URL local. A Evolution API não consegue acessar 127.0.0.1/localhost.';
        } else {
            \Log::info('Webhook configurado com sucesso durante conexão', [
                'url' => $webhookUrl,
                'events_count' => count($events),
            ]);
        }

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

        // Check status
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
            
            // Extract pairing code if available
            if (isset($qrcodeResult['pairingCode'])) {
                $pairingCode = $qrcodeResult['pairingCode'];
            } elseif (isset($qrcodeResult['pairing_code'])) {
                $pairingCode = $qrcodeResult['pairing_code'];
            }
            
            // Handle different QR code response formats
            if (isset($qrcodeResult['qrcode'])) {
                if (is_string($qrcodeResult['qrcode']) && $this->isValidBase64Image($qrcodeResult['qrcode'])) {
                    $qrcode = ['base64' => $qrcodeResult['qrcode']];
                } elseif (is_array($qrcodeResult['qrcode'])) {
                    if (isset($qrcodeResult['qrcode']['base64']) && $this->isValidBase64Image($qrcodeResult['qrcode']['base64'])) {
                        $qrcode = $qrcodeResult['qrcode'];
                    } elseif (isset($qrcodeResult['qrcode']['pairingCode'])) {
                        $pairingCode = $qrcodeResult['qrcode']['pairingCode'];
                    }
                }
            } elseif (isset($qrcodeResult['code']) && $this->isValidBase64Image($qrcodeResult['code'])) {
                $qrcode = ['base64' => $qrcodeResult['code']];
            } elseif (isset($qrcodeResult['base64']) && $this->isValidBase64Image($qrcodeResult['base64'])) {
                $qrcode = ['base64' => $qrcodeResult['base64']];
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

        // Adicionar warning se houve erro na criação mas QR code está disponível
        $creationWarning = null;
        if (isset($result['error']) && ($qrcode !== null || $pairingCode !== null)) {
            $creationWarning = 'Aviso: ' . $result['error'] . ' (mas a instância foi criada e o QR code está disponível)';
        }
        
        $responseData = [
            'success' => true,
            'message' => 'Instância criada com sucesso! Escaneie o QR Code para conectar.',
            'status' => $status,
            'qrcode' => $qrcode,
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
        
        $response = [];
        
        // Extract pairing code if available
        if (isset($result['pairingCode'])) {
            $response['pairingCode'] = $result['pairingCode'];
        } elseif (isset($result['pairing_code'])) {
            $response['pairingCode'] = $result['pairing_code'];
        }
        
        // Extract QR code image if available
        $qrcode = null;
        if (isset($result['qrcode'])) {
            if (is_string($result['qrcode']) && $this->isValidBase64Image($result['qrcode'])) {
                $qrcode = ['base64' => $result['qrcode']];
            } elseif (is_array($result['qrcode'])) {
                if (isset($result['qrcode']['base64']) && $this->isValidBase64Image($result['qrcode']['base64'])) {
                    $qrcode = $result['qrcode'];
                } elseif (isset($result['qrcode']['pairingCode'])) {
                    $response['pairingCode'] = $result['qrcode']['pairingCode'];
                }
            }
        } elseif (isset($result['code']) && $this->isValidBase64Image($result['code'])) {
            $qrcode = ['base64' => $result['code']];
        } elseif (isset($result['base64']) && $this->isValidBase64Image($result['base64'])) {
            $qrcode = ['base64' => $result['base64']];
        }
        
        if ($qrcode) {
            $response['qrcode'] = $qrcode;
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

        $result = $this->evolutionApi->setWebhook($url, $events, $webhookBase64);
        
        if (isset($result['error'])) {
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
     * Handle webhook from Evolution API.
     */
    public function webhook(Request $request): JsonResponse
    {
        // Log webhook data for debugging
        \Log::info('Evolution API Webhook', [
            'data' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        // Handle different event types
        $event = $request->input('event');
        $data = $request->input('data');

        switch ($event) {
            case 'qrcode.updated':
                // QR Code was updated
                break;
            case 'connection.update':
                // Connection status changed
                break;
            case 'messages.upsert':
                // New message received
                $this->handleIncomingMessage($data);
                break;
            case 'messages.update':
                // Message status updated (sent, delivered, read)
                $this->handleMessageUpdate($data);
                break;
            default:
                // Handle other events
                break;
        }

        return response()->json(['status' => 'ok']);
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
