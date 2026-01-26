<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiService
{
    private ?string $baseUrl = null;
    private ?string $apiKey = null;
    private ?string $instanceName = null;

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Initialize service with configuration.
     */
    private function initialize(): void
    {
        $baseUrl = config('services.evolution_api.url');
        $apiKey = config('services.evolution_api.key');
        
        if (empty($baseUrl) || empty($apiKey)) {
            Log::warning('Evolution API não configurada', [
                'url_set' => !empty($baseUrl),
                'key_set' => !empty($apiKey),
            ]);
            // Set empty strings instead of null to avoid type errors
            $this->baseUrl = '';
            $this->apiKey = '';
            $this->instanceName = 'secretario_guest';
            return;
        }
        
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        
        // Prefer instance name from session if available
        $sessionInstance = session()->get('whatsapp_instance_name');
        if (!empty($sessionInstance)) {
            $this->instanceName = $sessionInstance;
            return;
        }

        // Only set instance name if user is authenticated
        if (auth()->check()) {
            $this->instanceName = 'secretario_' . auth()->id();
        } else {
            $this->instanceName = 'secretario_guest';
        }
    }

    /**
     * Check if service is configured.
     */
    private function isConfigured(): bool
    {
        if (empty($this->baseUrl) || empty($this->apiKey) || $this->baseUrl === '' || $this->apiKey === '') {
            return false;
        }

        // Validate URL format
        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            Log::warning('Evolution API - URL inválida', ['url' => $this->baseUrl]);
            return false;
        }

        return true;
    }

    /**
     * Create or get instance.
     */
    public function createInstance(?string $whatsappNumber = null): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Evolution API não configurada. Verifique as variáveis EVOLUTION_API_URL e EVOLUTION_API_KEY no arquivo .env'];
        }

        // Use WhatsApp number if provided, otherwise use default
        if (!empty($whatsappNumber)) {
            // Clean number (only digits)
            $whatsappNumber = preg_replace('/\D/', '', $whatsappNumber);
            $this->instanceName = $whatsappNumber;
        } else {
            // Ensure instance name is set correctly
            if (auth()->check()) {
                $expectedName = 'secretario_' . auth()->id();
                if ($this->instanceName !== $expectedName) {
                    $this->instanceName = $expectedName;
                }
            } else {
                $this->instanceName = 'secretario_guest';
            }
        }

        try {
            $url = "{$this->baseUrl}/instance/create";
            $payload = [
                'instanceName' => $this->instanceName,
                'qrcode' => true,
                'integration' => 'WHATSAPP-BAILEYS',
            ];
            
            // Token is optional - only include if needed (API will generate dynamically if not provided)
            // According to docs, token can be left empty to be created dynamically

            Log::info('Evolution API - Criando instância', [
                'url' => $url,
                'instanceName' => $this->instanceName,
                'baseUrl' => $this->baseUrl,
                'hasApiKey' => !empty($this->apiKey),
                'payload' => $payload,
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'apikey' => $this->apiKey,
                ])
                ->asJson()
                ->post($url, $payload);

            $statusCode = $response->status();
            $responseBody = $response->json();
            $responseText = $response->body();

            Log::info('Evolution API - Resposta ao criar instância', [
                'status' => $statusCode,
                'response' => $responseBody,
                'response_text' => $responseText,
            ]);

            // 201 Created is success, 200 OK is also success
            if ($statusCode !== 201 && $statusCode !== 200 && !$response->successful()) {
                // Try to extract error message from different possible formats
                $errorMessage = 'Erro ao criar instância';
                
                // Handle specific HTTP status codes
                if ($statusCode === 400) {
                    $errorMessage = 'Requisição inválida. Verifique os parâmetros enviados.';
                    // Try to get more details from response
                    if (is_array($responseBody) && isset($responseBody['message'])) {
                        $errorMessage = $responseBody['message'];
                    }
                } elseif ($statusCode === 403) {
                    $errorMessage = 'Acesso negado. Verifique se a API Key está correta e tem permissões adequadas.';
                } elseif ($statusCode === 404) {
                    $errorMessage = 'Endpoint não encontrado. Verifique se a URL da Evolution API está correta.';
                } elseif ($statusCode === 401) {
                    $errorMessage = 'Não autorizado. Verifique se a API Key está correta.';
                } elseif ($statusCode === 500) {
                    $errorMessage = 'Erro interno do servidor Evolution API. Tente novamente mais tarde.';
                }
                
                if (is_array($responseBody)) {
                    $extractedMessage = $responseBody['message'] 
                        ?? $responseBody['error'] 
                        ?? $responseBody['errorMessage']
                        ?? (isset($responseBody['response']['message']) ? $responseBody['response']['message'] : null);
                    
                    if ($extractedMessage) {
                        $errorMessage = $extractedMessage;
                    }
                } elseif (!empty($responseText)) {
                    // Try to parse JSON from text
                    $decoded = json_decode($responseText, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $extractedMessage = $decoded['message'] ?? $decoded['error'] ?? null;
                        if ($extractedMessage) {
                            $errorMessage = $extractedMessage;
                        }
                    } else {
                        // If not JSON, use the text directly if it's not empty
                        if (strlen(trim($responseText)) > 0) {
                            $errorMessage = $responseText;
                        }
                    }
                }

                // Add status code to error message if not already included
                if (strpos($errorMessage, 'Status:') === false) {
                    $errorMessage .= " (Status: {$statusCode})";
                }

                Log::error('Evolution API - Erro ao criar instância', [
                    'status' => $statusCode,
                    'error' => $errorMessage,
                    'response_body' => $responseBody,
                    'response_text' => $responseText,
                    'url' => $url,
                    'instanceName' => $this->instanceName,
                    'baseUrl' => $this->baseUrl,
                    'payload' => $payload,
                    'api_key_length' => strlen($this->apiKey ?? ''),
                ]);

                return ['error' => $errorMessage];
            }

            // Success - return the response body
            if (is_array($responseBody) && !empty($responseBody)) {
                return $responseBody;
            }

            // If response is empty but status is success, return success indicator
            return ['success' => true, 'status' => 'created'];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $errorMessage = "Não foi possível conectar à Evolution API. Verifique se a URL está correta e se o servidor está acessível. URL: " . ($this->baseUrl ?? 'não configurado');
            Log::error('Evolution API - Erro de conexão ao criar instância', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl ?? 'não configurado',
                'baseUrl' => $this->baseUrl,
            ]);
            return ['error' => $errorMessage];
        } catch (\Exception $e) {
            $errorMessage = "Erro ao criar instância: " . $e->getMessage();
            Log::error('Evolution API - Exceção ao criar instância', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'url' => $this->baseUrl ?? 'não configurado',
            ]);
            return ['error' => $errorMessage];
        }
    }

    /**
     * Get QR Code.
     */
    public function getQrCode(): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Evolution API não configurada'];
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->get("{$this->baseUrl}/instance/connect/{$this->instanceName}", [
                'qrcode' => true,
            ]);

            $data = $response->json();
            
            // Ensure data is an array
            if (!is_array($data)) {
                $data = [];
            }
            
            // If instance doesn't exist, create it first
            if (isset($data['error']) || $response->status() === 404) {
                $createResult = $this->createInstance();
                if (isset($createResult['error'])) {
                    return $createResult;
                }
                // Try again after creation
                $response = Http::withHeaders([
                    'apikey' => $this->apiKey,
                ])->get("{$this->baseUrl}/instance/connect/{$this->instanceName}", [
                    'qrcode' => true,
                ]);
                $data = $response->json();
                // Ensure data is an array
                if (!is_array($data)) {
                    $data = [];
                }
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao obter QR Code', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get instance status.
     */
    public function getInstanceStatus(): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 'not_configured'];
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->get("{$this->baseUrl}/instance/fetchInstances");

            $instances = $response->json() ?? [];
            
            // If response is an object with instances array
            if (isset($instances['instances'])) {
                $instances = $instances['instances'];
            }
            
            foreach ($instances as $instance) {
                if (isset($instance['instanceName']) && $instance['instanceName'] === $this->instanceName) {
                    return [
                        'status' => $instance['state'] ?? $instance['status'] ?? 'unknown',
                        'instance' => $instance,
                    ];
                }
            }

            return ['status' => 'not_found'];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao obter status', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Logout instance.
     */
    public function logoutInstance(): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Evolution API não configurada'];
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->delete("{$this->baseUrl}/instance/logout/{$this->instanceName}");

            $data = $response->json();
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao fazer logout', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Delete instance.
     */
    public function deleteInstance(): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Evolution API não configurada'];
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->delete("{$this->baseUrl}/instance/delete/{$this->instanceName}");

            $data = $response->json();
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao deletar instância', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Set webhook configuration.
     */
    public function setWebhook(string $url, array $events = [], bool $webhookBase64 = false): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Evolution API não configurada'];
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->post("{$this->baseUrl}/webhook/set/{$this->instanceName}", [
                'url' => $url,
                'webhook_by_events' => true,
                'webhook_base64' => $webhookBase64,
                'events' => $events,
            ]);

            $data = $response->json();
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao configurar webhook', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get webhook configuration.
     */
    public function getWebhook(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->get("{$this->baseUrl}/webhook/find/{$this->instanceName}");

            $data = $response->json();
            
            // Ensure we always return an array
            if (!is_array($data)) {
                return [];
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao obter webhook', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Send message.
     */
    public function sendMessage(string $number, string $message): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Evolution API não configurada'];
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->post("{$this->baseUrl}/message/sendText/{$this->instanceName}", [
                'number' => $number,
                'text' => $message,
            ]);

            $data = $response->json();
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao enviar mensagem', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get instance name for current user.
     */
    public function getInstanceName(): string
    {
        if (empty($this->instanceName) || $this->instanceName === 'secretario_guest') {
            if (auth()->check()) {
                $this->instanceName = 'secretario_' . auth()->id();
            } else {
                $this->instanceName = 'secretario_guest';
            }
        }
        
        // Ensure we always return a non-empty string
        return $this->instanceName ?: 'secretario_guest';
    }
}
