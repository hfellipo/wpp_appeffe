<?php

namespace App\Services;

use App\Jobs\RunAutomationFromActionJob;
use App\Jobs\RunAutomationFromNodeJob;
use App\Models\Automation;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * Executa uma automação para um contato.
 *
 * - Se a automação tiver fluxo visual (flowNodes/flowEdges), executa o grafo a partir do nó "start".
 * - Senão, usa a lista legada de actions por position.
 * - Nó/job "Aguardar (delay)" agenda a continuação em resume_at.
 */
class AutomationRunnerService
{
    public function __construct(
        private WhatsAppSendService $whatsAppSend
    ) {
    }

    /**
     * Executa a automação para um único contato (teste ou fila).
     * Se houver "Aguardar (delay)", o run fica com resume_at no metadata; o cron retoma depois.
     * Garante um único envio por (automação, contato) por ciclo: se já existir run recente, não envia de novo.
     *
     * @return array{success: bool, message: string, run: ?AutomationRun, details: array}
     */
    public function runForContact(Automation $automation, Contact $contact, bool $skipDelays = false, bool $dryRun = false): array
    {
        $accountId = (int) $automation->user_id;
        if ((int) $contact->user_id !== $accountId) {
            return [
                'success' => false,
                'message' => __('Contato não pertence à sua conta.'),
                'run' => null,
                'details' => [],
            ];
        }

        $automation->load(['actions', 'flowNodes', 'flowEdges']);

        $useFlow = $automation->flowNodes->isNotEmpty();
        if ($useFlow) {
            $startNode = $automation->flowNodes->firstWhere('type', 'start');
            if (! $startNode) {
                return [
                    'success' => false,
                    'message' => __('Fluxo sem nó de início. Adicione um nó "Início" no editor.'),
                    'run' => null,
                    'details' => [],
                ];
            }
        } elseif ($automation->actions->isEmpty()) {
            return [
                'success' => false,
                'message' => __('Esta automação não tem nenhuma ação configurada.'),
                'run' => null,
                'details' => [],
            ];
        }

        // Se run_once_per_contact=true (padrão), bloqueia re-execução para o mesmo contato
        $runOnce = $automation->run_once_per_contact ?? true;
        if ($runOnce) {
            $existingRun = AutomationRun::query()
                ->where('automation_id', $automation->id)
                ->where('contact_id', $contact->id)
                ->first();
            if ($existingRun !== null) {
                Log::info('automation run skipped: contact already received this automation', [
                    'automation_id' => $automation->id,
                    'contact_id' => $contact->id,
                    'existing_run_id' => $existingRun->id,
                ]);
                return [
                    'success' => true,
                    'message' => __('Automação já executada para este contato. Cada contato recebe no máximo uma vez por automação.'),
                    'run' => $existingRun,
                    'details' => [],
                ];
            }
        }

        $run = AutomationRun::create([
            'contact_id' => $contact->id,
            'automation_id' => $automation->id,
            'ran_at' => now(),
            'status' => 'success',
            'metadata' => [],
        ]);

        $result = $useFlow
            ? $this->runFlowFromNode($automation, $contact, $run, $startNode, $skipDelays, $dryRun)
            : $this->runNodesFrom($automation, $contact, $run, 0);

        if (! ($result['done'] ?? true)) {
            $delayMinutes = (int) ($result['delay_minutes'] ?? 0);
            $message = __('Automação iniciada. Próximo nó será executado em :min min.', ['min' => $delayMinutes]);
            return [
                'success' => true,
                'message' => $message,
                'run' => $run,
                'details' => $result['details'] ?? [],
            ];
        }

        $run->update(['status' => $result['status'] ?? 'success', 'metadata' => ['details' => $result['details'] ?? []]]);

        $message = ($result['status'] ?? 'success') === 'success'
            ? __('Automação executada com sucesso para :name.', ['name' => $contact->name])
            : __('Automação executada com ressalvas (alguma ação falhou). Veja os detalhes.');

        return [
            'success' => ($result['status'] ?? 'success') === 'success',
            'message' => $message,
            'run' => $run,
            'details' => $result['details'] ?? [],
        ];
    }

