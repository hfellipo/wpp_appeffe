<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Testar automação') }}: {{ $automation->name }}
            </h2>
            <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'action']) }}" class="btn-secondary">{{ __('Voltar') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Executar para um contato') }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ __('A automação será executada uma vez para o contato escolhido (todas as ações em sequência). Use para validar antes de depender do cron.') }}</p>
                </div>
                <form action="{{ route('automacao.runTest', $automation) }}" method="POST" class="p-6">
                    @csrf
                    <div class="mb-4">
                        <x-input-label for="contact_id" :value="__('Contato')" />
                        <select name="contact_id" id="contact_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                            <option value="">{{ __('Selecione um contato') }}</option>
                            @foreach($contacts as $c)
                                <option value="{{ $c->id }}" {{ old('contact_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }} @if($c->phone) — {{ $c->phone }} @endif
                                </option>
                            @endforeach
                        </select>
                        @error('contact_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'action']) }}" class="btn-secondary">{{ __('Cancelar') }}</a>
                        <x-primary-button type="submit">{{ __('Executar teste') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <p class="mt-6 text-sm text-gray-500">
                {{ __('A execução automática (cron) roda a cada minuto. O mesmo cron retoma ações após "Aguardar (delay)"') }}
                — <code class="bg-gray-100 px-1 rounded">php artisan schedule:run</code> {{ __('no crontab a cada minuto') }}.
            </p>
        </div>
    </div>
</x-app-layout>
