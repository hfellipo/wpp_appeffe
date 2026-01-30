<?php

namespace App\Jobs;

use App\Services\EvolutionWebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEvolutionWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $event;
    /** @var array<string,mixed> */
    public array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(string $event, array $data)
    {
        $this->event = $event;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app(EvolutionWebhookProcessor::class)->handle($this->event, $this->data);
    }
}
