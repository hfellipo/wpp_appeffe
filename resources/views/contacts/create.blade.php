<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Novo Contato') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('contacts.store') }}" method="POST">
                        @csrf

                        <!-- Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nome')" />
                            <x-text-input 
                                id="name" 
                                name="name" 
                                type="text" 
                                class="mt-1 block w-full" 
                                :value="old('name')" 
                                required 
                                autofocus 
                            />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Phone -->
                        <div class="mb-4">
                            <x-input-label for="phone" :value="__('Telefone')" />
                            <x-text-input 
                                id="phone" 
                                name="phone" 
                                type="text" 
                                class="mt-1 block w-full" 
                                :value="old('phone')" 
                                required
                                placeholder="(XX)XXXXX-XXXX"
                                x-data
                                x-mask="(99)99999-9999"
                            />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>

                        <!-- Email -->
                        <div class="mb-4">
                            <x-input-label for="email" :value="__('E-mail')" />
                            <x-text-input 
                                id="email" 
                                name="email" 
                                type="email" 
                                class="mt-1 block w-full" 
                                :value="old('email')" 
                            />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <!-- Notes -->
                        <div class="mb-4">
                            <x-input-label for="notes" :value="__('Observações')" />
                            <textarea 
                                id="notes" 
                                name="notes" 
                                rows="3"
                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                            >{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <!-- Custom Fields -->
                        @if($customFields->count() > 0)
                            <div class="border-t border-gray-200 pt-4 mt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Campos Personalizados') }}</h3>
                                
                                @foreach($customFields as $field)
                                    <div class="mb-4">
                                        <x-input-label :for="'field_' . $field->id" :value="$field->name" />
                                        
                                        @if($field->type === 'textarea')
                                            <textarea 
                                                id="field_{{ $field->id }}" 
                                                name="fields[{{ $field->id }}]" 
                                                rows="3"
                                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                                            >{{ old('fields.' . $field->id) }}</textarea>
                                        @elseif($field->type === 'select')
                                            <select 
                                                id="field_{{ $field->id }}" 
                                                name="fields[{{ $field->id }}]"
                                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                                            >
                                                <option value="">{{ __('Selecione...') }}</option>
                                                @foreach($field->options ?? [] as $option)
                                                    <option value="{{ $option }}" {{ old('fields.' . $field->id) === $option ? 'selected' : '' }}>
                                                        {{ $option }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif($field->type === 'date')
                                            <x-text-input 
                                                :id="'field_' . $field->id" 
                                                :name="'fields[' . $field->id . ']'" 
                                                type="date" 
                                                class="mt-1 block w-full" 
                                                :value="old('fields.' . $field->id)"
                                            />
                                        @elseif($field->type === 'number')
                                            <x-text-input 
                                                :id="'field_' . $field->id" 
                                                :name="'fields[' . $field->id . ']'" 
                                                type="number" 
                                                step="any"
                                                class="mt-1 block w-full" 
                                                :value="old('fields.' . $field->id)"
                                            />
                                        @else
                                            <x-text-input 
                                                :id="'field_' . $field->id" 
                                                :name="'fields[' . $field->id . ']'" 
                                                :type="$field->type === 'url' ? 'url' : ($field->type === 'email' ? 'email' : 'text')" 
                                                class="mt-1 block w-full" 
                                                :value="old('fields.' . $field->id)"
                                            />
                                        @endif

                                        <x-input-error :messages="$errors->get('fields.' . $field->id)" class="mt-2" />
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-center justify-end mt-6 space-x-3">
                            <a href="{{ route('contacts.index') }}" class="btn-secondary">
                                {{ __('Cancelar') }}
                            </a>
                            <x-primary-button>
                                {{ __('Salvar') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
