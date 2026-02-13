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
 * Executa o fluxo a partir de um nó (editor drag-and-drop).
 * Usado quando um nó "Aguardar (delay)" agenda a continuação.
 */
class RunAutomationFromNodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $automationId,
        public int $contactId,
        public int $automationRunId,
        public int $nodeId
    ) {
    }

    public function handle(AutomationRunnerService $runner): void
    {
        $automation = Automation::query()->with(['flowNodes', 'flowEdges'])->find($this->automationId);
        $contact = Contact::query()->find($this->contactId);
        $run = AutomationRun::query()->find($this->automationRunId);

        if (! $automation || ! $contact || ! $run) {
            return;
        }
        if ((int) $contact->user_id !== (int) $automation->user_id) {
            return;
        }

        $runner->runForContactFromNode($automation, $contact, $run, $this->nodeId);
    }
}
