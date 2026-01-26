<?php

namespace Tests\Unit\Services\EvolutionApi\Resources;

use App\Services\EvolutionApi\Client;
use App\Services\EvolutionApi\Resources\MessageResource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessageResourceTest extends TestCase
{
    public function test_send_text_sends_payload(): void
    {
        Http::fake([
            'https://evolution.test/message/sendText/*' => Http::response(['ok' => true], 200),
        ]);

        $client = new Client('https://evolution.test', 'test-key');
        $resource = new MessageResource($client);

        $result = $resource->sendText('instance-1', '5511999999999', 'Oi');

        $this->assertIsArray($result);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://evolution.test/message/sendText/instance-1'
                && $request['number'] === '5511999999999'
                && $request['text'] === 'Oi';
        });
    }
}
