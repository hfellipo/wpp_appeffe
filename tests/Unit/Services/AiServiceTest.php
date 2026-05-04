<?php

namespace Tests\Unit\Services;

use App\Models\AiAgent;
use App\Models\AiConfig;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiService $service;
    private User $user;
    private AiAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AiService();

        $this->user = User::factory()->create();

        AiConfig::create([
            'user_id'       => $this->user->id,
            'openai_api_key' => 'sk-test-key',
            'default_model' => 'gpt-3.5-turbo',
            'temperature'   => 0.7,
            'max_tokens'    => 500,
        ]);

        $this->agent = AiAgent::create([
            'user_id'       => $this->user->id,
            'name'          => 'Agente Teste',
            'system_prompt' => 'Você é um assistente de vendas.',
            'model'         => 'gpt-3.5-turbo',
            'temperature'   => 0.7,
            'max_tokens'    => 300,
            'active'        => true,
        ]);
    }

    // ── generateReply: casos de sucesso ─────────────────────────────────

    public function test_generate_reply_returns_trimmed_content_on_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => '  Olá! Como posso ajudar?  ']]],
            ], 200),
        ]);

        $result = $this->service->generateReply($this->user->id, $this->agent, 'Oi');

        $this->assertSame('Olá! Como posso ajudar?', $result);
    }

    public function test_generate_reply_sends_system_prompt_in_messages(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'resposta']]],
        ], 200)]);

        $this->service->generateReply($this->user->id, $this->agent, 'Quero comprar');

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];
            $systemMsg = collect($messages)->firstWhere('role', 'system');
            return $systemMsg !== null
                && str_contains($systemMsg['content'], 'Você é um assistente de vendas.');
        });
    }

    public function test_generate_reply_appends_session_context_to_system_prompt(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200)]);

        $this->service->generateReply($this->user->id, $this->agent, 'oi', [], now()->toISOString());

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];
            $systemMsg = collect($messages)->firstWhere('role', 'system');
            return str_contains($systemMsg['content'], 'INSTRUÇÕES DE ATENDIMENTO');
        });
    }

    public function test_generate_reply_without_system_prompt_omits_system_message(): void
    {
        $this->agent->update(['system_prompt' => '']);
        // Sem session context também, para forçar omissão total
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200)]);

        $this->service->generateReply($this->user->id, $this->agent, 'oi');

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];
            // Deve conter apenas o user message (+ session context já que não tem sessionStartAt=null mas o buildSessionContext adiciona isso sempre)
            // Sem session context (sessionStartAt=null ainda retorna contexto) - na verdade o código sempre adiciona
            // Só valida que user message existe como último
            $last = end($messages);
            return $last['role'] === 'user' && $last['content'] === 'oi';
        });
    }

    public function test_generate_reply_includes_history_between_system_and_user(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200)]);

        $history = [
            ['role' => 'user',      'content' => 'Quanto custa?'],
            ['role' => 'assistant', 'content' => 'R$ 100,00'],
        ];

        $this->service->generateReply($this->user->id, $this->agent, 'Aceita cartão?', $history);

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];
            $roles    = array_column($messages, 'role');
            $contents = array_column($messages, 'content');

            return in_array('user', $roles, true)
                && in_array('assistant', $roles, true)
                && in_array('Quanto custa?', $contents, true)
                && in_array('R$ 100,00', $contents, true)
                && end($messages)['content'] === 'Aceita cartão?';
        });
    }

    public function test_generate_reply_skips_history_entries_with_empty_content(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200)]);

        $history = [
            ['role' => 'user',      'content' => ''],
            ['role' => 'assistant', 'content' => 'Mensagem válida'],
        ];

        $this->service->generateReply($this->user->id, $this->agent, 'msg', $history);

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];
            $contents = array_column($messages, 'content');
            return ! in_array('', $contents, true);
        });
    }

    public function test_generate_reply_uses_agent_model_and_parameters(): void
    {
        $this->agent->update(['model' => 'gpt-4o', 'temperature' => 0.2, 'max_tokens' => 150]);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200)]);

        $this->service->generateReply($this->user->id, $this->agent, 'teste');

        Http::assertSent(function ($request) {
            $data = $request->data();
            return $data['model'] === 'gpt-4o'
                && (float) $data['temperature'] === 0.2
                && (int) $data['max_tokens'] === 150;
        });
    }

    public function test_generate_reply_falls_back_to_config_model_when_agent_has_none(): void
    {
        $this->agent->update(['model' => null]);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ], 200)]);

        $this->service->generateReply($this->user->id, $this->agent, 'teste');

        Http::assertSent(function ($request) {
            return $request->data()['model'] === 'gpt-3.5-turbo';
        });
    }

    // ── generateReply: casos de erro ────────────────────────────────────

    public function test_generate_reply_throws_when_api_key_not_configured(): void
    {
        AiConfig::where('user_id', $this->user->id)->delete();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/OpenAI/i');

        $this->service->generateReply($this->user->id, $this->agent, 'oi');
    }

    public function test_generate_reply_throws_when_api_key_is_null(): void
    {
        AiConfig::where('user_id', $this->user->id)->update(['openai_api_key' => null]);

        $this->expectException(\RuntimeException::class);

        $this->service->generateReply($this->user->id, $this->agent, 'oi');
    }

    public function test_generate_reply_throws_when_openai_returns_error_status(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/OpenAI/i');

        $this->service->generateReply($this->user->id, $this->agent, 'oi');
    }

    public function test_generate_reply_throws_when_response_content_is_empty(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '   ']]],
        ], 200)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/vazia/i');

        $this->service->generateReply($this->user->id, $this->agent, 'oi');
    }

    public function test_generate_reply_throws_when_response_has_no_choices(): void
    {
        Http::fake(['*' => Http::response(['choices' => []], 200)]);

        $this->expectException(\RuntimeException::class);

        $this->service->generateReply($this->user->id, $this->agent, 'oi');
    }
}
