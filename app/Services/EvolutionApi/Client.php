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

        return Http::timeout(30)
            ->withHeaders([
                'apikey' => $this->apiKey,
            ])
            ->asJson()
            ->{$method}($url, $payload);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }
}
