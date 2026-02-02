<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppInboxRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function makeRootUser(array $overrides = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($overrides);

        // Conta raiz: account_id = id (migração não seta para novos registros)
        if ($user->account_id === null) {
            $user->account_id = $user->id;
            $user->save();
        }

        return $user;
    }

    public function test_guest_cannot_access_whatsapp_inbox(): void
    {
        $response = $this->get('/whatsapp');
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_whatsapp_inbox(): void
    {
        $user = $this->makeRootUser();

        $response = $this->actingAs($user)->get('/whatsapp');
        $response->assertStatus(200);
    }

    public function test_stream_endpoint_returns_sse_and_emits_events_once_mode(): void
    {
        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        WhatsAppEvent::create([
            'user_id' => $accountId,
            'type' => 'wa.message.created',
            'payload' => [
                'conversation_id' => 'conv-test',
                'message' => [
                    'id' => 'msg-test',
                    'direction' => 'in',
                    'message_type' => 'text',
                    'body' => 'hello',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get('/whatsapp/stream?once=1');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();
        $this->assertStringContainsString("event: wa.ready\n", $content);
        $this->assertStringContainsString("event: wa.message.created\n", $content);
        $this->assertStringContainsString('data: ', $content);
    }
}

