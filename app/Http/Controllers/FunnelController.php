<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\Funnel;
use App\Models\FunnelDisparo;
use App\Models\FunnelLead;
use App\Models\FunnelStage;
use App\Models\FunnelStageRule;
use App\Models\Lista;
use App\Models\ScheduledPost;
use App\Models\Tag;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\AutomationRunnerService;
use App\Services\FunnelDisparoService;
use App\Services\WhatsAppSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FunnelController extends Controller
{
    public function index(): View
    {
        $funnels = Funnel::forUser(auth()->user()->accountId())
            ->withCount('leads')
            ->withSum('leads', 'value')
            ->orderBy('updated_at', 'desc')
            ->paginate(12);

        return view('funis.index', compact('funnels'));
    }

    public function create(): View
    {
        return view('funis.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $funnel = Funnel::create([
            'user_id' => auth()->user()->accountId(),
            'name' => $validated['name'],
        ]);

        foreach (Funnel::defaultStages() as $stage) {
            FunnelStage::create([
                'funnel_id' => $funnel->id,
                'name' => $stage['name'],
                'position' => $stage['position'],
                'color' => $stage['color'],
            ]);
        }

        return redirect()
            ->route('funis.show', $funnel)
            ->with('success', __('Funil criado. Use "Novo lead" para adicionar cards ao quadro.'));
    }

    public function show(Funnel $funnel): View|RedirectResponse
    {
        $this->authorize('view', $funnel);

        $funnel->load(['stages.leads' => fn ($q) => $q->with(['contact.listas', 'contact.tags'])->orderBy('position'), 'stages.automation' => fn ($q) => $q->with(['trigger', 'actions']), 'stages.stageRules' => fn ($q) => $q->with('targetStage')]);
        $funnel->loadCount('leads')->loadSum('leads', 'value');
        $userId = auth()->user()->accountId();
        $contacts = Contact::forUser($userId)->orderBy('name')->get(['id', 'name', 'phone']);
        $listas = Lista::forUser($userId)->orderBy('name')->get(['id', 'name']);
        $tags = Tag::forUser($userId)->orderBy('name')->get(['id', 'name']);
        $automations = Automation::forUser($userId)->orderBy('name')->get(['id', 'name', 'is_active']);

        $stageMessageStatus = [];
        foreach ($funnel->stages as $stage) {
            $stageMessageStatus[$stage->id] = $this->getStageMessageStatusMap($stage);
        }

        $contactIds = $funnel->stages->pluck('leads')->flatten()->pluck('contact_id')->filter()->unique()->values()->all();
        $contactConversations = [];
        if (! empty($contactIds)) {
            $convs = WhatsAppConversation::query()
                ->where('user_id', $userId)
                ->whereIn('contact_id', $contactIds)
                ->get(['contact_id', 'public_id']);
            foreach ($convs as $c) {
                $contactConversations[$c->contact_id] = $c->public_id;
            }
        }

        return view('funis.show', compact('funnel', 'contacts', 'listas', 'tags', 'automations', 'stageMessageStatus', 'contactConversations'));
    }

    /**
     * Map contact_id => message status (responded|read|delivered|sent|failed) for the last automation/funnel message per contact in this stage.
     */
    private function getStageMessageStatusMap(FunnelStage $stage): array
    {
        $runIds = AutomationRun::where('automation_id', $stage->automation_id)->pluck('id')->toArray();

        $messages = WhatsAppMessage::query()
            ->where('direction', 'out')
            ->where(function ($q) use ($stage, $runIds) {
                $q->where(function ($q2) use ($stage) {
                    $q2->where('source_type', 'funnel_stage')->where('source_id', $stage->id);
                });
                if (! empty($runIds)) {
                    $q->orWhereIn('automation_run_id', $runIds);
                }
            })
            ->whereHas('conversation', fn ($q) => $q->whereNotNull('contact_id'))
            ->with(['conversation:id,contact_id', 'replies:id,in_reply_to_message_id'])
            ->orderByDesc('id')
            ->get();

        $byContact = [];
        foreach ($messages as $msg) {
            $cid = $msg->conversation->contact_id ?? null;
            if ($cid === null) {
                continue;
            }
            if (isset($byContact[$cid])) {
                continue;
            }
            $byContact[$cid] = $msg->funnelDisplayStatus();
        }

        return $byContact;
    }

    public function edit(Funnel $funnel): View
    {
        $this->authorize('update', $funnel);
        return view('funis.edit', compact('funnel'));
    }

    public function update(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorize('update', $funnel);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $funnel->update(['name' => $validated['name']]);

        return redirect()
            ->route('funis.index')
            ->with('success', __('Funil atualizado.'));
    }

    public function destroy(Funnel $funnel): RedirectResponse
    {
        $this->authorize('delete', $funnel);
        $funnel->delete();
        return redirect()
            ->route('funis.index')
            ->with('success', __('Funil excluído.'));
    }

    public function storeLead(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorize('update', $funnel);

        $validated = $request->validate([
            'funnel_stage_id' => ['required', 'integer', 'exists:funnel_stages,id'],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ]);

        $stage = FunnelStage::where('funnel_id', $funnel->id)->findOrFail($validated['funnel_stage_id']);
        $maxPos = $funnel->leads()->where('funnel_stage_id', $stage->id)->max('position') ?? 0;

        FunnelLead::create([
            'funnel_id' => $funnel->id,
            'funnel_stage_id' => $stage->id,
            'contact_id' => $validated['contact_id'] ?? null,
            'name' => $validated['name'],
            'title' => $validated['title'] ?? null,
            'value' => $validated['value'] ?? 0,
            'position' => $maxPos + 1,
        ]);

        return back()->with('success', __('Lead adicionado.'));
    }

    public function storeLeadsBulk(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorize('update', $funnel);

        $validated = $request->validate([
            'funnel_stage_id' => ['required', 'integer', 'exists:funnel_stages,id'],
            'list_ids' => ['nullable', 'array'],
            'list_ids.*' => ['integer', 'exists:listas,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'logic' => ['nullable', 'in:and,or'],
        ]);

        $listIds = array_filter($validated['list_ids'] ?? []);
        $tagIds = array_filter($validated['tag_ids'] ?? []);
        $logic = $validated['logic'] ?? 'or';

        if (empty($listIds) && empty($tagIds)) {
            return back()->with('error', __('Selecione ao menos uma lista ou uma tag.'));
        }

        $userId = auth()->user()->accountId();
        $stage = FunnelStage::where('funnel_id', $funnel->id)->findOrFail($validated['funnel_stage_id']);

        $query = Contact::forUser($userId);

        if (! empty($listIds) && ! empty($tagIds)) {
            if ($logic === 'and') {
                $query->whereHas('listas', fn ($q) => $q->whereIn('listas.id', $listIds))
                    ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
            } else {
                $query->where(function ($q) use ($listIds, $tagIds) {
                    $q->whereHas('listas', fn ($q) => $q->whereIn('listas.id', $listIds))
                        ->orWhereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
                });
            }
        } elseif (! empty($listIds)) {
            $query->whereHas('listas', fn ($q) => $q->whereIn('listas.id', $listIds));
        } else {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
        }

        $contacts = $query->get(['id', 'name', 'user_id']);
        $maxPos = $funnel->leads()->where('funnel_stage_id', $stage->id)->max('position') ?? 0;
        $added = 0;
        $seenIds = [];

        foreach ($contacts as $contact) {
            if (isset($seenIds[$contact->id])) {
                continue;
            }
            $seenIds[$contact->id] = true;
            FunnelLead::create([
                'funnel_id' => $funnel->id,
                'funnel_stage_id' => $stage->id,
                'contact_id' => $contact->id,
                'name' => $contact->name,
                'title' => null,
                'value' => 0,
                'position' => ++$maxPos,
            ]);
            $added++;
        }

        return back()->with('success', $added > 0 ? __(':count lead(s) adicionado(s).', ['count' => $added]) : __('Nenhum contato encontrado com os critérios.'));
    }

    public function moveLead(Request $request, Funnel $funnel, FunnelLead $lead): RedirectResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $lead->funnel_id !== (int) $funnel->id) {
            abort(404);
        }

        $validated = $request->validate([
            'funnel_stage_id' => ['required', 'integer', 'exists:funnel_stages,id'],
        ]);

        $newStage = FunnelStage::where('funnel_id', $funnel->id)->findOrFail($validated['funnel_stage_id']);
        $maxPos = $funnel->leads()->where('funnel_stage_id', $newStage->id)->max('position') ?? 0;

        $lead->update([
            'funnel_stage_id' => $newStage->id,
            'position' => $maxPos + 1,
        ]);

        return back()->with('success', __('Lead movido.'));
    }

    public function destroyLead(Funnel $funnel, FunnelLead $lead): RedirectResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $lead->funnel_id !== (int) $funnel->id) {
            abort(404);
        }
        $lead->delete();
        return back()->with('success', __('Lead removido.'));
    }

    public function updateLead(Request $request, Funnel $funnel, FunnelLead $lead): RedirectResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $lead->funnel_id !== (int) $funnel->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ]);

        $lead->update([
            'name' => $validated['name'],
            'title' => $validated['title'] ?? null,
            'value' => $validated['value'] ?? 0,
            'contact_id' => $validated['contact_id'] ?? null,
        ]);

        return back()->with('success', __('Lead atualizado.'));
    }

    public function storeStage(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorize('update', $funnel);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'in:yellow,purple,green,blue,gray,red,indigo'],
        ]);

        $maxPosition = $funnel->stages()->max('position') ?? -1;

        FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => $validated['name'],
            'position' => $maxPosition + 1,
            'color' => $validated['color'] ?? 'gray',
        ]);

        return back()->with('success', __('Coluna adicionada.'));
    }

    public function updateStage(Request $request, Funnel $funnel, FunnelStage $stage): RedirectResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'in:yellow,purple,green,blue,gray,red,indigo'],
        ]);

        $stage->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? $stage->color,
        ]);

        return back()->with('success', __('Coluna atualizada.'));
    }

    public function destroyStage(Funnel $funnel, FunnelStage $stage): RedirectResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id) {
            abort(404);
        }

        if ($funnel->stages()->count() <= 1) {
            return back()->with('error', __('O funil precisa ter pelo menos uma coluna.'));
        }

        $firstOther = $funnel->stages()->where('id', '!=', $stage->id)->orderBy('position')->first();
        if ($firstOther) {
            $maxPos = $firstOther->leads()->max('position') ?? 0;
            foreach ($stage->leads as $index => $lead) {
                $lead->update(['funnel_stage_id' => $firstOther->id, 'position' => $maxPos + $index + 1]);
            }
        }
        $stage->delete();

        return back()->with('success', __('Coluna removida. Os leads foram movidos para a primeira coluna.'));
    }

    public function updateStageAutomation(Request $request, Funnel $funnel, FunnelStage $stage): RedirectResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id) {
            abort(404);
        }

        $automationId = filter_var($request->input('automation_id'), FILTER_VALIDATE_INT);
        if ($automationId === false || $automationId === null) {
            $automationId = null;
        } else {
            $automation = Automation::forUser(auth()->user()->accountId())->find($automationId);
            if (! $automation) {
                return back()->with('error', __('Automação não encontrada.'));
            }
        }

        $stage->update(['automation_id' => $automationId]);

        return back()->with('success', $automationId ? __('Automação vinculada à coluna.') : __('Automação desvinculada.'));
    }

    public function runStageAutomation(Funnel $funnel, FunnelStage $stage, AutomationRunnerService $runner): RedirectResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id) {
            abort(404);
        }

        $automation = $stage->automation;
        if (! $automation) {
            return back()->with('error', __('Esta coluna não tem automação configurada.'));
        }

        $this->authorize('update', $automation);

        $contactIds = $stage->leads()->whereNotNull('contact_id')->pluck('contact_id')->unique()->filter();
        $contacts = Contact::forUser(auth()->user()->accountId())->whereIn('id', $contactIds)->get();

        if ($contacts->isEmpty()) {
            return back()->with('error', __('Nenhum lead desta coluna tem contato vinculado.'));
        }

        $run = 0;
        $errors = [];
        foreach ($contacts as $contact) {
            $result = $runner->runForContact($automation, $contact);
            if ($result['success']) {
                $run++;
            } else {
                $errors[] = $contact->name . ': ' . ($result['message'] ?? '');
            }
        }

        if ($run > 0) {
            $msg = $run === $contacts->count()
                ? __('Automação disparada para :n contato(s).', ['n' => $run])
                : __('Automação disparada para :n de :total contato(s).', ['n' => $run, 'total' => $contacts->count()]);
            return back()->with('success', $msg);
        }

        return back()->with('error', __('Nenhum contato executou a automação.') . ' ' . implode(' ', array_slice($errors, 0, 2)));
    }

    public function stageContacts(Funnel $funnel, FunnelStage $stage): JsonResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id) {
            abort(404);
        }

        $contactIds = $stage->leads()->whereNotNull('contact_id')->pluck('contact_id')->unique()->filter()->values();
        $contacts = Contact::forUser(auth()->user()->accountId())
            ->whereIn('id', $contactIds)
            ->get(['id', 'name', 'phone'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone ?? '']);

        return response()->json(['contacts' => $contacts]);
    }

    public function sendStageMessage(Request $request, Funnel $funnel, FunnelStage $stage, FunnelDisparoService $disparoService): JsonResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id) {
            abort(404);
        }

        $validated = $request->validate([
            'message'        => ['nullable', 'string', 'max:65535'],
            'image'          => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'mode'           => ['nullable', 'string', 'in:sequential,round_robin,random'],
            'delay_seconds'  => ['nullable', 'integer', 'min:0', 'max:300'],
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:today'],
            'scheduled_time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
        ]);

        $message = trim((string) ($validated['message'] ?? ''));
        $imagePath = null;
        $imageMime = null;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $dir  = 'funnel_disparos/' . now()->format('Y/m');
            $name = Str::ulid() . '.' . ($file->getClientOriginalExtension() ?: 'jpg');
            $imagePath = $file->storeAs($dir, $name, ['disk' => 'local']);
            $imageMime = $file->getMimeType() ?: 'image/jpeg';
        }

        if ($message === '' && ! $imagePath) {
            return response()->json(['error' => __('Informe a mensagem ou envie uma imagem.')], 422);
        }

        $accountId  = auth()->user()->accountId();
        $contactIds = $stage->leads()
            ->whereNotNull('contact_id')
            ->pluck('contact_id')
            ->unique()
            ->filter()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($contactIds)) {
            return response()->json(['error' => __('Nenhum lead desta coluna tem contato vinculado.')], 422);
        }

        $scheduledAt = null;
        if (! empty($validated['scheduled_date']) && ! empty($validated['scheduled_time'])) {
            $tz          = config('app.timezone', 'America/Sao_Paulo');
            $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_date'] . ' ' . $validated['scheduled_time'] . ':00', $tz);
            if ($scheduledAt->isPast()) {
                return response()->json(['error' => __('A data e hora devem ser no futuro.')], 422);
            }
        }

        $disparo = $disparoService->createFromStage($accountId, $stage->id, $contactIds, [
            'message'       => $message ?: null,
            'image_path'    => $imagePath,
            'image_mime'    => $imageMime,
            'mode'          => $validated['mode'] ?? 'sequential',
            'delay_seconds' => (int) ($validated['delay_seconds'] ?? 0),
            'scheduled_at'  => $scheduledAt,
        ]);

        // If no delay and no schedule, process first message immediately
        if (! $scheduledAt && $disparo->delay_seconds === 0) {
            $disparoService->processNext($disparo->fresh());
        }

        return response()->json([
            'success'         => true,
            'disparo_id'      => $disparo->id,
            'total'           => $disparo->total_contacts,
            'scheduled'       => $scheduledAt?->toIso8601String(),
            'delay_seconds'   => $disparo->delay_seconds,
            'message'         => $scheduledAt
                ? __('Disparo agendado para :date às :time (:n contato(s)).', [
                    'date' => $scheduledAt->format('d/m/Y'),
                    'time' => $scheduledAt->format('H:i'),
                    'n'    => count($contactIds),
                ])
                : __('Disparo iniciado para :n contato(s).', ['n' => count($contactIds)]),
        ]);
    }

    public function disparoStatus(Funnel $funnel, FunnelStage $stage): JsonResponse
    {
        $this->authorize('view', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id) abort(404);

        $disparo = FunnelDisparo::query()
            ->where('funnel_stage_id', $stage->id)
            ->where('user_id', auth()->user()->accountId())
            ->whereIn('status', ['pending', 'running'])
            ->latest()
            ->first();

        if (! $disparo) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active'    => true,
            'id'        => $disparo->id,
            'status'    => $disparo->status,
            'sent'      => $disparo->sent_count,
            'failed'    => $disparo->failed_count,
            'total'     => $disparo->total_contacts,
            'percent'   => $disparo->progressPercent(),
            'mode'      => $disparo->mode,
            'delay'     => $disparo->delay_seconds,
        ]);
    }

    public function disparoCancel(Funnel $funnel, FunnelStage $stage, FunnelDisparo $disparo): JsonResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id) abort(404);
        if ((int) $disparo->funnel_stage_id !== (int) $stage->id) abort(404);

        $disparo->update(['status' => 'cancelled']);

        return response()->json(['success' => true]);
    }

    public function storeStageRule(Request $request, Funnel $funnel, FunnelStage $stage): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id) {
            abort(404);
        }

        $actionType = $request->input('action_type', 'move');

        $validator = Validator::make($request->all(), [
            'trigger_type'   => ['required', 'string', 'in:message_status,whatsapp_replied,specific_reply,tag_added,list_added'],
            'target_stage_id'=> ['nullable', 'integer', 'exists:funnel_stages,id'],
            'tag_id'         => ['nullable', 'required_if:trigger_type,tag_added', 'integer', 'exists:tags,id'],
            'lista_id'       => ['nullable', 'required_if:trigger_type,list_added', 'integer', 'exists:listas,id'],
            'status'         => ['nullable', 'required_if:trigger_type,message_status', 'string', 'in:sent,delivered,read,failed,responded'],
            'keyword'        => ['nullable', 'required_if:trigger_type,specific_reply', 'string', 'max:255'],
            'action_type'    => ['nullable', 'string', 'in:move,send,move_and_send'],
            'action_message' => ['nullable', 'required_if:action_type,send', 'required_if:action_type,move_and_send', 'string', 'max:65535'],
        ]);

        if ($validator->fails()) {
            $error = $validator->errors()->first();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => $error], 422);
            }
            return back()->with('error', $error);
        }

        $validated = $validator->validated();

        // action_type "move" and "move_and_send" require a target_stage
        if (in_array($actionType, ['move', 'move_and_send'], true) && empty($validated['target_stage_id'])) {
            $error = __('Escolha a etapa de destino.');
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => $error], 422);
            }
            return back()->with('error', $error);
        }

        $targetStage = null;
        if (! empty($validated['target_stage_id'])) {
            $targetStage = FunnelStage::where('funnel_id', $funnel->id)->find($validated['target_stage_id']);
            if (! $targetStage) {
                $error = __('Estágio de destino inválido.');
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['error' => $error], 422);
                }
                return back()->with('error', $error);
            }
            if (in_array($actionType, ['move', 'move_and_send'], true) && (int) $targetStage->id === (int) $stage->id) {
                $error = __('Escolha uma etapa diferente da atual.');
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['error' => $error], 422);
                }
                return back()->with('error', $error);
            }
        }

        $config = [];
        if ($validated['trigger_type'] === 'tag_added' && ! empty($validated['tag_id'])) {
            $config['tag_id'] = (int) $validated['tag_id'];
        }
        if ($validated['trigger_type'] === 'list_added' && ! empty($validated['lista_id'])) {
            $config['lista_id'] = (int) $validated['lista_id'];
        }
        if ($validated['trigger_type'] === 'message_status' && ! empty($validated['status'])) {
            $config['status'] = $validated['status'];
        }

        $rule = FunnelStageRule::create([
            'funnel_stage_id' => $stage->id,
            'trigger_type'    => $validated['trigger_type'],
            'trigger_config'  => $config ?: null,
            'target_stage_id' => $targetStage?->id,
            'keyword'         => $validated['keyword'] ?? null,
            'action_type'     => $actionType,
            'action_message'  => $validated['action_message'] ?? null,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            $triggerTypes = FunnelStageRule::triggerTypes();
            $extra = '';
            if ($rule->trigger_type === 'message_status' && ! empty($rule->trigger_config['status'])) {
                $extra = $rule->trigger_config['status'];
            } elseif ($rule->trigger_type === 'tag_added' && ! empty($rule->trigger_config['tag_id'])) {
                $extra = Tag::find($rule->trigger_config['tag_id'])?->name ?? '';
            } elseif ($rule->trigger_type === 'list_added' && ! empty($rule->trigger_config['lista_id'])) {
                $extra = Lista::find($rule->trigger_config['lista_id'])?->name ?? '';
            }

            return response()->json([
                'success' => true,
                'rule'    => [
                    'id'                => $rule->id,
                    'trigger_type'      => $rule->trigger_type,
                    'trigger_label'     => $triggerTypes[$rule->trigger_type] ?? $rule->trigger_type,
                    'trigger_extra'     => $extra,
                    'keyword'           => $rule->keyword,
                    'action_type'       => $rule->action_type ?? 'move',
                    'action_message'    => $rule->action_message,
                    'target_stage_name' => $targetStage?->name ?? '',
                    'destroy_url'       => route('funis.stages.rules.destroy', [$funnel->id, $stage->id, $rule->id]),
                ],
            ]);
        }

        return back()->with('success', __('Regra adicionada com sucesso.'));
    }

    public function destroyStageRule(Request $request, Funnel $funnel, FunnelStage $stage, FunnelStageRule $rule): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $funnel);
        if ((int) $stage->funnel_id !== (int) $funnel->id || (int) $rule->funnel_stage_id !== (int) $stage->id) {
            abort(404);
        }

        $rule->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', __('Regra removida.'));
    }
}
