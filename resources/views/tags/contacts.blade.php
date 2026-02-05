<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Contatos da tag') }}: {{ $tag->name }}
            </h2>
            <a href="{{ route('tags.index', ['tag' => $tag->id]) }}" class="btn-secondary">
                {{ __('Voltar') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <p class="text-gray-600 mb-6">
                {{ __('Marque os contatos que devem ter esta tag.') }}
            </p>

            <form action="{{ route('tags.contacts.update', $tag) }}" method="POST">
                @csrf

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 max-h-96 overflow-y-auto">
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

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('tags.index', ['tag' => $tag->id]) }}" class="btn-secondary">{{ __('Cancelar') }}</a>
                    <x-primary-button type="submit">{{ __('Salvar contatos da tag') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
