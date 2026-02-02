<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppEvent;
use App\Models\WhatsAppInstance;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppInboxApiTest extends TestCase
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

    public function test_guest_cannot_access_whatsapp_inbox_and_api(): void
    {
        $this->get('/whatsapp')->assertRedirect(route('login'));
        $this->get('/whatsapp/api/conversations')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_list_conversations_json_with_avatar(): void
    {
        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        $conv = WhatsAppConversation::create([
            'user_id' => $accountId,
            'instance_name' => '5511999999999',
            'kind' => 'direct',
            'peer_jid' => '5511999999999@s.whatsapp.net',
            'contact_number' => '5511999999999',
            'contact_name' => null,
            'last_message_at' => now(),
            'last_message_preview' => 'Olá',
            'unread_count' => 3,
        ]);

        WhatsAppContact::create([
            'user_id' => $accountId,
            'instance_name' => '5511999999999',
            'contact_jid' => '5511999999999@s.whatsapp.net',
            'contact_number' => '5511999999999',
            'display_name' => 'Cliente',
            'avatar_url' => 'https://example.test/avatar.png',
        ]);

        $response = $this->actingAs($user)->getJson('/whatsapp/api/conversations');
        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'items']);

        $items = $response->json('items');
        $this->assertNotEmpty($items);
        $this->assertSame($conv->public_id, $items[0]['id']);
        $this->assertSame('Cliente', $items[0]['contact_name']);
        $this->assertSame('https://example.test/avatar.png', $items[0]['avatar_url']);
        $this->assertSame(3, $items[0]['unread_count']);
    }

    public function test_contacts_endpoint_returns_contacts_from_contacts_table(): void
    {
        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        \App\Models\Contact::create([
            'user_id' => $accountId,
            'name' => 'Clarissa Menezes',
            'phone' => '(31)99339-5671',
            'email' => null,
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/whatsapp/api/contacts');
        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'items' => [['id', 'name', 'phone', 'raw_phone']]]);

        $items = $response->json('items');
        $this->assertNotEmpty($items);
        $this->assertSame('Clarissa Menezes', $items[0]['name']);
        $this->assertSame('(31)99339-5671', $items[0]['phone']);
        $this->assertSame('31993395671', $items[0]['raw_phone']);
    }

    public function test_start_conversation_creates_or_reuses_conversation_from_contact(): void
    {
        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        $ct = \App\Models\Contact::create([
            'user_id' => $accountId,
            'name' => 'Contato 1',
            'phone' => '(31)99339-5671',
        ]);

        // Need at least one instance to attach conversation
        WhatsAppInstance::create([
            'user_id' => $accountId,
            'instance_name' => '5511999999999',
            'whatsapp_number' => '5511999999999',
            'status' => 'open',
            'instance_token' => 'token-x',
            'metadata' => [],
        ]);

        $resp = $this->actingAs($user)->postJson('/whatsapp/api/conversations/start', [
            'contact_id' => $ct->id,
        ]);
        $resp->assertOk();
        $resp->assertJson(['success' => true]);
        $resp->assertJsonStructure(['success', 'conversation' => ['id', 'instance_name', 'contact_number', 'contact_name']]);

        $convId = $resp->json('conversation.id');
        $this->assertNotEmpty($convId);

        // Second call should reuse same conversation (unique by user+instance+peer_jid)
        $resp2 = $this->actingAs($user)->postJson('/whatsapp/api/conversations/start', [
            'contact_id' => $ct->id,
        ]);
        $resp2->assertOk();
        $this->assertSame($convId, $resp2->json('conversation.id'));
    }

    public function test_messages_endpoint_returns_messages_and_marks_conversation_as_read_and_emits_event(): void
    {
        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        $conv = WhatsAppConversation::create([
            'user_id' => $accountId,
            'instance_name' => '5511999999999',
            'kind' => 'direct',
            'peer_jid' => '5511999999999@s.whatsapp.net',
            'contact_number' => '5511999999999',
            'contact_name' => 'Cliente',
            'last_message_at' => now(),
            'last_message_preview' => 'x',
            'unread_count' => 2,
        ]);

        $m1 = WhatsAppMessage::create([
            'public_id' => (string) Str::ulid(),
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'message_type' => 'text',
            'body' => 'a',
            'status' => null,
            'sent_at' => now(),
        ]);
        $m2 = WhatsAppMessage::create([
            'public_id' => (string) Str::ulid(),
            'conversation_id' => $conv->id,
            'direction' => 'out',
            'message_type' => 'text',
            'body' => 'b',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/whatsapp/api/conversations/{$conv->public_id}/messages");
        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'items', 'meta' => ['limit']]);

        $conv->refresh();
        $this->assertSame(0, (int) $conv->unread_count);

        $items = $response->json('items');
        $this->assertCount(2, $items);
        $this->assertSame($m1->public_id, $items[0]['id']);
        $this->assertSame($m2->public_id, $items[1]['id']);

        $this->assertTrue(
            WhatsAppEvent::query()
                ->where('user_id', $accountId)
                ->where('type', 'wa.conversation.read')
                ->exists()
        );
    }

    public function test_messages_endpoint_supports_before_cursor_for_older_messages(): void
    {
        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        $conv = WhatsAppConversation::create([
            'user_id' => $accountId,
            'instance_name' => '5511999999999',
            'kind' => 'direct',
            'peer_jid' => '5511999999999@s.whatsapp.net',
            'contact_number' => '5511999999999',
            'contact_name' => 'Cliente',
            'unread_count' => 0,
        ]);

        $id1 = (string) Str::ulid();
        $id2 = (string) Str::ulid();
        $id3 = (string) Str::ulid();

        WhatsAppMessage::create([
            'public_id' => $id1,
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'message_type' => 'text',
            'body' => '1',
        ]);
        WhatsAppMessage::create([
            'public_id' => $id2,
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'message_type' => 'text',
            'body' => '2',
        ]);
        WhatsAppMessage::create([
            'public_id' => $id3,
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'message_type' => 'text',
            'body' => '3',
        ]);

        $response = $this->actingAs($user)->getJson("/whatsapp/api/conversations/{$conv->public_id}/messages?before={$id3}&limit=2");
        $response->assertOk();
        $items = $response->json('items');
        $this->assertCount(2, $items);
        $this->assertSame($id1, $items[0]['id']);
        $this->assertSame($id2, $items[1]['id']);
    }

    public function test_send_message_calls_evolution_and_persists_message_and_emits_event(): void
    {
        config()->set('services.evolution_api.url', 'http://evolution.test');
        config()->set('services.evolution_api.key', 'test-key');

        Http::fake([
            'http://evolution.test/message/sendText/*' => Http::response(['ok' => true], 200),
        ]);

        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        WhatsAppInstance::create([
            'user_id' => $accountId,
            'instance_name' => '5511999999999',
            'whatsapp_number' => '5511999999999',
            'status' => 'open',
            'instance_token' => 'token-x',
            'metadata' => [],
        ]);

        $conv = WhatsAppConversation::create([
            'user_id' => $accountId,
            'instance_name' => '5511999999999',
            'kind' => 'direct',
            'peer_jid' => '5511888888888@s.whatsapp.net',
            'contact_number' => '5511888888888',
            'contact_name' => 'Cliente',
            'unread_count' => 0,
        ]);

        $response = $this->actingAs($user)->postJson("/whatsapp/api/conversations/{$conv->public_id}/send", [
            'text' => 'Teste',
        ]);
        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'message' => ['id', 'direction', 'message_type', 'body']]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conv->id,
            'direction' => 'out',
            'message_type' => 'text',
        ]);

        $conv->refresh();
        $this->assertSame('Teste', $conv->last_message_preview);

        $this->assertTrue(
            WhatsAppEvent::query()
                ->where('user_id', $accountId)
                ->where('type', 'wa.message.created')
                ->exists()
        );

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'http://evolution.test/message/sendText/')
                && ($request['text'] ?? null) === 'Teste';
        });
    }

    public function test_send_message_returns_error_if_evolution_is_not_configured(): void
    {
        config()->set('services.evolution_api.url', '');
        config()->set('services.evolution_api.key', '');

        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        $conv = WhatsAppConversation::create([
            'user_id' => $accountId,
            'instance_name' => '5511999999999',
            'kind' => 'direct',
            'peer_jid' => '5511888888888@s.whatsapp.net',
            'contact_number' => '5511888888888',
            'contact_name' => 'Cliente',
        ]);

        $response = $this->actingAs($user)->postJson("/whatsapp/api/conversations/{$conv->public_id}/send", [
            'text' => 'Teste',
        ]);
        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }
}

