<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Editar funil') }}
            </h2>
            <a href="{{ route('funis.show', $funnel) }}" class="btn-secondary">
                {{ __('Voltar ao quadro') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="alert-success mb-6">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('funis.update', $funnel) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nome do funil')" />
                            <x-text-input
                                id="name"
                                name="name"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('name', $funnel->name)"
                                required
                                autofocus
                            />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-between gap-3 mt-6">
                            <form action="{{ route('funis.destroy', $funnel) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('Excluir este funil e todos os leads?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900 text-sm">
                                    {{ __('Excluir funil') }}
                                </button>
                            </form>
                            <div class="flex gap-3">
                                <a href="{{ route('funis.index') }}" class="btn-secondary">
                                    {{ __('Cancelar') }}
                                </a>
                                <x-primary-button type="submit">
                                    {{ __('Salvar') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
