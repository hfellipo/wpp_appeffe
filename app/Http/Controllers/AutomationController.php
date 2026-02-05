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
            ->with(['trigger', 'conditions', 'actions'])
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

        $automacao->load(['trigger', 'conditions.contactField', 'actions']);

        $accountId = auth()->user()->accountId();
        $listas = Lista::forUser($accountId)->orderBy('name')->get(['id', 'name']);
        $tags = Tag::forUser($accountId)->orderBy('name')->get(['id', 'name', 'color']);
        $customFields = \App\Models\ContactField::forUser($accountId)->active()->ordered()->get(['id', 'name']);

        return view('automacao.edit', [
            'automation' => $automacao,
            'step' => $step,
            'listas' => $listas,
            'tags' => $tags,
            'customFields' => $customFields,
            'triggerTypes' => Automation::triggerTypes(),
            'conditionOperators' => Automation::conditionOperators(),
            'attributeFields' => Automation::attributeFields(),
            'actionTypes' => Automation::actionTypes(),
        ]);
    }

    public function update(Request $request, Automation $automacao): RedirectResponse
    {
        $this->authorize('update', $automacao);

        $step = $request->input('step', 'trigger');

        if ($step === 'trigger') {
            $validated = $request->validate([
                'trigger_type' => ['required', 'string', 'in:tag_added,list_added'],
                'trigger_lista_id' => ['nullable', 'required_if:trigger_type,list_added', 'exists:listas,id'],
                'trigger_tag_id' => ['nullable', 'required_if:trigger_type,tag_added', 'exists:tags,id'],
                'interval_minutes' => ['required', 'integer', 'in:5,15,30,60'],
                'run_once_per_contact' => ['required', 'boolean'],
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

            $automacao->update([
                'interval_minutes' => (int) $validated['interval_minutes'],
                'run_once_per_contact' => (bool) $validated['run_once_per_contact'],
            ]);
            $automacao->trigger()->updateOrCreate(
                ['automation_id' => $automacao->id],
                ['type' => $validated['trigger_type'], 'config' => $config]
            );

            return redirect()
                ->route('automacao.edit', ['automacao' => $automacao, 'step' => 'condition'])
                ->with('success', __('Gatilho salvo. Configure quem deve receber (condições).'));
        }

        if ($step === 'condition') {
            $validated = $request->validate([
                'condition_mode' => ['required', 'string', 'in:all,rules'],
                'condition_logic' => ['nullable', 'required_if:condition_mode,rules', 'string', 'in:and,or'],
                'conditions' => ['nullable', 'array'],
                'conditions.*.field_type' => ['required_with:conditions', 'string', 'in:attribute,custom'],
                'conditions.*.field_key' => ['nullable', 'required_if:conditions.*.field_type,attribute', 'string', 'in:name,email,phone'],
                'conditions.*.contact_field_id' => ['nullable', 'required_if:conditions.*.field_type,custom', 'exists:contact_fields,id'],
                'conditions.*.operator' => ['required_with:conditions', 'string', 'in:equals,not_equals,contains,is_empty,is_not_empty'],
                'conditions.*.value' => ['nullable', 'string', 'max:500'],
            ]);

            $accountId = auth()->user()->accountId();

            if ($validated['condition_mode'] === 'all') {
                $automacao->update(['condition_logic' => null]);
                $automacao->conditions()->delete();
            } else {
                $automacao->update(['condition_logic' => $validated['condition_logic'] ?? 'and']);
                $automacao->conditions()->delete();
                $rules = $validated['conditions'] ?? [];
                foreach ($rules as $i => $r) {
                    if (empty($r['field_type']) || empty($r['operator'])) {
                        continue;
                    }
                    if ($r['field_type'] === 'custom' && empty($r['contact_field_id'])) {
                        continue;
                    }
                    if ($r['field_type'] === 'attribute' && empty($r['field_key'])) {
                        continue;
                    }
                    $contactFieldId = null;
                    $fieldKey = null;
                    if ($r['field_type'] === 'custom') {
                        $contactFieldId = (int) $r['contact_field_id'];
                        if (!\App\Models\ContactField::forUser($accountId)->where('id', $contactFieldId)->exists()) {
                            continue;
                        }
                    } else {
                        $fieldKey = $r['field_key'];
                    }
                    // Valor: ler do request direto para garantir que não se perde (validated pode não incluir)
                    $value = $request->input("conditions.{$i}.value", $r['value'] ?? null);
                    $value = $value !== null && $value !== '' ? trim((string) $value) : null;
                    $automacao->conditions()->create([
                        'type' => 'rule', // legado: tabela exige type; regras novas usam field_type/operator/value
                        'position' => $i,
                        'field_type' => $r['field_type'],
                        'field_key' => $fieldKey,
                        'contact_field_id' => $contactFieldId,
                        'operator' => $r['operator'],
                        'value' => $value,
                    ]);
                }
            }

            return redirect()
                ->route('automacao.edit', ['automacao' => $automacao, 'step' => 'action'])
                ->with('success', __('Condições salvas. Configure as ações.'));
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
