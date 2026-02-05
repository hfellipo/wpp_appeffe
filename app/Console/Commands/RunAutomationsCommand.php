<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Services\AutomationRunnerService;
use Illuminate\Console\Command;

class RunAutomationsCommand extends Command
{
    protected $signature = 'automations:run';

    protected $description = 'Verifica automações devidas e executa ações (envio WhatsApp, lista, tag) igual ao chat.';

    public function handle(AutomationRunnerService $runner): int
    {
        $now = now();
        $automations = Automation::query()
            ->where('is_active', true)
            ->with(['trigger', 'conditions', 'actions'])
            ->get();

        $due = $automations->filter(function (Automation $a) use ($now) {
            $last = $a->last_checked_at;
            if ($last === null) {
                return true;
            }
            $interval = (int) ($a->interval_minutes ?? 15);
            return $last->copy()->addMinutes($interval)->lte($now);
        });

        foreach ($due as $automation) {
            $automation->update(['last_checked_at' => $now]);

            $trigger = $automation->trigger;
            if (! $trigger) {
                continue;
            }

            $contactIds = $this->contactsMatchingTrigger($automation, $trigger);
            if ($contactIds->isEmpty()) {
                continue;
            }

            $contacts = Contact::query()
                ->forUser($automation->user_id)
                ->whereIn('id', $contactIds)
                ->get();

            foreach ($contacts as $contact) {
                if (! $this->contactPassesConditions($automation, $contact)) {
                    continue;
                }

                $runner->runForContact($automation, $contact);
            }
        }

        return self::SUCCESS;
    }

    private function contactsMatchingTrigger(Automation $automation, $trigger): \Illuminate\Support\Collection
    {
        $alreadyRunIds = $automation->run_once_per_contact
            ? AutomationRun::query()->where('automation_id', $automation->id)->pluck('contact_id')
            : collect();

        $excludeRun = $alreadyRunIds->isNotEmpty();

        if ($trigger->type === 'tag_added') {
            $tagId = (int) ($trigger->config['tag_id'] ?? 0);
            if ($tagId <= 0) {
                return collect();
            }
            $q = Contact::query()
                ->forUser($automation->user_id)
                ->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
            if ($excludeRun) {
                $q->whereNotIn('id', $alreadyRunIds);
            }
            return $q->pluck('id');
        }

        if ($trigger->type === 'list_added') {
            $listaId = (int) ($trigger->config['lista_id'] ?? 0);
            if ($listaId <= 0) {
                return collect();
            }
            $q = Contact::query()
                ->forUser($automation->user_id)
                ->whereHas('listas', fn ($q) => $q->where('listas.id', $listaId));
            if ($excludeRun) {
                $q->whereNotIn('id', $alreadyRunIds);
            }
            return $q->pluck('id');
        }

        return collect();
    }

    private function contactPassesConditions(Automation $automation, Contact $contact): bool
    {
        if ($automation->condition_logic === null) {
            return true;
        }
        $rules = $automation->conditions;
        if ($rules->isEmpty()) {
            return true;
        }
        $results = $rules->map(fn ($r) => $r->evaluate($contact));
        return $automation->condition_logic === 'and'
            ? $results->every(fn ($v) => $v)
            : $results->contains(true);
    }
}
