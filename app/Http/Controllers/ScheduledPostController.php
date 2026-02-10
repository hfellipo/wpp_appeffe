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

    public function destroy(ScheduledPost $scheduled_post): RedirectResponse
    {
        $this->authorize('delete', $scheduled_post);

        if ($scheduled_post->sent_at !== null) {
            return redirect()
                ->route('automacao.agendamentos.index')
                ->with('error', __('Não é possível excluir um post já enviado.'));
        }

        $scheduled_post->delete();

        return redirect()
            ->route('automacao.agendamentos.index')
            ->with('success', __('Agendamento cancelado.'));
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
     * Chamada por um cron externo (ex.: cron-job.org) a cada minuto, quando o servidor não tem cron.
     * GET /automacao/agendamentos/cron?token=SEU_TOKEN_DO_ENV
     */
    public function cron(Request $request): JsonResponse
    {
        $token = config('services.scheduled_posts_cron_token');
        if (empty($token) || $request->query('token') !== $token) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        try {
            Artisan::call('scheduled_posts:process');
            $output = trim(Artisan::output());
            return response()->json(['ok' => true, 'message' => $output ?: 'Nenhum post vencido.']);
        } catch (\Throwable $e) {
            Log::channel('single')->error('ScheduledPostController cron: falha', ['message' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
