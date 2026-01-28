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
        // IMPORTANTE: A API key vem de EVOLUTION_API_KEY no .env (via config/services.php)
        $authType = env('EVOLUTION_API_AUTH_TYPE', 'apikey');
        
        $headers = [];
        switch (strtolower($authType)) {
            case 'bearer':
                // Usa: Authorization: Bearer <EVOLUTION_API_KEY>
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
                break;
            case 'x-api-key':
                // Usa: x-api-key: <EVOLUTION_API_KEY>
                $headers['x-api-key'] = $this->apiKey;
                break;
            case 'apikey':
            default:
                // Usa: apikey: <EVOLUTION_API_KEY> (padrão)
                $headers['apikey'] = $this->apiKey;
                break;
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        
        // Log detalhado do request (especialmente útil para debug de webhook)
        Log::info('Evolution API - CLIENT REQUEST - INÍCIO', [
            'timestamp' => now()->toIso8601String(),
            'method' => strtoupper($method),
            'url' => $url,
            'path' => $path,
            'base_url' => $this->baseUrl,
            'auth_type' => $authType,
            'headers' => array_keys($headers), // Não logar o token por segurança
            'has_api_key' => !empty($this->apiKey),
            'api_key_length' => strlen($this->apiKey ?? ''),
            'payload' => $payload,
            'payload_json' => $payloadJson,
            'payload_json_length' => strlen($payloadJson),
            'payload_keys' => is_array($payload) ? array_keys($payload) : null,
        ]);

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->asJson()
            ->{$method}($url, $payload);

        $statusCode = $response->status();
        $responseBody = $response->json();
        $responseText = $response->body();
        $responseHeaders = $response->headers();

        // Log detalhado da resposta (especialmente útil para debug de erros 400)
        Log::info('Evolution API - CLIENT REQUEST - RESPOSTA', [
            'timestamp' => now()->toIso8601String(),
            'method' => strtoupper($method),
            'url' => $url,
            'status_code' => $statusCode,
            'successful' => $response->successful(),
            'response_body' => $responseBody,
            'response_body_type' => gettype($responseBody),
            'response_text' => $responseText,
            'response_text_length' => strlen($responseText),
            'response_headers' => $responseHeaders,
            'curl_error' => null, // Laravel Http não expõe curl_error diretamente
        ]);

        return $response;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }
}
