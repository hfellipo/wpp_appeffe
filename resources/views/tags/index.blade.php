<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Tags') }}
            </h2>
            <a href="{{ route('tags.create') }}" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                {{ __('Nova Tag') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="alert-success mb-6">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Lista de tags + filtro --}}
                <div class="lg:col-span-1">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Filtrar por tag') }}</h3>
                        </div>
                        <div class="p-4">
                            <a href="{{ route('tags.index') }}" class="block px-4 py-2 rounded-lg text-sm font-medium {{ !$selectedTag ? 'bg-brand-100 text-brand-800' : 'text-gray-700 hover:bg-gray-100' }}">
                                {{ __('Todos os contatos') }}
                            </a>
                            @foreach($tags as $tag)
                                <a href="{{ route('tags.index', ['tag' => $tag->id]) }}" class="mt-1 flex items-center justify-between px-4 py-2 rounded-lg text-sm font-medium {{ $selectedTag && (int)$selectedTag->id === (int)$tag->id ? 'bg-brand-100 text-brand-800' : 'text-gray-700 hover:bg-gray-100' }}">
                                    <span class="flex items-center gap-2">
                                        @if($tag->color)
                                            <span class="w-3 h-3 rounded-full shrink-0" style="background-color: {{ $tag->color }}"></span>
                                        @endif
                                        {{ $tag->name }}
                                    </span>
                                    <span class="text-gray-500 text-xs">({{ $tag->contacts_count }})</span>
                                </a>
                            @endforeach
                            @if($tags->isEmpty())
                                <p class="text-sm text-gray-500 mt-2">{{ __('Nenhuma tag ainda.') }} <a href="{{ route('tags.create') }}" class="text-brand-600 hover:underline">{{ __('Criar tag') }}</a></p>
                            @endif
                        </div>
                    </div>

                    {{-- Ações por tag (quando uma tag está selecionada) --}}
                    @if($selectedTag)
                        <div class="mt-4 flex flex-col gap-2">
                            <a href="{{ route('tags.contacts.edit', $selectedTag) }}" class="btn-secondary text-center">
                                {{ __('Gerir contatos desta tag') }}
                            </a>
                            <a href="{{ route('tags.edit', $selectedTag) }}" class="btn-secondary text-center">
                                {{ __('Editar tag') }}
                            </a>
                            <form action="{{ route('tags.destroy', $selectedTag) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('Tem certeza que deseja excluir esta tag?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full px-4 py-2 border border-red-300 rounded-md text-red-700 hover:bg-red-50 text-sm">
                                    {{ __('Excluir tag') }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                {{-- Contatos (todos ou filtrados pela tag) --}}
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">
                                @if($selectedTag)
                                    {{ __('Contatos com a tag') }}: {{ $selectedTag->name }}
                                @else
                                    {{ __('Contatos') }}
                                @endif
                            </h3>
                            <p class="text-sm text-gray-500">
                                @if($selectedTag)
                                    {{ $contacts->count() }} {{ __('contato(s)') }}
                                @else
                                    {{ __('Selecione uma tag à esquerda para filtrar os contatos.') }}
                                @endif
                            </p>
                        </div>
                        <div class="overflow-x-auto">
                            @if($selectedTag && $contacts->isEmpty())
                                <div class="px-6 py-12 text-center text-gray-500">
                                    <p>{{ __('Nenhum contato com esta tag.') }}</p>
                                    <a href="{{ route('tags.contacts.edit', $selectedTag) }}" class="mt-2 inline-block text-brand-600 hover:underline">{{ __('Adicionar contatos') }}</a>
                                </div>
                            @elseif($selectedTag && $contacts->isNotEmpty())
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Nome') }}</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Telefone') }}</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('E-mail') }}</th>
                                            <th class="relative px-6 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($contacts as $contact)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $contact->name }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $contact->formatted_phone ?? $contact->phone }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $contact->email ?? '-' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                                    <a href="{{ route('contacts.edit', $contact) }}" class="text-brand-600 hover:text-brand-900 text-sm">{{ __('Editar') }}</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="px-6 py-12 text-center text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                    <p class="mt-4">{{ __('Selecione uma tag para ver os contatos.') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
