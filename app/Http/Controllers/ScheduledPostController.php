<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessScheduledPostJob;
use App\Models\Lista;
use App\Models\ScheduledPost;
use App\Models\Tag;
use App\Models\WhatsAppConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Envia imediatamente um post agendado (ainda pendente).
     */
    public function sendNow(ScheduledPost $scheduled_post): RedirectResponse
    {
        $this->authorize('sendNow', $scheduled_post);

        if ($scheduled_post->sent_at !== null) {
            return redirect()
                ->route('automacao.agendamentos.index')
                ->with('error', __('Este post já foi enviado.'));
        }

        try {
            ProcessScheduledPostJob::dispatchSync($scheduled_post->id, true);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('single')->error('ScheduledPost sendNow: falha', [
                'scheduled_post_id' => $scheduled_post->id,
                'message' => $e->getMessage(),
            ]);
            return redirect()
                ->route('automacao.agendamentos.index')
                ->with('error', __('Falha ao enviar: :msg. Verifique a Evolution API e os logs.', ['msg' => $e->getMessage()]));
        }

        return redirect()
            ->route('automacao.agendamentos.index')
            ->with('success', __('Mensagem enviada com sucesso.'));
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
