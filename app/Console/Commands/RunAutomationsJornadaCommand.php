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
 * Apenas retoma runs pausados no "Aguardar (delay)".
 * A descoberta de contatos e envio inicial é feita somente pelo automations:run,
 * para evitar envio duplicado quando dois crons rodam no mesmo ciclo.
 *
 * Exemplo no crontab:
 *   * * * * * php /path/to/artisan automations:run-jornada
 * Ou via URL: GET /automacao/jornada/cron?token=...
 */
class RunAutomationsJornadaCommand extends Command
{
    protected $signature = 'automations:run-jornada';

    protected $description = 'Cron de automação jornada: apenas retoma runs em delay (evita duplicar envio com automations:run).';

    public function handle(AutomationRunnerService $runner): int
    {
        $now = now();

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

        return self::SUCCESS;
    }
}
