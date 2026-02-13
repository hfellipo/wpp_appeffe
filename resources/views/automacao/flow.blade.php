<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <div class="flex items-center gap-3">
                <a href="{{ route('automacao.index') }}" class="text-gray-500 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100 transition-colors" title="{{ __('Voltar') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $automation->name }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">{{ __('Editor de fluxo') }} — {{ __('Arraste nós e conecte como no N8N') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('automacao.edit', $automation) }}" class="btn-secondary text-sm">{{ __('Gatilho e condições') }}</a>
                <a href="{{ route('automacao.test', $automation) }}" class="btn-primary text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('Testar') }}
                </a>
                <button type="button" id="flow-save-btn" class="btn-primary text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    <span id="flow-save-text">{{ __('Salvar fluxo') }}</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex">
        <div id="automation-flow-sidebar" class="w-56 flex-shrink-0 border-r border-gray-200 bg-white p-3 overflow-y-auto">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ __('Adicionar nó') }}</p>
            <div class="space-y-1" id="flow-node-palette"></div>
        </div>
        <div id="automation-flow-root" class="flex-1 min-h-0" style="height: 100%;"></div>
        <div id="automation-flow-props" class="w-72 flex-shrink-0 border-l border-gray-200 bg-white overflow-y-auto hidden">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">{{ __('Configurar nó') }}</h3>
                <p class="text-sm text-gray-500 mt-0.5" id="flow-props-node-type"></p>
            </div>
            <div class="p-4" id="flow-props-fields"></div>
        </div>
    </div>

    <script>
        window.AUTOMATION_FLOW = @json([
            'automationId' => $automation->id,
            'flowDataUrl' => route('automacao.flow.data', ['automacao' => $automation]),
            'flowUpdateUrl' => route('automacao.flow.update', ['automacao' => $automation]),
            'csrfToken' => csrf_token(),
            'listas' => $listas,
            'tags' => $tags,
            'nodeTypes' => $nodeTypes,
        ]);
    </script>
    @vite(['resources/css/app.css', 'resources/js/automation-flow.jsx'])
</x-app-layout>
