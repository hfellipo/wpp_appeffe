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

    /**
     * Send media (image, video or document) via Evolution API.
     * POST /message/sendMedia/{instance}
     * Body: number, mediatype, mimetype, media (base64 data URL or URL), caption?, fileName?
     *
     * @param  array{number: string, mediatype: string, mimetype: string, media: string, caption?: string, fileName?: string}  $payload
     * @return array{status:int, json:array|null, text:string, headers:array}
     */
    public function sendMedia(string $instance, array $payload): array
    {
        $instance = trim($instance);
        if ($instance === '') {
            return ['status' => 0, 'json' => null, 'text' => 'Empty instance', 'headers' => []];
        }
        return $this->post("/message/sendMedia/{$instance}", $payload);
    }

    /**
     * Fetch all groups from the WhatsApp instance (Evolution API).
     * GET /group/fetchAllGroups/{instance}?getParticipants=true
     * Use getParticipants=true to get owner info for "groups I created".
     *
     * @return array{status:int, json:array|null, text:string, headers:array}
     */
    public function fetchAllGroups(string $instance, bool $getParticipants = true): array
    {
        $instance = trim($instance);
        if ($instance === '') {
            return ['status' => 0, 'json' => null, 'text' => 'Empty instance', 'headers' => []];
        }
        return $this->get("/group/fetchAllGroups/{$instance}", [
            'getParticipants' => $getParticipants ? 'true' : 'false',
        ]);
    }

    /**
     * Get media (image/video/document) as base64 from a received message.
     * POST /chat/getBase64FromMediaMessage/{instance}
     * Body: { "message": { "key": { "id": "MESSAGE_ID" } }, "convertToMp4": false }
     * Message ID = remote_id of the WhatsApp message.
     *
     * @return array{status:int, json:array|null, text:string, headers:array}
     */
    public function getBase64FromMediaMessage(string $instance, string $messageKeyId): array
    {
        $instance = trim($instance);
        if ($instance === '' || trim($messageKeyId) === '') {
            return ['status' => 0, 'json' => null, 'text' => 'Missing instance or message id', 'headers' => []];
        }
        return $this->post("/chat/getBase64FromMediaMessage/{$instance}", [
            'message' => [
                'key' => [
                    'id' => $messageKeyId,
                ],
            ],
            'convertToMp4' => false,
        ]);
    }
}

