<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="{{ route('automacao.flow', $automation) }}"
                   class="text-gray-500 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                   title="{{ __('Voltar ao flow') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Testar flow') }}: {{ $automation->name }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">{{ __('Executa o fluxo ignorando o gatilho') }}</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-5 py-4 text-emerald-800 flex items-start gap-3">
                    <svg class="w-5 h-5 mt-0.5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="font-semibold">{{ __('Fluxo executado') }}</p>
                        <p class="text-sm mt-0.5">{{ session('success') }}</p>
                    </div>
                </div>
            @endif
            @if(session('error'))
                <div class="rounded-xl bg-red-50 border border-red-200 px-5 py-4 text-red-800 flex items-start gap-3">
                    <svg class="w-5 h-5 mt-0.5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="font-semibold">{{ __('Erro ao executar') }}</p>
                        <p class="text-sm mt-0.5">{{ session('error') }}</p>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Selecionar contato para o teste') }}</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ __('O fluxo será executado a partir do nó de início, ignorando o gatilho. Se o contato já testou antes, o run anterior é removido automaticamente.') }}
                    </p>
                </div>

                <form action="{{ route('automacao.flow.runTest', $automation) }}" method="POST" class="p-6 space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="contact_id" :value="__('Contato')" />
                        <select name="contact_id" id="contact_id"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm"
                                required>
                            <option value="">{{ __('— Selecionar contato —') }}</option>
                            @foreach($contacts as $c)
                                <option value="{{ $c->id }}" {{ old('contact_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}@if($c->phone) — {{ $c->phone }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('contact_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-1">
                        <a href="{{ route('automacao.flow', $automation) }}"
                           class="text-sm text-gray-500 hover:text-gray-700">
                            {{ __('← Voltar ao flow') }}
                        </a>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ __('Executar flow') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-xl bg-amber-50 border border-amber-200 px-5 py-4 text-amber-800 text-sm">
                <p class="font-semibold mb-1">{{ __('Sobre delays no teste') }}</p>
                <p>{{ __('Nós de "Aguardar" pausam a execução e criam um agendamento. Para retomar após o delay sem esperar o cron, acesse:') }}</p>
                <code class="block mt-2 bg-amber-100 rounded px-2 py-1 text-xs font-mono break-all">
                    {{ url('/automacao/jornada/cron') }}?token={{ config('services.scheduled_posts_cron_token') }}
                </code>
            </div>
        </div>
    </div>
</x-app-layout>
