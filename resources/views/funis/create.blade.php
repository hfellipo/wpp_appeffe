<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Novo funil') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('funis.store') }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nome do funil')" />
                            <x-text-input
                                id="name"
                                name="name"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('name')"
                                required
                                autofocus
                                placeholder="{{ __('Ex: Vendas B2B, Captação 2025') }}"
                            />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <p class="text-sm text-gray-500 mb-6">
                            {{ __('Serão criados os estágios: Leads de entrada, Decidindo, Discussão de contrato, Decisão final.') }}
                        </p>

                        <div class="flex items-center justify-end gap-3 mt-6">
                            <a href="{{ route('funis.index') }}" class="btn-secondary">
                                {{ __('Cancelar') }}
                            </a>
                            <x-primary-button type="submit">
                                {{ __('Criar funil') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
