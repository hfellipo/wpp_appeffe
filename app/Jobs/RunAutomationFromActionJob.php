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
 * Executa o próximo nó do fluxo (estilo n8n) na hora exata de resume_at.
 * Disparado pelo AutomationRunnerService quando encontra um nó "Aguardar (delay)".
 * Se no meio do fluxo houver outro delay, reenvia este job para o próximo resume_at.
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

        $runner->runForContactFromPosition($automation, $contact, $run, $this->startFromPosition);
        // Se houver outro wait_delay no fluxo, o AutomationRunnerService já dispara o próximo job em resume_at.
    }
}
