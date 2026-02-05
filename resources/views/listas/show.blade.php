<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $lista->name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('listas.contacts.edit', $lista) }}" class="btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    {{ __('Adicionar contatos') }}
                </a>
                <a href="{{ route('listas.edit', $lista) }}" class="btn-secondary">
                    {{ __('Editar nome') }}
                </a>
                <a href="{{ route('listas.index') }}" class="btn-secondary">
                    {{ __('Voltar') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ formToSubmit: null }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="alert-success mb-6">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Contatos (tabela contacts) --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Contatos') }} ({{ $lista->contacts->count() }})</h3>
                    <p class="text-sm text-gray-500">{{ __('Contatos cadastrados na aplicação') }}</p>
                </div>
                <div class="overflow-x-auto">
                    @if($lista->contacts->isEmpty())
                        <div class="px-6 py-8 text-center text-gray-500">
                            {{ __('Nenhum contato nesta lista.') }}
                            <a href="{{ route('listas.contacts.edit', $lista) }}" class="text-brand-600 hover:text-brand-800 ml-1">{{ __('Adicionar') }}</a>
                        </div>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Nome') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Telefone') }}</th>
                                    <th class="relative px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($lista->contacts as $contact)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('contacts.show', $contact) }}" class="text-sm font-medium text-brand-600 hover:text-brand-800">{{ $contact->name }}</a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $contact->formatted_phone ?? $contact->phone }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('contacts.show', $contact) }}" class="text-gray-600 hover:text-gray-900 inline-flex items-center gap-1 mr-3" title="{{ __('Ver contato') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                {{ __('Ver') }}
                                            </a>
                                            <form action="{{ route('listas.contacts.detach-contact', $lista) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="contact_id" value="{{ $contact->id }}">
                                                <button type="button" class="text-red-600 hover:text-red-900 text-sm" @click="formToSubmit = $event.target.closest('form'); $dispatch('open-modal', 'confirm-remove-from-list')">
                                                    {{ __('Remover') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Contatos WhatsApp --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Contatos WhatsApp') }} ({{ $lista->whatsappContacts->count() }})</h3>
                    <p class="text-sm text-gray-500">{{ __('Contatos que aparecem no WhatsApp') }}</p>
                </div>
                <div class="overflow-x-auto">
                    @if($lista->whatsappContacts->isEmpty())
                        <div class="px-6 py-8 text-center text-gray-500">
                            {{ __('Nenhum contato WhatsApp nesta lista.') }}
                            <a href="{{ route('listas.contacts.edit', $lista) }}" class="text-brand-600 hover:text-brand-800 ml-1">{{ __('Adicionar') }}</a>
                        </div>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Nome') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Número') }}</th>
                                    <th class="relative px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($lista->whatsappContacts as $wa)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $wa->display_name ?: __('Sem nome') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $wa->contact_number ?: $wa->contact_jid }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <form action="{{ route('listas.contacts.detach-whatsapp', $lista) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="whatsapp_contact_id" value="{{ $wa->id }}">
                                                <button type="button" class="text-red-600 hover:text-red-900 text-sm" @click="formToSubmit = $event.target.closest('form'); $dispatch('open-modal', 'confirm-remove-from-list')">
                                                    {{ __('Remover') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <x-modal name="confirm-remove-from-list" maxWidth="sm">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ __('Remover da lista') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('Remover este contato da lista?') }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'confirm-remove-from-list')">
                        {{ __('Cancelar') }}
                    </x-secondary-button>
                    <x-danger-button type="button" x-on:click="if(formToSubmit) formToSubmit.submit(); $dispatch('close-modal', 'confirm-remove-from-list')">
                        {{ __('Remover') }}
                    </x-danger-button>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
