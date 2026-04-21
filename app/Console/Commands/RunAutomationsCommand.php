<?php

namespace App\Console\Commands;

use App\Services\AutomationProcessorService;
use App\Services\FunnelDisparoService;
use Illuminate\Console\Command;

class RunAutomationsCommand extends Command
{
    protected $signature = 'automations:run';

    protected $description = 'Processa automações e disparos de funil pendentes.';

    public function handle(AutomationProcessorService $processor, FunnelDisparoService $disparoService): int
    {
        $processor->process();
        $disparoService->processPending();

        return self::SUCCESS;
    }
}
