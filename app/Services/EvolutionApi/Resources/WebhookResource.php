<?php

namespace App\Services\EvolutionApi\Resources;

use App\Services\EvolutionApi\Client;
use Illuminate\Support\Facades\Log;

class WebhookResource
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Configure webhook for an instance.
     * Format according to official documentation: /webhook/set/{instance}
     * 
     * @param string $instanceName Instance name
     * @param string $url Webhook URL
     * @param array $events Array of events to listen to
     * @param bool $webhookBase64 Whether to send media as base64
     * @param bool $enabled Whether webhook is enabled (default: true)
     * @param bool $byEvents Whether to use byEvents mode (default: false)
     * @param array|null $headers Custom headers (optional)
     * @return array
     */
    public function set(
        string $instanceName, 
        string $url, 
        array $events, 
        bool $webhookBase64 = false,
        bool $enabled = true,
        bool $byEvents = false,
        ?array $headers = null
    ): array {
        // Formato conforme exemplo fornecido pelo usuário
        // Payload direto, sem objeto "webhook" envolvendo
        
        // IMPORTANTE: Adicionar "/" no final da URL conforme exemplo
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        // Headers padrão se não fornecidos
        if ($headers === null || empty($headers)) {
            $headers = [
                'Content-Type' => 'application/json'
            ];
        }
        
        // Payload no formato exato do exemplo
        $payload = [
            'url' => $url,
            'events' => $events,
            'webhook_by_events' => $byEvents,
            'webhook_base64' => $webhookBase64,
            'headers' => $headers,
        ];

        Log::info('Evolution API - Configurando webhook', [
            'instance_name' => $instanceName,
            'url' => $url,
            'url_with_slash' => $url, // URL já tem "/" no final
            'events_count' => count($events),
            'events' => $events,
            'webhook_base64' => $webhookBase64,
            'webhook_by_events' => $byEvents,
            'headers' => $headers,
            'payload' => $payload,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            'endpoint' => "/webhook/set/{$instanceName}",
            'full_url' => $this->client->baseUrl() . "/webhook/set/{$instanceName}",
        ]);

        $response = $this->client->post("/webhook/set/{$instanceName}", $payload);

        $statusCode = $response->status();
        $responseBody = $response->json();
        $responseText = $response->body();

        Log::info('Evolution API - Resposta da configuração do webhook', [
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'response_text' => $responseText,
            'response_text_length' => strlen($responseText),
            'url_enviada' => $url,
            'instance_name' => $instanceName,
            'payload_enviado' => $payload,
        ]);
        
        // Se erro 400, logar TODOS os detalhes e tentar formato alternativo
        if ($statusCode === 400) {
            // Extrair mensagem de erro real da resposta
            $errorMessage = 'Bad Request';
            if (is_array($responseBody)) {
                $errorMessage = $responseBody['message'] 
                    ?? $responseBody['error'] 
                    ?? $responseBody['errorMessage']
                    ?? ($responseBody['response']['message'] ?? null)
                    ?? (isset($responseBody['response']) && is_string($responseBody['response']) ? $responseBody['response'] : null)
                    ?? json_encode($responseBody, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } elseif (!empty($responseText)) {
                $decoded = json_decode($responseText, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $errorMessage = $decoded['message'] ?? $decoded['error'] ?? $responseText;
                } else {
                    $errorMessage = $responseText;
                }
            }

            Log::error('Evolution API - Erro 400 Bad Request - DETALHES COMPLETOS', [
                'status_code' => $statusCode,
                'error_message' => $errorMessage,
                'response_body' => $responseBody,
                'response_text' => $responseText,
                'response_text_raw' => $response->body(),
                'response_headers' => $response->headers(),
                'payload_enviado' => $payload,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'url_webhook' => $url,
                'url_webhook_ends_with_slash' => substr($url, -1) === '/',
                'instance_name' => $instanceName,
                'events' => $events,
                'events_count' => count($events),
                'webhook_base64' => $webhookBase64,
                'webhook_by_events' => $byEvents,
                'headers' => $headers,
                'endpoint' => "/webhook/set/{$instanceName}",
                'full_endpoint_url' => $this->client->baseUrl() . "/webhook/set/{$instanceName}",
            ]);
            
            // NOTA: Não tentamos formato alternativo pois já estamos usando o formato do exemplo fornecido
            // Se ainda der erro 400, verificar os logs acima para identificar o problema
        }

        return $this->normalizeResponse($response, 'Erro ao configurar webhook');
    }

    /**
     * Configure webhook with simple parameters (backward compatibility).
     * 
     * @param string $instanceName Instance name
     * @param string $url Webhook URL
     * @param array $events Array of events to listen to
     * @param bool $webhookBase64 Whether to send media as base64
     * @return array
     */
    public function setSimple(string $instanceName, string $url, array $events, bool $webhookBase64 = false): array
    {
        return $this->set($instanceName, $url, $events, $webhookBase64);
    }

    /**
     * Configure webhook using official documentation format (alternative method).
     * Format: Direct fields without "webhook" wrapper object.
     * Endpoint: /webhook/set/{instance}
     * 
     * @param string $instanceName Instance name
     * @param string $url Webhook URL
     * @param array $events Array of events
     * @param bool $webhookBase64 Whether to send media as base64
     * @param bool $enabled Whether webhook is enabled
     * @param bool $byEvents Whether to use byEvents mode
     * @return array
     */
    public function setOfficialFormat(
        string $instanceName,
        string $url,
        array $events,
        bool $webhookBase64 = false,
        bool $enabled = true,
        bool $byEvents = false
    ): array {
        // Formato conforme exemplo fornecido (igual ao método set principal)
        // Adicionar "/" no final da URL conforme exemplo
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        $payload = [
            'url' => $url,
            'events' => $events,
            'webhook_by_events' => $byEvents,
            'webhook_base64' => $webhookBase64,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
        ];

        Log::info('Evolution API - Configurando webhook (formato oficial)', [
            'instance_name' => $instanceName,
            'url' => $url,
            'url_with_slash' => $url,
            'events_count' => count($events),
            'events' => $events,
            'payload' => $payload,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            'endpoint' => "/webhook/set/{$instanceName}",
        ]);

        $response = $this->client->post("/webhook/set/{$instanceName}", $payload);

        $statusCode = $response->status();
        $responseBody = $response->json();
        $responseText = $response->body();

        Log::info('Evolution API - Resposta webhook (formato oficial)', [
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'response_text' => $responseText,
        ]);

        return $this->normalizeResponse($response, 'Erro ao configurar webhook');
    }

    public function find(string $instanceName): array
    {
        $response = $this->client->get("/webhook/find/{$instanceName}");

        return $this->normalizeResponse($response, 'Erro ao obter webhook');
    }

    private function normalizeResponse($response, string $defaultMessage): array
    {
        $statusCode = $response->status();
        $responseBody = $response->json();
        $responseText = $response->body();

        if (!$response->successful()) {
            $errorMessage = $this->extractErrorMessage($statusCode, $responseBody, $responseText, $defaultMessage);
            Log::error('Evolution API - Webhook error', [
                'status' => $statusCode,
                'error' => $errorMessage,
                'response' => $responseText,
            ]);
            return ['error' => $errorMessage];
        }

        if (is_array($responseBody) && !empty($responseBody)) {
            return $responseBody;
        }

        return ['success' => true, 'status' => 'ok'];
    }

    private function extractErrorMessage(int $statusCode, $responseBody, string $responseText, string $defaultMessage): string
    {
        $errorMessage = $defaultMessage;

        if ($statusCode === 400) {
            $errorMessage = 'Requisição inválida (Bad Request). Verifique se a instância existe e os parâmetros estão corretos.';
            
            // Log detalhado para erro 400
            Log::error('Evolution API - Erro 400 Bad Request no webhook', [
                'response_body' => $responseBody,
                'response_text' => $responseText,
                'status_code' => $statusCode,
            ]);
        } elseif ($statusCode === 401) {
            $errorMessage = 'Não autorizado. Verifique se a API Key está correta.';
        } elseif ($statusCode === 403) {
            $errorMessage = 'Acesso negado. Verifique se a API Key está correta e tem permissões adequadas.';
        } elseif ($statusCode === 404) {
            $errorMessage = 'Instância não encontrada. Verifique se a instância foi criada corretamente.';
        } elseif ($statusCode >= 500) {
            $errorMessage = 'Erro interno do servidor Evolution API. Tente novamente mais tarde.';
        }

        if (is_array($responseBody)) {
            $extractedMessage = $responseBody['message']
                ?? $responseBody['error']
                ?? $responseBody['errorMessage']
                ?? $responseBody['response']['message'] ?? null
                ?? (isset($responseBody['response']) && is_string($responseBody['response']) ? $responseBody['response'] : null);
                
            if ($extractedMessage) {
                $errorMessage = is_string($extractedMessage) 
                    ? $extractedMessage 
                    : json_encode($extractedMessage, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } else {
                // Se não encontrou mensagem específica, retornar o JSON completo para debug
                $errorMessage = json_encode($responseBody, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            }
        } elseif (!empty($responseText)) {
            $decoded = json_decode($responseText, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $extractedMessage = $decoded['message'] ?? $decoded['error'] ?? null;
                if ($extractedMessage) {
                    $errorMessage = $extractedMessage;
                } else {
                    // Se não encontrou mensagem específica, retornar o JSON completo
                    $errorMessage = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                }
            } elseif (strlen(trim($responseText)) > 0) {
                $errorMessage = $responseText;
            }
        }

        if (strpos($errorMessage, 'Status:') === false) {
            $errorMessage .= " (Status: {$statusCode})";
        }

        return $errorMessage;
    }
}
