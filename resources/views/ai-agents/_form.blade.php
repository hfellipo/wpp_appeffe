@php $agent = $aiAgent ?? null; @endphp

<!-- Nome -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ __('Nome do agente') }} <span class="text-red-500">*</span>
    </label>
    <input
        type="text"
        name="name"
        value="{{ old('name', $agent?->name) }}"
        placeholder="{{ __('Ex: Atendente de Vendas, Suporte Técnico...') }}"
        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500"
        required
    >
    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<!-- Descrição -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ __('Descrição') }}
        <span class="text-gray-400 font-normal">({{ __('opcional, para sua referência') }})</span>
    </label>
    <input
        type="text"
        name="description"
        value="{{ old('description', $agent?->description) }}"
        placeholder="{{ __('Ex: Agente para qualificação de leads no funil de vendas') }}"
        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500"
    >
    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<!-- System Prompt -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ __('Prompt do sistema (regras do agente)') }} <span class="text-red-500">*</span>
    </label>
    <p class="text-xs text-gray-500 mb-2">
        {{ __('Defina a personalidade, tom de voz, restrições e regras do agente. Quanto mais detalhado, melhor o comportamento.') }}
    </p>
    <textarea
        name="system_prompt"
        rows="10"
        placeholder="{{ __("Você é um atendente de vendas chamado João da empresa XYZ.\n\nRegras:\n- Responda sempre em português\n- Seja educado e objetivo\n- Nunca dê preços sem antes qualificar o lead\n- Se o cliente perguntar sobre concorrentes, mude de assunto\n- Seu objetivo é agendar uma demonstração\n\nInformações sobre o produto:\n...") }}"
        class="w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:ring-purple-500 focus:border-purple-500"
        required
    >{{ old('system_prompt', $agent?->system_prompt) }}</textarea>
    @error('system_prompt')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<!-- Configurações avançadas -->
<div>
    <button type="button" onclick="document.getElementById('advanced-config').classList.toggle('hidden')" class="flex items-center text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
        </svg>
        {{ __('Configurações avançadas (modelo, temperatura)') }}
    </button>
    <div id="advanced-config" class="{{ ($agent?->model || $agent?->temperature || $agent?->max_tokens) ? '' : 'hidden' }} mt-4 p-4 bg-gray-50 rounded-lg space-y-4">
        <p class="text-xs text-gray-500">{{ __('Deixe em branco para usar os valores padrão definidos nas Configurações de IA.') }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Modelo') }}</label>
                <select name="model" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
                    <option value="">{{ __('Usar padrão da conta') }}</option>
                    @foreach(\App\Models\AiConfig::availableModels() as $value => $label)
                        <option value="{{ $value }}" {{ old('model', $agent?->model) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Temperatura') }}</label>
                <input type="number" name="temperature" value="{{ old('temperature', $agent?->temperature) }}"
                    min="0" max="1" step="0.05" placeholder="{{ __('Padrão da conta') }}"
                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Máx. tokens') }}</label>
                <input type="number" name="max_tokens" value="{{ old('max_tokens', $agent?->max_tokens) }}"
                    min="50" max="4000" step="50" placeholder="{{ __('Padrão da conta') }}"
                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
        </div>
    </div>
</div>

<!-- Ativo -->
<div class="flex items-center gap-3">
    <input type="hidden" name="active" value="0">
    <input
        type="checkbox"
        name="active"
        value="1"
        id="active"
        {{ old('active', $agent?->active ?? true) ? 'checked' : '' }}
        class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
    >
    <label for="active" class="text-sm text-gray-700">{{ __('Agente ativo') }}</label>
</div>
