<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessScheduledPostJob;
use App\Models\Contact;
use App\Models\FunnelStage;
use App\Models\Lista;
use App\Models\ScheduledPost;
use App\Models\Tag;
use App\Models\WhatsAppConversation;
use App\Services\WhatsAppSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ScheduledPostController extends Controller
{
    public function index(Request $request): View
    {
        // Processa posts agendados vencidos (sem depender de cron ou fila)
        $processed = 0;
        $due = ScheduledPost::query()->pending()->get();
        foreach ($due as $post) {
            try {
                ProcessScheduledPostJob::dispatchSync($post->id);
                $processed++;
            } catch (\Throwable $e) {
                Log::channel('single')->error('ScheduledPostController: falha ao processar post agendado', [
                    'scheduled_post_id' => $post->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
        if ($processed > 0) {
            $request->session()->flash('success', $processed === 1
                ? __('1 post agendado enviado.')
                : __(':count posts agendados enviados.', ['count' => $processed]));
        }

        $posts = ScheduledPost::forUser(auth()->user()->accountId())
            ->orderByDesc('scheduled_at')
            ->paginate(15);

        return view('automacao.agendamentos.index', compact('posts'));
    }

    public function create(): View
    {
        $accountId = auth()->user()->accountId();
        $groups = WhatsAppConversation::query()
            ->where('user_id', $accountId)
            ->where('kind', 'group')
            ->orderByRaw('COALESCE(custom_contact_name, contact_name) ASC')
            ->get(['id', 'contact_name', 'custom_contact_name', 'peer_jid']);
        $listas = Lista::forUser($accountId)->orderBy('name')->get(['id', 'name']);
        $tags = Tag::forUser($accountId)->orderBy('name')->get(['id', 'name', 'color']);

        return view('automacao.agendamentos.create', [
            'groups' => $groups,
            'listas' => $listas,
            'tags' => $tags,
            'targetTypes' => ScheduledPost::targetTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = auth()->user()->accountId();

        $validated = $request->validate([
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'scheduled_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'target_type' => ['required', 'string', 'in:group,list,tag'],
            'target_group_id' => ['nullable', 'required_if:target_type,group', 'integer', 'min:1'],
            'target_list_id' => ['nullable', 'required_if:target_type,list', 'integer', 'min:1'],
            'target_tag_id' => ['nullable', 'required_if:target_type,tag', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:65535'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $targetId = match ($validated['target_type']) {
            'group' => (int) ($validated['target_group_id'] ?? 0),
            'list' => (int) ($validated['target_list_id'] ?? 0),
            'tag' => (int) ($validated['target_tag_id'] ?? 0),
            default => 0,
        };
        if ($targetId < 1) {
            return back()->withInput()->withErrors(['target_id' => __('Selecione o destino.')]);
        }

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_date'] . ' ' . $validated['scheduled_time'] . ':00', $tz);
        if ($scheduledAt->isPast()) {
            return back()->withInput()->withErrors(['scheduled_time' => __('A data e hora devem ser no futuro.')]);
        }

        if ($validated['target_type'] === 'group') {
            $exists = WhatsAppConversation::query()
                ->where('user_id', $accountId)
                ->where('kind', 'group')
                ->where('id', $targetId)
                ->exists();
            if (! $exists) {
                return back()->withInput()->withErrors(['target_id' => __('Grupo inválido.')]);
            }
        }
        if ($validated['target_type'] === 'list') {
            if (! Lista::forUser($accountId)->where('id', $targetId)->exists()) {
                return back()->withInput()->withErrors(['target_id' => __('Lista inválida.')]);
            }
        }
        if ($validated['target_type'] === 'tag') {
            if (! Tag::forUser($accountId)->where('id', $targetId)->exists()) {
                return back()->withInput()->withErrors(['target_id' => __('Tag inválida.')]);
            }
        }

        $message = trim((string) ($validated['message'] ?? ''));
        if ($message === '' && ! $request->hasFile('image')) {
            return back()->withInput()->withErrors(['message' => __('Informe a mensagem (legenda) ou envie uma imagem.')]);
        }

        $imagePath = null;
        $imageMime = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $dir = 'scheduled_posts/' . now()->format('Y/m');
            $name = Str::ulid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName() ?: 'image.jpg');
            $imagePath = $file->storeAs($dir, $name, ['disk' => 'local']);
            $imageMime = $file->getMimeType() ?: 'image/jpeg';
        }

        ScheduledPost::create([
            'user_id' => $accountId,
            'scheduled_at' => $scheduledAt,
            'target_type' => $validated['target_type'],
            'target_id' => $targetId,
            'message' => $message,
            'image_path' => $imagePath,
            'image_mime' => $imageMime,
        ]);

        return redirect()
            ->route('automacao.agendamentos.index')
            ->with('success', __('Post agendado com sucesso.'));
    }

    public function edit(ScheduledPost $scheduled_post): View|RedirectResponse
    {
        $this->authorize('update', $scheduled_post);
        if ($scheduled_post->sent_at !== null) {
            return redirect()
                ->route('automacao.agendamentos.index')
                ->with('error', __('Só é possível reconfigurar posts ainda não enviados. Use Duplicar para aproveitar as configurações.'));
        }

        $accountId = auth()->user()->accountId();
        $groups = WhatsAppConversation::query()
            ->where('user_id', $accountId)
            ->where('kind', 'group')
            ->orderByRaw('COALESCE(custom_contact_name, contact_name) ASC')
            ->get(['id', 'contact_name', 'custom_contact_name', 'peer_jid']);
        $listas = Lista::forUser($accountId)->orderBy('name')->get(['id', 'name']);
        $tags = Tag::forUser($accountId)->orderBy('name')->get(['id', 'name', 'color']);

        return view('automacao.agendamentos.edit', [
            'post' => $scheduled_post,
            'groups' => $groups,
            'listas' => $listas,
            'tags' => $tags,
            'targetTypes' => ScheduledPost::targetTypes(),
        ]);
    }

    public function update(Request $request, ScheduledPost $scheduled_post): RedirectResponse
    {
        $this->authorize('update', $scheduled_post);
        if ($scheduled_post->sent_at !== null) {
            return redirect()->route('automacao.agendamentos.index')->with('error', __('Post já enviado.'));
        }

        $accountId = auth()->user()->accountId();
        $validated = $request->validate([
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'target_type' => ['required', 'string', 'in:group,list,tag'],
            'target_group_id' => ['nullable', 'required_if:target_type,group', 'integer', 'min:1'],
            'target_list_id' => ['nullable', 'required_if:target_type,list', 'integer', 'min:1'],
            'target_tag_id' => ['nullable', 'required_if:target_type,tag', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:65535'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $targetId = match ($validated['target_type']) {
            'group' => (int) ($validated['target_group_id'] ?? 0),
            'list' => (int) ($validated['target_list_id'] ?? 0),
            'tag' => (int) ($validated['target_tag_id'] ?? 0),
            default => 0,
        };
        if ($targetId < 1) {
            return back()->withInput()->withErrors(['target_id' => __('Selecione o destino.')]);
        }

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_date'] . ' ' . $validated['scheduled_time'] . ':00', $tz);
        if ($scheduledAt->isPast()) {
            return back()->withInput()->withErrors(['scheduled_time' => __('A data e hora devem ser no futuro.')]);
        }

        if ($validated['target_type'] === 'group') {
            if (! WhatsAppConversation::query()->where('user_id', $accountId)->where('kind', 'group')->where('id', $targetId)->exists()) {
                return back()->withInput()->withErrors(['target_id' => __('Grupo inválido.')]);
            }
        }
        if ($validated['target_type'] === 'list') {
            if (! Lista::forUser($accountId)->where('id', $targetId)->exists()) {
                return back()->withInput()->withErrors(['target_id' => __('Lista inválida.')]);
            }
        }
        if ($validated['target_type'] === 'tag') {
            if (! Tag::forUser($accountId)->where('id', $targetId)->exists()) {
                return back()->withInput()->withErrors(['target_id' => __('Tag inválida.')]);
            }
        }

        $message = trim((string) ($validated['message'] ?? ''));
        $imagePath = $scheduled_post->image_path;
        $imageMime = $scheduled_post->image_mime;
        if ($message === '' && ! $request->hasFile('image') && ! $imagePath) {
            return back()->withInput()->withErrors(['message' => __('Informe a mensagem ou envie uma imagem.')]);
        }
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $dir = 'scheduled_posts/' . now()->format('Y/m');
            $name = Str::ulid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName() ?: 'image.jpg');
            $imagePath = $file->storeAs($dir, $name, ['disk' => 'local']);
            $imageMime = $file->getMimeType() ?: 'image/jpeg';
        }

        $scheduled_post->update([
            'scheduled_at' => $scheduledAt,
            'target_type' => $validated['target_type'],
            'target_id' => $targetId,
            'message' => $message,
            'image_path' => $imagePath,
            'image_mime' => $imageMime,
        ]);

        return redirect()
            ->route('automacao.agendamentos.index')
            ->with('success', __('Post reconfigurado com sucesso.'));
    }

    public function duplicate(ScheduledPost $scheduled_post): RedirectResponse
    {
        $this->authorize('view', $scheduled_post);
        $accountId = auth()->user()->accountId();
        if ((int) $scheduled_post->user_id !== (int) $accountId) {
            abort(403);
        }

        $scheduledAt = now(config('app.timezone'))->addDay();
        if ($scheduled_post->scheduled_at) {
            $scheduledAt = $scheduled_post->scheduled_at->copy()->addDay();
            if ($scheduledAt->isPast()) {
                $scheduledAt = now(config('app.timezone'))->addHour();
            }
        }

        $imagePath = null;
        $imageMime = $scheduled_post->image_mime;
        if ($scheduled_post->image_path && Storage::disk('local')->exists($scheduled_post->image_path)) {
            $dir = 'scheduled_posts/' . now()->format('Y/m');
            $name = Str::ulid() . '_' . basename($scheduled_post->image_path);
            $newPath = $dir . '/' . $name;
            Storage::disk('local')->copy($scheduled_post->image_path, $newPath);
            $imagePath = $newPath;
        }

        $newPost = ScheduledPost::create([
            'user_id' => $accountId,
            'scheduled_at' => $scheduledAt,
            'target_type' => $scheduled_post->target_type,
            'target_id' => $scheduled_post->target_id,
            'message' => $scheduled_post->message,
            'image_path' => $imagePath,
            'image_mime' => $imageMime,
        ]);

        return redirect()
            ->route('automacao.agendamentos.edit', $newPost)
            ->with('success', __('Post duplicado. Ajuste a data/hora e salve.'));
    }

    public function destroy(ScheduledPost $scheduled_post): RedirectResponse
    {
        $this->authorize('delete', $scheduled_post);

        $scheduled_post->delete();

        return redirect()
            ->route('automacao.agendamentos.index')
            ->with('success', $scheduled_post->sent_at ? __('Post removido da lista.') : __('Agendamento cancelado.'));
    }

    /**
     * Página de envio em tempo real: mostra progresso (qual contato está recebendo) e resumo final.
     */
    public function enviando(ScheduledPost $scheduled_post): View|RedirectResponse
    {
        $this->authorize('sendNow', $scheduled_post);

        if ($scheduled_post->sent_at !== null) {
            return redirect()
                ->route('automacao.agendamentos.index')
                ->with('error', __('Este post já foi enviado.'));
        }

        return view('automacao.agendamentos.enviando', ['post' => $scheduled_post]);
    }

    /**
     * Stream SSE: envia o post e emite eventos de progresso (contato atual, %) e resultado (enviado/falha).
     */
    public function sendNowStream(ScheduledPost $scheduled_post, WhatsAppSendService $sendService): StreamedResponse
    {
        $this->authorize('sendNow', $scheduled_post);

        $accountId = (int) $scheduled_post->user_id;
        $message = trim((string) $scheduled_post->message);
        $hasImage = ! empty($scheduled_post->image_path);
        $sendMedia = $hasImage && Storage::disk('local')->exists($scheduled_post->image_path);
        $mimeType = $sendMedia ? ($scheduled_post->image_mime ?: 'image/jpeg') : null;

        $sendEvent = function (array $data) {
            echo 'data: ' . json_encode($data) . "\n\n";
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        };

        return response()->stream(function () use ($scheduled_post, $sendService, $accountId, $message, $sendMedia, $mimeType, $sendEvent) {
            if ($scheduled_post->sent_at !== null) {
                $sendEvent(['type' => 'error', 'message' => __('Este post já foi enviado.')]);
                return;
            }
            if (! $sendMedia && $message === '') {
                $sendEvent(['type' => 'error', 'message' => __('Mensagem e imagem vazios.')]);
                return;
            }

            $results = [];
            $total = 0;
            $current = 0;

            try {
                if ($scheduled_post->target_type === 'group') {
                    $conversation = WhatsAppConversation::query()
                        ->where('user_id', $accountId)
                        ->where('kind', 'group')
                        ->where('id', $scheduled_post->target_id)
                        ->first();
                    if (! $conversation) {
                        $sendEvent(['type' => 'error', 'message' => __('Grupo não encontrado.')]);
                        return;
                    }
                    $label = trim($conversation->custom_contact_name ?? '') ?: trim($conversation->contact_name ?? '') ?: $conversation->peer_jid;
                    $total = 1;
                    $sendEvent(['type' => 'progress', 'contact_name' => $label, 'percent' => 0, 'current' => 0, 'total' => 1]);
                    if ($sendMedia) {
                        $sent = $sendService->sendMediaToConversation($conversation, $scheduled_post->image_path, $mimeType, $message, null, 'scheduled_post', $scheduled_post->id);
                    } else {
                        $sent = $sendService->sendTextToConversation($conversation, $message, null, 'scheduled_post', $scheduled_post->id);
                    }
                    $sendEvent(['type' => 'result', 'contact_name' => $label, 'status' => $sent ? 'sent' : 'failed']);
                    $results[] = ['name' => $label, 'status' => $sent ? 'sent' : 'failed'];
                    $sendEvent(['type' => 'progress', 'contact_name' => $label, 'percent' => 100, 'current' => 1, 'total' => 1]);
                } else {
                    $contacts = collect();
                    if ($scheduled_post->target_type === 'list') {
                        $lista = Lista::forUser($accountId)->find($scheduled_post->target_id);
                        if (! $lista) {
                            $sendEvent(['type' => 'error', 'message' => __('Lista não encontrada.')]);
                            return;
                        }
                        $contacts = $lista->contacts()->get();
                    } elseif ($scheduled_post->target_type === 'tag') {
                        $tag = Tag::forUser($accountId)->find($scheduled_post->target_id);
                        if (! $tag) {
                            $sendEvent(['type' => 'error', 'message' => __('Tag não encontrada.')]);
                            return;
                        }
                        $contacts = $tag->contacts()->get();
                    } elseif ($scheduled_post->target_type === 'funnel_stage') {
                        $stage = FunnelStage::query()->find($scheduled_post->target_id);
                        if (! $stage) {
                            $sendEvent(['type' => 'error', 'message' => __('Coluna do funil não encontrada.')]);
                            return;
                        }
                        $contactIds = $stage->leads()->whereNotNull('contact_id')->pluck('contact_id')->unique()->filter();
                        $contacts = Contact::forUser($accountId)->whereIn('id', $contactIds)->get();
                    }
                    $total = $contacts->count();
                    if ($total === 0) {
                        $sendEvent(['type' => 'done', 'summary' => ['sent' => 0, 'failed' => 0, 'results' => []], 'message' => __('Nenhum contato na lista/tag/coluna.')]);
                        return;
                    }
                    foreach ($contacts as $index => $contact) {
                        $current = $index + 1;
                        $name = $contact->name ?: $contact->phone ?: ('#' . $contact->id);
                        $percent = (int) round(($current / $total) * 100);
                        $sendEvent(['type' => 'progress', 'contact_name' => $name, 'percent' => $percent, 'current' => $current, 'total' => $total]);
                        $sent = false;
                        if ($sendMedia) {
                            $sent = $sendService->sendMediaToContact($accountId, $contact, $scheduled_post->image_path, $mimeType, $message, null, 'scheduled_post', $scheduled_post->id) !== null;
                        } else {
                            $sent = $sendService->sendTextToContact($accountId, $contact, $message, null, 'scheduled_post', $scheduled_post->id) !== null;
                        }
                        $status = $sent ? 'sent' : 'failed';
                        $sendEvent(['type' => 'result', 'contact_name' => $name, 'status' => $status]);
                        $results[] = ['name' => $name, 'status' => $status];
                    }
                }

                $scheduled_post->update(['sent_at' => now()]);

                $sentCount = collect($results)->where('status', 'sent')->count();
                $failedCount = collect($results)->where('status', 'failed')->count();
                $deliveredCount = \App\Models\WhatsAppMessage::query()
                    ->where('source_type', 'scheduled_post')
                    ->where('source_id', $scheduled_post->id)
                    ->whereNotNull('delivered_at')
                    ->count();
                $readCount = \App\Models\WhatsAppMessage::query()
                    ->where('source_type', 'scheduled_post')
                    ->where('source_id', $scheduled_post->id)
                    ->whereNotNull('read_at')
                    ->count();

                $sendEvent([
                    'type' => 'done',
                    'summary' => [
                        'sent' => $sentCount,
                        'failed' => $failedCount,
                        'total' => count($results),
                        'delivered' => $deliveredCount,
                        'read' => $readCount,
                        'results' => $results,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::channel('single')->error('ScheduledPost sendNowStream: falha', [
                    'scheduled_post_id' => $scheduled_post->id,
                    'message' => $e->getMessage(),
                ]);
                $sendEvent(['type' => 'error', 'message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Envia imediatamente um post agendado (redireciona para a página de progresso).
     */
    public function sendNow(ScheduledPost $scheduled_post): RedirectResponse
    {
        $this->authorize('sendNow', $scheduled_post);

        if ($scheduled_post->sent_at !== null) {
            return redirect()
                ->route('automacao.agendamentos.index')
                ->with('error', __('Este post já foi enviado.'));
        }

        return redirect()->route('automacao.agendamentos.enviando', $scheduled_post);
    }

    /**
     * Rota de cron por URL: processa posts agendados vencidos.
     * Configure o cron externo para chamar esta URL a cada 1 minuto.
     * GET /automacao/agendamentos/cron?token=SEU_TOKEN_DO_ENV
     *
     * Regra: qualquer post com scheduled_at <= agora (no fuso do app) e sent_at = null é enviado.
     */
    public function cron(Request $request): JsonResponse
    {
        $token = config('services.scheduled_posts_cron_token');
        if (empty($token)) {
            return response()->json([
                'ok' => false,
                'error' => 'SCHEDULED_POSTS_CRON_TOKEN não configurado no .env',
            ], 503);
        }
        if ($request->query('token') !== $token) {
            return response()->json(['ok' => false, 'error' => 'Token inválido'], 403);
        }

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $now = \Carbon\Carbon::now($tz);
        $processed = 0;
        $errors = [];

        try {
            $due = ScheduledPost::query()
                ->whereNull('sent_at')
                ->where('scheduled_at', '<=', $now)
                ->orderBy('scheduled_at')
                ->get();

            foreach ($due as $post) {
                try {
                    ProcessScheduledPostJob::dispatchSync($post->id);
                    $processed++;
                } catch (\Throwable $e) {
                    Log::channel('single')->error('ScheduledPostController cron: falha em post', [
                        'scheduled_post_id' => $post->id,
                        'message' => $e->getMessage(),
                    ]);
                    $errors[] = 'Post #' . $post->id . ': ' . $e->getMessage();
                }
            }

            $nextPending = ScheduledPost::query()
                ->whereNull('sent_at')
                ->where('scheduled_at', '>', $now)
                ->orderBy('scheduled_at')
                ->first(['scheduled_at']);

            return response()->json([
                'ok' => true,
                'processed' => $processed,
                'server_time' => $now->format('Y-m-d H:i:s'),
                'timezone' => $tz,
                'next_scheduled_at' => $nextPending?->scheduled_at?->format('Y-m-d H:i:s'),
                'message' => $processed > 0
                    ? $processed . ' post(s) enviado(s).'
                    : ($nextPending ? 'Nenhum vencido. Próximo: ' . $nextPending->scheduled_at->format('d/m/Y H:i') : 'Nenhum post agendado.'),
                'errors' => $errors ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('ScheduledPostController cron: falha', ['message' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'server_time' => $now->format('Y-m-d H:i:s'),
                'timezone' => $tz,
            ], 500);
        }
    }
}
