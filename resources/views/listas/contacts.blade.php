<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Contatos da lista') }}: {{ $lista->name }}
            </h2>
            <a href="{{ route('listas.show', $lista) }}" class="btn-secondary">
                {{ __('Voltar à lista') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <p class="text-gray-600 mb-6">
                {{ __('Marque os contatos que devem pertencer a esta lista. Contatos da aplicação e contatos do WhatsApp podem ser adicionados.') }}
            </p>

            <form action="{{ route('listas.contacts.update', $lista) }}" method="POST">
                @csrf

                {{-- Contatos (app) --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Contatos (aplicação)') }}</h3>
                        <p class="text-sm text-gray-500">{{ __('Contatos cadastrados em Contatos') }}</p>
                    </div>
                    <div class="p-6 max-h-64 overflow-y-auto">
                        @if($contacts->isEmpty())
                            <p class="text-gray-500">{{ __('Nenhum contato cadastrado.') }} <a href="{{ route('contacts.create') }}" class="text-brand-600">{{ __('Criar contato') }}</a></p>
                        @else
                            <ul class="space-y-2">
                                @foreach($contacts as $c)
                                    <li class="flex items-center">
                                        <input type="checkbox" name="contact_ids[]" value="{{ $c->id }}" id="contact-{{ $c->id }}"
                                            {{ in_array($c->id, $currentContactIds) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                        <label for="contact-{{ $c->id }}" class="ml-2 text-sm text-gray-700">
                                            {{ $c->name }}
                                            @if($c->phone)
                                                <span class="text-gray-500"> — {{ $c->phone }}</span>
                                            @endif
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                {{-- Contatos WhatsApp --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Contatos WhatsApp') }}</h3>
                        <p class="text-sm text-gray-500">{{ __('Contatos que aparecem nas conversas do WhatsApp') }}</p>
                    </div>
                    <div class="p-6 max-h-64 overflow-y-auto">
                        @if($whatsappContacts->isEmpty())
                            <p class="text-gray-500">{{ __('Nenhum contato WhatsApp ainda. Eles aparecem quando você conversa pelo WhatsApp.') }}</p>
                        @else
                            <ul class="space-y-2">
                                @foreach($whatsappContacts as $wa)
                                    <li class="flex items-center">
                                        <input type="checkbox" name="whatsapp_contact_ids[]" value="{{ $wa->id }}" id="wa-{{ $wa->id }}"
                                            {{ in_array($wa->id, $currentWaIds) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                        <label for="wa-{{ $wa->id }}" class="ml-2 text-sm text-gray-700">
                                            {{ $wa->display_name ?: __('Sem nome') }}
                                            @if($wa->contact_number)
                                                <span class="text-gray-500"> — {{ $wa->contact_number }}</span>
                                            @endif
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('listas.show', $lista) }}" class="btn-secondary">
                        {{ __('Cancelar') }}
                    </a>
                    <x-primary-button type="submit">
                        {{ __('Salvar contatos da lista') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
