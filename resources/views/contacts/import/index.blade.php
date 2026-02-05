<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Importar Contatos') }}
            </h2>
            <a href="{{ route('contacts.index') }}" class="btn-secondary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ __('Voltar') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ listaOption: '{{ old('lista_option', 'none') }}' }">
        <style>[x-cloak]{display:none!important}</style>
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Como funciona a importação') }}</h3>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>{{ __('Faça upload de uma planilha Excel (.xlsx, .xls) ou CSV') }}</li>
                            <li>{{ __('A primeira linha deve conter os cabeçalhos das colunas') }}</li>
                            <li>{{ __('Você poderá mapear as colunas para os campos do sistema') }}</li>
                            <li>{{ __('Nome e Telefone são obrigatórios') }}</li>
                            <li>{{ __('O telefone deve estar no formato (XX)XXXXX-XXXX ou apenas números') }}</li>
                        </ul>
                    </div>

                    <div class="mb-6 p-4 bg-lime-50 rounded-lg border border-lime-200">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-lime-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-lime-800 font-medium">{{ __('Dica: Baixe o modelo de planilha para facilitar a importação') }}</span>
                        </div>
                        <a href="{{ route('contacts.import.template') }}" class="mt-2 inline-flex items-center text-lime-700 hover:text-lime-900">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            {{ __('Baixar modelo de planilha') }}
                        </a>
                    </div>

                    <form action="{{ route('contacts.import.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-6">
                            <x-input-label for="file" :value="__('Selecione o arquivo')" />
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-brand-400 transition-colors">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="file" class="relative cursor-pointer bg-white rounded-md font-medium text-brand-600 hover:text-brand-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-brand-500">
                                            <span>{{ __('Clique para selecionar') }}</span>
                                            <input id="file" name="file" type="file" class="sr-only" accept=".xlsx,.xls,.csv" required>
                                        </label>
                                        <p class="pl-1">{{ __('ou arraste e solte') }}</p>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        {{ __('XLSX, XLS ou CSV até 10MB') }}
                                    </p>
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('file')" class="mt-2" />
                        </div>

                        <div id="file-name" class="hidden mb-4 p-3 bg-brand-50 rounded-lg flex items-center justify-between">
                            <span class="text-sm text-brand-800 font-medium"></span>
                            <button type="button" onclick="clearFile()" class="text-brand-600 hover:text-brand-800">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        {{-- Atribuir contatos importados a uma lista --}}
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">{{ __('Atribuir a uma lista') }}</h4>
                            <p class="text-sm text-gray-500 mb-4">{{ __('Opcional: os contatos importados podem ser adicionados a uma lista existente ou a uma nova.') }}</p>
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input type="radio" name="lista_option" value="none" x-model="listaOption" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span class="ml-2 text-sm text-gray-700">{{ __('Não atribuir a nenhuma lista') }}</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="lista_option" value="existing" x-model="listaOption" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span class="ml-2 text-sm text-gray-700">{{ __('Lista existente') }}</span>
                                </label>
                                <div x-show="listaOption === 'existing'" x-cloak class="ml-6 mt-2">
                                    <select name="lista_id" class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 block w-full max-w-xs text-sm">
                                        <option value="">{{ __('Selecione a lista') }}</option>
                                        @foreach($listas as $l)
                                            <option value="{{ $l->id }}" {{ old('lista_id') == $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
                                        @endforeach
                                    </select>
                                    @if($listas->isEmpty())
                                        <p class="mt-1 text-xs text-amber-600">{{ __('Você ainda não tem listas.') }} <a href="{{ route('listas.create') }}" class="underline">{{ __('Criar lista') }}</a></p>
                                    @endif
                                    <x-input-error :messages="$errors->get('lista_id')" class="mt-1" />
                                </div>
                                <label class="flex items-center">
                                    <input type="radio" name="lista_option" value="new" x-model="listaOption" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span class="ml-2 text-sm text-gray-700">{{ __('Criar nova lista') }}</span>
                                </label>
                                <div x-show="listaOption === 'new'" x-cloak class="ml-6 mt-2">
                                    <x-text-input
                                        name="new_list_name"
                                        type="text"
                                        class="block w-full max-w-xs text-sm"
                                        :value="old('new_list_name')"
                                        placeholder="{{ __('Nome da nova lista') }}"
                                    />
                                    <x-input-error :messages="$errors->get('new_list_name')" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                {{ __('Enviar e Continuar') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const fileNameDiv = document.getElementById('file-name');
            if (fileName) {
                fileNameDiv.classList.remove('hidden');
                fileNameDiv.querySelector('span').textContent = fileName;
            } else {
                fileNameDiv.classList.add('hidden');
            }
        });

        function clearFile() {
            document.getElementById('file').value = '';
            document.getElementById('file-name').classList.add('hidden');
        }
    </script>
</x-app-layout>
