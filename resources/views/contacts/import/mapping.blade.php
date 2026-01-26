<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Importar Contatos') }}
            </h2>
            <a href="{{ route('contacts.import.index') }}" class="btn-secondary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ __('Voltar') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="importMapping()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('contacts.import.process') }}" method="POST">
                @csrf
                
                <!-- Hidden fields to preserve session data -->
                <input type="hidden" name="import_file" value="{{ session('import_file') }}">
                <input type="hidden" name="import_headers" value="{{ json_encode(session('import_headers', [])) }}">

                <!-- Instruções -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="text-sm text-blue-700">
                            <p class="font-medium mb-1">{{ __('Como funciona:') }}</p>
                            <ul class="list-disc list-inside space-y-1 text-blue-600">
                                <li>{{ __('Selecione as colunas que deseja importar') }}</li>
                                <li>{{ __('Escolha para qual campo do sistema cada coluna deve ir') }}</li>
                                <li>{{ __('Marque os campos que são obrigatórios (linhas com valores vazios serão ignoradas)') }}</li>
                                <li>{{ __('Campos novos serão criados automaticamente se você escolher "Criar novo campo"') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Colunas do Arquivo (Espelho) -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                {{ __('Colunas do Arquivo') }}
                            </h3>

                            <div class="space-y-3">
                                @foreach($headers as $index => $header)
                                    <div class="border rounded-lg p-4 transition-all duration-200"
                                         :class="columns[{{ $index }}].selected ? 'border-brand-500 bg-brand-50' : 'border-gray-200 hover:border-gray-300'">
                                        
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center">
                                                <input 
                                                    type="checkbox" 
                                                    id="select_{{ $index }}"
                                                    x-model="columns[{{ $index }}].selected"
                                                    class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 h-5 w-5"
                                                >
                                                <label for="select_{{ $index }}" class="ml-3 cursor-pointer">
                                                    <span class="bg-gray-100 text-gray-600 text-xs font-mono px-2 py-1 rounded">
                                                        Col {{ $index + 1 }}
                                                    </span>
                                                    <span class="ml-2 font-medium text-gray-900">
                                                        {{ $header ?: '(Sem nome)' }}
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Exemplo de dados -->
                                        <div class="mt-2 ml-8 text-xs text-gray-500">
                                            <span class="font-medium">{{ __('Exemplo:') }}</span>
                                            @php
                                                $examples = array_slice(array_column($preview, $index), 0, 2);
                                                $examples = array_filter($examples, fn($v) => !empty(trim($v ?? '')));
                                            @endphp
                                            <span class="italic">
                                                {{ implode(', ', array_slice($examples, 0, 2)) ?: '(vazio)' }}
                                            </span>
                                        </div>

                                        <!-- Configuração quando selecionado -->
                                        <div x-show="columns[{{ $index }}].selected" x-cloak class="mt-4 ml-8 space-y-3 border-t pt-3">
                                            <!-- Mapear para -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    {{ __('Importar como:') }}
                                                </label>
                                                <select 
                                                    x-model="columns[{{ $index }}].mapTo"
                                                    class="w-full text-sm border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                                                >
                                                    <optgroup label="{{ __('Campos Padrão') }}">
                                                        <option value="name">📛 {{ __('Nome') }}</option>
                                                        <option value="phone">📱 {{ __('Telefone') }}</option>
                                                        <option value="email">📧 {{ __('E-mail') }}</option>
                                                        <option value="notes">📝 {{ __('Observações') }}</option>
                                                    </optgroup>
                                                    @if($customFields->count() > 0)
                                                        <optgroup label="{{ __('Campos Personalizados Existentes') }}">
                                                            @foreach($customFields as $field)
                                                                <option value="field_{{ $field->id }}">{{ $field->name }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                    <optgroup label="{{ __('Novo') }}">
                                                        <option value="new">✨ {{ __('Criar novo campo') }}</option>
                                                    </optgroup>
                                                </select>
                                            </div>

                                            <!-- Obrigatório -->
                                            <div class="flex items-center">
                                                <input 
                                                    type="checkbox" 
                                                    id="required_{{ $index }}"
                                                    x-model="columns[{{ $index }}].required"
                                                    class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500"
                                                >
                                                <label for="required_{{ $index }}" class="ml-2 text-sm text-gray-700">
                                                    {{ __('Campo obrigatório') }}
                                                    <span class="text-gray-500">({{ __('linhas vazias serão ignoradas') }})</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Hidden inputs -->
                                        <template x-if="columns[{{ $index }}].selected">
                                            <div>
                                                <input type="hidden" name="columns[{{ $index }}][index]" value="{{ $index }}">
                                                <input type="hidden" name="columns[{{ $index }}][header]" value="{{ $header }}">
                                                <input type="hidden" name="columns[{{ $index }}][mapTo]" :value="columns[{{ $index }}].mapTo">
                                                <input type="hidden" name="columns[{{ $index }}][required]" :value="columns[{{ $index }}].required ? '1' : '0'">
                                            </div>
                                        </template>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Preview e Resumo -->
                    <div class="space-y-6">
                        <!-- Resumo da Importação -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                    </svg>
                                    {{ __('Resumo') }}
                                </h3>

                                <div class="space-y-4">
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <span class="text-gray-600">{{ __('Colunas selecionadas') }}</span>
                                        <span class="font-semibold text-brand-600" x-text="selectedCount"></span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <span class="text-gray-600">{{ __('Campos obrigatórios') }}</span>
                                        <span class="font-semibold text-red-600" x-text="requiredCount"></span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <span class="text-gray-600">{{ __('Novos campos a criar') }}</span>
                                        <span class="font-semibold text-golden-600" x-text="newFieldsCount"></span>
                                    </div>
                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-gray-600">{{ __('Linhas no arquivo') }}</span>
                                        <span class="font-semibold text-gray-900">{{ count($preview) + 1 }}+</span>
                                    </div>
                                </div>

                                <!-- Campos que serão importados -->
                                <div class="mt-6" x-show="selectedCount > 0">
                                    <h4 class="font-medium text-gray-700 mb-2">{{ __('Mapeamento:') }}</h4>
                                    <div class="bg-gray-50 rounded-lg p-3 space-y-1 text-sm">
                                        @foreach($headers as $index => $header)
                                            <div x-show="columns[{{ $index }}].selected" class="flex items-center justify-between">
                                                <span class="text-gray-600">{{ $header ?: "(Coluna ".($index+1).")" }}</span>
                                                <span class="text-brand-600">→</span>
                                                <span class="font-medium" x-text="getFieldLabel(columns[{{ $index }}].mapTo)"></span>
                                                <span x-show="columns[{{ $index }}].required" class="text-red-500 text-xs">*</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview dos dados -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    {{ __('Prévia dos Dados') }}
                                </h3>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                @foreach($headers as $index => $header)
                                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase"
                                                        :class="columns[{{ $index }}].selected ? 'text-brand-700 bg-brand-50' : 'text-gray-400'">
                                                        {{ $header ?: "(Col ".($index+1).")" }}
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach(array_slice($preview, 0, 3) as $row)
                                                <tr>
                                                    @foreach($headers as $index => $header)
                                                        <td class="px-3 py-2 whitespace-nowrap"
                                                            :class="columns[{{ $index }}].selected ? 'text-gray-900' : 'text-gray-300'">
                                                            {{ Str::limit($row[$index] ?? '-', 20) }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">{{ __('Mostrando 3 primeiras linhas') }}</p>
                            </div>
                        </div>

                        <!-- Botões de ação -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('contacts.import.index') }}" class="btn-secondary">
                                {{ __('Cancelar') }}
                            </a>
                            <button 
                                type="submit"
                                class="btn-primary"
                                :disabled="selectedCount === 0"
                                :class="{ 'opacity-50 cursor-not-allowed': selectedCount === 0 }"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                {{ __('Importar Contatos') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function importMapping() {
            const fieldLabels = {
                'name': '📛 Nome',
                'phone': '📱 Telefone',
                'email': '📧 E-mail',
                'notes': '📝 Observações',
                'new': '✨ Novo campo',
                @foreach($customFields as $field)
                'field_{{ $field->id }}': '{{ $field->name }}',
                @endforeach
            };

            return {
                columns: {
                    @foreach($headers as $index => $header)
                    {{ $index }}: {
                        selected: false,
                        mapTo: 'new',
                        required: false
                    },
                    @endforeach
                },
                get selectedCount() {
                    return Object.values(this.columns).filter(c => c.selected).length;
                },
                get requiredCount() {
                    return Object.values(this.columns).filter(c => c.selected && c.required).length;
                },
                get newFieldsCount() {
                    return Object.values(this.columns).filter(c => c.selected && c.mapTo === 'new').length;
                },
                getFieldLabel(mapTo) {
                    return fieldLabels[mapTo] || mapTo;
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
