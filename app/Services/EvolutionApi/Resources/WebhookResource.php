<?php

namespace App\Services\EvolutionApi\Resources;

use App\Services\EvolutionApi\Client;

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

        $data = $response->json();
        return is_array($data) ? $data : [];
    }

    public function find(string $instanceName): array
    {
        $response = $this->client->get("/webhook/find/{$instanceName}");

        $data = $response->json();
        return is_array($data) ? $data : [];
    }
}
