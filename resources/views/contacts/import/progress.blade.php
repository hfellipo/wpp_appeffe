<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Processando Importação') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12" x-data="importProgress()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Progress Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8">
                    <!-- Status -->
                    <div class="text-center mb-8">
                        <div x-show="!completed && !cancelled && !error" class="inline-block">
                            <svg class="animate-spin h-12 w-12 text-brand-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Processando...</h3>
                        </div>
                        
                        <div x-show="completed" class="inline-block">
                            <svg class="h-12 w-12 text-brand-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="text-lg font-medium text-brand-600 mb-2">Importação Concluída!</h3>
                        </div>
                        
                        <div x-show="cancelled" class="inline-block">
                            <svg class="h-12 w-12 text-yellow-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <h3 class="text-lg font-medium text-yellow-600 mb-2">Importação Cancelada</h3>
                        </div>
                        
                        <div x-show="error" class="inline-block">
                            <svg class="h-12 w-12 text-red-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="text-lg font-medium text-red-600 mb-2">Erro na Importação</h3>
                            <p class="text-sm text-red-600" x-text="errorMessage"></p>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mb-6" x-show="!completed && !cancelled && !error">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">
                                Processando linha <span x-text="processed"></span> de <span x-text="total"></span>
                            </span>
                            <span class="text-sm font-medium text-brand-600" x-text="progress + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                            <div 
                                class="bg-brand-600 h-4 rounded-full transition-all duration-300 ease-out"
                                :style="'width: ' + progress + '%'"
                            ></div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 mb-6" x-show="stats.imported > 0 || stats.updated > 0 || stats.skipped > 0">
                        <div class="bg-brand-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-brand-600" x-text="stats.imported || 0"></div>
                            <div class="text-sm text-gray-600">Novos</div>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-blue-600" x-text="stats.updated || 0"></div>
                            <div class="text-sm text-gray-600">Atualizados</div>
                        </div>
                        <div class="bg-yellow-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-yellow-600" x-text="stats.skipped || 0"></div>
                            <div class="text-sm text-gray-600">Ignorados</div>
                        </div>
                    </div>

                    <!-- Errors -->
                    <div x-show="errors.length > 0" class="mb-6">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h4 class="font-medium text-red-800 mb-2">Erros encontrados:</h4>
                            <ul class="text-sm text-red-700 space-y-1 max-h-40 overflow-y-auto">
                                <template x-for="error in errors" :key="error">
                                    <li x-text="error"></li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-center space-x-4">
                        <button 
                            x-show="!completed && !cancelled && !error"
                            @click="cancelImport()"
                            class="btn-secondary"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancelar Importação
                        </button>
                        
                        <a 
                            x-show="completed || cancelled"
                            href="{{ route('contacts.index') }}"
                            class="btn-primary"
                        >
                            Ver Contatos
                        </a>
                        
                        <a 
                            x-show="error"
                            href="{{ route('contacts.import.index') }}"
                            class="btn-secondary"
                        >
                            Tentar Novamente
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function importProgress() {
            return {
                completed: false,
                cancelled: false,
                error: false,
                errorMessage: '',
                progress: 0,
                processed: 0,
                total: {{ $totalRows }},
                stats: {
                    imported: 0,
                    updated: 0,
                    skipped: 0
                },
                errors: [],
                chunk: 0,
                processing: false,

                init() {
                    this.processNextChunk();
                },

                async processNextChunk() {
                    if (this.completed || this.cancelled || this.error || this.processing) {
                        return;
                    }

                    this.processing = true;

                    try {
                        const response = await fetch('{{ route("contacts.import.process-chunk") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                chunk: this.chunk
                            })
                        });

                        const data = await response.json();

                        if (data.cancelled) {
                            this.cancelled = true;
                            this.processing = false;
                            return;
                        }

                        if (data.error) {
                            this.error = true;
                            this.errorMessage = data.error;
                            this.processing = false;
                            return;
                        }

                        this.progress = data.progress || 0;
                        this.processed = data.processed || 0;
                        this.stats = data.stats || this.stats;
                        
                        if (data.errors && data.errors.length > 0) {
                            this.errors.push(...data.errors);
                        }

                        if (data.completed) {
                            this.completed = true;
                            this.progress = 100;
                            this.processing = false;
                            
                            // Redirect after 3 seconds
                            setTimeout(() => {
                                window.location.href = '{{ route("contacts.index") }}';
                            }, 3000);
                        } else {
                            this.chunk++;
                            this.processing = false;
                            // Process next chunk after a short delay
                            setTimeout(() => this.processNextChunk(), 100);
                        }
                    } catch (e) {
                        this.error = true;
                        this.errorMessage = 'Erro ao processar: ' + e.message;
                        this.processing = false;
                    }
                },

                async cancelImport() {
                    if (this.cancelled || this.completed) {
                        return;
                    }

                    try {
                        await fetch('{{ route("contacts.import.cancel") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });

                        this.cancelled = true;
                    } catch (e) {
                        console.error('Erro ao cancelar:', e);
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
