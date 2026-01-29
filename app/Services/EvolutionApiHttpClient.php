<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class EvolutionApiHttpClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.evolution_api.url'), '/');
        $this->apiKey = (string) config('services.evolution_api.key');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return array{status:int, json:array|null, text:string, headers:array}
     */
    private function normalize(Response $response): array
    {
        return [
            'status' => $response->status(),
            'json' => $response->json(),
            'text' => $response->body(),
            'headers' => $response->headers(),
        ];
    }

    private function headers(): array
    {
        // Evolution API padrão: apikey: <key>
        return [
            'apikey' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function post(string $path, array $payload): array
    {
        $url = "{$this->baseUrl}/" . ltrim($path, '/');
        $response = Http::timeout(30)->withHeaders($this->headers())->asJson()->post($url, $payload);
        return $this->normalize($response);
    }

    public function get(string $path, array $query = []): array
    {
        $url = "{$this->baseUrl}/" . ltrim($path, '/');
        $response = Http::timeout(30)->withHeaders($this->headers())->get($url, $query);
        return $this->normalize($response);
    }

    public function delete(string $path, array $query = []): array
    {
        $url = "{$this->baseUrl}/" . ltrim($path, '/');
        $response = Http::timeout(30)->withHeaders($this->headers())->delete($url, $query);
        return $this->normalize($response);
    }
}

