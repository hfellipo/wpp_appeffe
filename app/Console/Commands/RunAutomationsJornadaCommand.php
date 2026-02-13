<?php

namespace App\Console\Commands;

use App\Services\AutomationProcessorService;
use Illuminate\Console\Command;

/**
 * Cron dedicado para automação jornada (por URL ou schedule).
 * Executa o mesmo fluxo completo que automations:run: retoma runs em delay
 * e processa automações devidas (gatilho → condições → ações).
 * Assim, configurar apenas a URL /automacao/jornada/cron é suficiente.
 */
class RunAutomationsJornadaCommand extends Command
{
    protected $signature = 'automations:run-jornada';

    protected $description = 'Automação jornada: mesmo fluxo do automations:run (retoma delays + processa gatilhos e ações).';

    public function handle(AutomationProcessorService $processor): int
    {
        $processor->process();

        return self::SUCCESS;
    }
}
