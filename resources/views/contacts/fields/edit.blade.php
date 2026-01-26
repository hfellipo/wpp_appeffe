<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Editar Campo') }}: {{ $field->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('contacts.fields.update', $field) }}" method="POST" x-data="{ fieldType: '{{ old('type', $field->type) }}' }">
                        @csrf
                        @method('PUT')

                        <!-- Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nome do Campo')" />
                            <x-text-input 
                                id="name" 
                                name="name" 
                                type="text" 
                                class="mt-1 block w-full" 
                                :value="old('name', $field->name)" 
                                required 
                                autofocus
                            />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Type -->
                        <div class="mb-4">
                            <x-input-label for="type" :value="__('Tipo do Campo')" />
                            <select 
                                id="type" 
                                name="type"
                                x-model="fieldType"
                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                                required
                            >
                                @foreach($types as $value => $label)
                                    <option value="{{ $value }}" {{ old('type', $field->type) === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <!-- Options (for select type) -->
                        <div class="mb-4" x-show="fieldType === 'select'" x-cloak>
                            <x-input-label for="options" :value="__('Opções (uma por linha)')" />
                            <textarea 
                                id="options" 
                                name="options" 
                                rows="4"
                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                            >{{ old('options', $field->options ? implode("\n", $field->options) : '') }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Digite cada opção em uma linha separada.') }}</p>
                            <x-input-error :messages="$errors->get('options')" class="mt-2" />
                        </div>

                        <!-- Required -->
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    name="required" 
                                    value="1"
                                    {{ old('required', $field->required) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500"
                                >
                                <span class="ml-2 text-sm text-gray-600">{{ __('Campo obrigatório') }}</span>
                            </label>
                        </div>

                        <!-- Show in List -->
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    name="show_in_list" 
                                    value="1"
                                    {{ old('show_in_list', $field->show_in_list) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500"
                                >
                                <span class="ml-2 text-sm text-gray-600">{{ __('Mostrar na listagem de contatos') }}</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end mt-6 space-x-3">
                            <a href="{{ route('contacts.fields.index') }}" class="btn-secondary">
                                {{ __('Cancelar') }}
                            </a>
                            <x-primary-button>
                                {{ __('Atualizar') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
