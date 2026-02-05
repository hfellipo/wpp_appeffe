<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nova Tag') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('tags.store') }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nome da tag')" />
                            <x-text-input
                                id="name"
                                name="name"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('name')"
                                required
                                autofocus
                                placeholder="{{ __('Ex: VIP, Cliente, Fornecedor') }}"
                            />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="color" :value="__('Cor (opcional)')" />
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" id="color-picker" value="{{ old('color', '#3B82F6') }}" class="h-10 w-14 rounded border border-gray-300 cursor-pointer">
                                <x-text-input
                                    id="color"
                                    name="color"
                                    type="text"
                                    class="block w-32 font-mono"
                                    :value="old('color', '#3B82F6')"
                                    placeholder="#3B82F6"
                                    maxlength="7"
                                />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Formato: #RRGGBB (ex: #3B82F6)') }}</p>
                            <x-input-error :messages="$errors->get('color')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-3 mt-6">
                            <a href="{{ route('tags.index') }}" class="btn-secondary">{{ __('Cancelar') }}</a>
                            <x-primary-button type="submit">{{ __('Criar tag') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('color-picker')?.addEventListener('input', function() {
            document.getElementById('color').value = this.value;
        });
        document.getElementById('color')?.addEventListener('input', function() {
            var picker = document.getElementById('color-picker');
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) picker.value = this.value;
        });
    </script>
</x-app-layout>
