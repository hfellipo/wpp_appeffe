<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Editar Contato') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('contacts.update', $contact) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nome')" />
                            <x-text-input 
                                id="name" 
                                name="name" 
                                type="text" 
                                class="mt-1 block w-full" 
                                :value="old('name', $contact->name)" 
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
                                :value="old('phone', $contact->phone)" 
                                required
                                placeholder="(XX)XXXXX-XXXX"
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
                                :value="old('email', $contact->email)" 
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
                            >{{ old('notes', $contact->notes) }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <!-- Tags -->
                        @if(isset($tags) && $tags->count() > 0)
                            <div class="border-t border-gray-200 pt-4 mt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Tags') }}</h3>
                                <div class="flex flex-wrap gap-3">
                                    @foreach($tags as $tag)
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                                {{ in_array($tag->id, old('tag_ids', $contact->tags->pluck('id')->all())) ? 'checked' : '' }}>
                                            <span class="ml-2 text-sm flex items-center gap-1">
                                                @if($tag->color)
                                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $tag->color }}"></span>
                                                @endif
                                                {{ $tag->name }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Custom Fields -->
                        @if($customFields->count() > 0)
                            <div class="border-t border-gray-200 pt-4 mt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Campos Personalizados') }}</h3>
                                
                                @foreach($customFields as $field)
                                    @php
                                        $fieldValue = old('fields.' . $field->id, $contact->getFieldValue($field));
                                    @endphp
                                    <div class="mb-4">
                                        <x-input-label :for="'field_' . $field->id" :value="$field->name" />
                                        
                                        @if($field->type === 'textarea')
                                            <textarea 
                                                id="field_{{ $field->id }}" 
                                                name="fields[{{ $field->id }}]" 
                                                rows="3"
                                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                                                @if($field->required) required @endif
                                            >{{ $fieldValue }}</textarea>
                                        @elseif($field->type === 'select')
                                            <select 
                                                id="field_{{ $field->id }}" 
                                                name="fields[{{ $field->id }}]"
                                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                                                @if($field->required) required @endif
                                            >
                                                <option value="">{{ __('Selecione...') }}</option>
                                                @foreach($field->options ?? [] as $option)
                                                    <option value="{{ $option }}" {{ $fieldValue === $option ? 'selected' : '' }}>
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
                                                :value="$fieldValue"
                                                :required="$field->required"
                                            />
                                        @elseif($field->type === 'number')
                                            <x-text-input 
                                                :id="'field_' . $field->id" 
                                                :name="'fields[' . $field->id . ']'" 
                                                type="number" 
                                                step="any"
                                                class="mt-1 block w-full" 
                                                :value="$fieldValue"
                                                :required="$field->required"
                                            />
                                        @else
                                            <x-text-input 
                                                :id="'field_' . $field->id" 
                                                :name="'fields[' . $field->id . ']'" 
                                                :type="$field->type === 'url' ? 'url' : ($field->type === 'email' ? 'email' : 'text')" 
                                                class="mt-1 block w-full" 
                                                :value="$fieldValue"
                                                :required="$field->required"
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
                                {{ __('Atualizar') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
