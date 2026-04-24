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
        $automations = Automation::query()
            ->where('is_active', true)
            ->where('user_id', $contact->user_id)
            ->whereHas('trigger', fn ($q) => $q
                ->where('type', 'list_added')
                ->where('config->lista_id', $listaId)
            )
            ->with(['trigger', 'conditions'])
            ->get();

        foreach ($automations as $automation) {
            $this->dispatchIfEligible($automation, $contact, "list_added:lista_id={$listaId}");
        }
    }

    /**
     * Chamado quando uma tag é adicionada a um contato.
     */
    public function contactTagAdded(Contact $contact, int $tagId): void
    {
        $automations = Automation::query()
            ->where('is_active', true)
            ->where('user_id', $contact->user_id)
            ->whereHas('trigger', fn ($q) => $q
                ->where('type', 'tag_added')
                ->where('config->tag_id', $tagId)
            )
            ->with(['trigger', 'conditions'])
            ->get();

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
            Log::info('AutomationEventService: skipped (already ran)', [
                'automation_id' => $automation->id,
                'contact_id'    => $contact->id,
                'context'       => $context,
            ]);
            return;
        }

        Log::info('AutomationEventService: dispatching', [
            'automation_id' => $automation->id,
            'contact_id'    => $contact->id,
            'context'       => $context,
        ]);

        // dispatchSync: executa imediatamente (sem depender de queue worker)
        TriggerAutomationForContactJob::dispatchSync($automation->id, $contact->id);
    }
}
