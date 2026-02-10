<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledPostJob;
use App\Models\ScheduledPost;
use Illuminate\Console\Command;

class ProcessScheduledPostsCommand extends Command
{
    protected $signature = 'scheduled_posts:process';

    protected $description = 'Processa posts agendados cuja data/hora já passou e envia a mensagem (grupo, lista ou tag).';

    public function handle(): int
    {
        $due = ScheduledPost::query()
            ->pending()
            ->get();

        foreach ($due as $post) {
            ProcessScheduledPostJob::dispatch($post->id);
        }

        if ($due->isNotEmpty()) {
            $this->info('Despachados ' . $due->count() . ' post(s) agendado(s).');
        }

        return self::SUCCESS;
    }
}
