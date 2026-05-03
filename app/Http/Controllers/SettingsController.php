<?php

namespace App\Http\Controllers;

use App\Models\AiConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private function accountId(): int
    {
        return auth()->user()->accountId();
    }

    public function index(): View
    {
        $aiConfig = AiConfig::firstOrNew(['user_id' => $this->accountId()]);

        return view('settings.index', compact('aiConfig'));
    }

    public function saveAiConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'openai_api_key' => 'nullable|string|max:200',
            'default_model'  => 'nullable|string|in:' . implode(',', array_keys(AiConfig::availableModels())),
            'temperature'    => 'nullable|numeric|min:0|max:1',
            'max_tokens'     => 'nullable|integer|min:50|max:4000',
        ]);

        $config = AiConfig::firstOrNew(['user_id' => $this->accountId()]);
        $config->user_id = $this->accountId();

        if (! empty($validated['openai_api_key'])) {
            $config->openai_api_key = $validated['openai_api_key'];
        }

        $config->default_model = $validated['default_model'] ?? $config->default_model ?? 'gpt-3.5-turbo';
        $config->temperature   = $validated['temperature']   ?? $config->temperature   ?? 0.70;
        $config->max_tokens    = $validated['max_tokens']    ?? $config->max_tokens    ?? 500;
        $config->save();

        return redirect()->route('settings.index')->with('ai_success', __('Configurações de IA salvas com sucesso!'));
    }
}
