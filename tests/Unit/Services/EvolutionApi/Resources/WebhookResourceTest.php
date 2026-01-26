<?php

namespace Tests\Unit\Services\EvolutionApi\Resources;

use App\Services\EvolutionApi\Client;
use App\Services\EvolutionApi\Resources\WebhookResource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookResourceTest extends TestCase
{
    public function test_set_webhook_sends_payload(): void
    {
        Http::fake([
            'https://evolution.test/webhook/set/*' => Http::response(['ok' => true], 200),
        ]);

        $client = new Client('https://evolution.test', 'test-key');
        $resource = new WebhookResource($client);

        $result = $resource->set('instance-1', 'https://app.test/webhook', ['EVENT_A'], true);

        $this->assertIsArray($result);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://evolution.test/webhook/set/instance-1'
                && $request['url'] === 'https://app.test/webhook'
                && $request['webhook_by_events'] === true
                && $request['webhook_base64'] === true
                && $request['events'] === ['EVENT_A'];
        });
    }

    public function test_find_webhook_returns_array(): void
    {
        Http::fake([
            'https://evolution.test/webhook/find/*' => Http::response(['url' => 'https://app.test/webhook'], 200),
        ]);

        $client = new Client('https://evolution.test', 'test-key');
        $resource = new WebhookResource($client);

        $result = $resource->find('instance-1');

        $this->assertSame('https://app.test/webhook', $result['url']);
    }
}
