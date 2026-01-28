<?php

namespace App\Services\EvolutionApi;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Client
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function isConfigured(): bool
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            return false;
        }

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            Log::warning('Evolution API - URL inválida', ['url' => $this->baseUrl]);
            return false;
        }

        return true;
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->request('get', $path, $query);
    }

    public function post(string $path, array $payload = []): Response
    {
        return $this->request('post', $path, $payload);
    }

    public function delete(string $path, array $payload = []): Response
    {
        return $this->request('delete', $path, $payload);
    }

    private function request(string $method, string $path, array $payload = []): Response
    {
        $url = "{$this->baseUrl}/" . ltrim($path, '/');

        // Suporte para diferentes tipos de headers de autenticação
        // Pode ser configurado via EVOLUTION_API_AUTH_TYPE no .env
        // Valores: 'apikey' (padrão), 'bearer', 'x-api-key'
        $authType = env('EVOLUTION_API_AUTH_TYPE', 'apikey');
        
        $headers = [];
        switch (strtolower($authType)) {
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
                break;
            case 'x-api-key':
                $headers['x-api-key'] = $this->apiKey;
                break;
            case 'apikey':
            default:
                $headers['apikey'] = $this->apiKey;
                break;
        }

        // Log detalhado do request (especialmente útil para debug de webhook)
        Log::info('Evolution API - Request', [
            'method' => strtoupper($method),
            'url' => $url,
            'path' => $path,
            'auth_type' => $authType,
            'headers' => array_keys($headers), // Não logar o token por segurança
            'payload' => $payload,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ]);

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->asJson()
            ->{$method}($url, $payload);

        // Log detalhado da resposta (especialmente útil para debug de erros 400)
        Log::info('Evolution API - Response', [
            'method' => strtoupper($method),
            'url' => $url,
            'status_code' => $response->status(),
            'response_body' => $response->json(),
            'response_text' => $response->body(),
            'response_headers' => $response->headers(),
        ]);

        return $response;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }
}
