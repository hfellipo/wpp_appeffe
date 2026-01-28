<?php

namespace App\Services;

use App\Models\WhatsAppInstance;
use App\Services\EvolutionApi\Client;
use App\Services\EvolutionApi\Resources\InstanceResource;
use App\Services\EvolutionApi\Resources\MessageResource;
use App\Services\EvolutionApi\Resources\WebhookResource;
use Illuminate\Support\Facades\Log;

class EvolutionApiService
{
    private ?string $baseUrl = null;
    private ?string $apiKey = null;
    private ?string $instanceName = null;
    private Client $client;
    private InstanceResource $instances;
    private WebhookResource $webhooks;
    private MessageResource $messages;

    public function __construct(
        Client $client,
        InstanceResource $instances,
        WebhookResource $webhooks,
        MessageResource $messages
    )
    {
        $this->client = $client;
        $this->instances = $instances;
        $this->webhooks = $webhooks;
        $this->messages = $messages;

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
            $dbInstance = WhatsAppInstance::where('user_id', auth()->id())
                ->latest('id')
                ->first();

            if ($dbInstance) {
                $this->instanceName = $dbInstance->instance_name;
                session()->put('whatsapp_instance_name', $this->instanceName);
                return;
            }

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

        return $this->client->isConfigured();
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
            $result = $this->instances->create($this->instanceName);

            if (isset($result['error'])) {
                Log::error('Evolution API - Erro ao criar instância', [
                    'error' => $result['error'],
                    'instanceName' => $this->instanceName,
                ]);
            }

            return $result;
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
            $data = $this->instances->connect($this->instanceName);
            
            // If instance doesn't exist, create it first
            if (isset($data['error'])) {
                $createResult = $this->createInstance();
                if (isset($createResult['error'])) {
                    return $createResult;
                }
                // Try again after creation
                $data = $this->instances->connect($this->instanceName);
            }

            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao obter QR Code', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get instance status.
     * Uses connectionState endpoint (more direct) or falls back to fetchInstances.
     */
    public function getInstanceStatus(): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 'not_configured'];
        }

        try {
            // Try connectionState endpoint first (more direct according to Postman collection)
            try {
                $connectionState = $this->instances->connectionState($this->instanceName);
                
                if (!isset($connectionState['error'])) {
                    // Extract state from connectionState response
                    $state = $connectionState['state'] 
                        ?? $connectionState['connectionStatus'] 
                        ?? $connectionState['status'] 
                        ?? null;
                    
                    if ($state !== null) {
                        return [
                            'status' => $state,
                            'data' => $connectionState,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Evolution API - Erro ao usar connectionState, tentando fetchInstances', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Fallback to fetchInstances
            $instances = $this->instances->fetchInstances();
            
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
            $data = $this->instances->logout($this->instanceName);
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
            $data = $this->instances->delete($this->instanceName);
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao deletar instância', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Set webhook configuration (simple version).
     */
    public function setWebhook(string $url, array $events = [], bool $webhookBase64 = false): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Evolution API não configurada'];
        }

        try {
            $data = $this->webhooks->set($this->instanceName, $url, $events, $webhookBase64);
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao configurar webhook', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Set webhook for a specific instance (avoids relying on session/last-instance).
     */
    public function setWebhookForInstance(string $instanceName, string $url, array $events = [], bool $webhookBase64 = false): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Evolution API não configurada'];
        }

        $instanceName = trim($instanceName);
        if ($instanceName === '') {
            return ['error' => 'Nome da instância é obrigatório para configurar o webhook'];
        }

        try {
            $data = $this->webhooks->set($instanceName, $url, $events, $webhookBase64);
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao configurar webhook (instância explícita)', [
                'error' => $e->getMessage(),
                'instance_name' => $instanceName,
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Set webhook configuration with advanced options.
     * 
     * @param string $url Webhook URL
     * @param array $events Array of events
     * @param bool $webhookBase64 Whether to send media as base64
     * @param bool $enabled Whether webhook is enabled
     * @param bool $byEvents Whether to use byEvents mode
     * @param array|null $headers Custom headers
     * @return array
     */
    public function setWebhookAdvanced(
        string $url, 
        array $events = [], 
        bool $webhookBase64 = false,
        bool $enabled = true,
        bool $byEvents = false,
        ?array $headers = null
    ): array {
        if (!$this->isConfigured()) {
            return ['error' => 'Evolution API não configurada'];
        }

        try {
            $data = $this->webhooks->set(
                $this->instanceName, 
                $url, 
                $events, 
                $webhookBase64,
                $enabled,
                $byEvents,
                $headers
            );
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('Evolution API - Erro ao configurar webhook avançado', ['error' => $e->getMessage()]);
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
            $data = $this->webhooks->find($this->instanceName);
            return is_array($data) ? $data : [];
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
            $data = $this->messages->sendText($this->instanceName, $number, $message);
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
