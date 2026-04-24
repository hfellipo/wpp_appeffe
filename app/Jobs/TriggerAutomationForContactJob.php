<?php

namespace App\Jobs;

use App\Models\Automation;
use App\Models\Contact;
use App\Services\AutomationRunnerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TriggerAutomationForContactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $automationId,
        public readonly int $contactId,
    ) {}

    public function handle(AutomationRunnerService $runner): void
    {
        $automation = Automation::with(['flowNodes', 'flowEdges', 'actions', 'trigger', 'conditions'])->find($this->automationId);
        $contact    = Contact::find($this->contactId);

        if (! $automation || ! $contact) {
            return;
        }

        if (! $automation->is_active) {
            return;
        }

        Log::info('TriggerAutomationForContactJob: running', [
            'automation_id' => $this->automationId,
            'contact_id'    => $this->contactId,
        ]);

        $runner->runForContact($automation, $contact);
    }
}
