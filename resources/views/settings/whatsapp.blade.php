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

@php
    // Ative com ?debugAlerts=1 na URL
    $debugAlerts = request()->query('debugAlerts') === '1';
    // Identificador manual de build desta view (para confirmar se produção está servindo código atualizado)
    $whatsappViewBuild = 'wa-settings-2026-01-28-01';
@endphp

<script>
  // DEBUG fora do Alpine (provar que a view carregou)
  window.__WHATSAPP_DEBUG_ALERTS__ = {!! $debugAlerts ? 'true' : 'false' !!};
  window.__WHATSAPP_VIEW_BUILD__ = @json($whatsappViewBuild);
  if (window.__WHATSAPP_DEBUG_ALERTS__) {
    alert(
      'DEBUG: settings/whatsapp.blade.php foi carregado (debugAlerts=1)\n' +
      'build=' + window.__WHATSAPP_VIEW_BUILD__ + '\n' +
      'url=' + window.location.href
    );
  }
</script>

<div class="py-12" x-data="whatsappConfig()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if($debugAlerts)
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    <div class="text-sm font-mono">
                        DEBUG ATIVO (debugAlerts=1) — build: {{ $whatsappViewBuild }}
                    </div>
                </div>
            @endif
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

                        <!-- QR Code Modal (similar ao React) -->
                        <div 
                            x-show="qrModal.isOpen" 
                            x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 z-50 overflow-y-auto"
                            style="display: none;"
                            @click.away="qrModal.isOpen = false"
                        >
                            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                                <!-- Background overlay -->
                                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="qrModal.isOpen = false"></div>

                                <!-- Modal panel -->
                                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <!-- Header -->
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-lg font-medium text-gray-900">
                                                {{ __('Conectar WhatsApp') }}
                                            </h3>
                                            <button 
                                                @click="qrModal.isOpen = false"
                                                class="text-gray-400 hover:text-gray-500"
                                            >
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- QR Code Image -->
                                        <div x-show="qrModal.qrCode && !qrModal.pairingCode" class="text-center mb-4">
                                            <p class="text-sm text-gray-600 mb-3">{{ __('Escaneie o QR Code com seu WhatsApp:') }}</p>
                                            <div class="flex justify-center">
                                                {{-- ✅ Usar EXATAMENTE qrcode.base64 como vem da API (data:image/png;base64,...) --}}
                                                {{-- ❌ NÃO usar qrcode.code aqui - isso quebraria a imagem --}}
                                                <img 
                                                    :src="(() => { 
                                                        const v = qrModal.qrCode; 
                                                        if (!v || typeof v !== 'string' || !v.startsWith('data:image')) return ''; 
                                                        const comma = v.indexOf(','); 
                                                        if (comma === -1) return ''; 
                                                        const payload = v.substring(comma + 1); 
                                                        // Se o payload começa com dígito@, é pairing code e NÃO pode ir para <img>
                                                        if (/^\\d+@/.test(payload)) return ''; 
                                                        return v; 
                                                    })()" 
                                                    alt="QR Code"
                                                    class="border-2 border-gray-300 rounded-lg p-2 bg-white max-w-xs mx-auto"
                                                    x-on:error="handleQrCodeError($event)"
                                                >
                                            </div>
                                            <p class="text-xs text-gray-500 mt-3">
                                                {{ __('1. Abra o WhatsApp no seu celular') }}<br>
                                                {{ __('2. Toque em Menu ou Configurações e selecione Aparelhos conectados') }}<br>
                                                {{ __('3. Toque em Conectar um aparelho') }}<br>
                                                {{ __('4. Aponte seu celular para esta tela para capturar o código') }}
                                            </p>
                                        </div>

                                        <!-- QR Code via biblioteca (fallback) -->
                                        <div x-show="qrModal.qrText && !qrModal.qrCode && !qrModal.pairingCode" class="text-center mb-4">
                                            <p class="text-sm text-gray-600 mb-3">{{ __('Escaneie o QR Code com seu WhatsApp:') }}</p>
                                            <div class="flex justify-center">
                                                <div x-ref="qrLibContainer" class="bg-white border-2 border-gray-300 rounded-lg p-2"></div>
                                            </div>
                                        </div>

                                        <!-- Pairing Code -->
                                        <div x-show="qrModal.pairingCode && !qrModal.qrCode" class="text-center mb-4">
                                            <div class="mb-4">
                                                <svg class="w-16 h-16 mx-auto text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                            <p class="text-base text-gray-700 mb-4 font-medium">{{ __('Use o código abaixo no seu WhatsApp') }}</p>
                                            <div class="bg-white border-3 border-brand-400 rounded-xl p-8 inline-block shadow-lg">
                                                <p class="text-sm text-gray-500 mb-2">{{ __('CÓDIGO DE PAREAMENTO') }}</p>
                                                <p class="text-3xl font-mono font-bold text-brand-600 tracking-wider select-all" x-text="formatPairingCode(qrModal.pairingCode)"></p>
                                            </div>
                                            <div class="mt-6 text-left bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                                                <p class="text-sm text-gray-700 mb-3 font-semibold flex items-center">
                                                    <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    {{ __('Como conectar:') }}
                                                </p>
                                                <ol class="text-sm text-gray-600 space-y-2 list-decimal list-inside ml-2">
                                                    <li>{{ __('Abra o WhatsApp no seu celular') }}</li>
                                                    <li>{{ __('Toque em "Menu" (⋮) ou "Configurações"') }}</li>
                                                    <li>{{ __('Selecione "Aparelhos conectados"') }}</li>
                                                    <li>{{ __('Toque em "Conectar um aparelho"') }}</li>
                                                    <li class="font-semibold text-brand-700">{{ __('Selecione "Conectar com número de telefone"') }}</li>
                                                    <li>{{ __('Digite o código exibido acima') }}</li>
                                                </ol>
                                            </div>
                                        </div>

                                        <!-- Loading state -->
                                        <div x-show="qrModal.loading" class="text-center mb-4">
                                            <svg class="animate-spin h-8 w-8 text-brand-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <p class="text-sm text-gray-600 mt-2">{{ __('Carregando...') }}</p>
                                        </div>
                                    </div>

                                    <!-- Footer with actions -->
                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button 
                                            @click="refreshQrCode()"
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-brand-600 text-base font-medium text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 sm:ml-3 sm:w-auto sm:text-sm"
                                            :disabled="qrModal.loading"
                                        >
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            {{ __('Atualizar QR Code') }}
                                        </button>
                                        <button 
                                            @click="checkConnectionStatus()"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                            :disabled="qrModal.loading"
                                        >
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ __('Verificar Status') }}
                                        </button>
                                        <button 
                                            @click="qrModal.isOpen = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 sm:mt-0 sm:w-auto sm:text-sm"
                                        >
                                            {{ __('Fechar') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
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
                                type="button"
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

            <!-- Configuração do Webhook (etapa FINAL: só depois de conectar) -->
            <div x-show="connectionStatus !== 'open'" class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
                <div class="text-sm">
                    {{ __('Webhook será configurado depois que o WhatsApp estiver conectado (status: Conectado).') }}
                </div>
            </div>

            <div x-show="connectionStatus === 'open'" style="display:none" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                        {{ __('Configuração do Webhook') }}
                    </h3>

                    <form action="{{ route('whatsapp.webhook.configure') }}" method="POST">
                        @csrf
                        <input type="hidden" name="instance_name" :value="currentInstanceName || '{{ $instanceName }}'">

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
    
    {{-- Biblioteca QRCode (gera QR a partir de texto quando base64 não vier) --}}
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <script>
        function whatsappConfig() {
            return {
                debugAlerts: window.__WHATSAPP_DEBUG_ALERTS__ === true,
                connectionStatus: '{{ $status['status'] ?? 'not_found' }}',
                qrCode: null,
                pairingCode: null,
                showQrCode: false,
                // QR Code Modal (similar ao React)
                qrModal: {
                    isOpen: false,
                    qrCode: '',
                    pairingCode: '',
                    qrText: '',
                    whatsappNumber: '',
                    loading: false
                },
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
                    
                    // Watch qrCode para garantir que só aceita data URI válido
                    this.$watch('qrCode', (value) => {
                        if (!value) return;

                        // Agora qrCode armazena o data URI completo (data:image/png;base64,...)
                        if (typeof value !== 'string' || !value.startsWith('data:image')) {
                            console.warn('⚠ QR Code inválido (não começa com data:image), limpando...');
                            this.qrCode = null;
                            this.showQrCode = false;
                        }
                    });
                    
                    // Cleanup on component destroy
                    this.$watch('connectionStatus', (value) => {
                        if (value === 'open' && this.statusCheckInterval) {
                            clearInterval(this.statusCheckInterval);
                            this.statusCheckInterval = null;
                        }
                    });
                },

                renderQrFromText() {
                    try {
                        const container = this.$refs.qrLibContainer;
                        if (!container) return;
                        container.innerHTML = '';
                        if (!this.qrModal.qrText) return;
                        if (typeof QRCode === 'undefined') {
                            console.error('QRCode library not loaded');
                            return;
                        }
                        // Render QR into container
                        new QRCode(container, {
                            text: this.qrModal.qrText,
                            width: 256,
                            height: 256,
                            correctLevel: QRCode.CorrectLevel.M
                        });
                    } catch (e) {
                        console.error('Erro ao renderizar QR via biblioteca:', e);
                    }
                },

                alertApiSummary(title, data) {
                    try {
                        const summary = {
                            success: data?.success,
                            status: data?.status,
                            instanceName: data?.instanceName,
                            build: data?.build,
                            hasQrcodeObj: !!data?.qrcode,
                            qrcodeBase64IsString: typeof data?.qrcode?.base64 === 'string',
                            qrcodeBase64StartsWith: typeof data?.qrcode?.base64 === 'string' ? data.qrcode.base64.substring(0, 30) : null,
                            qrcodeBase64Length: typeof data?.qrcode?.base64 === 'string' ? data.qrcode.base64.length : null,
                            hasPairingCode: !!data?.pairingCode,
                            pairingCodePrefix: typeof data?.pairingCode === 'string' ? data.pairingCode.substring(0, 30) : null,
                            hasQrText: !!data?.qrText,
                            qrTextPrefix: typeof data?.qrText === 'string' ? data.qrText.substring(0, 30) : null,
                            creationWarning: data?.creation_warning,
                            dbWarning: data?.db_warning,
                            webhookWarning: data?.webhook_warning,
                        };
                        alert(title + "\n\n" + JSON.stringify(summary, null, 2));
                    } catch (e) {
                        alert(title + "\n\n" + "Não foi possível resumir a resposta (erro no JS).");
                    }
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
                    alert('DEBUG: connect() foi chamado');
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

                    // Reset modal state
                    this.qrModal = {
                        isOpen: false,
                        qrCode: '',
                        pairingCode: '',
                        qrText: '',
                        whatsappNumber: cleanNumber,
                        loading: true
                    };

                    // Reset QR code and pairing code before new connection
                    this.qrCode = null;
                    this.pairingCode = null;
                    this.showQrCode = false;
                    
                    this.loading = true;
                    try {
                        // IMPORTANTE: webhook é configurado SEPARADAMENTE.
                        // Aqui (connect) enviamos apenas o número para criar/conectar a instância e obter QR.
                        const formData = new FormData();
                        formData.append('whatsapp_number', cleanNumber);

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

                        // ALERT: resumo do JsonResponse (sem base64 gigante)
                        this.alertApiSummary('RETORNO (JsonResponse) - Criar/Conectar Instância', data);
                        
                        // Log do retorno JSON da API
                        console.log('═══════════════════════════════════════════');
                        console.log('RETORNO DA API DE CRIAR INSTÂNCIA');
                        console.log('═══════════════════════════════════════════');
                        console.log('Resposta completa:', JSON.stringify(data, null, 2));
                        console.log('---');
                        console.log('data.qrcode:', data.qrcode);
                        console.log('typeof data.qrcode:', typeof data.qrcode);
                        if (data.qrcode && typeof data.qrcode === 'object') {
                            console.log('data.qrcode.base64:', data.qrcode.base64);
                            console.log('data.qrcode.base64 length:', data.qrcode.base64?.length);
                            console.log('data.qrcode.base64 primeiros 50 chars:', data.qrcode.base64?.substring(0, 50));
                        }
                        console.log('---');
                        console.log('data.pairingCode:', data.pairingCode);
                        console.log('data.status:', data.status);
                        console.log('═══════════════════════════════════════════');

                        if (response.ok && data.success) {
                            // Update instance name
                            this.currentInstanceName = cleanNumber;
                            
                            // Update status
                            this.connectionStatus = data.status || 'connecting';
                            
                            // O backend já devolve dados normalizados:
                            // - qrcode.base64 (data:image/png;base64,iVBORw0K...) OU
                            // - pairingCode (string)
                            const qrCodeBase64 = data.qrcode?.base64 || null;
                            const pairingCode = data.pairingCode || null;
                            const qrText = data.qrText || null;
                            
                            // Abrir modal com QR code ou pairing code
                            if (qrCodeBase64) {
                                // QR code válido - abrir modal (armazenar data URI completo)
                                this.qrModal = {
                                    isOpen: true,
                                    qrCode: qrCodeBase64, // data:image/png;base64,...
                                    pairingCode: '',
                                    qrText: '',
                                    whatsappNumber: cleanNumber,
                                    loading: false
                                };
                                this.qrCode = qrCodeBase64;
                                this.showQrCode = true;
                            } else if (pairingCode) {
                                // Pairing code - abrir modal com pairing code
                                this.qrModal = {
                                    isOpen: true,
                                    qrCode: '',
                                    pairingCode: pairingCode,
                                    qrText: '',
                                    whatsappNumber: cleanNumber,
                                    loading: false
                                };
                                this.pairingCode = pairingCode;
                                this.qrCode = null;
                                this.showQrCode = false;
                            } else if (qrText) {
                                // Gerar QR via biblioteca usando texto cru
                                this.qrModal = {
                                    isOpen: true,
                                    qrCode: '',
                                    pairingCode: '',
                                    qrText: qrText,
                                    whatsappNumber: cleanNumber,
                                    loading: false
                                };
                                this.qrCode = null;
                                this.pairingCode = null;
                                this.showQrCode = false;
                                this.$nextTick(() => this.renderQrFromText());
                            } else {
                                // Tentar obter QR code se status é connecting
                                this.qrCode = null;
                                this.showQrCode = false;
                                if (this.connectionStatus === 'connecting') {
                                    alert('Tentando carregar QR Code agora (GET /qrcode)...');
                                    await this.getQrCode();
                                }
                            }
                            
                            // If already connected, close modal and hide QR code
                            if (this.connectionStatus === 'open') {
                                this.qrModal.isOpen = false;
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
                                        this.qrModal.isOpen = false; // Fechar modal quando conectado
                                        this.showQrCode = false;
                                        this.showSuccessMessage('WhatsApp conectado com sucesso!');
                                    }
                                }, 3000); // Check every 3 seconds
                            }
                            
                            // Show success notification
                            if (data.message) {
                                // Create a temporary success message
                                this.showSuccessMessage(data.message);
                            }

                            // Não exibir alert de webhook/db para o usuário (isso é separado).
                            // Se precisar, ative ?debugAlerts=1 para ver.
                            if (data.webhook_warning) {
                                console.warn('Webhook warning:', data.webhook_warning);
                            }
                            if (data.db_warning) {
                                console.warn('DB warning:', data.db_warning);
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

                // Refresh QR Code (chamado pelo botão do modal)
                async refreshQrCode() {
                    this.qrModal.loading = true;
                    try {
                        await this.getQrCode();
                    } finally {
                        this.qrModal.loading = false;
                    }
                },

                // Check connection status (chamado pelo botão do modal)
                async checkConnectionStatus() {
                    this.qrModal.loading = true;
                    try {
                        await this.checkStatus();
                        if (this.connectionStatus === 'open') {
                            // Fechar modal se conectado
                            this.qrModal.isOpen = false;
                            this.showSuccessMessage('WhatsApp conectado com sucesso!');
                        }
                    } finally {
                        this.qrModal.loading = false;
                    }
                },

                async getQrCode() {
                    try {
                        alert('Carregando QR Code (GET /qrcode)...');
                        const response = await fetch('{{ route("whatsapp.qrcode") }}');
                        const data = await response.json();

                        // ALERT: resumo do JsonResponse do /qrcode
                        this.alertApiSummary('RETORNO (JsonResponse) - Endpoint /qrcode', data);
                        
                        console.log('=== RESPOSTA DO ENDPOINT /qrcode ===');
                        console.log(data);
                        console.log('====================================');
                        
                        // Backend já devolve normalizado: qrcode.base64 OU pairingCode
                        const qrCodeBase64 = data.qrcode?.base64 || null;
                        const pairingCode = data.pairingCode || null;
                        const qrText = data.qrText || null;
                        
                        // Atualizar modal
                        if (qrCodeBase64) {
                            this.qrModal.qrCode = qrCodeBase64; // data:image/png;base64,...
                            this.qrModal.pairingCode = '';
                            this.qrModal.qrText = '';
                            this.qrCode = qrCodeBase64; // data URI completo
                            this.showQrCode = true;
                            this.pairingCode = null;
                            if (!this.qrModal.isOpen) {
                                this.qrModal.isOpen = true;
                            }
                        } else if (pairingCode) {
                            this.qrModal.qrCode = '';
                            this.qrModal.pairingCode = pairingCode;
                            this.qrModal.qrText = '';
                            this.pairingCode = pairingCode;
                            this.qrCode = null;
                            this.showQrCode = false;
                            if (!this.qrModal.isOpen) {
                                this.qrModal.isOpen = true;
                            }
                        } else if (qrText) {
                            this.qrModal.qrCode = '';
                            this.qrModal.pairingCode = '';
                            this.qrModal.qrText = qrText;
                            this.qrCode = null;
                            this.pairingCode = null;
                            this.showQrCode = false;
                            if (!this.qrModal.isOpen) {
                                this.qrModal.isOpen = true;
                            }
                            this.$nextTick(() => this.renderQrFromText());
                        } else {
                            // Evitar manter um QR antigo inválido na tela
                            this.qrModal.qrCode = '';
                            this.qrModal.pairingCode = '';
                            this.qrModal.qrText = '';
                            this.qrCode = null;
                            this.showQrCode = false;
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
                                // Fechar modal quando conectado
                                this.qrModal.isOpen = false;
                                this.showQrCode = false;
                                this.qrCode = null; // Limpar QR code quando conectado
                                this.pairingCode = null; // Limpar pairing code quando conectado
                                this.qrModal.qrCode = '';
                                this.qrModal.pairingCode = '';
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

                handleQrCodeError(event) {
                    console.error('❌ Erro ao carregar imagem do QR code', {
                        error: event,
                        qrCodeLength: this.qrModal.qrCode?.length,
                        qrCodeFirstChars: this.qrModal.qrCode?.substring(0, 100),
                        hasPairingCode: !!this.pairingCode,
                        pairingCode: this.pairingCode,
                        connectionStatus: this.connectionStatus,
                    });
                    this.showQrCode = false;
                    this.qrCode = null;
                    this.qrModal.qrCode = ''; // Limpar QR code do modal
                    
                    // Se temos pairing code, atualizar modal para mostrar pairing code
                    if (this.pairingCode) {
                        this.qrModal.pairingCode = this.pairingCode;
                        this.qrModal.qrCode = '';
                        if (!this.qrModal.isOpen) {
                            this.qrModal.isOpen = true;
                        }
                        console.log('✓ Modal atualizado para mostrar pairing code');
                    } else if (this.connectionStatus === 'connecting') {
                        // Se não temos pairing code, tentar obter novamente
                        console.log('Tentando obter QR code novamente...');
                        setTimeout(() => this.getQrCode(), 2000);
                    }
                },

                formatPairingCode(code) {
                    if (!code) return '';
                    
                    // Remove the prefix like "2@" if present
                    let cleaned = code;
                    if (/^\d+@/.test(code)) {
                        // Keep only the part after @
                        const parts = code.split('@');
                        if (parts.length > 1) {
                            cleaned = parts.slice(1).join('@');
                        }
                    }
                    
                    // Remove any commas or special characters
                    cleaned = cleaned.replace(/[,\s]/g, '');
                    
                    // If it's very long (Evolution API sometimes returns long codes)
                    // take only the first 8-12 characters which is typical for pairing codes
                    if (cleaned.length > 20) {
                        // It might be encoded, let's try to extract something meaningful
                        // WhatsApp pairing codes are usually 8 digits
                        const match = cleaned.match(/[A-Z0-9]{8}/);
                        if (match) {
                            cleaned = match[0];
                        } else {
                            // Take first 8 characters
                            cleaned = cleaned.substring(0, 8).toUpperCase();
                        }
                    }
                    
                    // Format in groups of 4 for readability (e.g., ABCD-EFGH)
                    if (cleaned.length === 8) {
                        return cleaned.substring(0, 4) + '-' + cleaned.substring(4, 8);
                    }
                    
                    return cleaned.toUpperCase();
                },

            }
        }
    </script>
</x-app-layout>
