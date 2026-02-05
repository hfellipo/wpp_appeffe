<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nova automação') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    {{ __('Em seguida você configurará:') }}
                    <strong>{{ __('Gatilho') }}</strong> (quando disparar),
                    <strong>{{ __('Condição') }}</strong> (quem deve receber) e
                    <strong>{{ __('Ação') }}</strong> (o que fazer, ex: enviar mensagem WhatsApp).
                </p>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('automacao.store') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nome da automação')" />
                            <x-text-input
                                id="name"
                                name="name"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('name')"
                                required
                                autofocus
                                placeholder="{{ __('Ex: Boas-vindas ao entrar na lista') }}"
                            />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('automacao.index') }}" class="btn-secondary">{{ __('Cancelar') }}</a>
                            <x-primary-button type="submit">{{ __('Criar e configurar') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
