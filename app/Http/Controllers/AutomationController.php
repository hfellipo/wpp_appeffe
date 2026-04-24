<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationAction;
use App\Models\AutomationCondition;
use App\Models\AutomationEdge;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\AutomationTrigger;
use App\Models\Contact;
use App\Models\Lista;
use App\Models\Tag;
use App\Services\AutomationRunnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

    /**
     * Editor de fluxo drag-and-drop — usado tanto por /flow quanto por /jornada.
     */
    public function flow(Automation $automacao): View
    {
        $this->authorize('update', $automacao);
        $automacao->load(['flowNodes', 'flowEdges']);
        $accountId = auth()->user()->accountId();

        $listas       = Lista::forUser($accountId)->orderBy('name')->get(['id', 'name']);
        $tags         = Tag::forUser($accountId)->orderBy('name')->get(['id', 'name', 'color']);
        $customFields = \App\Models\ContactField::forUser($accountId)->active()->ordered()->get(['id', 'name']);
        $automations  = Automation::forUser($accountId)->orderBy('name')->get(['id', 'name']);

        $contacts = Contact::forUser($accountId)->orderBy('name')->get(['id', 'name', 'phone']);

        $flowConfig = [
            'automationId'  => $automacao->id,
            'flowDataUrl'   => route('automacao.flow.data',   ['automacao' => $automacao]),
            'flowUpdateUrl' => route('automacao.flow.update', ['automacao' => $automacao]),
            'flowTestUrl'   => route('automacao.flow.testAjax', ['automacao' => $automacao]),
            'csrfToken'     => csrf_token(),
            'listas'        => $listas,
            'tags'          => $tags,
            'customFields'  => $customFields,
            'automations'   => $automations,
            'contacts'      => $contacts,
            'title'         => $automacao->name,
        ];

        $lastRun = \App\Models\AutomationRun::where('automation_id', $automacao->id)
            ->where('status', 'success')
            ->orderByDesc('ran_at')
            ->first(['ran_at', 'contact_id']);

        $totalRuns = \App\Models\AutomationRun::where('automation_id', $automacao->id)
            ->where('status', 'success')
            ->count();

        return view('automacao.flow', [
            'automation' => $automacao,
            'flowConfig' => $flowConfig,
            'lastRun'    => $lastRun,
            'totalRuns'  => $totalRuns,
        ]);
    }

    /**
     * Constrói o config do nó start a partir do trigger e condições salvas no DB.
     */
    private function buildStartNodeConfig(Automation $automacao): array
    {
        $trigger = $automacao->trigger;
        if (! $trigger) {
            return [];
        }

        $config = [
            'trigger_type'        => $trigger->type,
            'tag_id'              => $trigger->config['tag_id']   ?? null,
            'lista_id'            => $trigger->config['lista_id'] ?? null,
            'run_once_per_contact' => $automacao->run_once_per_contact ?? true,
            'condition_logic'     => $automacao->condition_logic ?? 'and',
            'conditions'          => $automacao->conditions->map(fn (AutomationCondition $c) => [
                'field_type'       => $c->field_type,
                'field_key'        => $c->field_key,
                'contact_field_id' => $c->contact_field_id,
                'operator'         => $c->operator,
                'value'            => $c->value,
            ])->values()->all(),
        ];

        return $config;
    }

    /**
     * API: retorna nodes e edges para o editor.
     */
    public function flowData(Automation $automacao): JsonResponse
    {
        $this->authorize('update', $automacao);
        $automacao->load(['flowNodes', 'flowEdges', 'trigger', 'conditions']);

        $startConfig = $this->buildStartNodeConfig($automacao);

        $nodes = $automacao->flowNodes->map(fn (AutomationNode $n) => [
            'id' => (string) $n->id,
            'type' => $n->type,
            'position' => ['x' => (float) $n->position_x, 'y' => (float) $n->position_y],
            'data' => [
                'label'  => $n->label ?? AutomationNode::nodeTypes()[$n->type] ?? $n->type,
                'config' => $n->type === 'start' && ! empty($startConfig)
                    ? $startConfig
                    : ($n->config ?? []),
            ],
        ])->values()->all();

        $edges = $automacao->flowEdges->map(fn (AutomationEdge $e) => [
            'id'           => "e{$e->source_node_id}-{$e->target_node_id}",
            'source'       => (string) $e->source_node_id,
            'target'       => (string) $e->target_node_id,
            'sourceHandle' => $e->source_handle ?? 'default',
            'targetHandle' => $e->target_handle ?? 'input',
        ])->values()->all();

        return response()->json(['nodes' => $nodes, 'edges' => $edges]);
    }

    /**
     * Sincroniza o config do nó start com as tabelas de trigger e condições.
     */
    private function syncTriggerFromStartNode(Automation $automacao, array $nodesPayload): void
    {
        $startNode = collect($nodesPayload)->first(fn ($n) => $n['type'] === 'start');
        if (! $startNode) {
            return;
        }

        $config      = $startNode['data']['config'] ?? [];
        $triggerType = $config['trigger_type'] ?? null;

        if (! $triggerType) {
            return;
        }

        $triggerConfig = [];
        if ($triggerType === 'tag_added' && ! empty($config['tag_id'])) {
            $triggerConfig['tag_id'] = (int) $config['tag_id'];
        }
        if ($triggerType === 'list_added' && ! empty($config['lista_id'])) {
            $triggerConfig['lista_id'] = (int) $config['lista_id'];
        }

        $automacao->trigger()->updateOrCreate(
            ['automation_id' => $automacao->id],
            ['type' => $triggerType, 'config' => $triggerConfig]
        );

        $runOnce = isset($config['run_once_per_contact']) ? (bool) $config['run_once_per_contact'] : true;
        $automacao->update(['run_once_per_contact' => $runOnce]);

        $conditions = array_values(array_filter($config['conditions'] ?? [], fn ($c) => ! empty($c['field_type']) && ! empty($c['operator'])));

        $automacao->update([
            'condition_logic' => count($conditions) > 0 ? ($config['condition_logic'] ?? 'and') : null,
        ]);

        $automacao->conditions()->delete();
        foreach ($conditions as $i => $cond) {
            $automacao->conditions()->create([
                'position'         => $i,
                'field_type'       => $cond['field_type'],
                'field_key'        => $cond['field_key'] ?? null,
                'contact_field_id' => ! empty($cond['contact_field_id']) ? (int) $cond['contact_field_id'] : null,
                'operator'         => $cond['operator'],
                'value'            => $cond['value'] ?? null,
            ]);
        }
    }

    /**
     * API: salva nodes e edges do fluxo.
     */
    public function flowUpdate(Request $request, Automation $automacao): JsonResponse
    {
        $this->authorize('update', $automacao);
        $validated = $request->validate([
            'nodes'                   => ['required', 'array'],
            'nodes.*.id'              => ['required', 'string'],
            'nodes.*.type'            => ['required', 'string', 'in:start,send_message,condition,delay,go_to,user_input,update_field,add_tag,remove_tag,add_list,remove_list,human_transfer'],
            'nodes.*.position'        => ['required', 'array'],
            'nodes.*.position.x'      => ['required', 'numeric'],
            'nodes.*.position.y'      => ['required', 'numeric'],
            'nodes.*.data'            => ['nullable', 'array'],
            'nodes.*.data.label'      => ['nullable', 'string'],
            'nodes.*.data.config'     => ['nullable', 'array'],
            'edges'                   => ['required', 'array'],
            'edges.*.source'          => ['required', 'string'],
            'edges.*.target'          => ['required', 'string'],
            'edges.*.sourceHandle'    => ['nullable', 'string'],
            'edges.*.targetHandle'    => ['nullable', 'string'],
        ]);

        $nodesPayload = $validated['nodes'];
        $edgesPayload = $validated['edges'];
        $automacao->load('flowNodes');
        $existingByFrontId = $automacao->flowNodes->keyBy(fn (AutomationNode $node) => (string) $node->id);
        $idMap = [];

        foreach ($nodesPayload as $n) {
            $pos     = $n['position'];
            $data    = $n['data'] ?? [];
            $config  = $data['config'] ?? [];
            $label   = $data['label'] ?? null;
            $type    = $n['type'];
            $frontId = $n['id'] ?? null;
            $existing = $frontId ? $existingByFrontId->get($frontId) : null;
            if ($existing) {
                $existing->update([
                    'type'       => $type,
                    'position_x' => $pos['x'],
                    'position_y' => $pos['y'],
                    'config'     => $config,
                    'label'      => $label,
                ]);
                $idMap[$frontId] = $existing->id;
            } else {
                $node = AutomationNode::create([
                    'automation_id' => $automacao->id,
                    'type'          => $type,
                    'position_x'    => $pos['x'],
                    'position_y'    => $pos['y'],
                    'config'        => $config,
                    'label'         => $label,
                ]);
                $idMap[$frontId] = $node->id;
            }
        }

        $payloadFrontIds = collect($nodesPayload)->pluck('id')->map(fn ($id) => (string) $id)->flip();
        $toDelete = $automacao->flowNodes->filter(fn (AutomationNode $node) => ! $payloadFrontIds->has((string) $node->id));
        foreach ($toDelete as $node) {
            $node->delete();
        }

        $automacao->flowEdges()->delete();
        foreach ($edgesPayload as $e) {
            $src = $idMap[$e['source']] ?? null;
            $tgt = $idMap[$e['target']] ?? null;
            if ($src && $tgt) {
                AutomationEdge::create([
                    'automation_id'  => $automacao->id,
                    'source_node_id' => $src,
                    'target_node_id' => $tgt,
                    'source_handle'  => $e['sourceHandle'] ?? 'default',
                    'target_handle'  => $e['targetHandle'] ?? 'input',
                ]);
            }
        }

        $this->syncTriggerFromStartNode($automacao, $nodesPayload);

        return response()->json(['ok' => true, 'message' => __('Fluxo salvo.')]);
    }

    /**
     * Visão da jornada — agora renderiza o editor de fluxo drag-and-drop.
     */
    public function jornada(Automation $automacao): View
    {
        return $this->flow($automacao);
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
            'messageStatusOperators' => Automation::messageStatusOperators(),
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
                'conditions.*.field_type' => ['required_with:conditions', 'string', 'in:attribute,custom,message_status'],
                'conditions.*.field_key' => ['nullable', 'required_if:conditions.*.field_type,attribute', 'string', 'in:name,email,phone'],
                'conditions.*.contact_field_id' => ['nullable', 'required_if:conditions.*.field_type,custom', 'exists:contact_fields,id'],
                'conditions.*.operator' => ['required_with:conditions', 'string', 'in:equals,not_equals,contains,is_empty,is_not_empty,is_sent,is_delivered,is_read,is_not_delivered,is_not_read'],
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
                    $contactFieldId = null;
                    $fieldKey = null;
                    $value = null;
                    if ($r['field_type'] === 'message_status') {
                        if (! in_array($r['operator'], ['is_sent', 'is_delivered', 'is_read', 'is_not_delivered', 'is_not_read'], true)) {
                            continue;
                        }
                    } elseif ($r['field_type'] === 'custom') {
                        if (empty($r['contact_field_id'])) {
                            continue;
                        }
                        $contactFieldId = (int) $r['contact_field_id'];
                        if (! \App\Models\ContactField::forUser($accountId)->where('id', $contactFieldId)->exists()) {
                            continue;
                        }
                        $value = $request->input("conditions.{$i}.value", $r['value'] ?? null);
                        $value = $value !== null && $value !== '' ? trim((string) $value) : null;
                    } elseif ($r['field_type'] === 'attribute') {
                        if (empty($r['field_key'])) {
                            continue;
                        }
                        $fieldKey = $r['field_key'];
                        $value = $request->input("conditions.{$i}.value", $r['value'] ?? null);
                        $value = $value !== null && $value !== '' ? trim((string) $value) : null;
                    }
                    $automacao->conditions()->create([
                        'type' => 'rule',
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

    /**
     * Cron por URL para automação jornada (sem auth; exige SCHEDULED_POSTS_CRON_TOKEN no .env).
     */
    public function cronJornada(Request $request): JsonResponse
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

        Artisan::call('automations:run-jornada');

        return response()->json([
            'ok' => true,
            'message' => 'Cron automação jornada executado.',
        ]);
    }

    /**
     * Página para testar a automação com um contato.
     */
    public function test(Automation $automacao): View
    {
        $this->authorize('update', $automacao);

        $contacts = Contact::forUser(auth()->user()->accountId())
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        return view('automacao.test', [
            'automation' => $automacao,
            'contacts' => $contacts,
        ]);
    }

    /**
     * Executa a automação uma vez para o contato selecionado (teste).
     */
    public function runTest(Request $request, Automation $automacao, AutomationRunnerService $runner): RedirectResponse
    {
        $this->authorize('update', $automacao);

        $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
        ]);

        $contact = Contact::forUser(auth()->user()->accountId())
            ->where('id', $request->input('contact_id'))
            ->firstOrFail();

        $result = $runner->runForContact($automacao, $contact);

        if ($result['success']) {
            return redirect()
                ->route('automacao.edit', ['automacao' => $automacao, 'step' => 'action'])
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $result['message']);
    }

    /**
     * API JSON: executa o flow para teste, retorna resultado nó a nó para o editor React.
     */
    public function testFlowAjax(Request $request, Automation $automacao, AutomationRunnerService $runner): JsonResponse
    {
        $this->authorize('update', $automacao);

        $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
        ]);

        $contact = Contact::forUser(auth()->user()->accountId())
            ->where('id', $request->input('contact_id'))
            ->firstOrFail();

        // Remove run anterior para permitir o teste
        AutomationRun::where('automation_id', $automacao->id)
            ->where('contact_id', $contact->id)
            ->delete();

        $result = $runner->runForContact($automacao, $contact, skipDelays: true, dryRun: true);

        // Remove o run criado pelo dry run para não bloquear disparos reais futuros
        AutomationRun::where('automation_id', $automacao->id)
            ->where('contact_id', $contact->id)
            ->delete();

        $automacao->load('flowNodes');
        $startNode = $automacao->flowNodes->firstWhere('type', 'start');

        $details = $result['details'] ?? [];

        if ($startNode && ! collect($details)->contains('node_id', $startNode->id)) {
            array_unshift($details, [
                'action'  => 'start',
                'node_id' => $startNode->id,
                'success' => true,
            ]);
        }

        $details = array_map(
            fn ($d) => array_merge($d, ['node_id' => (string) ($d['node_id'] ?? '')]),
            $details
        );

        return response()->json([
            'ok'      => true,
            'success' => $result['success'],
            'message' => $result['message'],
            'details' => $details,
        ]);
    }

    /**
     * Página de teste do flow: seleciona contato e dispara o fluxo ignorando o gatilho.
     */
    public function testFlow(Automation $automacao): View
    {
        $this->authorize('update', $automacao);

        $contacts = Contact::forUser(auth()->user()->accountId())
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        return view('automacao.test_flow', [
            'automation' => $automacao,
            'contacts'   => $contacts,
        ]);
    }

    /**
     * Executa o flow a partir do nó start, ignorando o gatilho e apagando run anterior
     * (para permitir re-testes do mesmo contato).
     */
    public function runTestFlow(Request $request, Automation $automacao, AutomationRunnerService $runner): RedirectResponse
    {
        $this->authorize('update', $automacao);

        $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
        ]);

        $contact = Contact::forUser(auth()->user()->accountId())
            ->where('id', $request->input('contact_id'))
            ->firstOrFail();

        // Remove run anterior para permitir re-teste com o mesmo contato
        AutomationRun::where('automation_id', $automacao->id)
            ->where('contact_id', $contact->id)
            ->delete();

        $result = $runner->runForContact($automacao, $contact);

        $flash = $result['success'] ? 'success' : 'error';

        return redirect()
            ->route('automacao.flow', $automacao)
            ->with($flash, $result['message']);
    }
}
