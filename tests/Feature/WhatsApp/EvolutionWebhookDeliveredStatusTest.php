<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppEvent;
use App\Models\WhatsAppInstance;
use App\Models\WhatsAppMessage;
use App\Services\EvolutionWebhookProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvolutionWebhookDeliveredStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeRootUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        if ($user->account_id === null) {
            $user->account_id = $user->id;
            $user->save();
        }
        return $user;
    }

    public function test_messages_update_sets_delivered_at_and_emits_event(): void
    {
        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        $wa = WhatsAppInstance::create([
            'user_id' => $accountId,
            'instance_name' => '5511999999999',
            'whatsapp_number' => '5511999999999',
            'status' => 'open',
            'instance_token' => 'token-x',
            'metadata' => [],
        ]);

        $conv = WhatsAppConversation::create([
            'user_id' => $accountId,
            'instance_name' => $wa->instance_name,
            'kind' => 'direct',
            'peer_jid' => '5531999990000@s.whatsapp.net',
            'contact_number' => '5531999990000',
            'contact_name' => 'Teste',
            'unread_count' => 0,
        ]);

        $remoteId = 'remote-abc-1';
        $msg = WhatsAppMessage::create([
            'public_id' => (string) Str::ulid(),
            'conversation_id' => $conv->id,
            'direction' => 'out',
            'message_type' => 'text',
            'body' => 'oi',
            'remote_id' => $remoteId,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        app(EvolutionWebhookProcessor::class)->handle('MESSAGES_UPDATE', [
            'instanceName' => $wa->instance_name,
            'key' => [
                'remoteJid' => $conv->peer_jid,
                'id' => $remoteId,
                'fromMe' => true,
            ],
            'status' => 'delivered',
        ]);

        $msg->refresh();
        $this->assertSame('delivered', $msg->status);
        $this->assertNotNull($msg->delivered_at);

        $this->assertTrue(
            WhatsAppEvent::query()
                ->where('user_id', $accountId)
                ->where('type', 'wa.message.updated')
                ->exists()
        );
    }
}

