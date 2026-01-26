<?php

namespace App\Services\EvolutionApi\Resources;

use App\Services\EvolutionApi\Client;

class MessageResource
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function sendText(string $instanceName, string $number, string $message): array
    {
        $response = $this->client->post("/message/sendText/{$instanceName}", [
            'number' => $number,
            'text' => $message,
        ]);

        $data = $response->json();
        return is_array($data) ? $data : [];
    }
}
