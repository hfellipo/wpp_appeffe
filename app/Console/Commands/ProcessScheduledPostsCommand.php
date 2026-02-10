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
            try {
                ProcessScheduledPostJob::dispatchSync($post->id);
            } catch (\Throwable $e) {
                $this->error("Post #{$post->id}: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::channel('single')->error('scheduled_posts:process falha', [
                    'scheduled_post_id' => $post->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($due->isNotEmpty()) {
            $this->info('Processados ' . $due->count() . ' post(s) agendado(s).');
        }

        return self::SUCCESS;
    }
}
