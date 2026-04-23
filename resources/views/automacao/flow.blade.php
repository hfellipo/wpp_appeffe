<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-3">
                <a href="{{ route('automacao.index') }}"
                   class="text-gray-500 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                   title="{{ __('Voltar para automações') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $automation->name }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">{{ __('Editor de jornada') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                {{-- Toggle ativo/pausado --}}
                <form action="{{ route('automacao.toggle', $automation) }}" method="POST" class="inline">
                    @csrf
                    @if($automation->is_active)
                        <button type="submit" class="btn-secondary text-sm inline-flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            {{ __('Ativa') }}
                        </button>
                    @else
                        <button type="submit" class="btn-secondary text-sm inline-flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                            {{ __('Pausada') }}
                        </button>
                    @endif
                </form>

                <a href="{{ route('automacao.test', $automation) }}"
                   class="btn-primary text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Testar') }}
                </a>
            </div>
        </div>
    </x-slot>

    {{-- Full-height canvas: subtract header (~64px nav + ~65px page header) --}}
    <div style="height: calc(100vh - 8rem);" id="automation-flow-root"></div>

    <script>
        window.AUTOMATION_FLOW = {!! json_encode($flowConfig) !!};
    </script>

    @vite(['resources/css/app.css', 'resources/js/automation-flow.jsx'])
</x-app-layout>
