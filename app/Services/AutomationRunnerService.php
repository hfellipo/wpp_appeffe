<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * Executa uma automação para um contato (ações em sequência).
 * Usado pelo comando automations:run e pelo teste manual.
 */
class AutomationRunnerService
{
    public function __construct(
        private WhatsAppSendService $whatsAppSend
    ) {
    }

    /**
     * Executa a automação para um único contato (teste ou fila).
     * Ignora gatilho/condição no teste; no cron são aplicados antes.
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

        $details = [];
        $runStatus = 'success';

        foreach ($automation->actions as $action) {
            if ($action->type === 'wait_delay') {
                $details[] = ['action' => $action->type, 'skipped' => true];
                continue;
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

        $message = $runStatus === 'success'
            ? __('Automação executada com sucesso para :name.', ['name' => $contact->name])
            : __('Automação executada com ressalvas (alguma ação falhou). Veja os detalhes.');

        return [
            'success' => $runStatus === 'success',
            'message' => $message,
            'run' => $run,
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
