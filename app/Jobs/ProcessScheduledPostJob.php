<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Lista;
use App\Models\ScheduledPost;
use App\Models\Tag;
use App\Models\WhatsAppConversation;
use App\Services\WhatsAppSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $scheduledPostId,
        public bool $forceImmediate = false
    ) {}

    public function handle(WhatsAppSendService $sendService): void
    {
        $post = ScheduledPost::query()->find($this->scheduledPostId);
        if (! $post) {
            return;
        }
        if ($post->sent_at !== null) {
            return;
        }
        if (! $this->forceImmediate && $post->scheduled_at->isFuture()) {
            return;
        }

        $accountId = (int) $post->user_id;
        $message = trim((string) $post->message);
        if ($message === '') {
            Log::channel('single')->warning('ProcessScheduledPostJob: mensagem vazia', ['scheduled_post_id' => $post->id]);
            $post->update(['sent_at' => now()]);
            return;
        }

        try {
            if ($post->target_type === 'group') {
                $conversation = WhatsAppConversation::query()
                    ->where('user_id', $accountId)
                    ->where('kind', 'group')
                    ->where('id', $post->target_id)
                    ->first();
                if (! $conversation) {
                    Log::channel('single')->warning('ProcessScheduledPostJob: conversa de grupo não encontrada', [
                        'scheduled_post_id' => $post->id,
                        'target_id' => $post->target_id,
                    ]);
                    throw new \RuntimeException(__('Grupo não encontrado. Verifique se a conversa ainda existe no WhatsApp.'));
                }
                $sent = $sendService->sendTextToConversation($conversation, $message, null);
                if (! $sent) {
                    throw new \RuntimeException(__('Evolution API não enviou a mensagem. Verifique a instância e os logs.'));
                }
            } elseif ($post->target_type === 'list') {
                $lista = Lista::forUser($accountId)->find($post->target_id);
                if (! $lista) {
                    throw new \RuntimeException(__('Lista não encontrada.'));
                }
                $contacts = $lista->contacts()->get();
                foreach ($contacts as $contact) {
                    $sendService->sendTextToContact($accountId, $contact, $message, null);
                }
            } elseif ($post->target_type === 'tag') {
                $tag = Tag::forUser($accountId)->find($post->target_id);
                if (! $tag) {
                    throw new \RuntimeException(__('Tag não encontrada.'));
                }
                $contacts = $tag->contacts()->get();
                foreach ($contacts as $contact) {
                    $sendService->sendTextToContact($accountId, $contact, $message, null);
                }
            }

            $post->update(['sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('ProcessScheduledPostJob: falha ao enviar', [
                'scheduled_post_id' => $post->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
