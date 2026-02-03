<?php

namespace Tests\Feature;

use App\Jobs\ProcessEvolutionWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WebhookEvolutionEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_evolution_returns_ok_and_dispatches_job_for_messages_payload(): void
    {
        Bus::fake();

        $payload = [
            // legacy-ish shape that should infer MESSAGES_UPSERT
            'key' => [
                'remoteJid' => '5511999999999@s.whatsapp.net',
                'id' => 'remote-id-1',
                'fromMe' => false,
            ],
            'message' => [
                'conversation' => 'hello',
            ],
            'instanceName' => '5511999999999',
        ];

        $response = $this->postJson('/webhook/evolution', $payload);
        $response->assertOk();
        $response->assertJson(['ok' => true]);

        Bus::assertDispatched(ProcessEvolutionWebhookEvent::class, function (ProcessEvolutionWebhookEvent $job) {
            return strtoupper($job->event) === 'MESSAGES_UPSERT' && is_array($job->data);
        });
    }

    public function test_webhook_evolution_supports_wrapped_event_shape(): void
    {
        Bus::fake();

        $response = $this->postJson('/webhook/evolution', [
            'event' => 'CONNECTION_UPDATE',
            'data' => [
                'instanceName' => '5511999999999',
                'state' => 'open',
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        Bus::assertDispatched(ProcessEvolutionWebhookEvent::class, function (ProcessEvolutionWebhookEvent $job) {
            return strtoupper($job->event) === 'CONNECTION_UPDATE'
                && ($job->data['state'] ?? null) === 'open';
        });
    }

    public function test_webhook_evolution_merges_instance_name_from_root_into_data(): void
    {
        Bus::fake();

        // Evolution API sends instanceName at ROOT; data may not contain it
        $response = $this->postJson('/webhook/evolution', [
            'event' => 'MESSAGES_UPSERT',
            'instanceName' => '5511888777666',
            'data' => [
                'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'id' => 'msg-1', 'fromMe' => false],
                'message' => ['conversation' => 'hello'],
            ],
        ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessEvolutionWebhookEvent::class, function (ProcessEvolutionWebhookEvent $job) {
            return ($job->data['instanceName'] ?? '') === '5511888777666'
                && strtoupper($job->event) === 'MESSAGES_UPSERT';
        });
    }

    public function test_webhook_evolution_test_endpoint_returns_ok(): void
    {
        $get = $this->getJson('/webhook/evolution/test');
        $get->assertOk();
        $get->assertJson([
            'ok' => true,
        ]);

        $post = $this->postJson('/webhook/evolution/test', ['x' => 1]);
        $post->assertOk();
        $post->assertJson([
            'ok' => true,
        ]);
        $post->assertJsonStructure(['received', 'headers']);
    }
}

