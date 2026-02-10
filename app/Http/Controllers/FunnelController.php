<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\Funnel;
use App\Models\FunnelLead;
use App\Models\FunnelStage;
use App\Models\Lista;
use App\Models\Tag;
use App\Services\AutomationRunnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $funnel->load(['stages.leads' => fn ($q) => $q->with(['contact.listas', 'contact.tags'])->orderBy('position'), 'stages.automation' => fn ($q) => $q->with(['trigger', 'actions'])]);
        $funnel->loadCount('leads')->loadSum('leads', 'value');
        $userId = auth()->user()->accountId();
        $contacts = Contact::forUser($userId)->orderBy('name')->get(['id', 'name', 'phone']);
        $listas = Lista::forUser($userId)->orderBy('name')->get(['id', 'name']);
        $tags = Tag::forUser($userId)->orderBy('name')->get(['id', 'name']);
        $automations = Automation::forUser($userId)->orderBy('name')->get(['id', 'name', 'is_active']);

        return view('funis.show', compact('funnel', 'contacts', 'listas', 'tags', 'automations'));
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
}
