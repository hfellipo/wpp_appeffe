<?php

namespace App\Console\Commands;

use App\Services\AutomationProcessorService;
use Illuminate\Console\Command;

class RunAutomationsCommand extends Command
{
    protected $signature = 'automations:run';

    protected $description = 'Processa automações: retoma jornadas em delay e executa gatilhos (tag/lista) com condições e ações (estilo BotConversa).';

    public function handle(AutomationProcessorService $processor): int
    {
        $processor->process();

        return self::SUCCESS;
    }
}
