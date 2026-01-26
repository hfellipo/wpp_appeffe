<?php

namespace App\Http\Controllers;

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
        $result = $this->evolutionApi->createInstance($whatsappNumber);
        
        if (isset($result['error'])) {
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

        // Persist instance name for subsequent requests
        $request->session()->put('whatsapp_instance_name', $whatsappNumber);

        // Try to extract QR code from creation response (faster UX)
        $qrcode = null;
        if (isset($result['qrcode'])) {
            if (is_string($result['qrcode'])) {
                $qrcode = ['base64' => $result['qrcode']];
            } elseif (is_array($result['qrcode'])) {
                $qrcode = $result['qrcode'];
            }
        } elseif (isset($result['base64'])) {
            $qrcode = ['base64' => $result['base64']];
        } elseif (isset($result['code'])) {
            $qrcode = ['base64' => $result['code']];
        }

        // Wait a moment for instance to be ready
        sleep(1);

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

        // Get QR code if connecting (fallback if not returned on create)
        if ($status === 'connecting' || $status === 'not_found') {
            $qrcodeResult = $this->evolutionApi->getQrCode();
            
            // Handle different QR code response formats
            if (isset($qrcodeResult['qrcode'])) {
                if (is_string($qrcodeResult['qrcode'])) {
                    $qrcode = ['base64' => $qrcodeResult['qrcode']];
                } elseif (is_array($qrcodeResult['qrcode'])) {
                    $qrcode = $qrcodeResult['qrcode'];
                }
            } elseif (isset($qrcodeResult['code'])) {
                $qrcode = ['base64' => $qrcodeResult['code']];
            } elseif (isset($qrcodeResult['base64'])) {
                $qrcode = ['base64' => $qrcodeResult['base64']];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Instância criada com sucesso! Escaneie o QR Code para conectar.',
            'status' => $status,
            'qrcode' => $qrcode,
            'instanceName' => $whatsappNumber,
            'webhook_warning' => $webhookWarning,
        ]);
    }

    private function isLocalWebhookUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        return in_array($host, ['127.0.0.1', 'localhost'], true);
    }

    /**
     * Get QR Code.
     */
    public function qrcode(): JsonResponse
    {
        $result = $this->evolutionApi->getQrCode();
        
        // Handle different response formats
        if (isset($result['qrcode'])) {
            return response()->json(['qrcode' => $result['qrcode']]);
        } elseif (isset($result['code'])) {
            return response()->json(['qrcode' => ['base64' => $result['code']]]);
        } elseif (isset($result['base64'])) {
            return response()->json(['qrcode' => ['base64' => $result['base64']]]);
        }
        
        return response()->json($result);
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
