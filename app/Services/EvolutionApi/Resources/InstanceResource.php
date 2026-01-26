<?php

namespace App\Services\EvolutionApi\Resources;

use App\Services\EvolutionApi\Client;
use Illuminate\Support\Facades\Log;

class InstanceResource
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function create(string $instanceName): array
    {
        $payload = [
            'instanceName' => $instanceName,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ];

        Log::info('Evolution API - Criando instância', [
            'url' => $this->client->baseUrl() . '/instance/create',
            'instanceName' => $instanceName,
            'payload' => $payload,
        ]);

        $response = $this->client->post('/instance/create', $payload);

        return $this->normalizeResponse($response, 'Erro ao criar instância');
    }

    public function connect(string $instanceName): array
    {
        $response = $this->client->get("/instance/connect/{$instanceName}", [
            'qrcode' => true,
        ]);

        return $this->normalizeResponse($response, 'Erro ao obter QR Code');
    }

    public function fetchInstances(): array
    {
        $response = $this->client->get('/instance/fetchInstances');

        return $this->normalizeResponse($response, 'Erro ao obter instâncias');
    }

    public function logout(string $instanceName): array
    {
        $response = $this->client->delete("/instance/logout/{$instanceName}");

        return $this->normalizeResponse($response, 'Erro ao fazer logout');
    }

    public function delete(string $instanceName): array
    {
        $response = $this->client->delete("/instance/delete/{$instanceName}");

        return $this->normalizeResponse($response, 'Erro ao deletar instância');
    }

    private function normalizeResponse($response, string $defaultMessage): array
    {
        $statusCode = $response->status();
        $responseBody = $response->json();
        $responseText = $response->body();

        if ($statusCode !== 201 && $statusCode !== 200 && !$response->successful()) {
            $errorMessage = $this->extractErrorMessage($statusCode, $responseBody, $responseText, $defaultMessage);
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
            $errorMessage = 'Requisição inválida. Verifique os parâmetros enviados.';
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
