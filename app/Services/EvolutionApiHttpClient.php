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
        try {
            $response = Http::timeout(15)->withHeaders($this->headers())->asJson()->post($url, $payload);
            return $this->normalize($response);
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'json' => null,
                'text' => $e->getMessage(),
                'headers' => [],
            ];
        }
    }

    public function get(string $path, array $query = []): array
    {
        $url = "{$this->baseUrl}/" . ltrim($path, '/');
        try {
            $response = Http::timeout(10)->withHeaders($this->headers())->get($url, $query);
            return $this->normalize($response);
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'json' => null,
                'text' => $e->getMessage(),
                'headers' => [],
            ];
        }
    }

    public function delete(string $path, array $query = []): array
    {
        $url = "{$this->baseUrl}/" . ltrim($path, '/');
        try {
            $response = Http::timeout(10)->withHeaders($this->headers())->delete($url, $query);
            return $this->normalize($response);
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'json' => null,
                'text' => $e->getMessage(),
                'headers' => [],
            ];
        }
    }

    /**
     * Fetch profile picture URL for a number/JID.
     * Evolution API: POST /chat/fetchProfilePictureUrl/{instance}
     * Body: { "number": "5511999999999" or "5511999999999@s.whatsapp.net" }
     *
     * @return array{status:int, json:array|null, text:string, headers:array}
     */
    public function fetchProfilePictureUrl(string $instance, string $numberOrJid): array
    {
        $numberOrJid = trim($numberOrJid);
        if ($numberOrJid === '') {
            return ['status' => 0, 'json' => null, 'text' => 'Empty number', 'headers' => []];
        }
        return $this->post("/chat/fetchProfilePictureUrl/{$instance}", [
            'number' => $numberOrJid,
        ]);
    }
}

