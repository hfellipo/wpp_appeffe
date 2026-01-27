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

    public function set(string $instanceName, string $url, array $events, bool $webhookBase64): array
    {
        $response = $this->client->post("/webhook/set/{$instanceName}", [
            'url' => $url,
            'webhook_by_events' => true,
            'webhook_base64' => $webhookBase64,
            'events' => $events,
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
            $errorMessage = 'Requisição inválida. Verifique se a instância existe e os parâmetros estão corretos.';
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
                ?? (isset($responseBody['response']['message']) ? $responseBody['response']['message'] : null);
            if ($extractedMessage) {
                $errorMessage = $extractedMessage;
            }
        } elseif (!empty($responseText)) {
            $decoded = json_decode($responseText, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $extractedMessage = $decoded['message'] ?? $decoded['error'] ?? null;
                if ($extractedMessage) {
                    $errorMessage = $extractedMessage;
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
