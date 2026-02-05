<?php

namespace App\Jobs;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\AutomationRun;
use App\Services\AutomationRunnerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Executa as ações de uma automação a partir de uma posição (ex.: após um delay).
 * Ao encontrar "Aguardar (delay)", reenvia o job com atraso para continuar depois.
 */
class RunAutomationFromActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $automationId;
    public int $contactId;
    public int $automationRunId;
    public int $startFromPosition;

    public function __construct(int $automationId, int $contactId, int $automationRunId, int $startFromPosition)
    {
        $this->automationId = $automationId;
        $this->contactId = $contactId;
        $this->automationRunId = $automationRunId;
        $this->startFromPosition = $startFromPosition;
    }

    public function handle(AutomationRunnerService $runner): void
    {
        $automation = Automation::query()->with('actions')->find($this->automationId);
        $contact = Contact::query()->find($this->contactId);
        $run = AutomationRun::query()->find($this->automationRunId);

        if (! $automation || ! $contact || ! $run) {
            return;
        }
        if ((int) $contact->user_id !== (int) $automation->user_id) {
            return;
        }

        $result = $runner->runForContactFromPosition($automation, $contact, $run, $this->startFromPosition);

        if ($result['done'] ?? true) {
            return;
        }

        $delayMinutes = (int) ($result['delay_minutes'] ?? 0);
        $nextPosition = (int) ($result['next_position'] ?? 0);
        if ($delayMinutes <= 0 || $nextPosition <= 0) {
            return;
        }

        self::dispatch($this->automationId, $this->contactId, $this->automationRunId, $nextPosition)
            ->delay(now()->addMinutes($delayMinutes));
    }
}
