<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('WhatsApp') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Criar instância e conectar via QR Code (Evolution API).') }}
                </p>
            </div>
            <a href="{{ route('settings.index') }}" class="btn-secondary">
                {{ __('Voltar') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="waSettings()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">

                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Número do WhatsApp') }}
                                </label>
                                <input type="text"
                                       class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                                       placeholder="5511999999999"
                                       x-model.trim="whatsappNumber" />
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ __('Use somente números com DDI + DDD (ex: 55...).') }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button type="button"
                                        class="btn-primary w-full justify-center"
                                        :disabled="loading"
                                        @click="createInstance()">
                                    <span x-show="!loading">{{ __('Criar instância') }}</span>
                                    <span x-show="loading">{{ __('Aguarde...') }}</span>
                                </button>

                                <button type="button"
                                        class="btn-secondary w-full justify-center"
                                        :disabled="loading || !instanceName"
                                        @click="connect()">
                                    {{ __('Conectar (QR)') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <template x-if="error">
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
                            <div class="font-semibold">{{ __('Erro') }}</div>
                            <div class="text-sm mt-1" x-text="error"></div>
                        </div>
                    </template>

                    <template x-if="successMessage">
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-700">
                            <div class="font-semibold">{{ __('OK') }}</div>
                            <div class="text-sm mt-1" x-text="successMessage"></div>
                        </div>
                    </template>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">{{ __('Status') }}</h3>
                                <span class="text-xs text-gray-500" x-text="buildLabel"></span>
                            </div>
                            <div class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Instância') }}</span>
                                    <span class="font-medium" x-text="instanceName || '-'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Token (hash)') }}</span>
                                    <span class="font-medium truncate ml-3" x-text="hash || '-'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('HTTP') }}</span>
                                    <span class="font-medium" x-text="httpStatus || '-'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">{{ __('Conexão (QR)') }}</h3>
                                <button type="button"
                                        class="text-sm text-brand-700 hover:text-brand-800"
                                        :disabled="loading || !instanceName"
                                        @click="connect()">
                                    {{ __('Atualizar') }}
                                </button>
                            </div>

                            <div class="mt-4">
                                <template x-if="qrImage">
                                    <div class="flex flex-col items-center gap-3">
                                        <img :src="qrImage"
                                             alt="QR Code"
                                             class="w-64 h-64 object-contain rounded-md border border-gray-200 bg-white" />
                                        <p class="text-xs text-gray-500">{{ __('Escaneie o QR no WhatsApp.') }}</p>
                                    </div>
                                </template>

                                <template x-if="!qrImage && qrText">
                                    <div class="space-y-3">
                                        <div class="text-sm text-gray-700">
                                            {{ __('QR recebido como texto (ex: 2@...). Gerando QR localmente:') }}
                                        </div>
                                        <div class="flex justify-center">
                                            <div id="qrCanvas" class="p-3 bg-white border border-gray-200 rounded-md"></div>
                                        </div>
                                        <details class="text-xs text-gray-600">
                                            <summary class="cursor-pointer">{{ __('Ver conteúdo do código') }}</summary>
                                            <pre class="mt-2 whitespace-pre-wrap break-words" x-text="qrText"></pre>
                                        </details>
                                    </div>
                                </template>

                                <template x-if="!qrImage && !qrText && pairingCode">
                                    <div class="space-y-2">
                                        <div class="text-sm text-gray-700">
                                            {{ __('Pairing code recebido:') }}
                                        </div>
                                        <pre class="text-xs bg-gray-50 border border-gray-200 rounded-md p-3 whitespace-pre-wrap break-words" x-text="pairingCode"></pre>
                                    </div>
                                </template>

                                <template x-if="!qrImage && !qrText && !pairingCode">
                                    <div class="text-sm text-gray-600">
                                        {{ __('Crie a instância e clique em “Conectar (QR)”.') }}
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Debug (última resposta)') }}</h3>
                            <button type="button" class="text-sm text-gray-600 hover:text-gray-800" @click="debugOpen = !debugOpen">
                                <span x-text="debugOpen ? 'Ocultar' : 'Mostrar'"></span>
                            </button>
                        </div>
                        <div class="mt-3" x-show="debugOpen">
                            <pre class="text-xs bg-gray-50 border border-gray-200 rounded-md p-3 whitespace-pre-wrap break-words"
                                 x-text="JSON.stringify(lastResponse, null, 2)"></pre>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            function waSettings() {
                return {
                    loading: false,
                    debugOpen: true,
                    lastResponse: null,

                    whatsappNumber: '',
                    instanceName: '',
                    hash: '',
                    httpStatus: '',
                    buildLabel: '',
                    successMessage: '',
                    error: '',

                    qrImage: '',
                    qrText: '',
                    pairingCode: '',

                    csrf() {
                        const el = document.querySelector('meta[name="csrf-token"]');
                        return el ? el.getAttribute('content') : '';
                    },

                    normalizeNumber(input) {
                        return String(input || '').replace(/\D/g, '');
                    },

                    clearMessages() {
                        this.error = '';
                        this.successMessage = '';
                    },

                    setFromPayload(payload) {
                        this.lastResponse = payload;
                        this.buildLabel = payload?.build ? `build: ${payload.build}` : '';
                        this.httpStatus = payload?.http_status ?? payload?.httpStatus ?? '';
                        this.instanceName = payload?.instanceName || this.instanceName;
                        this.hash = payload?.hash || this.hash;

                        // contrato novo:
                        // - qrcode.base64 => SOMENTE se for data:image...
                        // - qrText => texto (2@...) quando não há imagem
                        this.qrImage = payload?.qrcode?.base64 || '';
                        this.qrText = payload?.qrText || '';
                        this.pairingCode = payload?.pairingCode || '';

                        // evita ERR_INVALID_URL se backend mandar algo errado
                        if (this.qrImage && !String(this.qrImage).startsWith('data:image')) {
                            this.qrImage = '';
                        }
                        if (this.qrText && String(this.qrText).startsWith('data:image')) {
                            // se por algum motivo vier "data:image...2@..." tratamos como texto
                            const maybe = String(this.qrText);
                            const idx = maybe.indexOf('base64,');
                            this.qrText = idx >= 0 ? maybe.slice(idx + 'base64,'.length) : maybe;
                        }

                        this.$nextTick(() => this.renderQr());
                    },

                    renderQr() {
                        const el = document.getElementById('qrCanvas');
                        if (!el) return;
                        el.innerHTML = '';
                        if (!this.qrText) return;

                        // qrcodejs
                        // eslint-disable-next-line no-undef
                        new QRCode(el, {
                            text: String(this.qrText),
                            width: 256,
                            height: 256,
                            correctLevel: QRCode.CorrectLevel.M
                        });
                    },

                    async createInstance() {
                        this.clearMessages();
                        this.loading = true;
                        this.qrImage = '';
                        this.qrText = '';
                        this.pairingCode = '';

                        const number = this.normalizeNumber(this.whatsappNumber);
                        this.whatsappNumber = number;

                        try {
                            const resp = await fetch("{{ route('whatsapp.instance.create') }}", {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf(),
                                },
                                body: JSON.stringify({ whatsapp_number: number }),
                            });

                            const payload = await resp.json().catch(() => ({}));
                            this.setFromPayload(payload);

                            if (!resp.ok || payload?.success === false) {
                                this.error = payload?.error || payload?.message || `HTTP ${resp.status}`;
                                return;
                            }

                            this.successMessage = payload?.message || 'Instância criada. Agora conecte via QR.';
                        } catch (e) {
                            this.error = e?.message || String(e);
                        } finally {
                            this.loading = false;
                        }
                    },

                    async connect() {
                        this.clearMessages();
                        this.loading = true;
                        this.qrImage = '';
                        this.qrText = '';
                        this.pairingCode = '';

                        const instance = this.normalizeNumber(this.instanceName || this.whatsappNumber);
                        if (!instance) {
                            this.error = 'Instance inválida.';
                            this.loading = false;
                            return;
                        }

                        try {
                            const url = "{{ url('/settings/whatsapp/connect') }}/" + encodeURIComponent(instance);
                            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            const payload = await resp.json().catch(() => ({}));

                            this.setFromPayload(payload);

                            if (!resp.ok || payload?.success === false) {
                                this.error = payload?.error || payload?.message || `HTTP ${resp.status}`;
                                return;
                            }

                            this.successMessage = payload?.message || 'QR atualizado.';
                        } catch (e) {
                            this.error = e?.message || String(e);
                        } finally {
                            this.loading = false;
                        }
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>

