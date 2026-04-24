<?php

namespace App\Services;

use App\Jobs\TriggerAutomationForContactJob;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * Dispara automações com base em eventos (ex: contato adicionado a lista/tag).
 * Chamado imediatamente quando o evento ocorre — não depende de cron.
 */
class AutomationEventService
{
    /**
     * Chamado quando um contato é adicionado a uma lista.
     */
    public function contactAddedToList(Contact $contact, int $listaId): void
    {
        Log::channel('single')->info('[AutomationEvent] contactAddedToList', [
            'contact_id'   => $contact->id,
            'contact_name' => $contact->name,
            'lista_id'     => $listaId,
            'user_id'      => $contact->user_id,
        ]);

        $automations = Automation::query()
            ->where('is_active', true)
            ->where('user_id', $contact->user_id)
            ->whereHas('trigger', fn ($q) => $q
                ->where('type', 'list_added')
                ->where('config->lista_id', $listaId)
            )
            ->with(['trigger', 'conditions'])
            ->get();

        Log::channel('single')->info('[AutomationEvent] automations encontradas para list_added', [
            'lista_id' => $listaId,
            'count'    => $automations->count(),
            'ids'      => $automations->pluck('id')->toArray(),
        ]);

        foreach ($automations as $automation) {
            $this->dispatchIfEligible($automation, $contact, "list_added:lista_id={$listaId}");
        }
    }

    /**
     * Chamado quando uma tag é adicionada a um contato.
     */
    public function contactTagAdded(Contact $contact, int $tagId): void
    {
        Log::channel('single')->info('[AutomationEvent] contactTagAdded', [
            'contact_id'   => $contact->id,
            'contact_name' => $contact->name,
            'tag_id'       => $tagId,
            'user_id'      => $contact->user_id,
        ]);

        $automations = Automation::query()
            ->where('is_active', true)
            ->where('user_id', $contact->user_id)
            ->whereHas('trigger', fn ($q) => $q
                ->where('type', 'tag_added')
                ->where('config->tag_id', $tagId)
            )
            ->with(['trigger', 'conditions'])
            ->get();

        Log::channel('single')->info('[AutomationEvent] automations encontradas para tag_added', [
            'tag_id' => $tagId,
            'count'  => $automations->count(),
            'ids'    => $automations->pluck('id')->toArray(),
        ]);

        foreach ($automations as $automation) {
            $this->dispatchIfEligible($automation, $contact, "tag_added:tag_id={$tagId}");
        }
    }

    /**
     * Verifica se o contato é elegível e despacha o job.
     */
    private function dispatchIfEligible(Automation $automation, Contact $contact, string $context): void
    {
        $alreadyRan = AutomationRun::query()
            ->where('automation_id', $automation->id)
            ->where('contact_id', $contact->id)
            ->exists();

        if ($alreadyRan) {
            Log::channel('single')->info('[AutomationEvent] SKIPPED (já executou)', [
                'automation_id'   => $automation->id,
                'automation_name' => $automation->name,
                'contact_id'      => $contact->id,
                'contact_name'    => $contact->name,
                'context'         => $context,
            ]);
            return;
        }

        Log::channel('single')->info('[AutomationEvent] DISPARANDO flow', [
            'automation_id'   => $automation->id,
            'automation_name' => $automation->name,
            'contact_id'      => $contact->id,
            'contact_name'    => $contact->name,
            'context'         => $context,
        ]);

        try {
            TriggerAutomationForContactJob::dispatchSync($automation->id, $contact->id);
            Log::channel('single')->info('[AutomationEvent] Flow executado com sucesso', [
                'automation_id' => $automation->id,
                'contact_id'    => $contact->id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[AutomationEvent] ERRO ao executar flow', [
                'automation_id' => $automation->id,
                'contact_id'    => $contact->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
        }
    }
}
