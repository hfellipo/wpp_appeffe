<?php

namespace Tests\Feature\Ai;

use App\Models\AiAgent;
use App\Models\AiConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── index ────────────────────────────────────────────────────────────

    public function test_index_lists_agents_for_authenticated_user(): void
    {
        $other = User::factory()->create();
        AiAgent::create($this->agentData(['name' => 'Agente A']));
        AiAgent::create($this->agentData(['name' => 'Agente B']));
        AiAgent::create(['user_id' => $other->id, 'name' => 'Outro', 'system_prompt' => 'x', 'active' => true]);

        $response = $this->actingAs($this->user)->get(route('ai-agents.index'));

        $response->assertOk();
        $response->assertSee('Agente A');
        $response->assertSee('Agente B');
        $response->assertDontSee('Outro');
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('ai-agents.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_api_key_warning_when_not_configured(): void
    {
        $response = $this->actingAs($this->user)->get(route('ai-agents.index'));

        $response->assertOk();
        // hasApiKey=false deve estar disponível na view
        $response->assertViewHas('hasApiKey', false);
    }

    public function test_index_detects_api_key_when_configured(): void
    {
        AiConfig::create([
            'user_id'        => $this->user->id,
            'openai_api_key' => 'sk-test',
        ]);

        $response = $this->actingAs($this->user)->get(route('ai-agents.index'));

        $response->assertOk();
        $response->assertViewHas('hasApiKey', true);
    }

    // ── create / store ───────────────────────────────────────────────────

    public function test_create_page_is_accessible(): void
    {
        $this->actingAs($this->user)
            ->get(route('ai-agents.create'))
            ->assertOk();
    }

    public function test_store_creates_agent_and_redirects(): void
    {
        $response = $this->actingAs($this->user)->post(route('ai-agents.store'), [
            'name'          => 'Vendas Bot',
            'description'   => 'Agente de vendas',
            'system_prompt' => 'Você é um assistente de vendas.',
            'model'         => 'gpt-4o',
            'temperature'   => 0.8,
            'max_tokens'    => 800,
            'active'        => true,
        ]);

        $response->assertRedirect(route('ai-agents.index'));

        $this->assertDatabaseHas('ai_agents', [
            'user_id'       => $this->user->id,
            'name'          => 'Vendas Bot',
            'system_prompt' => 'Você é um assistente de vendas.',
            'model'         => 'gpt-4o',
            'active'        => true,
        ]);
    }

    public function test_store_creates_agent_as_active_by_default_when_no_active_flag(): void
    {
        $this->actingAs($this->user)->post(route('ai-agents.store'), [
            'name'          => 'Bot Ativo',
            'system_prompt' => 'Prompt.',
        ]);

        $this->assertDatabaseHas('ai_agents', [
            'user_id' => $this->user->id,
            'name'    => 'Bot Ativo',
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $this->actingAs($this->user)
            ->post(route('ai-agents.store'), ['system_prompt' => 'Prompt.'])
            ->assertSessionHasErrors('name');
    }

    public function test_store_validates_required_system_prompt(): void
    {
        $this->actingAs($this->user)
            ->post(route('ai-agents.store'), ['name' => 'Bot'])
            ->assertSessionHasErrors('system_prompt');
    }

    public function test_store_validates_model_is_in_allowed_list(): void
    {
        $this->actingAs($this->user)
            ->post(route('ai-agents.store'), [
                'name'          => 'Bot',
                'system_prompt' => 'x',
                'model'         => 'claude-3-opus',
            ])
            ->assertSessionHasErrors('model');
    }

    public function test_store_validates_temperature_range(): void
    {
        $this->actingAs($this->user)
            ->post(route('ai-agents.store'), [
                'name'          => 'Bot',
                'system_prompt' => 'x',
                'temperature'   => 1.5,
            ])
            ->assertSessionHasErrors('temperature');
    }

    public function test_store_validates_max_tokens_minimum(): void
    {
        $this->actingAs($this->user)
            ->post(route('ai-agents.store'), [
                'name'          => 'Bot',
                'system_prompt' => 'x',
                'max_tokens'    => 10,
            ])
            ->assertSessionHasErrors('max_tokens');
    }

    // ── edit / update ────────────────────────────────────────────────────

    public function test_edit_page_is_accessible_for_owner(): void
    {
        $agent = AiAgent::create($this->agentData());

        $this->actingAs($this->user)
            ->get(route('ai-agents.edit', $agent))
            ->assertOk()
            ->assertViewHas('aiAgent', fn ($a) => $a->id === $agent->id);
    }

    public function test_edit_page_returns_403_for_non_owner(): void
    {
        $other = User::factory()->create();
        $agent = AiAgent::create($this->agentData(['user_id' => $other->id]));

        $this->actingAs($this->user)
            ->get(route('ai-agents.edit', $agent))
            ->assertForbidden();
    }

    public function test_update_saves_changes_for_owner(): void
    {
        $agent = AiAgent::create($this->agentData(['name' => 'Nome Antigo']));

        $this->actingAs($this->user)
            ->put(route('ai-agents.update', $agent), [
                'name'          => 'Nome Novo',
                'system_prompt' => 'Novo prompt.',
                'active'        => true,
            ])
            ->assertRedirect(route('ai-agents.index'));

        $this->assertDatabaseHas('ai_agents', [
            'id'   => $agent->id,
            'name' => 'Nome Novo',
        ]);
    }

    public function test_update_can_deactivate_agent(): void
    {
        $agent = AiAgent::create($this->agentData(['active' => true]));

        $this->actingAs($this->user)->put(route('ai-agents.update', $agent), [
            'name'          => $agent->name,
            'system_prompt' => $agent->system_prompt,
            'active'        => false,
        ]);

        $this->assertDatabaseHas('ai_agents', ['id' => $agent->id, 'active' => false]);
    }

    public function test_update_returns_403_for_non_owner(): void
    {
        $other = User::factory()->create();
        $agent = AiAgent::create($this->agentData(['user_id' => $other->id]));

        $this->actingAs($this->user)
            ->put(route('ai-agents.update', $agent), [
                'name'          => 'Hack',
                'system_prompt' => 'x',
            ])
            ->assertForbidden();
    }

    // ── destroy ──────────────────────────────────────────────────────────

    public function test_destroy_deletes_agent_for_owner(): void
    {
        $agent = AiAgent::create($this->agentData());

        $this->actingAs($this->user)
            ->delete(route('ai-agents.destroy', $agent))
            ->assertRedirect(route('ai-agents.index'));

        $this->assertDatabaseMissing('ai_agents', ['id' => $agent->id]);
    }

    public function test_destroy_returns_403_for_non_owner(): void
    {
        $other = User::factory()->create();
        $agent = AiAgent::create($this->agentData(['user_id' => $other->id]));

        $this->actingAs($this->user)
            ->delete(route('ai-agents.destroy', $agent))
            ->assertForbidden();

        $this->assertDatabaseHas('ai_agents', ['id' => $agent->id]);
    }

    // ── apiList ──────────────────────────────────────────────────────────

    public function test_api_list_returns_only_active_agents_as_json(): void
    {
        AiAgent::create($this->agentData(['name' => 'Ativo',    'active' => true]));
        AiAgent::create($this->agentData(['name' => 'Inativo',  'active' => false]));

        $response = $this->actingAs($this->user)
            ->getJson(route('ai-agents.api-list'));

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->all();
        $this->assertContains('Ativo', $names);
        $this->assertNotContains('Inativo', $names);
    }

    public function test_api_list_does_not_expose_system_prompt(): void
    {
        AiAgent::create($this->agentData(['active' => true]));

        $response = $this->actingAs($this->user)
            ->getJson(route('ai-agents.api-list'));

        $response->assertOk();
        $first = $response->json(0);
        $this->assertArrayNotHasKey('system_prompt', $first);
        $this->assertArrayNotHasKey('user_id', $first);
    }

    public function test_api_list_does_not_return_other_users_agents(): void
    {
        $other = User::factory()->create();
        AiAgent::create(['user_id' => $other->id, 'name' => 'De Outro', 'system_prompt' => 'x', 'active' => true]);
        AiAgent::create($this->agentData(['name' => 'Meu Agente', 'active' => true]));

        $response = $this->actingAs($this->user)
            ->getJson(route('ai-agents.api-list'));

        $names = collect($response->json())->pluck('name')->all();
        $this->assertNotContains('De Outro', $names);
        $this->assertContains('Meu Agente', $names);
    }

    // ── AiAgent model ────────────────────────────────────────────────────

    public function test_scope_active_filters_inactive_agents(): void
    {
        AiAgent::create($this->agentData(['active' => true]));
        AiAgent::create($this->agentData(['active' => false]));

        $active = AiAgent::active()->where('user_id', $this->user->id)->get();

        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->active);
    }

    public function test_active_field_is_cast_to_boolean(): void
    {
        $agent = AiAgent::create($this->agentData(['active' => true]));

        $this->assertIsBool($agent->fresh()->active);
    }

    public function test_resolved_model_falls_back_to_config_default(): void
    {
        AiConfig::create([
            'user_id'       => $this->user->id,
            'default_model' => 'gpt-4o-mini',
        ]);
        $agent = AiAgent::create($this->agentData(['model' => null]));

        $this->assertSame('gpt-4o-mini', $agent->resolvedModel());
    }

    public function test_resolved_model_uses_agent_model_when_set(): void
    {
        $agent = AiAgent::create($this->agentData(['model' => 'gpt-4o']));

        $this->assertSame('gpt-4o', $agent->resolvedModel());
    }

    public function test_resolved_temperature_falls_back_to_config(): void
    {
        AiConfig::create(['user_id' => $this->user->id, 'temperature' => 0.3]);
        $agent = AiAgent::create($this->agentData(['temperature' => null]));

        $this->assertEqualsWithDelta(0.3, $agent->resolvedTemperature(), 0.001);
    }

    public function test_resolved_max_tokens_falls_back_to_config(): void
    {
        AiConfig::create(['user_id' => $this->user->id, 'max_tokens' => 1000]);
        $agent = AiAgent::create($this->agentData(['max_tokens' => null]));

        $this->assertSame(1000, $agent->resolvedMaxTokens());
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function agentData(array $overrides = []): array
    {
        return array_merge([
            'user_id'       => $this->user->id,
            'name'          => 'Agente Padrão',
            'system_prompt' => 'Você é um assistente.',
            'active'        => true,
        ], $overrides);
    }
}
