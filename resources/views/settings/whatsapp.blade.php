<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Configuração do WhatsApp') }}
            </h2>
            <a href="{{ route('settings.index') }}" class="btn-secondary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ __('Voltar') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="whatsappConfig()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="bg-brand-100 border border-brand-400 text-brand-700 px-4 py-3 rounded relative">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Success message from Alpine.js -->
            <div 
                x-show="successMessage"
                x-transition
                class="bg-brand-100 border border-brand-400 text-brand-700 px-4 py-3 rounded relative"
                style="display: none;"
            >
                <span x-text="successMessage"></span>
            </div>

            @php
                $isNotConfigured = !($configured ?? true);
            @endphp
            @if($isNotConfigured)
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <div>
                            <p class="font-medium">{{ __('Evolution API não configurada') }}</p>
                            <p class="text-sm mt-1">
                                {{ __('Por favor, configure as variáveis EVOLUTION_API_URL e EVOLUTION_API_KEY no arquivo .env') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Status da Conexão -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ __('Status da Conexão') }}
                    </h3>

                    <div class="space-y-4">
                        <!-- Número do WhatsApp -->
                        <div x-show="connectionStatus === 'close' || connectionStatus === 'not_found'">
                            <x-input-label for="whatsapp_number" :value="__('Número do WhatsApp')" />
                            <x-text-input 
                                id="whatsapp_number" 
                                type="text" 
                                class="mt-1 block w-full" 
                                placeholder="5511999999999"
                                x-model="whatsappNumber"
                                maxlength="20"
                            />
                            <p class="mt-1 text-sm text-gray-500">
                                {{ __('Digite o número com código do país (ex: 5511999999999)') }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">{{ __('Instância') }}:</span>
                            <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded" x-text="currentInstanceName || '{{ $instanceName }}'"></span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">{{ __('Status') }}:</span>
                            <span 
                                class="px-3 py-1 rounded-full text-sm font-medium"
                                :class="{
                                    'bg-green-100 text-green-800': connectionStatus === 'open',
                                    'bg-yellow-100 text-yellow-800': connectionStatus === 'connecting',
                                    'bg-red-100 text-red-800': connectionStatus === 'close' || connectionStatus === 'not_found'
                                }"
                                x-text="getStatusLabel(connectionStatus)"
                            ></span>
                        </div>

                        <!-- QR Code -->
                        <div x-show="showQrCode && qrCode" class="mt-4 p-4 bg-gray-50 rounded-lg text-center">
                            <p class="text-sm text-gray-600 mb-3">{{ __('Escaneie o QR Code com seu WhatsApp:') }}</p>
                            <div class="flex justify-center">
                                <img 
                                    :src="qrCode" 
                                    alt="QR Code"
                                    class="border-2 border-gray-300 rounded-lg p-2 bg-white max-w-xs"
                                    x-on:error="showQrCode = false"
                                >
                            </div>
                            <p class="text-xs text-gray-500 mt-3">
                                {{ __('1. Abra o WhatsApp no seu celular') }}<br>
                                {{ __('2. Toque em Menu ou Configurações e selecione Aparelhos conectados') }}<br>
                                {{ __('3. Toque em Conectar um aparelho') }}<br>
                                {{ __('4. Aponte seu celular para esta tela para capturar o código') }}
                            </p>
                        </div>
                        
                        <!-- QR Code Loading -->
                        <div x-show="connectionStatus === 'connecting' && !qrCode" class="mt-4 p-4 bg-gray-50 rounded-lg text-center">
                            <p class="text-sm text-gray-600 mb-3">{{ __('Aguardando QR Code...') }}</p>
                            <div class="flex justify-center">
                                <svg class="animate-spin h-8 w-8 text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex space-x-3 mt-4">
                            <button 
                                @click="connect()"
                                x-show="connectionStatus === 'close' || connectionStatus === 'not_found'"
                                class="btn-primary"
                                :disabled="loading || connectionStatus === 'not_configured'"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                                </svg>
                                <span x-show="!loading">{{ __('Conectar WhatsApp') }}</span>
                                <span x-show="loading">{{ __('Conectando...') }}</span>
                            </button>

                            <button 
                                @click="getQrCode()"
                                x-show="connectionStatus === 'connecting'"
                                class="btn-secondary"
                                :disabled="loading"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                </svg>
                                {{ __('Atualizar QR Code') }}
                            </button>

                            <button 
                                @click="logout()"
                                x-show="connectionStatus === 'open'"
                                class="btn-secondary"
                                :disabled="loading"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                {{ __('Desconectar') }}
                            </button>

                            <button 
                                @click="checkStatus()"
                                class="btn-secondary"
                                :disabled="loading"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                {{ __('Atualizar Status') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuração do Webhook -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                        {{ __('Configuração do Webhook') }}
                    </h3>

                    <form action="{{ route('whatsapp.webhook.configure') }}" method="POST">
                        @csrf

                        <div class="space-y-4">
                            @php
                                if (is_array($webhook) && isset($webhook['url'])) {
                                    $webhookUrl = old('url', $webhook['url']);
                                } else {
                                    $webhookUrl = old('url', route('evolution.webhook'));
                                }
                                
                                if (is_array($webhook) && isset($webhook['webhook_base64'])) {
                                    $webhookBase64 = old('webhook_base64', $webhook['webhook_base64']);
                                } else {
                                    $webhookBase64 = old('webhook_base64', false);
                                }
                            @endphp
                            <!-- Webhook URL -->
                            <div>
                                <x-input-label for="webhook_url" :value="__('URL do Webhook')" />
                                <x-text-input 
                                    id="webhook_url" 
                                    name="url" 
                                    type="url" 
                                    class="mt-1 block w-full" 
                                    :value="$webhookUrl"
                                    required
                                />
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ __('URL que receberá os eventos do WhatsApp') }}
                                </p>
                            </div>

                            <!-- Webhook Base64 -->
                            <div class="flex items-center">
                                <input 
                                    id="webhook_base64" 
                                    name="webhook_base64" 
                                    type="checkbox" 
                                    value="1"
                                    class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500"
                                    {!! $webhookBase64 ? 'checked' : '' !!}
                                >
                                <x-input-label for="webhook_base64" :value="__('Enviar mídia em Base64')" class="ml-2" />
                            </div>

                            <!-- Events -->
                            <div>
                                <x-input-label :value="__('Eventos do Webhook')" />
                                <p class="text-sm text-gray-500 mb-3">{{ __('Selecione os eventos que deseja receber:') }}</p>
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-4">
                                    @php
                                        $events = [
                                            'APPLICATION_STARTUP',
                                            'CALL',
                                            'CHATS_DELETE',
                                            'CHATS_SET',
                                            'CHATS_UPDATE',
                                            'CHATS_UPSERT',
                                            'CONNECTION_UPDATE',
                                            'CONTACTS_SET',
                                            'CONTACTS_UPDATE',
                                            'CONTACTS_UPSERT',
                                            'GROUP_PARTICIPANTS_UPDATE',
                                            'GROUP_UPDATE',
                                            'GROUPS_UPSERT',
                                            'LABELS_ASSOCIATION',
                                            'LABELS_EDIT',
                                            'LOGOUT_INSTANCE',
                                            'MESSAGES_DELETE',
                                            'MESSAGES_SET',
                                            'MESSAGES_UPDATE',
                                            'MESSAGES_UPSERT',
                                            'PRESENCE_UPDATE',
                                            'QRCODE_UPDATED',
                                            'REMOVE_INSTANCE',
                                            'SEND_MESSAGE',
                                            'TYPEBOT_CHANGE_STATUS',
                                            'TYPEBOT_START',
                                        ];
                                        $defaultEvents = ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'QRCODE_UPDATED', 'CONNECTION_UPDATE'];
                                        if (is_array($webhook) && isset($webhook['events']) && is_array($webhook['events'])) {
                                            $webhookEvents = $webhook['events'];
                                        } else {
                                            $webhookEvents = $defaultEvents;
                                        }
                                        $selectedEvents = old('events', $webhookEvents);
                                    @endphp

                                    @foreach($events as $event)
                                        <label class="flex items-center">
                                            <input 
                                                type="checkbox" 
                                                name="events[]" 
                                                value="{{ $event }}"
                                                class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500"
                                                {!! in_array($event, $selectedEvents) ? 'checked' : '' !!}
                                            >
                                            <span class="ml-2 text-sm text-gray-700">{{ $event }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="mt-2 flex space-x-2">
                                    <button 
                                        type="button"
                                        @click="markAllEvents(true)"
                                        class="text-sm text-brand-600 hover:text-brand-700 cursor-pointer underline"
                                    >
                                        {{ __('Marcar Todos') }}
                                    </button>
                                    <span class="text-gray-300">|</span>
                                    <button 
                                        type="button"
                                        @click="markAllEvents(false)"
                                        class="text-sm text-brand-600 hover:text-brand-700 cursor-pointer underline"
                                    >
                                        {{ __('Desmarcar Todos') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Submit -->
                            <div class="flex justify-end">
                                <x-primary-button>
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ __('Salvar Configurações') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @php
        $isConfigured = $configured ?? true;
        $configuredJs = $isConfigured ? 'true' : 'false';
    @endphp
    
    <script>
        function whatsappConfig() {
            return {
                connectionStatus: '{{ $status['status'] ?? 'not_found' }}',
                qrCode: null,
                showQrCode: false,
                loading: false,
                configured: {!! $configuredJs !!},
                statusCheckInterval: null,
                successMessage: null,
                whatsappNumber: '',
                currentInstanceName: '{{ $instanceName }}',

                init() {
                    this.checkStatus();
                    // Auto-refresh status every 5 seconds if connecting
                    const autoCheckInterval = setInterval(() => {
                        if (this.connectionStatus === 'connecting') {
                            this.checkStatus();
                        }
                    }, 5000);
                    
                    // Cleanup on component destroy
                    this.$watch('connectionStatus', (value) => {
                        if (value === 'open' && this.statusCheckInterval) {
                            clearInterval(this.statusCheckInterval);
                            this.statusCheckInterval = null;
                        }
                    });
                },

                getStatusLabel(status) {
                    const labels = {
                        'open': '{{ __('Conectado') }}',
                        'connecting': '{{ __('Conectando...') }}',
                        'close': '{{ __('Desconectado') }}',
                        'not_found': '{{ __('Não configurado') }}',
                        'not_configured': '{{ __('API não configurada') }}'
                    };
                    return labels[status] || status;
                },

                async connect() {
                    if (!this.configured || this.connectionStatus === 'not_configured') {
                        alert('{{ __('Evolution API não configurada. Verifique as variáveis no arquivo .env') }}');
                        return;
                    }

                    // Validate WhatsApp number
                    if (!this.whatsappNumber || this.whatsappNumber.trim() === '') {
                        alert('{{ __('Por favor, informe o número do WhatsApp') }}');
                        return;
                    }

                    // Clean number (only digits)
                    const cleanNumber = this.whatsappNumber.replace(/\D/g, '');
                    if (cleanNumber.length < 10) {
                        alert('{{ __('Número do WhatsApp inválido. Digite o número com código do país') }}');
                        return;
                    }

                    this.loading = true;
                    try {
                        // Collect webhook configuration from form
                        const webhookUrlInput = document.getElementById('webhook_url');
                        const webhookUrl = webhookUrlInput?.value?.trim() || '{{ route("evolution.webhook") }}';
                        const webhookBase64Input = document.getElementById('webhook_base64');
                        const webhookBase64 = webhookBase64Input?.checked || false;
                        const checkedEvents = Array.from(document.querySelectorAll('input[name="events[]"]:checked'));
                        const events = checkedEvents.map(cb => cb.value);
                        
                        // If no events selected, use default important events
                        const finalEvents = events.length > 0 ? events : [
                            'MESSAGES_UPSERT', 
                            'MESSAGES_UPDATE', 
                            'QRCODE_UPDATED', 
                            'CONNECTION_UPDATE'
                        ];

                        const formData = new FormData();
                        formData.append('whatsapp_number', cleanNumber);
                        formData.append('webhook_url', webhookUrl);
                        formData.append('webhook_base64', webhookBase64 ? '1' : '0');
                        finalEvents.forEach(event => formData.append('events[]', event));

                        const response = await fetch('{{ route("whatsapp.connect") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const data = await response.json();
                        
                        // Log do retorno JSON da API
                        console.log('=== RETORNO DA API DE CRIAR INSTÂNCIA ===');
                        console.log(data);
                        console.log('=========================================');

                        if (response.ok && data.success) {
                            // Update instance name
                            this.currentInstanceName = cleanNumber;
                            
                            // Update status
                            this.connectionStatus = data.status || 'connecting';
                            
                            // Show QR code if available
                            let qrCodeData = null;
                            if (data.qrcode) {
                                if (typeof data.qrcode === 'string') {
                                    qrCodeData = data.qrcode;
                                } else if (data.qrcode.base64) {
                                    qrCodeData = data.qrcode.base64;
                                } else if (data.qrcode.code) {
                                    qrCodeData = data.qrcode.code;
                                }
                            } else if (data.base64) {
                                qrCodeData = data.base64;
                            } else if (data.code) {
                                qrCodeData = data.code;
                            }
                            
                            const normalizedQr = this.normalizeQrCode(qrCodeData);
                            if (normalizedQr) {
                                this.qrCode = normalizedQr;
                                this.showQrCode = true;
                            } else {
                                // Try to get QR code if status is connecting
                                if (this.connectionStatus === 'connecting') {
                                    await this.getQrCode();
                                }
                            }
                            
                            // If already connected, hide QR code
                            if (this.connectionStatus === 'open') {
                                this.showQrCode = false;
                            }
                            
                            // Start checking status periodically if connecting
                            if (this.connectionStatus === 'connecting') {
                                // Clear any existing interval
                                if (this.statusCheckInterval) {
                                    clearInterval(this.statusCheckInterval);
                                }
                                
                                this.statusCheckInterval = setInterval(async () => {
                                    await this.checkStatus();
                                    if (this.connectionStatus === 'open') {
                                        clearInterval(this.statusCheckInterval);
                                        this.statusCheckInterval = null;
                                        this.showQrCode = false;
                                    }
                                }, 3000); // Check every 3 seconds
                            }
                            
                            // Show success notification
                            if (data.message) {
                                // Create a temporary success message
                                this.showSuccessMessage(data.message);
                            }

                            if (data.webhook_warning) {
                                alert(data.webhook_warning);
                            }
                            if (data.db_warning) {
                                alert(data.db_warning);
                            }
                        } else {
                            alert(data.error || 'Erro ao conectar WhatsApp');
                        }
                    } catch (e) {
                        console.error('Erro ao conectar:', e);
                        alert('Erro ao conectar WhatsApp. Tente novamente.');
                    } finally {
                        this.loading = false;
                    }
                },

                async getQrCode() {
                    try {
                        const response = await fetch('{{ route("whatsapp.qrcode") }}');
                        const data = await response.json();
                        
                        let qrCodeData = null;
                        if (data.qrcode) {
                            if (typeof data.qrcode === 'string') {
                                qrCodeData = data.qrcode;
                            } else if (data.qrcode.base64) {
                                qrCodeData = data.qrcode.base64;
                            } else if (data.qrcode.code) {
                                qrCodeData = data.qrcode.code;
                            }
                        } else if (data.code) {
                            qrCodeData = data.code;
                        } else if (data.base64) {
                            qrCodeData = data.base64;
                        }
                        
                        const normalizedQr = this.normalizeQrCode(qrCodeData);
                        if (normalizedQr) {
                            this.qrCode = normalizedQr;
                            this.showQrCode = true;
                        }
                    } catch (e) {
                        console.error('Erro ao obter QR Code:', e);
                    }
                },

                async checkStatus() {
                    try {
                        const response = await fetch('{{ route("whatsapp.status") }}');
                        const data = await response.json();
                        
                        if (data.status) {
                            const previousStatus = this.connectionStatus;
                            this.connectionStatus = data.status;
                            
                            if (data.status === 'open') {
                                this.showQrCode = false;
                            } else if (data.status === 'connecting' && previousStatus !== 'connecting') {
                                // Only fetch QR code if we just entered connecting state
                                await this.getQrCode();
                            }
                        }
                    } catch (e) {
                        console.error('Erro ao verificar status:', e);
                    }
                },

                async logout() {
                    if (!confirm('{{ __('Tem certeza que deseja desconectar o WhatsApp?') }}')) {
                        return;
                    }

                    this.loading = true;
                    try {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route("whatsapp.logout") }}';
                        
                        const csrf = document.createElement('input');
                        csrf.type = 'hidden';
                        csrf.name = '_token';
                        csrf.value = document.querySelector('meta[name="csrf-token"]').content;
                        form.appendChild(csrf);
                        
                        document.body.appendChild(form);
                        form.submit();
                    } catch (e) {
                        console.error('Erro ao desconectar:', e);
                        this.loading = false;
                    }
                },

                markAllEvents(checked) {
                    document.querySelectorAll('input[name="events[]"]').forEach(cb => {
                        cb.checked = checked;
                    });
                },

                showSuccessMessage(message) {
                    this.successMessage = message;
                    setTimeout(() => {
                        this.successMessage = null;
                    }, 5000); // Hide after 5 seconds
                },

                normalizeQrCode(qrCodeData) {
                    if (!qrCodeData || typeof qrCodeData !== 'string') {
                        return null;
                    }

                    const trimmed = qrCodeData.trim();
                    if (trimmed === '' || trimmed.toLowerCase().includes('undefined')) {
                        return null;
                    }

                    if (trimmed.startsWith('data:image')) {
                        return trimmed;
                    }

                    const base64Regex = /^[A-Za-z0-9+/=]+$/;
                    if (!base64Regex.test(trimmed)) {
                        return null;
                    }

                    return 'data:image/png;base64,' + trimmed;
                }
            }
        }
    </script>
</x-app-layout>
