<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Instâncias WhatsApp</h2>
                <p class="text-sm text-gray-500 mt-1">Gerencie as instâncias conectadas à Evolution API.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('whatsapp.index') }}" class="btn-secondary">Conectar número</a>
                <a href="{{ route('settings.index') }}" class="btn-secondary">Voltar</a>
            </div>
        </div>
    </x-slot>

    @push('styles')
    <style>
        [x-cloak] { display: none !important; }
        .wi-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px;
        }
        .wi-badge--open      { background: #dcfce7; color: #15803d; }
        .wi-badge--close,
        .wi-badge--closed,
        .wi-badge--disconnected { background: #fee2e2; color: #dc2626; }
        .wi-badge--connecting { background: #fef9c3; color: #854d0e; }
        .wi-badge--unknown    { background: #f3f4f6; color: #6b7280; }
        .wi-badge--only-db    { background: #fef3c7; color: #92400e; }
        .wi-token {
            font-family: monospace; font-size: 12px; background: #f8fafc;
            border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px;
            letter-spacing: .5px; cursor: pointer; user-select: all;
            max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;
        }
        .wi-card {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 20px 24px; transition: box-shadow .15s;
        }
        .wi-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
        .wi-card--orphan { border-color: #fbbf24; background: #fffbeb; }
        .wi-card--deleted { opacity: .65; border-style: dashed; }
        .wi-avatar {
            width: 48px; height: 48px; border-radius: 50%; object-fit: cover;
            border: 2px solid #e5e7eb; background: #f3f4f6;
        }
        .wi-avatar--placeholder {
            width: 48px; height: 48px; border-radius: 50%; background: #e5e7eb;
            display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 20px;
        }
        .wi-stat { text-align: center; }
        .wi-stat__val { font-size: 18px; font-weight: 700; color: #111827; }
        .wi-stat__lbl { font-size: 11px; color: #6b7280; }
        .wi-action { font-size: 12px; font-weight: 500; padding: 4px 12px; border-radius: 6px; border: 1px solid; cursor: pointer; transition: opacity .15s; }
        .wi-action:hover { opacity: .8; }
        .wi-action--sync  { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .wi-action--token { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .wi-action--del   { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
        .wi-empty { text-align: center; padding: 60px 20px; color: #9ca3af; }
    </style>
    @endpush

    <div class="py-10" x-data="waInstances()">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (!$configured)
                <div class="rounded-lg bg-yellow-50 border border-yellow-200 p-4 text-yellow-800 text-sm">
                    <strong>Evolution API não configurada.</strong> Verifique <code>EVOLUTION_API_URL</code> e <code>EVOLUTION_API_KEY</code> no <code>.env</code>.
                </div>
            @else
                <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-blue-700 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/></svg>
                    API: <strong>{{ $apiUrl }}</strong> &nbsp;·&nbsp; {{ $instances->count() }} instância(s)
                </div>
            @endif

            <!-- Mensagens de feedback -->
            <div x-show="feedback.msg" x-cloak
                 :class="feedback.ok ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
                 class="rounded-lg border p-3 text-sm flex justify-between items-center">
                <span x-text="feedback.msg"></span>
                <button @click="feedback.msg=''" class="ml-4 opacity-60 hover:opacity-100">✕</button>
            </div>

            @if ($instances->isEmpty())
                <div class="wi-empty wi-card">
                    <div class="text-4xl mb-3">📱</div>
                    <p class="font-medium text-gray-600">Nenhuma instância encontrada.</p>
                    <p class="text-sm mt-1">
                        <a href="{{ route('whatsapp.index') }}" class="text-brand-600 hover:underline">Adicionar um número WhatsApp →</a>
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($instances as $inst)
                    @php
                        $status  = $inst['status_ev'] ?? $inst['status_db'] ?? 'unknown';
                        $isOpen  = in_array($status, ['open','connected','online','ready']);
                        $isOrphan = !$inst['in_db'];
                        $isDeleted = $inst['deleted_at'] !== null;
                        $badgeClass = match(true) {
                            $isOpen => 'wi-badge--open',
                            in_array($status, ['close','closed','disconnected']) => 'wi-badge--close',
                            $status === 'connecting' => 'wi-badge--connecting',
                            !$inst['in_evolution'] && $inst['in_db'] => 'wi-badge--only-db',
                            default => 'wi-badge--unknown',
                        };
                        $token = $inst['ev_token'] ?? $inst['token'] ?? null;
                    @endphp
                    <div class="wi-card {{ $isOrphan ? 'wi-card--orphan' : '' }} {{ $isDeleted ? 'wi-card--deleted' : '' }}"
                         x-data="{ showToken: false, syncing: false, refreshingToken: false }">

                        <div class="flex flex-wrap items-start gap-4">

                            <!-- Avatar -->
                            <div class="shrink-0">
                                @if ($inst['profile_pic'])
                                    <img src="{{ $inst['profile_pic'] }}" alt="avatar"
                                         class="wi-avatar"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                    <div class="wi-avatar--placeholder" style="display:none">📱</div>
                                @else
                                    <div class="wi-avatar--placeholder">📱</div>
                                @endif
                            </div>

                            <!-- Info principal -->
                            <div class="flex-1 min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-gray-900 text-base">
                                        {{ $inst['profile_name'] ?? $inst['instance_name'] }}
                                    </span>
                                    <span class="wi-badge {{ $badgeClass }}">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full {{ $isOpen ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        {{ strtoupper($status) }}
                                    </span>
                                    @if ($isOrphan)
                                        <span class="wi-badge wi-badge--only-db">Só na Evolution</span>
                                    @endif
                                    @if (!$inst['in_evolution'] && $inst['in_db'])
                                        <span class="wi-badge wi-badge--only-db">Só no DB local</span>
                                    @endif
                                    @if ($isDeleted)
                                        <span class="wi-badge" style="background:#f3f4f6;color:#6b7280">Deletado localmente</span>
                                    @endif
                                </div>

                                <div class="text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                                    <span><span class="font-medium text-gray-700">Instância:</span> {{ $inst['instance_name'] }}</span>
                                    @if ($inst['owner_jid'])
                                        <span><span class="font-medium text-gray-700">JID:</span> {{ $inst['owner_jid'] }}</span>
                                    @endif
                                    @if ($inst['connected_at'])
                                        <span><span class="font-medium text-gray-700">Conectado em:</span> {{ \Carbon\Carbon::parse($inst['connected_at'])->format('d/m/Y H:i') }}</span>
                                    @endif
                                    @if ($inst['webhook_url'])
                                        <span><span class="font-medium text-gray-700">Webhook:</span>
                                            <span class="text-green-700">✓ configurado</span>
                                        </span>
                                    @endif
                                </div>

                                <!-- Token -->
                                <div class="flex items-center gap-2 mt-2 flex-wrap">
                                    <span class="text-xs font-medium text-gray-500">Token:</span>
                                    @if ($token)
                                        <span class="wi-token" x-show="!showToken"
                                              @click="showToken=true"
                                              title="Clique para revelar">
                                            {{ str_repeat('•', 12) . substr($token, -6) }}
                                        </span>
                                        <span class="wi-token" x-show="showToken" x-cloak
                                              @click="copyToken('{{ $token }}')"
                                              title="Clique para copiar">{{ $token }}</span>
                                        <button x-show="showToken" x-cloak @click="showToken=false"
                                                class="text-xs text-gray-400 hover:text-gray-700">ocultar</button>
                                    @else
                                        <span class="text-xs text-gray-400 italic">não disponível</span>
                                    @endif

                                    @if ($inst['in_evolution'])
                                        <button class="wi-action wi-action--token"
                                                :disabled="refreshingToken"
                                                @click="refreshToken('{{ $inst['instance_name'] }}', $el)">
                                            <span x-show="!refreshingToken">↻ Sincronizar token</span>
                                            <span x-show="refreshingToken" x-cloak>Aguarde...</span>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Stats Evolution -->
                            @if ($inst['in_evolution'])
                                <div class="flex gap-6 shrink-0">
                                    <div class="wi-stat">
                                        <div class="wi-stat__val">{{ number_format($inst['msg_count'] ?? 0) }}</div>
                                        <div class="wi-stat__lbl">Mensagens</div>
                                    </div>
                                    <div class="wi-stat">
                                        <div class="wi-stat__val">{{ number_format($inst['contact_count'] ?? 0) }}</div>
                                        <div class="wi-stat__lbl">Contatos</div>
                                    </div>
                                    <div class="wi-stat">
                                        <div class="wi-stat__val">{{ number_format($inst['chat_count'] ?? 0) }}</div>
                                        <div class="wi-stat__lbl">Chats</div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Ações -->
                        <div class="flex flex-wrap gap-2 mt-4 pt-3 border-t border-gray-100">
                            @if ($inst['in_db'] && !$isDeleted)
                                <button class="wi-action wi-action--sync"
                                        :disabled="syncing"
                                        @click="syncStatus('{{ $inst['instance_name'] }}', $el)">
                                    <span x-show="!syncing">⟳ Sincronizar status</span>
                                    <span x-show="syncing" x-cloak>Aguarde...</span>
                                </button>
                                <a href="{{ route('whatsapp.connect', $inst['instance_name']) }}"
                                   class="wi-action wi-action--sync" style="text-decoration:none">
                                    QR Code
                                </a>
                                <button class="wi-action wi-action--del"
                                        @click="deleteInstance('{{ $inst['instance_name'] }}')">
                                    Deletar
                                </button>
                            @endif
                            @if ($inst['in_evolution'] && !$inst['in_db'])
                                <span class="text-xs text-amber-700 italic self-center">
                                    Esta instância existe na Evolution mas não está no banco local. Conecte via <a href="{{ route('whatsapp.index') }}" class="underline">Conectar número</a>.
                                </span>
                            @endif
                        </div>

                    </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>

    @push('scripts')
    <script>
    function waInstances() {
        return {
            feedback: { msg: '', ok: true },

            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            },

            showFeedback(msg, ok = true) {
                this.feedback = { msg, ok };
                setTimeout(() => { this.feedback.msg = ''; }, 4000);
            },

            async copyToken(token) {
                try {
                    await navigator.clipboard.writeText(token);
                    this.showFeedback('Token copiado para a área de transferência!');
                } catch {
                    this.showFeedback('Não foi possível copiar. Selecione e copie manualmente.', false);
                }
            },

            async syncStatus(instance, btn) {
                const comp = Alpine.$data(btn.closest('[x-data]'));
                comp.syncing = true;
                try {
                    const resp = await fetch(`/settings/whatsapp/instances/${instance}/sync`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (data.success) {
                        this.showFeedback(`Status atualizado: ${(data.state || 'ok').toUpperCase()}`);
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        this.showFeedback(data.error || 'Erro ao sincronizar.', false);
                    }
                } catch (e) {
                    this.showFeedback('Erro de rede.', false);
                } finally {
                    comp.syncing = false;
                }
            },

            async refreshToken(instance, btn) {
                const comp = Alpine.$data(btn.closest('[x-data]'));
                comp.refreshingToken = true;
                try {
                    const resp = await fetch(`/settings/whatsapp/instances/${instance}/token-refresh`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (data.success) {
                        this.showFeedback('Token sincronizado com sucesso!');
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        this.showFeedback(data.error || 'Erro ao sincronizar token.', false);
                    }
                } catch (e) {
                    this.showFeedback('Erro de rede.', false);
                } finally {
                    comp.refreshingToken = false;
                }
            },

            async deleteInstance(instance) {
                if (!confirm(`Deletar a instância "${instance}" da Evolution e do sistema?`)) return;
                try {
                    const resp = await fetch(`/settings/whatsapp/delete/${instance}`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (data.success || data.deleted) {
                        this.showFeedback('Instância deletada com sucesso.');
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        this.showFeedback(data.error || 'Erro ao deletar.', false);
                    }
                } catch (e) {
                    this.showFeedback('Erro de rede.', false);
                }
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
