<?php

namespace Tests\Feature\Ai;

use App\Models\AiAgent;
use App\Models\AiConfig;
use App\Models\Automation;
use App\Models\AutomationEdge;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\User;
use App\Services\AiService;
use App\Services\AutomationRunnerService;
use App\Services\WhatsAppSendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class AutomationRunnerAiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Contact $contact;
    private Automation $automation;
    private AutomationNode $startNode;
    private MockInterface $waSend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->contact = Contact::factory()->create([
            'user_id' => $this->user->id,
            'phone'   => '(11)99999-1234',
        ]);

        $this->automation = Automation::create([
            'user_id'               => $this->user->id,
            'name'                  => 'Automação IA',
            'is_active'             => true,
            'run_once_per_contact'  => false,
        ]);

        $this->startNode = AutomationNode::create([
            'automation_id' => $this->automation->id,
            'type'          => 'start',
            'config'        => [],
        ]);

        AiConfig::create([
            'user_id'        => $this->user->id,
            'openai_api_key' => 'sk-test',
        ]);

        $this->waSend = $this->mock(WhatsAppSendService::class);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function runner(): AutomationRunnerService
    {
        return app(AutomationRunnerService::class);
    }

    private function createAiNode(array $config = [], string $type = 'ai_reply'): AutomationNode
    {
        return AutomationNode::create([
            'automation_id' => $this->automation->id,
            'type'          => $type,
            'config'        => $config,
        ]);
    }

    private function createEdge(AutomationNode $from, AutomationNode $to, string $handle = 'default'): AutomationEdge
    {
        return AutomationEdge::create([
            'automation_id'  => $this->automation->id,
            'source_node_id' => $from->id,
            'target_node_id' => $to->id,
            'source_handle'  => $handle,
            'target_handle'  => 'input',
        ]);
    }

    private function createRun(array $meta = []): AutomationRun
    {
        return AutomationRun::create([
            'contact_id'    => $this->contact->id,
            'automation_id' => $this->automation->id,
            'ran_at'        => now(),
            'status'        => 'success',
            'metadata'      => $meta,
        ]);
    }

    private function createActiveAgent(array $overrides = []): AiAgent
    {
        return AiAgent::create(array_merge([
            'user_id'       => $this->user->id,
            'name'          => 'Bot Teste',
            'system_prompt' => 'Você é um assistente.',
            'model'         => 'gpt-3.5-turbo',
            'active'        => true,
        ], $overrides));
    }

    private function reloadAutomation(): Automation
    {
        return $this->automation->fresh()->load(['flowNodes', 'flowEdges', 'actions']);
    }

    // ── ai_reply: sem agente selecionado ─────────────────────────────────

    public function test_ai_reply_node_without_agent_id_records_error_and_follows_error_branch(): void
    {
        $aiNode    = $this->createAiNode(['agent_id' => 0, 'mode' => 'immediate']);
        $errorNode = $this->createAiNode([], 'send_message');

        $this->createEdge($this->startNode, $aiNode);
        $this->createEdge($aiNode, $errorNode, 'error');

        $this->waSend->shouldNotReceive('sendTextToContact');

        $result = $this->runner()->runForContact($this->reloadAutomation(), $this->contact);

        // status fica 'partial' porque o nó ai_reply falhou (agent_id=0)
        $this->assertFalse($result['success']);
        $details = collect($result['details']);
        $aiDetail = $details->firstWhere('action', 'ai_reply');
        $this->assertFalse($aiDetail['success']);
        $this->assertStringContainsString('Nenhum agente', $aiDetail['reason']);
    }

    // ── ai_reply: agente não encontrado ──────────────────────────────────

    public function test_ai_reply_with_nonexistent_agent_records_error(): void
    {
        $aiNode = $this->createAiNode(['agent_id' => 99999, 'mode' => 'immediate']);
        $this->createEdge($this->startNode, $aiNode);

        $this->waSend->shouldNotReceive('sendTextToContact');

        $result = $this->runner()->runForContact(
            $this->reloadAutomation(),
            $this->contact,
            false,
            false,
            'Olá'
        );

        $detail = collect($result['details'])->firstWhere('action', 'ai_reply');
        $this->assertFalse($detail['success']);
        $this->assertStringContainsString('desativado', $detail['reason']);
    }

    // ── ai_reply: agente desativado (comportamento novo) ─────────────────

    public function test_ai_reply_with_inactive_agent_does_not_run_and_records_error(): void
    {
        $inactiveAgent = $this->createActiveAgent(['active' => false]);
        $aiNode        = $this->createAiNode(['agent_id' => $inactiveAgent->id, 'mode' => 'immediate']);
        // Usa add_tag para o branch de erro — não chama sendTextToContact
        $errorNode     = AutomationNode::create([
            'automation_id' => $this->automation->id,
            'type'          => 'add_tag',
            'config'        => ['tag_id' => 0],
        ]);

        $this->createEdge($this->startNode, $aiNode);
        $this->createEdge($aiNode, $errorNode, 'error');

        $this->waSend->shouldNotReceive('sendTextToContact');

        $result = $this->runner()->runForContact(
            $this->reloadAutomation(),
            $this->contact,
            false,
            false,
            'Oi'
        );

        $detail = collect($result['details'])->firstWhere('action', 'ai_reply');
        $this->assertNotNull($detail, 'Deve existir detail do nó ai_reply');
        $this->assertFalse($detail['success']);
        $this->assertStringContainsString('desativado', $detail['reason']);
    }

    public function test_ai_reply_with_inactive_agent_follows_error_branch(): void
    {
        $inactiveAgent = $this->createActiveAgent(['active' => false]);
        $aiNode        = $this->createAiNode(['agent_id' => $inactiveAgent->id, 'mode' => 'immediate']);
        $successNode   = $this->createAiNode(['message' => 'Sucesso'], 'send_message');
        $errorNode     = AutomationNode::create([
            'automation_id' => $this->automation->id,
            'type'          => 'add_tag',
            'config'        => ['tag_id' => 0],
        ]);

        $this->createEdge($this->startNode, $aiNode);
        $this->createEdge($aiNode, $successNode, 'success');
        $this->createEdge($aiNode, $errorNode,   'error');

        $this->waSend->shouldNotReceive('sendTextToContact');

        $result = $this->runner()->runForContact(
            $this->reloadAutomation(),
            $this->contact,
            false,
            false,
            'mensagem'
        );

        $types = collect($result['details'])->pluck('action')->all();
        $this->assertContains('ai_reply', $types);
        $this->assertNotContains('send_message', $types, 'Branch de sucesso não deve executar');
        $this->assertContains('add_tag', $types, 'Branch de erro deve executar');
    }

    // ── ai_reply: modo immediate (sucesso) ───────────────────────────────

    public function test_ai_reply_immediate_calls_ai_and_sends_message_on_success(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode(['agent_id' => $agent->id, 'mode' => 'immediate']);
        $this->createEdge($this->startNode, $aiNode);

        $this->mock(AiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateReply')
                ->once()
                ->andReturn('Resposta automática da IA');
        });

        $this->waSend->shouldReceive('sendTextToContact')
            ->once()
            ->with($this->user->id, $this->contact, 'Resposta automática da IA', \Mockery::any())
            ->andReturn(null);

        $result = $this->runner()->runForContact(
            $this->reloadAutomation(),
            $this->contact,
            false,
            false,
            'Preciso de ajuda'
        );

        $this->assertTrue($result['success']);
        $detail = collect($result['details'])->firstWhere('action', 'ai_reply');
        $this->assertTrue($detail['success']);
        $this->assertSame('immediate', $detail['mode']);
    }

    public function test_ai_reply_immediate_follows_success_branch(): void
    {
        $agent       = $this->createActiveAgent();
        $aiNode      = $this->createAiNode(['agent_id' => $agent->id, 'mode' => 'immediate']);
        $successNode = AutomationNode::create([
            'automation_id' => $this->automation->id,
            'type'          => 'add_tag',
            'config'        => ['tag_id' => 0],
        ]);

        $this->createEdge($this->startNode, $aiNode);
        $this->createEdge($aiNode, $successNode, 'success');

        $this->mock(AiService::class, fn (MockInterface $m) => $m
            ->shouldReceive('generateReply')->once()->andReturn('ok'));

        $this->waSend->shouldReceive('sendTextToContact')->once()->andReturn(null);

        $result = $this->runner()->runForContact(
            $this->reloadAutomation(),
            $this->contact,
            false,
            false,
            'oi'
        );

        $types = collect($result['details'])->pluck('action')->all();
        $this->assertContains('add_tag', $types);
    }

    public function test_ai_reply_immediate_uses_trigger_message_from_metadata(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode(['agent_id' => $agent->id, 'mode' => 'immediate']);
        $this->createEdge($this->startNode, $aiNode);

        $capturedMessage = null;

        $this->mock(AiService::class, function (MockInterface $mock) use (&$capturedMessage) {
            $mock->shouldReceive('generateReply')
                ->once()
                ->withArgs(function ($accountId, $agent, $msg) use (&$capturedMessage) {
                    $capturedMessage = $msg;
                    return true;
                })
                ->andReturn('ok');
        });

        $this->waSend->shouldReceive('sendTextToContact')->andReturn(null);

        $this->runner()->runForContact(
            $this->reloadAutomation(),
            $this->contact,
            false,
            false,
            'Mensagem de gatilho'
        );

        $this->assertSame('Mensagem de gatilho', $capturedMessage);
    }

    // ── ai_reply: modo immediate (falha na API) ──────────────────────────

    public function test_ai_reply_immediate_follows_error_branch_on_api_failure(): void
    {
        $agent     = $this->createActiveAgent();
        $aiNode    = $this->createAiNode(['agent_id' => $agent->id, 'mode' => 'immediate']);
        $errorNode = AutomationNode::create([
            'automation_id' => $this->automation->id,
            'type'          => 'add_tag',
            'config'        => ['tag_id' => 0],
        ]);

        $this->createEdge($this->startNode, $aiNode);
        $this->createEdge($aiNode, $errorNode, 'error');

        $this->mock(AiService::class, fn (MockInterface $m) => $m
            ->shouldReceive('generateReply')
            ->andThrow(new \RuntimeException('Timeout OpenAI')));

        $this->waSend->shouldNotReceive('sendTextToContact');

        $result = $this->runner()->runForContact(
            $this->reloadAutomation(),
            $this->contact,
            false,
            false,
            'oi'
        );

        $detail = collect($result['details'])->firstWhere('action', 'ai_reply');
        $this->assertFalse($detail['success']);
        $this->assertStringContainsString('Timeout OpenAI', $detail['reason']);

        $types = collect($result['details'])->pluck('action')->all();
        $this->assertContains('add_tag', $types);
    }

    // ── ai_reply: modo immediate sem mensagem de gatilho ─────────────────

    public function test_ai_reply_immediate_records_error_when_no_trigger_message_available(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode(['agent_id' => $agent->id, 'mode' => 'immediate']);
        $this->createEdge($this->startNode, $aiNode);

        // Sem trigger_message e sem conversa prévia
        $this->waSend->shouldNotReceive('sendTextToContact');

        $result = $this->runner()->runForContact($this->reloadAutomation(), $this->contact);

        $detail = collect($result['details'])->firstWhere('action', 'ai_reply');
        $this->assertFalse($detail['success']);
        $this->assertStringContainsString('Nenhuma mensagem', $detail['reason']);
    }

    // ── ai_reply: modo continuous ─────────────────────────────────────────

    public function test_ai_reply_continuous_sets_chat_active_and_returns_not_done(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode([
            'agent_id'       => $agent->id,
            'mode'           => 'continuous',
            'timeout_hours'  => 2,
            'stop_commands'  => ['sair'],
            'farewell_message' => 'Até logo!',
        ]);
        $this->createEdge($this->startNode, $aiNode);

        $this->mock(AiService::class, fn (MockInterface $m) => $m
            ->shouldReceive('generateReply')->andReturn('Olá, em que posso ajudar?'));

        $this->waSend->shouldReceive('sendTextToContact')->andReturn(null);

        $result = $this->runner()->runForContact(
            $this->reloadAutomation(),
            $this->contact,
            false,
            false,
            'preciso de ajuda'
        );

        // runForContact retorna success=true com mensagem "iniciada" quando o fluxo aguarda (done=false)
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('iniciada', strtolower($result['message']));
        // O run deve ter ai_chat_active=true no metadata
        $run = AutomationRun::where('automation_id', $this->automation->id)
            ->where('contact_id', $this->contact->id)
            ->first();
        $this->assertTrue($run->metadata['ai_chat_active']);
        $this->assertSame($aiNode->id, $run->metadata['ai_chat_node_id']);
    }

    public function test_ai_reply_continuous_with_inactive_agent_does_not_activate_chat(): void
    {
        $inactiveAgent = $this->createActiveAgent(['active' => false]);
        $aiNode        = $this->createAiNode([
            'agent_id' => $inactiveAgent->id,
            'mode'     => 'continuous',
        ]);
        $this->createEdge($this->startNode, $aiNode);

        $this->waSend->shouldNotReceive('sendTextToContact');

        $result = $this->runner()->runForContact(
            $this->reloadAutomation(),
            $this->contact,
            false,
            false,
            'oi'
        );

        $run = AutomationRun::where('automation_id', $this->automation->id)
            ->where('contact_id', $this->contact->id)
            ->first();

        $this->assertFalse($run->metadata['ai_chat_active'] ?? false);
    }

    // ── ai_reply: modo wait (aguardar resposta) ───────────────────────────

    public function test_ai_reply_wait_mode_sends_question_and_waits_for_reply(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode([
            'agent_id'        => $agent->id,
            'mode'            => 'wait',
            'question'        => 'Como posso te ajudar?',
            'timeout_minutes' => 60,
        ]);
        $this->createEdge($this->startNode, $aiNode);

        $this->waSend->shouldReceive('sendTextToContact')
            ->once()
            ->with($this->user->id, $this->contact, 'Como posso te ajudar?', \Mockery::any())
            ->andReturn(null);

        $result = $this->runner()->runForContact($this->reloadAutomation(), $this->contact);

        // Deve retornar "não concluído" enquanto aguarda resposta
        $this->assertStringContainsString('iniciada', strtolower($result['message']));

        $run = AutomationRun::where('automation_id', $this->automation->id)->first();
        $this->assertNotNull($run->resume_at);
        $this->assertSame($aiNode->id, $run->metadata['waiting_ai_reply_node_id']);
    }

    // ── ai_reply: dry run (teste/simulação) ───────────────────────────────

    public function test_ai_reply_dry_run_does_not_call_ai_or_send_messages(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode(['agent_id' => $agent->id, 'mode' => 'immediate']);
        $this->createEdge($this->startNode, $aiNode);

        $this->mock(AiService::class, fn (MockInterface $m) => $m
            ->shouldNotReceive('generateReply'));

        $this->waSend->shouldNotReceive('sendTextToContact');

        $run    = $this->createRun();
        $auto   = $this->reloadAutomation();
        $runner = $this->runner();

        // Acessa o método público runForContact com dryRun=true
        $result = $runner->runForContact($auto, $this->contact, true, true);

        $detail = collect($result['details'])->firstWhere('action', 'ai_reply');
        $this->assertNotNull($detail);
        $this->assertTrue($detail['dry_run'] ?? false);
    }

    // ── continueAiChat ────────────────────────────────────────────────────

    public function test_continue_ai_chat_calls_ai_and_sends_response(): void
    {
        $agent = $this->createActiveAgent();

        $run = $this->createRun([
            'ai_chat_active'   => true,
            'ai_chat_node_id'  => 1,
            'ai_chat_agent_id' => $agent->id,
        ]);

        $aiNode = $this->createAiNode(['agent_id' => $agent->id, 'mode' => 'continuous']);

        $this->automation->load(['flowNodes', 'flowEdges', 'actions']);

        $this->mock(AiService::class, fn (MockInterface $m) => $m
            ->shouldReceive('generateReply')
            ->once()
            ->andReturn('Posso ajudar sim!'));

        $this->waSend->shouldReceive('sendTextToContact')
            ->once()
            ->with($this->user->id, $this->contact, 'Posso ajudar sim!', $run->id)
            ->andReturn(null);

        $this->runner()->continueAiChat(
            $this->reloadAutomation(),
            $this->contact,
            $run,
            $aiNode->id,
            'Você consegue me ajudar?'
        );
    }

    public function test_continue_ai_chat_does_nothing_when_agent_is_inactive(): void
    {
        $inactiveAgent = $this->createActiveAgent(['active' => false]);

        $run = $this->createRun([
            'ai_chat_active'   => true,
            'ai_chat_agent_id' => $inactiveAgent->id,
        ]);

        $this->mock(AiService::class, fn (MockInterface $m) => $m
            ->shouldNotReceive('generateReply'));

        $this->waSend->shouldNotReceive('sendTextToContact');

        $this->runner()->continueAiChat(
            $this->reloadAutomation(),
            $this->contact,
            $run,
            0,
            'mensagem do contato'
        );
    }

    // ── runForContactFromAiReply ──────────────────────────────────────────

    public function test_run_from_ai_reply_with_active_agent_sends_response(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode(['agent_id' => $agent->id]);

        $run = $this->createRun(['waiting_ai_reply_node_id' => $aiNode->id]);

        $this->mock(AiService::class, fn (MockInterface $m) => $m
            ->shouldReceive('generateReply')->once()->andReturn('Boa pergunta!'));

        $this->waSend->shouldReceive('sendTextToContact')
            ->once()
            ->andReturn(null);

        $result = $this->runner()->runForContactFromAiReply(
            $this->reloadAutomation(),
            $this->contact,
            $run,
            $aiNode->id,
            'Qual o prazo de entrega?'
        );

        $this->assertTrue($result['done']);
        $this->assertSame('success', $result['status']);
    }

    public function test_run_from_ai_reply_with_inactive_agent_goes_to_error_branch(): void
    {
        $inactiveAgent = $this->createActiveAgent(['active' => false]);
        $aiNode        = $this->createAiNode(['agent_id' => $inactiveAgent->id]);
        $errorNode     = AutomationNode::create([
            'automation_id' => $this->automation->id,
            'type'          => 'add_tag',
            'config'        => ['tag_id' => 0],
        ]);
        $this->createEdge($aiNode, $errorNode, 'error');

        $run = $this->createRun(['waiting_ai_reply_node_id' => $aiNode->id]);

        $this->mock(AiService::class, fn (MockInterface $m) => $m
            ->shouldNotReceive('generateReply'));

        $this->waSend->shouldNotReceive('sendTextToContact');

        $result = $this->runner()->runForContactFromAiReply(
            $this->reloadAutomation(),
            $this->contact,
            $run,
            $aiNode->id,
            'mensagem do contato'
        );

        $this->assertSame('partial', $result['status']);
    }

    // ── runForContactFromAiChat: encerramento ─────────────────────────────

    public function test_run_from_ai_chat_ended_sends_farewell_message(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode(['agent_id' => $agent->id]);

        $run = $this->createRun([
            'ai_chat_active'    => true,
            'ai_chat_node_id'   => $aiNode->id,
            'ai_chat_agent_id'  => $agent->id,
            'ai_chat_farewell'  => 'Até logo, obrigado pelo contato!',
            'ai_session_start_at' => now()->subMinutes(5)->toISOString(),
        ]);

        $this->waSend->shouldReceive('sendTextToContact')
            ->once()
            ->with($this->user->id, $this->contact, 'Até logo, obrigado pelo contato!', $run->id)
            ->andReturn(null);

        $result = $this->runner()->runForContactFromAiChat(
            $this->reloadAutomation(),
            $this->contact,
            $run,
            $aiNode->id,
            'ended'
        );

        $this->assertTrue($result['done']);
        $this->assertSame('success', $result['status']);
    }

    public function test_run_from_ai_chat_ended_marks_chat_as_inactive(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode(['agent_id' => $agent->id]);

        $run = $this->createRun([
            'ai_chat_active'   => true,
            'ai_chat_agent_id' => $agent->id,
            'ai_chat_farewell' => '',
        ]);

        $this->waSend->shouldNotReceive('sendTextToContact');

        $this->runner()->runForContactFromAiChat(
            $this->reloadAutomation(),
            $this->contact,
            $run,
            $aiNode->id,
            'ended'
        );

        $updated = $run->fresh();
        $this->assertFalse($updated->metadata['ai_chat_active']);
        $this->assertNotNull($updated->metadata['ai_session_end_at']);
        $this->assertNull($updated->resume_at);
    }

    public function test_run_from_ai_chat_ended_generates_ai_farewell_when_no_static_message(): void
    {
        $agent  = $this->createActiveAgent();
        $aiNode = $this->createAiNode(['agent_id' => $agent->id]);

        $run = $this->createRun([
            'ai_chat_active'     => true,
            'ai_chat_agent_id'   => $agent->id,
            'ai_chat_farewell'   => '',
            'ai_session_start_at' => now()->subMinutes(10)->toISOString(),
        ]);

        $this->mock(AiService::class, fn (MockInterface $m) => $m
            ->shouldReceive('generateReply')
            ->once()
            ->andReturn('Foi um prazer atendê-lo!'));

        $this->waSend->shouldReceive('sendTextToContact')
            ->once()
            ->with($this->user->id, $this->contact, 'Foi um prazer atendê-lo!', $run->id)
            ->andReturn(null);

        $this->runner()->runForContactFromAiChat(
            $this->reloadAutomation(),
            $this->contact,
            $run,
            $aiNode->id,
            'ended'
        );
    }

    // ── normalizeReplyText ────────────────────────────────────────────────

    public function test_normalize_reply_text_lowercases_and_removes_accents(): void
    {
        $result = AutomationRunnerService::normalizeReplyText('Saír');
        $this->assertSame('sair', $result);
    }

    public function test_normalize_reply_text_trims_whitespace(): void
    {
        $result = AutomationRunnerService::normalizeReplyText('  encerrar  ');
        $this->assertSame('encerrar', $result);
    }

    public function test_normalize_reply_text_removes_punctuation(): void
    {
        $result = AutomationRunnerService::normalizeReplyText('Tchau!');
        $this->assertSame('tchau', $result);
    }
}
