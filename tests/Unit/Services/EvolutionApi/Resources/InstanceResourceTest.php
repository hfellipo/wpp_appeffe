<?php

namespace Tests\Unit\Services\EvolutionApi\Resources;

use App\Services\EvolutionApi\Client;
use App\Services\EvolutionApi\Resources\InstanceResource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstanceResourceTest extends TestCase
{
    public function test_create_instance_success(): void
    {
        Http::fake([
            'https://evolution.test/instance/create' => Http::response([
                'instance' => ['instanceName' => '5511999999999'],
                'status' => 'created',
            ], 201),
        ]);

        $client = new Client('https://evolution.test', 'test-key');
        $resource = new InstanceResource($client);

        $result = $resource->create('5511999999999');

        $this->assertIsArray($result);
        $this->assertSame('5511999999999', $result['instance']['instanceName']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://evolution.test/instance/create'
                && $request['instanceName'] === '5511999999999'
                && $request['qrcode'] === true
                && $request['integration'] === 'WHATSAPP-BAILEYS';
        });
    }

    public function test_create_instance_error_returns_message(): void
    {
        Http::fake([
            'https://evolution.test/instance/create' => Http::response([
                'message' => 'Bad Request',
            ], 400),
        ]);

        $client = new Client('https://evolution.test', 'test-key');
        $resource = new InstanceResource($client);

        $result = $resource->create('5511999999999');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Bad Request', $result['error']);
    }
}
