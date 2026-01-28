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
        // Formato conforme exemplo fornecido pelo usuário (top-level, sem wrapper "webhook")
        // Baseado em: api/set_webhook_top_level.php
        
        // IMPORTANTE: Garantir barra final na URL (conforme exemplo)
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        // Headers padrão se não fornecidos
        if ($headers === null || empty($headers)) {
            $headers = [
                'Content-Type' => 'application/json'
            ];
        }
        
        // Payload no formato top-level (exatamente como no exemplo que funciona)
        $payload = [
            'url' => $url,
            'events' => $events,
            'webhook_by_events' => $byEvents,
            'webhook_base64' => $webhookBase64,
            'headers' => $headers,
        ];

        $endpoint = "/webhook/set/{$instanceName}";
        $fullUrl = $this->client->baseUrl() . $endpoint;
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        
        Log::info('Evolution API - WEBHOOK SET - INÍCIO (formato top-level)', [
            'timestamp' => now()->toIso8601String(),
            'instance_name' => $instanceName,
            'webhook_url' => $url,
            'webhook_url_ends_with_slash' => substr($url, -1) === '/',
            'webhook_url_length' => strlen($url),
            'events' => $events,
            'events_count' => count($events),
            'webhook_base64' => $webhookBase64,
            'webhook_by_events' => $byEvents,
            'headers' => $headers,
            'payload' => $payload,
            'payload_json' => $payloadJson,
            'payload_json_length' => strlen($payloadJson),
            'endpoint' => $endpoint,
            'full_url' => $fullUrl,
            'base_url' => $this->client->baseUrl(),
            'called_from' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
        ]);

        // Tentativa 1: formato top-level (recomendado)
        $response = $this->client->post($endpoint, $payload);

        $statusCode = $response->status();
        $responseBody = $response->json();
        $responseText = $response->body();
        $responseHeaders = $response->headers();

        Log::info('Evolution API - WEBHOOK SET - RESPOSTA (formato top-level)', [
            'timestamp' => now()->toIso8601String(),
            'status_code' => $statusCode,
            'successful' => $response->successful(),
            'response_body' => $responseBody,
            'response_body_type' => gettype($responseBody),
            'response_text' => $responseText,
            'response_text_length' => strlen($responseText),
            'response_headers' => $responseHeaders,
            'url_enviada' => $url,
            'instance_name' => $instanceName,
            'payload_enviado' => $payload,
        ]);
        
        // Se erro 400, tentar formato alternativo (wrapper "webhook")
        if ($statusCode === 400) {
            Log::warning('Evolution API - WEBHOOK SET - Erro 400 com formato top-level, tentando formato wrapper', [
                'timestamp' => now()->toIso8601String(),
                'instance_name' => $instanceName,
                'url' => $url,
                'error_response_body' => $responseBody,
                'error_response_text' => $responseText,
            ]);
            
            // Tentativa 2: formato wrapper (algumas versões da Evolution API usam este formato)
            $payloadWrapper = [
                'webhook' => [
                    'enabled' => $enabled,
                    'url' => $url,
                    'headers' => $headers,
                    'byEvents' => $byEvents,
                    'base64' => $webhookBase64,
                    'events' => $events,
                ]
            ];
            
            $payloadWrapperJson = json_encode($payloadWrapper, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            
            Log::info('Evolution API - WEBHOOK SET - Tentando formato wrapper', [
                'timestamp' => now()->toIso8601String(),
                'instance_name' => $instanceName,
                'payload_wrapper' => $payloadWrapper,
                'payload_wrapper_json' => $payloadWrapperJson,
                'endpoint' => $endpoint,
            ]);
            
            $response = $this->client->post($endpoint, $payloadWrapper);
            $statusCode = $response->status();
            $responseBody = $response->json();
            $responseText = $response->body();
            $responseHeaders = $response->headers();
            
            Log::info('Evolution API - WEBHOOK SET - Resposta do formato wrapper', [
                'timestamp' => now()->toIso8601String(),
                'status_code' => $statusCode,
                'successful' => $response->successful(),
                'response_body' => $responseBody,
                'response_text' => $responseText,
                'response_headers' => $responseHeaders,
            ]);
        }
        
        // Se ainda deu erro, logar TODOS os detalhes
        if ($statusCode === 400 || !$response->successful()) {
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

            Log::error('Evolution API - WEBHOOK SET - ERRO FINAL (após tentar ambos formatos)', [
                'timestamp' => now()->toIso8601String(),
                'status_code' => $statusCode,
                'successful' => $response->successful(),
                'error_message' => $errorMessage,
                'response_body' => $responseBody,
                'response_body_type' => gettype($responseBody),
                'response_text' => $responseText,
                'response_text_length' => strlen($responseText),
                'response_headers' => $responseHeaders ?? $response->headers(),
                'url_webhook' => $url,
                'url_webhook_ends_with_slash' => substr($url, -1) === '/',
                'url_webhook_valid' => filter_var($url, FILTER_VALIDATE_URL) !== false,
                'instance_name' => $instanceName,
                'instance_name_length' => strlen($instanceName),
                'instance_name_clean' => preg_replace('/\s+/', '', $instanceName),
                'events' => $events,
                'events_count' => count($events),
                'events_json' => json_encode($events),
                'webhook_base64' => $webhookBase64,
                'webhook_by_events' => $byEvents,
                'endpoint' => $endpoint,
                'full_endpoint_url' => $fullUrl,
                'base_url' => $this->client->baseUrl(),
                'payload_original' => $payload,
                'payload_json_original' => $payloadJson,
            ]);
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
