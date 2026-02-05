<?php

namespace App\Services;

use App\Jobs\RunAutomationFromActionJob;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * Executa uma automação para um contato (ações em sequência).
 * Usado pelo comando automations:run e pelo teste manual.
 * Ao encontrar "Aguardar (delay)", agenda job para continuar após o tempo.
 */
class AutomationRunnerService
{
    public function __construct(
        private WhatsAppSendService $whatsAppSend
    ) {
    }

    /**
     * Executa a automação para um único contato (teste ou fila).
     * Se houver "Aguardar (delay)", o restante das ações é agendado em job com atraso.
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

        $result = $this->runForContactFromPosition($automation, $contact, $run, 0);

        if (! ($result['done'] ?? true)) {
            $delayMinutes = (int) ($result['delay_minutes'] ?? 0);
            $nextPosition = (int) ($result['next_position'] ?? 0);
            if ($delayMinutes > 0 && $nextPosition > 0) {
                RunAutomationFromActionJob::dispatch(
                    $automation->id,
                    $contact->id,
                    $run->id,
                    $nextPosition
                )->delay(now()->addMinutes($delayMinutes));
            }
            $message = __('Automação iniciada. Próximas ações agendadas para :min min.', ['min' => $delayMinutes]);
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
     * Executa ações a partir de uma posição. Ao encontrar wait_delay retorna done=false para o job ser agendado.
     *
     * @return array{done: bool, status?: string, details: array, delay_minutes?: int, next_position?: int}
     */
    public function runForContactFromPosition(Automation $automation, Contact $contact, AutomationRun $run, int $startFromPosition): array
    {
        $accountId = (int) $automation->user_id;
        $actions = $automation->actions->values();
        $details = (array) ($run->metadata['details'] ?? []);

        $runStatus = 'success';

        for ($i = $startFromPosition; $i < $actions->count(); $i++) {
            $action = $actions[$i];

            if ($action->type === 'wait_delay') {
                $minutes = (int) ($action->config['minutes'] ?? 0);
                $minutes = max(1, min(10080, $minutes)); // 1 min a 7 dias
                $details[] = ['action' => $action->type, 'scheduled_after_minutes' => $minutes];
                $run->update(['metadata' => ['details' => $details]]);
                return [
                    'done' => false,
                    'details' => $details,
                    'delay_minutes' => $minutes,
                    'next_position' => $i + 1,
                ];
            }

            $ok = $this->executeAction($action, $accountId, $contact, $run);
            $details[] = [
                'action' => $action->type,
                'success' => $ok,
            ];

            if (! $ok) {
                $runStatus = 'partial';
                if ($action->type === 'send_whatsapp_message') {
                    $details[array_key_last($details)]['reason'] = $this->lastSendFailureReason();
                }
            }
        }

        $run->update(['status' => $runStatus, 'metadata' => ['details' => $details]]);

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
