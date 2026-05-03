<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AutomationTrigger;
use App\Models\Contact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Processador central da automação tradicional (estilo BotConversa).
 *
 * Um único ponto de entrada: retoma runs em delay (jornada) e processa automações
 * devidas (gatilho → condições → ações). Assim, tanto o cron automations:run
 * quanto o cron por URL da jornada executam o mesmo fluxo completo.
 */
class AutomationProcessorService
{
    public function __construct(
        private AutomationRunnerService $runner
    ) {
    }

    /**
     * Executa o ciclo completo: retomar runs pausados no "Aguardar (delay)"
     * e processar automações devidas (descobrir contatos por gatilho, filtrar
     * por condições e executar ações para cada contato).
     */
    public function process(): void
    {
        $now = now();

        $this->resumeDelayedRuns($now);
        $this->processDueAutomations($now);
    }

    /**
     * Retoma runs que estavam aguardando delay (próximo nó da jornada).
     */
    private function resumeDelayedRuns(\DateTimeInterface $now): void
    {
        $toResume = AutomationRun::query()
            ->whereNotNull('resume_at')
            ->where('resume_at', '<=', $now)
            ->get()
            ->filter(function (AutomationRun $run) {
                $meta = $run->metadata ?? [];
                return $run->resume_from_position !== null
                    || ! empty($meta['resume_from_node_id'])
                    || ! empty($meta['waiting_smart_reply_node_id'])
                    || ! empty($meta['waiting_ai_reply_node_id']);
            });

        if ($toResume->isEmpty()) {
            return;
        }

        Log::info('automation processor: resuming delayed runs', [
            'count' => $toResume->count(),
            'run_ids' => $toResume->pluck('id')->toArray(),
        ]);

        foreach ($toResume as $run) {
            $automation = Automation::query()->with(['actions', 'flowNodes', 'flowEdges'])->find($run->automation_id);
            $contact = Contact::query()->find($run->contact_id);
            if (! $automation || ! $contact || (int) $contact->user_id !== (int) $automation->user_id) {
                continue;
            }
            $metadata = $run->metadata ?? [];

            // smart_reply timeout → route to fallback
            $smartReplyNodeId = isset($metadata['waiting_smart_reply_node_id']) ? (int) $metadata['waiting_smart_reply_node_id'] : null;
            if ($smartReplyNodeId > 0) {
                $this->runner->runForContactFromSmartReply($automation, $contact, $run->fresh(), $smartReplyNodeId, 'fallback');
                continue;
            }

            // ai_reply timeout → route to error
            $aiReplyNodeId = isset($metadata['waiting_ai_reply_node_id']) ? (int) $metadata['waiting_ai_reply_node_id'] : null;
            if ($aiReplyNodeId > 0) {
                $this->runner->runForContactFromAiReply($automation, $contact, $run->fresh(), $aiReplyNodeId, '');
                continue;
            }

            $nodeId = isset($metadata['resume_from_node_id']) ? (int) $metadata['resume_from_node_id'] : null;
            if ($nodeId > 0) {
                $this->runner->runForContactFromNode($automation, $contact, $run->fresh(), $nodeId);
                continue;
            }
            $fromPosition = (int) $run->resume_from_position;
            if ($fromPosition >= 0) {
                $this->runner->runForContactFromPosition($automation, $contact, $run->fresh(), $fromPosition);
            }
        }
    }

    /**
     * Processa automações ativas que estão devidas (intervalo já passou).
     */
    private function processDueAutomations(\DateTimeInterface $now): void
    {
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

                $lockKey = 'automation_run_' . $automation->id . '_' . $contact->id;
                $lock = Cache::lock($lockKey, 120);
                if ($lock->get()) {
                    try {
                        $this->runner->runForContact($automation, $contact);
                    } finally {
                        $lock->release();
                    }
                } else {
                    Log::info('automation processor: skipped contact (lock held)', [
                        'automation_id' => $automation->id,
                        'contact_id' => $contact->id,
                    ]);
                }
            }
        }
    }

    /**
     * Contatos que batem com o gatilho e ainda não receberam esta automação.
     * Garante no máximo uma execução por (automação, contato).
     */
    private function contactsMatchingTrigger(Automation $automation, AutomationTrigger $trigger): \Illuminate\Support\Collection
    {
        $alreadyRunIds = AutomationRun::query()
            ->where('automation_id', $automation->id)
            ->pluck('contact_id')
            ->unique()
            ->values();

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
