<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationAction;
use App\Models\AutomationCondition;
use App\Models\AutomationTrigger;
use App\Models\Lista;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(): View
    {
        $automations = Automation::forUser(auth()->user()->accountId())
            ->with(['trigger', 'condition', 'actions'])
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        return view('automacao.index', compact('automations'));
    }

    public function create(): View
    {
        return view('automacao.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $automation = Automation::create([
            'user_id' => auth()->user()->accountId(),
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('automacao.edit', ['automacao' => $automation, 'step' => 'trigger'])
            ->with('success', __('Automação criada. Configure o gatilho.'));
    }

    public function edit(Request $request, Automation $automacao): View
    {
        $this->authorize('update', $automacao);

        $step = $request->query('step', 'trigger');
        if (!in_array($step, ['trigger', 'condition', 'action'], true)) {
            $step = 'trigger';
        }

        $automacao->load(['trigger', 'condition', 'actions']);

        $listas = Lista::forUser(auth()->user()->accountId())->orderBy('name')->get(['id', 'name']);
        $tags = Tag::forUser(auth()->user()->accountId())->orderBy('name')->get(['id', 'name', 'color']);

        return view('automacao.edit', [
            'automation' => $automacao,
            'step' => $step,
            'listas' => $listas,
            'tags' => $tags,
            'triggerTypes' => Automation::triggerTypes(),
            'conditionTypes' => Automation::conditionTypes(),
            'actionTypes' => Automation::actionTypes(),
        ]);
    }

    public function update(Request $request, Automation $automacao): RedirectResponse
    {
        $this->authorize('update', $automacao);

        $step = $request->input('step', 'trigger');

        if ($step === 'trigger') {
            $validated = $request->validate([
                'trigger_type' => ['required', 'string', 'in:list_added,tag_added,manual,schedule_daily,schedule_weekly,schedule_monthly,schedule_yearly'],
                'trigger_lista_id' => ['nullable', 'required_if:trigger_type,list_added', 'exists:listas,id'],
                'trigger_tag_id' => ['nullable', 'required_if:trigger_type,tag_added', 'exists:tags,id'],
                'schedule_time' => ['nullable', 'string', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'],
                'schedule_weekday' => ['nullable', 'integer', 'min:0', 'max:6'],
                'schedule_day' => ['nullable', 'integer', 'min:1', 'max:31'],
                'schedule_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            ]);

            $accountId = auth()->user()->accountId();
            $config = [];
            if ($validated['trigger_type'] === 'list_added' && !empty($validated['trigger_lista_id'])) {
                if (Lista::forUser($accountId)->where('id', $validated['trigger_lista_id'])->exists()) {
                    $config['lista_id'] = (int) $validated['trigger_lista_id'];
                }
            }
            if ($validated['trigger_type'] === 'tag_added' && !empty($validated['trigger_tag_id'])) {
                if (Tag::forUser($accountId)->where('id', $validated['trigger_tag_id'])->exists()) {
                    $config['tag_id'] = (int) $validated['trigger_tag_id'];
                }
            }
            if (str_starts_with($validated['trigger_type'], 'schedule_')) {
                if (!empty($validated['schedule_time'])) {
                    $config['time'] = $validated['schedule_time'];
                }
                if (isset($validated['schedule_weekday'])) {
                    $config['weekday'] = (int) $validated['schedule_weekday'];
                }
                if (!empty($validated['schedule_day'])) {
                    $config['day'] = (int) $validated['schedule_day'];
                }
                if (!empty($validated['schedule_month'])) {
                    $config['month'] = (int) $validated['schedule_month'];
                }
            }

            $automacao->trigger()->updateOrCreate(
                ['automation_id' => $automacao->id],
                ['type' => $validated['trigger_type'], 'config' => $config]
            );

            return redirect()
                ->route('automacao.edit', ['automacao' => $automacao, 'step' => 'condition'])
                ->with('success', __('Gatilho salvo. Configure a condição.'));
        }

        if ($step === 'condition') {
            $validated = $request->validate([
                'condition_type' => ['required', 'string', 'in:always_yes,always_no,contact_in_list,contact_has_tag'],
                'condition_lista_id' => ['nullable', 'required_if:condition_type,contact_in_list', 'exists:listas,id'],
                'condition_tag_id' => ['nullable', 'required_if:condition_type,contact_has_tag', 'exists:tags,id'],
            ]);

            $accountId = auth()->user()->accountId();
            $config = [];
            if ($validated['condition_type'] === 'contact_in_list' && !empty($validated['condition_lista_id'])) {
                if (Lista::forUser($accountId)->where('id', $validated['condition_lista_id'])->exists()) {
                    $config['lista_id'] = (int) $validated['condition_lista_id'];
                }
            }
            if ($validated['condition_type'] === 'contact_has_tag' && !empty($validated['condition_tag_id'])) {
                if (Tag::forUser($accountId)->where('id', $validated['condition_tag_id'])->exists()) {
                    $config['tag_id'] = (int) $validated['condition_tag_id'];
                }
            }

            $automacao->condition()->updateOrCreate(
                ['automation_id' => $automacao->id],
                ['type' => $validated['condition_type'], 'config' => $config]
            );

            return redirect()
                ->route('automacao.edit', ['automacao' => $automacao, 'step' => 'action'])
                ->with('success', __('Condição salva. Configure a ação.'));
        }

        if ($step === 'action') {
            $validated = $request->validate([
                'action_type' => ['required', 'string', 'in:send_whatsapp_message,add_to_list,add_tag,wait_delay'],
                'action_message' => ['nullable', 'required_if:action_type,send_whatsapp_message', 'string', 'max:4000'],
                'action_lista_id' => ['nullable', 'required_if:action_type,add_to_list', 'exists:listas,id'],
                'action_tag_id' => ['nullable', 'required_if:action_type,add_tag', 'exists:tags,id'],
                'action_wait_minutes' => ['nullable', 'required_if:action_type,wait_delay', 'integer', 'min:1', 'max:10080'],
            ]);

            $accountId = auth()->user()->accountId();
            $config = [];
            if ($validated['action_type'] === 'send_whatsapp_message' && isset($validated['action_message'])) {
                $config['message'] = $validated['action_message'];
            }
            if ($validated['action_type'] === 'add_to_list' && !empty($validated['action_lista_id'])) {
                if (Lista::forUser($accountId)->where('id', $validated['action_lista_id'])->exists()) {
                    $config['lista_id'] = (int) $validated['action_lista_id'];
                }
            }
            if ($validated['action_type'] === 'add_tag' && !empty($validated['action_tag_id'])) {
                if (Tag::forUser($accountId)->where('id', $validated['action_tag_id'])->exists()) {
                    $config['tag_id'] = (int) $validated['action_tag_id'];
                }
            }
            if ($validated['action_type'] === 'wait_delay' && !empty($validated['action_wait_minutes'])) {
                $config['minutes'] = (int) $validated['action_wait_minutes'];
            }

            $maxPosition = $automacao->actions()->max('position') ?? -1;

            $automacao->actions()->create([
                'type' => $validated['action_type'],
                'config' => $config,
                'position' => $maxPosition + 1,
            ]);

            return redirect()
                ->route('automacao.edit', ['automacao' => $automacao, 'step' => 'action'])
                ->with('success', __('Ação adicionada.'));
        }

        return redirect()->route('automacao.index');
    }

    public function destroy(Automation $automacao): RedirectResponse
    {
        $this->authorize('delete', $automacao);
        $automacao->delete();
        return redirect()
            ->route('automacao.index')
            ->with('success', __('Automação excluída.'));
    }

    public function destroyAction(Automation $automacao, AutomationAction $action): RedirectResponse
    {
        $this->authorize('update', $automacao);
        if ((int) $action->automation_id !== (int) $automacao->id) {
            abort(404);
        }
        $action->delete();
        return redirect()
            ->route('automacao.edit', ['automacao' => $automacao, 'step' => 'action'])
            ->with('success', __('Ação removida.'));
    }

    public function toggle(Automation $automacao): RedirectResponse
    {
        $this->authorize('update', $automacao);
        $automacao->update(['is_active' => !$automacao->is_active]);
        return redirect()
            ->route('automacao.index')
            ->with('success', $automacao->is_active ? __('Automação ativada.') : __('Automação pausada.'));
    }
}
