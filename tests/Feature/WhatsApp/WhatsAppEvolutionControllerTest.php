<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\WhatsAppInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppEvolutionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.evolution_api.url', 'http://evolution.test');
        config()->set('services.evolution_api.key', 'test-key');
    }

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

    public function test_guest_cannot_access_whatsapp_settings_page(): void
    {
        $response = $this->get('/settings/whatsapp');
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_whatsapp_settings_page(): void
    {
        $user = $this->makeRootUser();

        $response = $this->actingAs($user)->get('/settings/whatsapp');
        $response->assertStatus(200);
    }

    public function test_create_instance_calls_evolution_and_saves_instance_for_account(): void
    {
        Http::fake([
            'http://evolution.test/instance/create' => Http::response([
                'instance' => [
                    'instanceName' => '5511999999999',
                    'status' => 'connecting',
                ],
                'hash' => 'hash-123',
                'qrcode' => [
                    'base64' => 'data:image/png;base64,AAA',
                ],
            ], 200),
        ]);

        $user = $this->makeRootUser();

        $response = $this->actingAs($user)->postJson(route('whatsapp.instance.create'), [
            'whatsapp_number' => '55 (11) 99999-9999',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'instanceName' => '5511999999999',
        ]);

        $this->assertDatabaseHas('whatsapp_instances', [
            'instance_name' => '5511999999999',
            'user_id' => $user->accountId(),
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://evolution.test/instance/create'
                && $request->method() === 'POST'
                && ($request['instanceName'] ?? null) === '5511999999999';
        });
    }

    public function test_connect_generates_qr_and_auto_disconnects_other_connected_instance(): void
    {
        Http::fake([
            'http://evolution.test/instance/logout/*' => Http::response([], 200),
            'http://evolution.test/instance/connect/*' => Http::response([
                'qrcode' => ['base64' => 'data:image/png;base64,BBB'],
            ], 200),
        ]);

        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        // Outra instância já conectada
        $connected = WhatsAppInstance::create([
            'user_id' => $accountId,
            'instance_name' => '5531999999999',
            'whatsapp_number' => '5531999999999',
            'status' => 'open',
            'instance_token' => 'token-a',
            'metadata' => [],
        ]);

        // Instância alvo
        WhatsAppInstance::create([
            'user_id' => $accountId,
            'instance_name' => '5531888888888',
            'whatsapp_number' => '5531888888888',
            'status' => 'connecting',
            'instance_token' => 'token-b',
            'metadata' => [],
        ]);

        $response = $this->actingAs($user)->getJson(route('whatsapp.connect', ['instance' => '5531888888888']));
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'instanceName' => '5531888888888',
        ]);

        $connected->refresh();
        $this->assertSame('disconnected', $connected->status);
        $this->assertNotNull($connected->disconnected_at);

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_starts_with($request->url(), 'http://evolution.test/instance/logout/');
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'http://evolution.test/instance/connect/');
        });
    }

    public function test_state_updates_instance_status_in_database_for_account(): void
    {
        Http::fake([
            'http://evolution.test/instance/connectionState/*' => Http::response([
                'instance' => ['state' => 'open'],
            ], 200),
        ]);

        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        $wa = WhatsAppInstance::create([
            'user_id' => $accountId,
            'instance_name' => '5531777777777',
            'whatsapp_number' => '5531777777777',
            'status' => 'connecting',
            'instance_token' => 'token-c',
            'metadata' => [],
        ]);

        $response = $this->actingAs($user)->getJson(route('whatsapp.state', ['instance' => '5531777777777']));
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'state' => 'open',
        ]);

        $wa->refresh();
        $this->assertSame('open', $wa->status);
        $this->assertNotNull($wa->connected_at);
    }

    public function test_disconnect_only_allows_instance_from_same_account(): void
    {
        Http::fake([
            'http://evolution.test/instance/logout/*' => Http::response([], 200),
        ]);

        $owner = $this->makeRootUser(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $accountId = $owner->accountId();

        $child = $this->makeRootUser(['role' => UserRole::User, 'status' => UserStatus::Active]);
        $child->account_id = $accountId;
        $child->save();

        $otherAccount = $this->makeRootUser(['role' => UserRole::User, 'status' => UserStatus::Active]);

        $waOther = WhatsAppInstance::create([
            'user_id' => $otherAccount->accountId(),
            'instance_name' => '5531666666666',
            'whatsapp_number' => '5531666666666',
            'status' => 'open',
            'instance_token' => 'token-d',
            'metadata' => [],
        ]);

        // filho não pode desconectar instância de outra conta
        $response = $this->actingAs($child)->postJson(route('whatsapp.disconnect', ['instance' => $waOther->instance_name]));
        $response->assertStatus(404);
    }

    public function test_child_user_can_manage_whatsapp_instances_from_account_owner(): void
    {
        Http::fake([
            'http://evolution.test/instance/logout/*' => Http::response([], 200),
        ]);

        $owner = $this->makeRootUser(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $accountId = $owner->accountId();

        $child = $this->makeRootUser(['role' => UserRole::User, 'status' => UserStatus::Active]);
        $child->account_id = $accountId;
        $child->save();

        $wa = WhatsAppInstance::create([
            'user_id' => $accountId,
            'instance_name' => '5531555555555',
            'whatsapp_number' => '5531555555555',
            'status' => 'open',
            'instance_token' => 'token-e',
            'metadata' => [],
        ]);

        // filho enxerga a tela de WhatsApp (mesmos dados)
        $this->actingAs($child)->get('/settings/whatsapp')->assertStatus(200);

        // filho consegue desconectar instância da conta
        $resp = $this->actingAs($child)->postJson(route('whatsapp.disconnect', ['instance' => $wa->instance_name]));
        $resp->assertOk()->assertJson([
            'success' => true,
            'status' => 'disconnected',
        ]);

        $wa->refresh();
        $this->assertSame('disconnected', $wa->status);
    }

    public function test_delete_removes_instance_in_evolution_and_soft_deletes_local_record(): void
    {
        Http::fake([
            'http://evolution.test/instance/delete/*' => Http::response([], 200),
        ]);

        $user = $this->makeRootUser();
        $accountId = $user->accountId();

        $wa = WhatsAppInstance::create([
            'user_id' => $accountId,
            'instance_name' => '5531444444444',
            'whatsapp_number' => '5531444444444',
            'status' => 'open',
            'instance_token' => 'token-f',
            'metadata' => [],
        ]);

        $resp = $this->actingAs($user)->postJson(route('whatsapp.delete', ['instance' => $wa->instance_name]));
        $resp->assertOk()->assertJson([
            'success' => true,
            'deleted' => true,
        ]);

        $this->assertSoftDeleted('whatsapp_instances', [
            'id' => $wa->id,
        ]);
    }
}

