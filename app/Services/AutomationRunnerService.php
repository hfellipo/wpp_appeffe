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
    public function runForContact(Automation $automation, Contact $contact): array
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

        // Garante no máximo uma mensagem por (automação, contato): se já existe qualquer run, não envia de novo
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

        $run = AutomationRun::create([
            'contact_id' => $contact->id,
            'automation_id' => $automation->id,
            'ran_at' => now(),
            'status' => 'success',
            'metadata' => [],
        ]);

        $result = $useFlow
            ? $this->runFlowFromNode($automation, $contact, $run, $startNode)
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
    private function runFlowFromNode(Automation $automation, Contact $contact, AutomationRun $run, AutomationNode $node): array
    {
        $accountId = (int) $automation->user_id;
        $details = (array) ($run->metadata['details'] ?? []);
        $runStatus = $run->metadata['run_status'] ?? 'success';

        if ($node->type === 'start') {
            $nextNodes = $this->getNextNodes($automation, $node);
            foreach ($nextNodes as $next) {
                $result = $this->runFlowFromNode($automation, $contact, $run->fresh(), $next);
                if (! ($result['done'] ?? true)) {
                    return $result;
                }
                $run = $run->fresh();
                $details = (array) ($run->metadata['details'] ?? []);
                $runStatus = $run->metadata['run_status'] ?? $runStatus;
            }
            $run->update(['status' => $runStatus, 'metadata' => ['details' => $details, 'run_status' => $runStatus]]);
            return ['done' => true, 'status' => $runStatus, 'details' => $details];
        }

        if ($node->type === 'delay') {
            $minutes = (int) ($node->config['minutes'] ?? 0);
            $minutes = max(1, min(10080, $minutes));
            $resumeAt = now()->addMinutes($minutes);
            $nextNodes = $this->getNextNodes($automation, $node);
            $details[] = ['action' => 'delay', 'node_id' => $node->id, 'scheduled_after_minutes' => $minutes];
            $nextNodeId = $nextNodes->isNotEmpty() ? $nextNodes->first()->id : null;
            $run->update([
                'metadata' => ['details' => $details, 'run_status' => $runStatus],
                'resume_at' => $resumeAt,
                'resume_from_position' => null,
            ]);
            if ($nextNodeId) {
                $run->update(['metadata' => array_merge($run->metadata ?? [], ['resume_from_node_id' => $nextNodeId])]);
                RunAutomationFromNodeJob::dispatch($automation->id, $contact->id, $run->id, $nextNodeId)->delay($resumeAt);
            }
            return [
                'done' => false,
                'details' => $details,
                'delay_minutes' => $minutes,
                'resume_at' => $resumeAt,
            ];
        }

        Log::info('automation flow executing node', ['run_id' => $run->id, 'node_id' => $node->id, 'node_type' => $node->type]);
        $ok = $this->executeNode($node, $accountId, $contact, $run);
        $details[] = ['action' => $node->type, 'node_id' => $node->id, 'success' => $ok];
        if (! $ok) {
            $runStatus = 'partial';
            if ($node->type === 'send_message') {
                $details[array_key_last($details)]['reason'] = $this->lastSendFailureReason();
            }
        }
        $run->update(['metadata' => ['details' => $details, 'run_status' => $runStatus]]);

        $nextNodes = $this->getNextNodes($automation, $node);
        foreach ($nextNodes as $next) {
            $result = $this->runFlowFromNode($automation, $contact, $run->fresh(), $next);
            if (! ($result['done'] ?? true)) {
                return $result;
            }
            $run = $run->fresh();
            $details = (array) ($run->metadata['details'] ?? []);
            $runStatus = $run->metadata['run_status'] ?? $runStatus;
        }

        $run->update(['status' => $runStatus, 'metadata' => ['details' => $details, 'run_status' => $runStatus], 'resume_at' => null]);
        return ['done' => true, 'status' => $runStatus, 'details' => $details];
    }

    private function getNextNodes(Automation $automation, AutomationNode $node): \Illuminate\Support\Collection
    {
        $targetIds = $automation->flowEdges
            ->where('source_node_id', $node->id)
            ->pluck('target_node_id')
            ->unique()
            ->values();
        if ($targetIds->isEmpty()) {
            return collect();
        }
        return $automation->flowNodes->whereIn('id', $targetIds)->values();
    }

    private function executeNode(AutomationNode $node, int $accountId, Contact $contact, AutomationRun $run): bool
    {
        $config = $node->config ?? [];
        switch ($node->type) {
            case 'send_message':
                $text = (string) ($config['message'] ?? '');
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
            case 'add_list':
                $listaId = (int) ($config['lista_id'] ?? 0);
                if ($listaId <= 0) {
                    return true;
                }
                $contact->listas()->syncWithoutDetaching([$listaId]);
                return true;
            case 'add_tag':
                $tagId = (int) ($config['tag_id'] ?? 0);
                if ($tagId <= 0) {
                    return true;
                }
                $contact->tags()->syncWithoutDetaching([$tagId]);
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
