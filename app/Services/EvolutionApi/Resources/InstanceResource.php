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
            'payload_json' => json_encode($payload),
        ]);

        $response = $this->client->post('/instance/create', $payload);
        
        $statusCode = $response->status();
        $responseBody = $response->json();
        $responseText = $response->body();
        
        // Log da resposta completa da Evolution API
        Log::info('Evolution API - RESPOSTA COMPLETA da criação de instância', [
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'response_text' => $responseText,
            'response_headers' => $response->headers(),
        ]);
        
        // Se retornou erro 400, logar detalhes do erro
        if ($statusCode === 400) {
            Log::error('Evolution API - Erro 400 Bad Request ao criar instância', [
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'response_text' => $responseText,
                'payload_enviado' => $payload,
            ]);
        }

        return $this->normalizeResponse($response, 'Erro ao criar instância');
    }

    public function connect(string $instanceName): array
    {
        $response = $this->client->get("/instance/connect/{$instanceName}", [
            'qrcode' => true,
        ]);
        
        // Log da resposta completa do connect
        Log::info('Evolution API - RESPOSTA COMPLETA do connect (QR Code)', [
            'status_code' => $response->status(),
            'response_body' => $response->json(),
            'response_text_length' => strlen($response->body()),
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

        // Se a resposta tem dados válidos (mesmo com erro 400), tentar extrair
        // A Evolution API pode retornar erro 400 mas ainda criar a instância e retornar QR code
        if (is_array($responseBody) && !empty($responseBody)) {
            // Verificar se tem QR code ou dados úteis mesmo com erro
            $hasUsefulData = isset($responseBody['qrcode']) 
                || isset($responseBody['base64']) 
                || isset($responseBody['code'])
                || isset($responseBody['pairingCode'])
                || isset($responseBody['instance'])
                || isset($responseBody['data']);
            
            if ($hasUsefulData && ($statusCode === 200 || $statusCode === 201)) {
                // Sucesso completo
                return $responseBody;
            } elseif ($hasUsefulData && $statusCode === 400) {
                // Erro 400 mas tem dados úteis (pode ser erro em campo opcional como webhook)
                Log::warning('Evolution API - Erro 400 mas resposta contém dados úteis', [
                    'response_body' => $responseBody,
                ]);
                // Retornar os dados mesmo com erro, mas adicionar aviso
                $responseBody['_warning'] = 'A API retornou erro 400, mas a instância pode ter sido criada. Verifique os logs.';
                return $responseBody;
            }
        }

        // Se não tem dados úteis e é erro, retornar erro
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