    /**
     * Executa nós a partir de uma posição (lista legada de actions).
     *
     * @return array{done: bool, status?: string, details: array, delay_minutes?: int, next_position?: int, resume_at?: \Carbon\CarbonImmutable}
     */
    public function runForContactFromPosition(Automation $automation, Contact $contact, AutomationRun $run, int $startFromPosition): array
    {
        return $this->runNodesFrom($automation, $contact, $run, $startFromPosition);
    }

    /**
     * Retoma o fluxo visual a partir de um nó (após delay).
     *
     * @return array{done: bool, status?: string, details: array, delay_minutes?: int, resume_at?: \Carbon\CarbonImmutable}
     */
    /**
     * Retoma o flow a partir de um nó smart_reply com o handle correspondente à resposta.
     */
    public function runForContactFromSmartReply(Automation $automation, Contact $contact, AutomationRun $run, int $nodeId, string $handle): array
    {
        $meta = $run->metadata ?? [];
        $run->update([
            'resume_at' => null,
            'metadata'  => array_merge($meta, ['waiting_smart_reply_node_id' => null, 'smart_reply_matched_handle' => $handle]),
        ]);

        $node = $automation->flowNodes->firstWhere('id', $nodeId);
        if (! $node) {
            return ['done' => true, 'status' => 'success', 'details' => $meta['details'] ?? []];
        }

        $nextNodes = $this->getNextNodesFromHandle($automation, $node, $handle);
        foreach ($nextNodes as $next) {
            $this->runFlowFromNode($automation, $contact, $run->fresh(), $next);
        }

        return ['done' => true, 'status' => 'success', 'details' => $run->fresh()->metadata['details'] ?? []];
    }

