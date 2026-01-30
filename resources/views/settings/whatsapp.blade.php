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

    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    @endpush

    <div class="py-12" x-data="waSettings()" x-init="init()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">

                    <!-- Modal: confirmar desconexão -->
                    <div x-cloak
                         x-show="confirmDisconnectOpen"
                         class="fixed inset-0 z-50 flex items-center justify-center px-4">
                        <div class="absolute inset-0 bg-black/40" @click="closeDisconnectConfirm()"></div>

                        <div class="relative w-full max-w-md rounded-lg bg-white shadow-lg border border-gray-200 p-5">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Confirmar desconexão') }}</h3>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ __('Deseja desconectar este WhatsApp agora? Você poderá reconectar depois escaneando um novo QR Code.') }}
                            </p>

                            <div class="mt-5 flex justify-end gap-2">
                                <button type="button" class="btn-secondary" @click="closeDisconnectConfirm()">
                                    {{ __('Cancelar') }}
                                </button>
                                <button type="button"
                                        class="btn-primary bg-red-600 hover:bg-red-700 focus:ring-red-500"
                                        :disabled="loading"
                                        @click="confirmDisconnect()">
                                    {{ __('Desconectar') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal: QR Code -->
                    <div x-cloak
                         x-show="qrModalOpen"
                         class="fixed inset-0 z-40 flex items-center justify-center px-4">
                        <div class="absolute inset-0 bg-black/40" @click="closeQrModal()"></div>

                        <div class="relative w-full max-w-lg rounded-lg bg-white shadow-lg border border-gray-200 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-lg font-medium text-gray-900">{{ __('QR Code') }}</h3>
                                <button type="button"
                                        class="text-sm text-gray-600 hover:text-gray-800"
                                        @click="closeQrModal()">
                                    {{ __('Fechar') }}
                                </button>
                            </div>

                            <div class="mt-4">
                                <template x-if="qrImage">
                                    <div class="flex flex-col items-center gap-3">
                                        <img :src="qrImage"
                                             alt="QR Code"
                                             class="w-64 h-64 object-contain rounded-md border border-gray-200 bg-white" />
                                    </div>
                                </template>

                                <template x-if="!qrImage && qrText">
                                    <div class="space-y-3">
                                        <div class="flex justify-center">
                                            <div id="qrCanvas" class="p-3 bg-white border border-gray-200 rounded-md"></div>
                                        </div>
                                        <details class="text-xs text-gray-600">
                                            <summary class="cursor-pointer">{{ __('Ver código') }}</summary>
                                            <pre class="mt-2 whitespace-pre-wrap break-words" x-text="qrText"></pre>
                                        </details>
                                    </div>
                                </template>

                                <template x-if="!qrImage && !qrText && pairingCode">
                                    <div class="space-y-2">
                                        <pre class="text-xs bg-gray-50 border border-gray-200 rounded-md p-3 whitespace-pre-wrap break-words" x-text="pairingCode"></pre>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Modal: adicionar WhatsApp (Bootstrap) -->
                    <div x-cloak x-show="addWhatsAppOpen">
                        <div class="modal-backdrop fade show"></div>
                        <div class="modal fade show d-block"
                             tabindex="-1"
                             role="dialog"
                             aria-modal="true"
                             @keydown.escape.window="closeAddWhatsApp()">
                            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <div>
                                            <h5 class="modal-title">{{ __('Adicionar WhatsApp') }}</h5>
                                            <div class="text-body-secondary small">
                                                {{ __('Informe o número com DDI + DDD (ex: 55...).') }}
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close" aria-label="Close" @click="closeAddWhatsApp()"></button>
                                    </div>

                                    <div class="modal-body">
                                        <div class="mb-2">
                                            <label class="form-label">{{ __('Número do WhatsApp') }}</label>
                                            <input type="text"
                                                   class="form-control form-control-lg"
                                                   placeholder="5511999999999"
                                                   x-model.trim="whatsappNumber" />
                                            <div class="form-text">
                                                {{ __('Use somente números com DDI + DDD (ex: 55...).') }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal-footer d-flex gap-2">
                                        <button type="button"
                                                class="btn btn-outline-secondary"
                                                :disabled="loading"
                                                @click="closeAddWhatsApp()">
                                            {{ __('Cancelar') }}
                                        </button>

                                        <button type="button"
                                                class="btn btn-success"
                                                :disabled="loading || !normalizeNumber(whatsappNumber)"
                                                @click="createInstance()">
                                            <span x-show="!loading">{{ __('Adicionar WhatsApp') }}</span>
                                            <span x-show="loading">{{ __('Aguarde...') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Registro do WhatsApp do usuário (somente nome e status) --}}
                    @php
                        /** @var \Illuminate\Support\Collection|\App\Models\WhatsAppInstance[] $instances */
                        $instances = $instances ?? collect();
                        $latest = $instances->first();
                        $isConnected = function (?string $status): bool {
                            $s = strtolower(trim((string) $status));
                            return in_array($s, ['connected', 'open', 'online', 'ready'], true);
                        };
                        $statusLabel = function (?string $status): string {
                            $s = strtolower(trim((string) $status));
                            return match ($s) {
                                'connected', 'open', 'online', 'ready' => 'Conectado',
                                'connecting' => 'Conectando',
                                'disconnected', 'close', 'closed', 'offline' => 'Desconectado',
                                'not_configured', 'not-configured' => 'Não configurado',
                                '' => '-',
                                default => (string) $status,
                            };
                        };
                    @endphp

                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Seu WhatsApp') }}</h3>
                            <div class="flex items-center gap-3">
                                <button type="button"
                                        class="btn-primary"
                                        @click="openAddWhatsApp()">
                                    {{ __('Adicionar novo WhatsApp') }}
                                </button>
                                @if($latest)
                                    <button type="button"
                                            class="text-sm text-brand-700 hover:text-brand-800"
                                            @click="refreshState('{{ $latest->instance_name }}')">
                                        {{ __('Atualizar status') }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if($latest && $isConnected($latest->status))
                            <div class="mt-3 rounded-md border border-green-200 bg-green-50 p-3 text-green-700 text-sm">
                                <span class="font-semibold">{{ __('Conectado com sucesso!') }}</span>
                                <span class="text-green-800">{{ __('Seu WhatsApp já está conectado.') }}</span>
                            </div>
                        @endif

                        <div class="mt-4">
                            @if($instances->isEmpty())
                                <div class="text-sm text-gray-600">
                                    {{ __('Nenhuma instância cadastrada ainda. Crie uma instância e conecte via QR Code.') }}
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-gray-500 border-b border-gray-200">
                                                <th class="py-2 pr-4">{{ __('Instância') }}</th>
                                                <th class="py-2">{{ __('Status') }}</th>
                                                <th class="py-2 text-right">{{ __('Ações') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($instances as $inst)
                                                <tr>
                                                    <td class="py-2 pr-4 font-medium text-gray-900">
                                                        {{ $inst->instance_name }}
                                                    </td>
                                                    <td class="py-2">
                                                        <span data-wa-status="{{ $inst->instance_name }}"
                                                              data-wa-state="{{ $inst->status }}"
                                                              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                                                {{ $isConnected($inst->status) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                            {{ $statusLabel($inst->status) }}
                                                        </span>
                                                    </td>
                                                    <td class="py-2 text-right">
                                                        <button type="button"
                                                                class="text-sm text-brand-700 hover:text-brand-800"
                                                                @click="reconnect('{{ $inst->instance_name }}')">
                                                            {{ __('Reconectar (QR)') }}
                                                        </button>
                                                        <span class="mx-2 text-gray-300">|</span>
                                                        <button type="button"
                                                                class="text-sm text-red-700 hover:text-red-800"
                                                                @click="openDisconnectConfirm('{{ $inst->instance_name }}')">
                                                            {{ __('Desconectar') }}
                                                        </button>
                                                        <span class="mx-2 text-gray-300">|</span>
                                                        <button type="button"
                                                                class="text-sm text-red-800 hover:text-red-900"
                                                                @click="deleteInstance('{{ $inst->instance_name }}')">
                                                            {{ __('Deletar') }}
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
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
                    _checkTimer: null,
                    _checking: false,
                    _headerConnected: null,
                    confirmDisconnectOpen: false,
                    confirmDisconnectInstance: '',
                    qrModalOpen: false,
                    addWhatsAppOpen: false,

                    whatsappNumber: '',
                    instanceName: '',
                    successMessage: '',
                    error: '',

                    qrImage: '',
                    qrText: '',
                    pairingCode: '',

                    openQrModal() {
                        this.qrModalOpen = true;
                        this.$nextTick(() => this.renderQr());
                    },

                    closeQrModal() {
                        this.qrModalOpen = false;
                    },

                    openAddWhatsApp() {
                        this.clearMessages();
                        this.addWhatsAppOpen = true;
                    },

                    closeAddWhatsApp() {
                        this.addWhatsAppOpen = false;
                    },

                    init() {
                        // Ao atualizar a página, sempre checa o status na Evolution.
                        // (silencioso; só mostra mensagem se estiver conectado)
                        this.refreshAllStates({ silent: true });
                    },

                    emitHeaderConnection(connected) {
                        const next = !!connected;
                        if (this._headerConnected === next) return;
                        this._headerConnected = next;
                        window.dispatchEvent(new CustomEvent('whatsapp-connection-changed', {
                            detail: { connected: next },
                        }));
                    },

                    isConnectedStatus(status) {
                        const s = String(status || '').trim().toLowerCase();
                        return ['connected', 'open', 'online', 'ready'].includes(s);
                    },

                    mapStatusText(status) {
                        const s = String(status || '').trim().toLowerCase();
                        if (['connected', 'open', 'online', 'ready'].includes(s)) return 'Conectado';
                        if (s === 'connecting') return 'Conectando';
                        if (['disconnected', 'close', 'closed', 'offline'].includes(s)) return 'Desconectado';
                        if (s === 'not_configured' || s === 'not-configured') return 'Não configurado';
                        if (!s) return '-';
                        return String(status);
                    },

                    getKnownInstances() {
                        return Array
                            .from(document.querySelectorAll('[data-wa-status]'))
                            .map((el) => el.getAttribute('data-wa-status'))
                            .filter(Boolean);
                    },

                    applyStatusBadge(inst, state) {
                        const el = document.querySelector(`[data-wa-status="${inst}"]`);
                        if (!el) return;

                        const raw = state ? String(state) : '';
                        el.setAttribute('data-wa-state', raw);
                        el.textContent = this.mapStatusText(raw);

                        // Ajusta cor do badge (sem depender do backend)
                        const connected = this.isConnectedStatus(raw);
                        el.classList.remove('bg-green-100', 'text-green-800', 'bg-gray-100', 'text-gray-800');
                        el.classList.add(connected ? 'bg-green-100' : 'bg-gray-100');
                        el.classList.add(connected ? 'text-green-800' : 'text-gray-800');
                    },

                    setError(msg, { checkStatus = true } = {}) {
                        this.error = msg || '';

                        // Sempre que der erro (create/connect), checa status na Evolution.
                        if (!checkStatus) return;
                        if (this._checkTimer) clearTimeout(this._checkTimer);
                        this._checkTimer = setTimeout(() => {
                            this.refreshAllStates({ silent: true });
                        }, 250);
                    },

                    async refreshAllStates({ silent = false } = {}) {
                        if (this._checking) return;
                        this._checking = true;

                        try {
                            const instances = this.getKnownInstances();
                            if (!instances.length) {
                                this.emitHeaderConnection(false);
                                return;
                            }

                            let anyConnected = false;
                            for (const inst of instances) {
                                // eslint-disable-next-line no-await-in-loop
                                const res = await this.refreshState(inst, { silent: true });
                                if (res?.ok && this.isConnectedStatus(res.state)) {
                                    anyConnected = true;
                                }
                                // pequeno espaçamento para não "martelar" a Evolution
                                // eslint-disable-next-line no-await-in-loop
                                await new Promise((r) => setTimeout(r, 150));
                            }

                            this.emitHeaderConnection(anyConnected);

                            if (!silent && anyConnected) {
                                this.successMessage = 'WhatsApp conectado com sucesso.';
                                this.error = '';
                            }
                            if (silent && anyConnected && !this.successMessage) {
                                // Em refresh de página: mostrar somente o sucesso (sem spam)
                                this.successMessage = 'WhatsApp conectado com sucesso.';
                            }
                        } finally {
                            this._checking = false;
                        }
                    },

                    async refreshState(instance, { silent = false } = {}) {
                        const inst = this.normalizeNumber(instance);
                        if (!inst) return { ok: false };

                        try {
                            const url = "{{ url('/settings/whatsapp/state') }}/" + encodeURIComponent(inst);
                            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            const payload = await resp.json().catch(() => ({}));

                            if (payload && payload.state) {
                                this.applyStatusBadge(inst, payload.state);
                            }

                            if (!resp.ok || payload?.success === false) {
                                if (!silent) {
                                    this.setError(payload?.error || payload?.message || `HTTP ${resp.status}`, { checkStatus: false });
                                }
                                return { ok: false };
                            }

                            if (!silent) {
                                this.successMessage = payload?.state
                                    ? `Status atualizado: ${this.mapStatusText(payload.state)}`
                                    : (payload?.message || 'Status atualizado.');
                                this.error = '';
                            }
                            return { ok: true, state: payload?.state };
                        } catch (e) {
                            if (!silent) {
                                this.setError(e?.message || String(e), { checkStatus: false });
                            }
                            return { ok: false };
                        }
                    },

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
                        this.instanceName = payload?.instanceName || this.instanceName;

                        // Aceita múltiplos formatos vindos do backend/Evolution:
                        // - qrcode.base64 = data:image/... OU base64 puro (sem prefixo)
                        // - qrText / qrcode.code = texto (2@...) quando não há imagem
                        // - pairingCode (quando aplicável)
                        const maybeQrImage =
                            payload?.qrcode?.base64 ??
                            payload?.qrcode ??
                            payload?.base64 ??
                            '';

                        this.qrImage = (typeof maybeQrImage === 'string') ? String(maybeQrImage).trim() : '';
                        this.qrText = payload?.qrText || payload?.qrcode?.code || payload?.code || '';
                        this.pairingCode = payload?.pairingCode || payload?.qrcode?.pairingCode || '';

                        // Normaliza base64 puro para data:image (para o <img> funcionar no modal)
                        if (this.qrImage) {
                            if (this.qrImage.startsWith('data:image')) {
                                // ok
                            } else {
                                const raw = this.qrImage.replace(/\s/g, '');
                                const looksLikeBase64 = /^[A-Za-z0-9+/=]+$/.test(raw) && raw.length > 200;
                                this.qrImage = looksLikeBase64 ? `data:image/png;base64,${raw}` : '';
                            }
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
                                this.setError(payload?.error || payload?.message || `HTTP ${resp.status}`);
                                return;
                            }

                            this.successMessage = payload?.message || 'Instância criada. Agora conecte via QR Code.';
                            this.closeAddWhatsApp();
                            this.openQrModal();
                            // Como a regra pode ter desconectado outra instância, atualiza a tabela silenciosamente
                            await this.refreshAllStates({ silent: true });
                        } catch (e) {
                            this.setError(e?.message || String(e));
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
                            this.setError('Instance inválida.', { checkStatus: false });
                            this.loading = false;
                            return;
                        }

                        try {
                            const url = "{{ url('/settings/whatsapp/connect') }}/" + encodeURIComponent(instance);
                            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            const payload = await resp.json().catch(() => ({}));

                            this.setFromPayload(payload);

                            if (!resp.ok || payload?.success === false) {
                                this.setError(payload?.error || payload?.message || `HTTP ${resp.status}`);
                                return;
                            }

                            this.successMessage = payload?.message || 'QR Code atualizado.';
                            this.closeAddWhatsApp();
                            this.openQrModal();
                            // Tenta atualizar o status no backend (e refletir no front)
                            await this.refreshState(instance, { silent: true });
                            await this.refreshAllStates({ silent: false });
                        } catch (e) {
                            this.setError(e?.message || String(e));
                        } finally {
                            this.loading = false;
                        }
                    },

                    async reconnect(instance) {
                        // Ação rápida: seleciona a instância e gera/atualiza o QR
                        const inst = this.normalizeNumber(instance);
                        if (!inst) {
                            this.setError('Instance inválida.', { checkStatus: false });
                            return;
                        }

                        this.instanceName = inst;
                        this.whatsappNumber = inst;

                        await this.connect();
                    },

                    openDisconnectConfirm(instance) {
                        const inst = this.normalizeNumber(instance);
                        if (!inst) {
                            this.setError('Instance inválida.', { checkStatus: false });
                            return;
                        }

                        this.confirmDisconnectInstance = inst;
                        this.confirmDisconnectOpen = true;
                    },

                    closeDisconnectConfirm() {
                        this.confirmDisconnectOpen = false;
                        this.confirmDisconnectInstance = '';
                    },

                    async confirmDisconnect() {
                        const inst = this.normalizeNumber(this.confirmDisconnectInstance);
                        if (!inst) {
                            this.closeDisconnectConfirm();
                            return;
                        }

                        this.closeDisconnectConfirm();
                        await this.disconnect(inst);
                    },

                    async disconnect(instance) {
                        const inst = this.normalizeNumber(instance);
                        if (!inst) {
                            this.setError('Instance inválida.', { checkStatus: false });
                            return;
                        }

                        this.clearMessages();
                        this.loading = true;

                        try {
                            const url = "{{ url('/settings/whatsapp/disconnect') }}/" + encodeURIComponent(inst);
                            const resp = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf(),
                                },
                                body: JSON.stringify({}),
                            });

                            const payload = await resp.json().catch(() => ({}));

                            if (!resp.ok || payload?.success === false) {
                                this.setError(payload?.error || payload?.message || `HTTP ${resp.status}`);
                                return;
                            }

                            // Atualiza badge e mensagem
                            this.applyStatusBadge(inst, payload?.status || 'disconnected');
                            this.successMessage = payload?.message || 'WhatsApp desconectado com sucesso.';
                            this.error = '';

                            // Recheca estados para refletir tudo
                            await this.refreshAllStates({ silent: true });
                        } catch (e) {
                            this.setError(e?.message || String(e));
                        } finally {
                            this.loading = false;
                        }
                    },

                    async deleteInstance(instance) {
                        const inst = this.normalizeNumber(instance);
                        if (!inst) {
                            this.setError('Instance inválida.', { checkStatus: false });
                            return;
                        }

                        if (!confirm('Isso irá DELETAR a instância na Evolution e remover do sistema. Deseja continuar?')) return;

                        this.clearMessages();
                        this.loading = true;

                        try {
                            const url = "{{ url('/settings/whatsapp/delete') }}/" + encodeURIComponent(inst);
                            const resp = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf(),
                                },
                                body: JSON.stringify({}),
                            });

                            const payload = await resp.json().catch(() => ({}));

                            if (!resp.ok || payload?.success === false) {
                                this.setError(payload?.error || payload?.message || `HTTP ${resp.status}`);
                                return;
                            }

                            this.successMessage = payload?.message || 'Instância deletada com sucesso.';
                            this.error = '';

                            // Recarrega para refletir remoção da tabela
                            setTimeout(() => window.location.reload(), 400);
                        } catch (e) {
                            this.setError(e?.message || String(e));
                        } finally {
                            this.loading = false;
                        }
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>

