<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\AiConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAgentController extends Controller
{
    private function accountId(): int
    {
        return auth()->user()->accountId();
    }

    public function index(): View
    {
        $accountId = $this->accountId();

        $agents = AiAgent::where('user_id', $accountId)
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $hasApiKey = AiConfig::where('user_id', $accountId)
            ->whereNotNull('openai_api_key')
            ->exists();

        return view('ai-agents.index', compact('agents', 'hasApiKey'));
    }

    public function create(): View
    {
        return view('ai-agents.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAgent($request);
        $validated['user_id'] = $this->accountId();

        AiAgent::create($validated);

        return redirect()->route('ai-agents.index')->with('success', __('Agente criado com sucesso.'));
    }

    public function edit(AiAgent $aiAgent): View
    {
        $this->authorizeAgent($aiAgent);

        return view('ai-agents.edit', compact('aiAgent'));
    }

    public function update(Request $request, AiAgent $aiAgent): RedirectResponse
    {
        $this->authorizeAgent($aiAgent);

        $validated = $this->validateAgent($request);
        $aiAgent->update($validated);

        return redirect()->route('ai-agents.index')->with('success', __('Agente atualizado com sucesso.'));
    }

    public function destroy(AiAgent $aiAgent): RedirectResponse
    {
        $this->authorizeAgent($aiAgent);
        $aiAgent->delete();

        return redirect()->route('ai-agents.index')->with('success', __('Agente removido.'));
    }

    public function apiList(): \Illuminate\Http\JsonResponse
    {
        $agents = AiAgent::where('user_id', $this->accountId())
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'model', 'temperature', 'max_tokens']);

        return response()->json($agents);
    }

    private function validateAgent(Request $request): array
    {
        return $request->validate([
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string|max:255',
            'system_prompt' => 'required|string|max:10000',
            'model'         => 'nullable|string|in:' . implode(',', array_keys(AiConfig::availableModels())),
            'temperature'   => 'nullable|numeric|min:0|max:1',
            'max_tokens'    => 'nullable|integer|min:50|max:4000',
            'active'        => 'boolean',
        ]);
    }

    private function authorizeAgent(AiAgent $agent): void
    {
        abort_if((int) $agent->user_id !== $this->accountId(), 403);
    }
}
