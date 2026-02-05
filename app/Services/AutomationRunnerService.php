<?php

namespace App\Services;

use App\Jobs\RunAutomationFromActionJob;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * Executa uma automação para um contato como fluxo de nós (estilo n8n).
 *
 * - Cada ação é um "nó"; os nós são executados em sequência (position).
 * - Nó "Aguardar (delay)": agenda o próximo nó para rodar em resume_at e encerra este passo.
 * - Assim que resume_at chega, o job RunAutomationFromActionJob roda e executa o próximo nó, e assim por diante.
 * - Cron automations:run ainda pode retomar runs como fallback se a fila não estiver rodando.
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

        $automation->load(['actions']);

        if ($automation->actions->isEmpty()) {
            return [
                'success' => false,
                'message' => __('Esta automação não tem nenhuma ação configurada.'),
                'run' => null,
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

        $result = $this->runNodesFrom($automation, $contact, $run, 0);

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
     * Executa nós a partir de uma posição (estilo n8n: um nó por vez, em ordem).
     * Ao encontrar nó wait_delay: grava resume_at, agenda job para rodar no exato resume_at e retorna done=false.
     *
     * @return array{done: bool, status?: string, details: array, delay_minutes?: int, next_position?: int, resume_at?: \Carbon\CarbonImmutable}
     */
    public function runForContactFromPosition(Automation $automation, Contact $contact, AutomationRun $run, int $startFromPosition): array
    {
        return $this->runNodesFrom($automation, $contact, $run, $startFromPosition);
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
