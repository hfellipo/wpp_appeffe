<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Services\AutomationRunnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cron dedicado para automação jornada.
 * Executa a mesma lógica de verificação e execução das automações,
 * permitindo agendar separadamente do cron principal (automations:run).
 *
 * Exemplo no crontab:
 *   * * * * * php /path/to/artisan automations:run-jornada
 * Ou via URL (se configurado): GET /automacao/jornada/cron?token=...
 */
class RunAutomationsJornadaCommand extends Command
{
    protected $signature = 'automations:run-jornada';

    protected $description = 'Cron de automação jornada: verifica e executa automações (trigger, condições, ações).';

    public function handle(AutomationRunnerService $runner): int
    {
        $now = now();

        // Retomar runs pausados no "Aguardar (delay)"
        $toResume = AutomationRun::query()
            ->whereNotNull('resume_at')
            ->whereNotNull('resume_from_position')
            ->where('resume_at', '<=', $now)
            ->get();

        if ($toResume->isNotEmpty()) {
            Log::info('automations:run-jornada resuming runs', [
                'count' => $toResume->count(),
                'run_ids' => $toResume->pluck('id')->toArray(),
            ]);
        }

        foreach ($toResume as $run) {
            $automation = Automation::query()->with('actions')->find($run->automation_id);
            $contact = Contact::query()->find($run->contact_id);
            if (! $automation || ! $contact || (int) $contact->user_id !== (int) $automation->user_id) {
                continue;
            }
            $fromPosition = (int) $run->resume_from_position;
            if ($fromPosition < 0) {
                continue;
            }
            $runner->runForContactFromPosition($automation, $contact, $run->fresh(), $fromPosition);
        }

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