    /**
     * Normaliza texto para comparação case-insensitive e sem acentos.
     */
    public static function normalizeReplyText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_D);
        }
        // Remove combining diacritical marks (accents)
        $text = preg_replace('/\p{Mn}/u', '', (string) $text);
        // Keep only letters, digits, spaces
        $text = preg_replace('/[^a-z0-9\s]/u', '', (string) $text);
        return trim((string) $text);
    }

    public function runForContactFromNode(Automation $automation, Contact $contact, AutomationRun $run, int $nodeId): array
    {
        $node = $automation->flowNodes->firstWhere('id', $nodeId);
        if (! $node) {
            $run->update(['resume_at' => null, 'metadata' => array_merge($run->metadata ?? [], ['resume_from_node_id' => null])]);
            return ['done' => true, 'status' => 'success', 'details' => $run->metadata['details'] ?? []];
        }
        $run->update([
            'resume_at' => null,
            'metadata' => array_merge($run->metadata ?? [], ['resume_from_node_id' => null]),
        ]);
        return $this->runFlowFromNode($automation, $contact, $run, $node);
    }

    /**
     * Executa o fluxo a partir de um nó (grafo). Retorna done=false quando agenda um delay.
     *
     * @return array{done: bool, status?: string, details: array, delay_minutes?: int, resume_at?: \Carbon\CarbonImmutable}
     */
    private function runFlowFromNode(Automation $automation, Contact $contact, AutomationRun $run, AutomationNode $node, bool $skipDelays = false, bool $dryRun = false): array
    {
        $accountId = (int) $automation->user_id;
        $details   = (array) ($run->metadata['details']    ?? []);
        $runStatus = (string) ($run->metadata['run_status'] ?? 'success');

        // ── start: pass through ───────────────────────────────────────
        if ($node->type === 'start') {
            $nextNodes = $this->getNextNodes($automation, $node);
            foreach ($nextNodes as $next) {
                $result = $this->runFlowFromNode($automation, $contact, $run->fresh(), $next, $skipDelays, $dryRun);
                if (! ($result['done'] ?? true)) return $result;
                $run       = $run->fresh();
                $details   = (array) ($run->metadata['details']    ?? []);
                $runStatus = (string) ($run->metadata['run_status'] ?? $runStatus);
            }
            $run->update(['status' => $runStatus, 'metadata' => ['details' => $details, 'run_status' => $runStatus]]);
            return ['done' => true, 'status' => $runStatus, 'details' => $details];
        }

        // ── delay ─────────────────────────────────────────────────────
        if ($node->type === 'delay') {
            $minutes   = max(1, min(10080, (int) ($node->config['minutes'] ?? 0)));
            $nextNodes = $this->getNextNodes($automation, $node);

            if ($skipDelays) {
                $details[] = ['action' => 'delay', 'node_id' => $node->id, 'scheduled_after_minutes' => $minutes, 'skipped_in_test' => true];
                $run->update(['metadata' => ['details' => $details, 'run_status' => $runStatus]]);
                foreach ($nextNodes as $next) {
                    $result = $this->runFlowFromNode($automation, $contact, $run->fresh(), $next, $skipDelays, $dryRun);
                    if (! ($result['done'] ?? true)) return $result;
                    $run       = $run->fresh();
                    $details   = (array) ($run->metadata['details']    ?? []);
                    $runStatus = (string) ($run->metadata['run_status'] ?? $runStatus);
                }
                $run->update(['status' => $runStatus, 'metadata' => ['details' => $details, 'run_status' => $runStatus]]);
                return ['done' => true, 'status' => $runStatus, 'details' => $details];
            }

            $resumeAt   = now()->addMinutes($minutes);
            $nextNodeId = $nextNodes->isNotEmpty() ? $nextNodes->first()->id : null;
            $details[]  = ['action' => 'delay', 'node_id' => $node->id, 'scheduled_after_minutes' => $minutes];
            $run->update(['metadata' => ['details' => $details, 'run_status' => $runStatus], 'resume_at' => $resumeAt, 'resume_from_position' => null]);
            if ($nextNodeId) {
                $run->update(['metadata' => array_merge($run->metadata ?? [], ['resume_from_node_id' => $nextNodeId])]);
                RunAutomationFromNodeJob::dispatch($automation->id, $contact->id, $run->id, $nextNodeId)->delay($resumeAt);
            }
            return ['done' => false, 'details' => $details, 'delay_minutes' => $minutes, 'resume_at' => $resumeAt];
        }

        // ── condition: always evaluate (dry run needs real branch) ────
        if ($node->type === 'condition') {
            $condResult   = $this->evaluateNodeCondition($node->config ?? [], $contact);
            $sourceHandle = $condResult ? 'yes' : 'no';
            $details[]    = ['action' => 'condition', 'node_id' => $node->id, 'result' => $condResult, 'branch' => $sourceHandle];
            $run->update(['metadata' => ['details' => $details, 'run_status' => $runStatus]]);
            $nextNodes = $this->getNextNodesFromHandle($automation, $node, $sourceHandle);
            foreach ($nextNodes as $next) {
                $result = $this->runFlowFromNode($automation, $contact, $run->fresh(), $next, $skipDelays, $dryRun);
                if (! ($result['done'] ?? true)) return $result;
                $run       = $run->fresh();
                $details   = (array) ($run->metadata['details']    ?? []);
                $runStatus = (string) ($run->metadata['run_status'] ?? $runStatus);
            }
            $run->update(['status' => $runStatus, 'metadata' => ['details' => $details, 'run_status' => $runStatus], 'resume_at' => null]);
            return ['done' => true, 'status' => $runStatus, 'details' => $details];
        }

        // ── smart_reply: envia pergunta e aguarda resposta por texto ──
        if ($node->type === 'smart_reply') {
            $question = trim((string) ($node->config['question'] ?? ''));
            $timeout  = max(1, (int) ($node->config['timeout_minutes'] ?? 1440));
            $choices  = (array) ($node->config['choices'] ?? []);

            if ($dryRun) {
                // Teste: simula passando pela primeira opção disponível
                $firstChoice = $choices[0] ?? null;
                $handle      = $firstChoice ? 'reply_' . ($firstChoice['id'] ?? '1') : 'fallback';
                $details[]   = ['action' => 'smart_reply', 'node_id' => $node->id, 'status' => 'simulated', 'simulated_choice' => $firstChoice['label'] ?? 'fallback', 'dry_run' => true];
                $run->update(['metadata' => array_merge($run->metadata ?? [], ['details' => $details, 'run_status' => $runStatus])]);
                $nextNodes = $this->getNextNodesFromHandle($automation, $node, $handle);
                foreach ($nextNodes as $next) {
                    $result = $this->runFlowFromNode($automation, $contact, $run->fresh(), $next, $skipDelays, $dryRun);
                    if (! ($result['done'] ?? true)) return $result;
                    $run       = $run->fresh();
                    $details   = (array) ($run->metadata['details']    ?? []);
                    $runStatus = (string) ($run->metadata['run_status'] ?? $runStatus);
                }
                $run->update(['status' => $runStatus, 'metadata' => array_merge($run->metadata ?? [], ['details' => $details, 'run_status' => $runStatus])]);
                return ['done' => true, 'status' => $runStatus, 'details' => $details];
            }

            if ($question !== '' && $contact->phone_for_whatsapp !== '') {
                $this->whatsAppSend->sendTextToContact($accountId, $contact, $question, $run->id);
            }
            $run->update([
                'metadata'  => array_merge($run->metadata ?? [], [
                    'details'                     => $details,
                    'run_status'                  => $runStatus,
                    'waiting_smart_reply_node_id' => $node->id,
                ]),
                'resume_at' => now()->addMinutes($timeout),
            ]);
            $details[] = ['action' => 'smart_reply', 'node_id' => $node->id, 'status' => 'waiting_reply'];
            $run->update(['metadata' => array_merge($run->metadata ?? [], ['details' => $details, 'run_status' => $runStatus])]);
            return ['done' => false, 'details' => $details, 'delay_minutes' => $timeout];
        }

        // ── user_input ────────────────────────────────────────────────
        if ($node->type === 'user_input') {
            if (! $dryRun) {
                $question = trim((string) ($node->config['question'] ?? ''));
                if ($question !== '' && $contact->phone_for_whatsapp !== '') {
                    $this->whatsAppSend->sendTextToContact($accountId, $contact, $question, $run->id);
                }
                $run->update([
                    'metadata'  => ['details' => $details, 'run_status' => $runStatus, 'waiting_input_node_id' => $node->id],
                    'resume_at' => now()->addMinutes((int) ($node->config['timeout_minutes'] ?? 60)),
                ]);
            }
            $details[] = ['action' => 'user_input', 'node_id' => $node->id, 'status' => 'waiting', 'dry_run' => $dryRun];
            $run->update(['metadata' => ['details' => $details, 'run_status' => $runStatus]]);
            return ['done' => false, 'details' => $details, 'delay_minutes' => (int) ($node->config['timeout_minutes'] ?? 60)];
        }

        // ── generic action node ───────────────────────────────────────
        if ($dryRun) {
            // Simulation: record what would happen without executing
            $details[] = ['action' => $node->type, 'node_id' => $node->id, 'success' => true, 'dry_run' => true];
        } else {
            Log::info('automation flow executing node', ['run_id' => $run->id, 'node_id' => $node->id, 'node_type' => $node->type]);
            $ok = $this->executeNode($node, $accountId, $contact, $run);
            $details[] = ['action' => $node->type, 'node_id' => $node->id, 'success' => $ok];
            if (! $ok) {
                $runStatus = 'partial';
                if ($node->type === 'send_message') {
                    $details[array_key_last($details)]['reason'] = $this->lastSendFailureReason();
                }
            }
        }
        $run->update(['metadata' => ['details' => $details, 'run_status' => $runStatus]]);

        $nextNodes = $this->getNextNodes($automation, $node);
        foreach ($nextNodes as $next) {
            $result = $this->runFlowFromNode($automation, $contact, $run->fresh(), $next, $skipDelays, $dryRun);
            if (! ($result['done'] ?? true)) return $result;
            $run       = $run->fresh();
            $details   = (array) ($run->metadata['details']    ?? []);
            $runStatus = (string) ($run->metadata['run_status'] ?? $runStatus);
        }

        $run->update(['status' => $runStatus, 'metadata' => ['details' => $details, 'run_status' => $runStatus], 'resume_at' => null]);
        return ['done' => true, 'status' => $runStatus, 'details' => $details];
    }

    private function getNextNodes(Automation $automation, AutomationNode $node): \Illuminate\Support\Collection
    {
        $targetIds = $automation->flowEdges
            ->where('source_node_id', $node->id)
            ->pluck('target_node_id')
            ->unique()->values();
        if ($targetIds->isEmpty()) {
            return collect();
        }
        return $automation->flowNodes->whereIn('id', $targetIds)->values();
    }

    /** For condition nodes: follow only the edges from the matching source handle (yes/no). */
    private function getNextNodesFromHandle(Automation $automation, AutomationNode $node, string $handle): \Illuminate\Support\Collection
    {
        $targetIds = $automation->flowEdges
            ->where('source_node_id', $node->id)
            ->where('source_handle', $handle)
            ->pluck('target_node_id')
            ->unique()->values();
        if ($targetIds->isEmpty()) {
            return collect();
        }
        return $automation->flowNodes->whereIn('id', $targetIds)->values();
    }

    /** Evaluates a condition node config against a contact. Returns true (yes branch) or false (no branch). */
    private function evaluateNodeCondition(array $config, Contact $contact): bool
    {
        $fieldType = (string) ($config['field_type'] ?? '');
        $operator  = (string) ($config['operator']   ?? '');

        if ($fieldType === 'tag') {
            $tagId  = (int) ($config['tag_id'] ?? 0);
            $hasTags = $contact->tags->pluck('id')->contains($tagId);
            return $operator === 'has_tag' ? $hasTags : ! $hasTags;
        }

        if ($fieldType === 'attribute') {
            $fieldKey     = (string) ($config['field_key'] ?? '');
            $contactValue = match ($fieldKey) {
                'name'  => (string) ($contact->name  ?? ''),
                'email' => (string) ($contact->email ?? ''),
                'phone' => (string) ($contact->phone ?? ''),
                default => '',
            };
            return $this->evaluateOperator($contactValue, $operator, (string) ($config['value'] ?? ''));
        }

        if ($fieldType === 'custom') {
            $fieldId     = (int) ($config['contact_field_id'] ?? 0);
            $contact->loadMissing('fieldValues');
            $fv          = $contact->fieldValues->firstWhere('contact_field_id', $fieldId);
            $contactValue = $fv ? (string) ($fv->value ?? '') : '';
            return $this->evaluateOperator($contactValue, $operator, (string) ($config['value'] ?? ''));
        }

        return false;
    }

    private function evaluateOperator(string $contactValue, string $operator, string $value): bool
    {
        $cv = mb_strtolower(trim($contactValue));
        $v  = mb_strtolower(trim($value));
        return match ($operator) {
            'equals'       => $cv === $v,
            'not_equals'   => $cv !== $v,
            'contains'     => str_contains($cv, $v),
            'not_contains' => ! str_contains($cv, $v),
            'starts_with'  => str_starts_with($cv, $v),
            'ends_with'    => str_ends_with($cv, $v),
            'is_empty'     => trim($contactValue) === '',
            'is_not_empty' => trim($contactValue) !== '',
            default        => false,
        };
    }

    private function executeNode(AutomationNode $node, int $accountId, Contact $contact, AutomationRun $run): bool
    {
        $config = $node->config ?? [];

        switch ($node->type) {

            // ── send_message (text + media URL) ──────────────────────
            case 'send_message':
                if ($contact->phone_for_whatsapp === '') {
                    Log::channel('single')->warning('AutomationRunner: contato sem telefone', ['contact_id' => $contact->id]);
                    return false;
                }
                $msgType = (string) ($config['message_type'] ?? 'text');
                if ($msgType === 'text') {
                    $text = trim((string) ($config['message'] ?? ''));
                    if ($text === '') {
                        return true;
                    }
                    return $this->whatsAppSend->sendTextToContact($accountId, $contact, $text, $run->id) !== null;
                }
                if (\in_array($msgType, ['image', 'video', 'document', 'audio'], true)) {
                    $mediaUrl = trim((string) ($config['media_url'] ?? ''));
                    if ($mediaUrl === '') {
                        return true;
                    }
                    $caption  = trim((string) ($config['caption']  ?? ''));
                    $filename = trim((string) ($config['filename'] ?? basename($mediaUrl)));
                    return $this->whatsAppSend->sendMediaUrlToContact($accountId, $contact, $mediaUrl, $msgType, $caption, $filename, $run->id) !== null;
                }
                if ($msgType === 'buttons') {
                    $text    = trim((string) ($config['message'] ?? ''));
                    $buttons = array_values(array_filter((array) ($config['buttons'] ?? []), fn ($b) => ! empty($b['text'])));
                    if ($text === '' || empty($buttons)) {
                        return true;
                    }
                    return $this->whatsAppSend->sendButtonsToContact($accountId, $contact, $text, $buttons, $run->id) !== null;
                }
                if ($msgType === 'list') {
                    $text     = trim((string) ($config['message'] ?? ''));
                    $btnText  = trim((string) ($config['list_button_text'] ?? 'Ver opções'));
                    $sections = (array) ($config['list_sections'] ?? []);
                    if ($text === '' || empty($sections)) {
                        return true;
                    }
                    return $this->whatsAppSend->sendListToContact($accountId, $contact, $text, $btnText, $sections, $run->id) !== null;
                }
                return true;

            // ── add / remove list ─────────────────────────────────────
            case 'add_list':
                $listaId = (int) ($config['lista_id'] ?? 0);
                if ($listaId > 0) {
                    $contact->listas()->syncWithoutDetaching([$listaId]);
                }
                return true;
            case 'remove_list':
                $listaId = (int) ($config['lista_id'] ?? 0);
                if ($listaId > 0) {
                    $contact->listas()->detach($listaId);
                }
                return true;

            // ── add / remove tag ──────────────────────────────────────
            case 'add_tag':
                $tagId = (int) ($config['tag_id'] ?? 0);
                if ($tagId > 0) {
                    $contact->tags()->syncWithoutDetaching([$tagId]);
                }
                return true;
            case 'remove_tag':
                $tagId = (int) ($config['tag_id'] ?? 0);
                if ($tagId > 0) {
                    $contact->tags()->detach($tagId);
                }
                return true;

            // ── update contact field ──────────────────────────────────
            case 'update_field':
                $fieldType = (string) ($config['field_type'] ?? 'attribute');
                $value     = (string) ($config['value']      ?? '');
                if ($fieldType === 'attribute') {
                    $key = (string) ($config['field_key'] ?? '');
                    if (\in_array($key, ['name', 'email', 'phone'], true)) {
                        $contact->update([$key => $value]);
                    }
                } elseif ($fieldType === 'custom') {
                    $fieldId = (int) ($config['contact_field_id'] ?? 0);
                    if ($fieldId > 0) {
                        $contact->fieldValues()->updateOrCreate(
                            ['contact_field_id' => $fieldId],
                            ['value' => $value]
                        );
                    }
                }
                return true;

            // ── go to another automation ──────────────────────────────
            case 'go_to':
                $targetAutoId = (int) ($config['automation_id'] ?? 0);
                if ($targetAutoId <= 0 || $targetAutoId === (int) $run->automation_id) {
                    return true;
                }
                $targetAuto = Automation::with(['flowNodes', 'flowEdges', 'actions'])->find($targetAutoId);
                if (! $targetAuto) {
                    return true;
                }
                try {
                    $this->runForContact($targetAuto, $contact);
                } catch (\Throwable $e) {
                    Log::warning('AutomationRunner go_to failed', ['error' => $e->getMessage()]);
                }
                return true;

            // ── transfer to human ─────────────────────────────────────
            case 'human_transfer':
                $message = trim((string) ($config['message'] ?? ''));
                if ($message !== '' && $contact->phone_for_whatsapp !== '') {
                    $this->whatsAppSend->sendTextToContact($accountId, $contact, $message, $run->id);
                }
                $tagId = (int) ($config['tag_id'] ?? 0);
                if ($tagId > 0) {
                    $contact->tags()->syncWithoutDetaching([$tagId]);
                }
                Log::info('AutomationRunner: human_transfer requested', [
                    'contact_id'   => $contact->id,
                    'automation_id' => $run->automation_id,
                ]);
                return true;

            default:
                return true;
        }
    }

    /**
     * Fluxo de nós: executa cada nó (action) em ordem; wait_delay agenda o próximo nó em resume_at.
     */
    private function runNodesFrom(Automation $automation, Contact $contact, AutomationRun $run, int $startFromPosition): array
    {
        $accountId = (int) $automation->user_id;
        $nodes = $automation->actions->values();
        $details = (array) ($run->metadata['details'] ?? []);

        if ($startFromPosition > 0) {
            Log::info('automation run resuming (next node)', [
                'run_id' => $run->id,
                'contact_id' => $contact->id,
                'automation_id' => $automation->id,
                'from_position' => $startFromPosition,
            ]);
            $run->update([
                'resume_at' => null,
                'resume_from_position' => null,
            ]);
        }

        $runStatus = 'success';

        for ($i = $startFromPosition; $i < $nodes->count(); $i++) {
            $node = $nodes[$i];

            if ($node->type === 'wait_delay') {
                $minutes = (int) ($node->config['minutes'] ?? 0);
                $minutes = max(1, min(10080, $minutes));
                $resumeAt = now()->addMinutes($minutes);
                $details[] = ['action' => $node->type, 'scheduled_after_minutes' => $minutes];
                $run->update([
                    'metadata' => ['details' => $details],
                    'resume_at' => $resumeAt,
                    'resume_from_position' => $i + 1,
                ]);
                // Executar o próximo nó exatamente em resume_at (job na fila)
                RunAutomationFromActionJob::dispatch(
                    $automation->id,
                    $contact->id,
                    $run->id,
                    $i + 1
                )->delay($resumeAt);
                return [
                    'done' => false,
                    'details' => $details,
                    'delay_minutes' => $minutes,
                    'next_position' => $i + 1,
                    'resume_at' => $resumeAt,
                ];
            }

            Log::info('automation executing node', [
                'run_id' => $run->id,
                'position' => $i,
                'action_type' => $node->type,
            ]);
            $ok = $this->executeAction($node, $accountId, $contact, $run);
            $details[] = [
                'action' => $node->type,
                'success' => $ok,
            ];

            if (! $ok) {
                $runStatus = 'partial';
                if ($node->type === 'send_whatsapp_message') {
                    $details[array_key_last($details)]['reason'] = $this->lastSendFailureReason();
                }
            }
        }

        $run->update([
            'status' => $runStatus,
            'metadata' => ['details' => $details],
            'resume_at' => null,
            'resume_from_position' => null,
        ]);

        return [
            'done' => true,
            'status' => $runStatus,
            'details' => $details,
        ];
    }

    private function executeAction($action, int $accountId, Contact $contact, AutomationRun $run): bool
    {
        switch ($action->type) {
            case 'send_whatsapp_message':
                $text = (string) ($action->config['message'] ?? '');
                if ($text === '') {
                    return true;
                }
                if ($contact->phone_for_whatsapp === '') {
                    Log::channel('single')->warning('AutomationRunner: contato sem telefone', [
                        'contact_id' => $contact->id,
                        'automation_id' => $run->automation_id,
                    ]);
                    return false;
                }
                $sent = $this->whatsAppSend->sendTextToContact($accountId, $contact, $text, $run->id);
                return $sent !== null;

            case 'add_to_list':
                $listaId = (int) ($action->config['lista_id'] ?? 0);
                if ($listaId <= 0) {
                    return true;
                }
                $contact->listas()->syncWithoutDetaching([$listaId]);
                return true;

            case 'add_tag':
                $tagId = (int) ($action->config['tag_id'] ?? 0);
                if ($tagId <= 0) {
                    return true;
                }
                $contact->tags()->syncWithoutDetaching([$tagId]);
                return true;

            default:
                return true;
        }
    }

    private function lastSendFailureReason(): string
    {
        return __('Possíveis causas: Evolution API não configurada, instância desconectada ou contato sem telefone.');
    }
}
