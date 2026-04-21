<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Instâncias WhatsApp</h2>
                <p class="text-sm text-gray-500 mt-1">Gerencie os números WhatsApp conectados.</p>
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

        .wi-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px 24px;
            transition: box-shadow .15s;
        }
        .wi-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
        .wi-card--inactive { opacity: .7; border-color: #d1d5db; background: #f9fafb; }

        .wi-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .wi-badge--active      { background: #dcfce7; color: #15803d; }
        .wi-badge--inactive    { background: #fee2e2; color: #dc2626; }
        .wi-badge--connecting  { background: #fef9c3; color: #854d0e; }
        .wi-badge--unknown     { background: #f3f4f6; color: #6b7280; }
        .wi-badge--no-db       { background: #fef3c7; color: #92400e; }

        .wi-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; }
        .wi-dot--active   { background: #22c55e; }
        .wi-dot--inactive { background: #ef4444; }
        .wi-dot--other    { background: #9ca3af; }

        .wi-avatar {
            width: 52px; height: 52px; border-radius: 50%; object-fit: cover;
            border: 2px solid #e5e7eb;
        }
        .wi-avatar--placeholder {
            width: 52px; height: 52px; border-radius: 50%;
            background: #e5e7eb; display: flex; align-items: center;
            justify-content: center; color: #9ca3af; font-size: 22px;
        }

        .wi-stat { text-align: center; min-width: 60px; }
        .wi-stat__val { font-size: 17px; font-weight: 700; color: #111827; }
        .wi-stat__lbl { font-size: 11px; color: #6b7280; }

        .wi-token {
            font-family: monospace; font-size: 12px;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
            padding: 4px 8px; letter-spacing: .5px; cursor: pointer;
            max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            display: inline-block; user-select: all;
        }

        .wi-action {
            font-size: 12px; font-weight: 500; padding: 5px 12px;
            border-radius: 6px; border: 1px solid; cursor: pointer;
            transition: opacity .15s; display: inline-flex; align-items: center; gap: 4px;
        }
        .wi-action:hover { opacity: .75; }
        .wi-action:disabled { opacity: .45; cursor: not-allowed; }
        .wi-action--sync  { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .wi-action--token { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .wi-action--del   { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
        .wi-action--qr    { background: #faf5ff; color: #7e22ce; border-color: #e9d5ff; text-decoration: none; }

        .wi-empty { text-align: center; padding: 60px 20px; color: #9ca3af; }
    </style>
    @endpush

    <div class="py-10" x-data="waInstances()">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (!$configured)
                <div class="rounded-lg bg-yellow-50 border border-yellow-200 p-4 text-yellow-800 text-sm">
                    <strong>Serviço WhatsApp não configurado.</strong>
                    Entre em contato com o suporte.
                </div>
            @endif

            @if ($instances->isNotEmpty())
            @php
                $total        = $instances->count();
                $ativos       = $instances->where('active', true)->count();
                $desativados  = $instances->where('active', false)->count();
                $totalMsg     = $instances->sum('msg_count');
                $totalChats   = $instances->sum('chat_count');
                $totalContatos= $instances->sum('contact_count');
                $semVinculo   = $instances->where('in_db', false)->count();
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="wi-card text-center py-4 px-3">
                    <div class="text-2xl font-bold text-gray-900">{{ $total }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">Total</div>
                </div>
                <div class="wi-card text-center py-4 px-3">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                        <span class="text-2xl font-bold text-green-700">{{ $ativos }}</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">Ativo{{ $ativos !== 1 ? 's' : '' }}</div>
                </div>
                <div class="wi-card text-center py-4 px-3">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>
                        <span class="text-2xl font-bold text-red-600">{{ $desativados }}</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">Desconectado{{ $desativados !== 1 ? 's' : '' }}</div>
                </div>
                <div class="wi-card text-center py-4 px-3">
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($totalChats) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">Conversas</div>
                </div>
                <div class="wi-card text-center py-4 px-3">
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($totalContatos) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">Contatos</div>
                </div>
                <div class="wi-card text-center py-4 px-3">
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($totalMsg) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">Mensagens</div>
                </div>
            </div>
            @endif

            <!-- Feedback -->
            <div x-show="feedback.msg" x-cloak
                 :class="feedback.ok ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
                 class="rounded-lg border p-3 text-sm flex justify-between items-center">
                <span x-text="feedback.msg"></span>
                <button @click="feedback.msg=''" class="ml-4 opacity-60 hover:opacity-100">✕</button>
            </div>

            @if ($instances->isEmpty())
                <div class="wi-empty wi-card">
                    <div class="text-4xl mb-3">📱</div>
                    <p class="font-medium text-gray-600">Nenhum número WhatsApp conectado.</p>
                    <p class="text-sm mt-1">
                        <a href="{{ route('whatsapp.index') }}" class="text-green-600 hover:underline">Adicionar número WhatsApp →</a>
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($instances as $inst)
                    @php
                        $active     = $inst['active'];
                        $status     = $inst['status'];
                        $connecting = $status === 'connecting';
                        $token      = $inst['token'];

                        $badgeClass = match(true) {
                            $active      => 'wi-badge--active',
                            $connecting  => 'wi-badge--connecting',
                            default      => 'wi-badge--inactive',
                        };
                        $dotClass = match(true) {
                            $active     => 'wi-dot--active',
                            $connecting => 'wi-dot--other',
                            default     => 'wi-dot--inactive',
                        };
                        $statusLabel = match(true) {
                            $active     => 'Ativo',
                            $connecting => 'Conectando',
                            default     => 'Desativado',
                        };
                    @endphp

                    <div class="wi-card {{ !$active ? 'wi-card--inactive' : '' }}"
                         x-data="{ showToken: false, syncing: false, refreshingToken: false }">

                        <div class="flex flex-wrap items-start gap-4">

                            <!-- Avatar -->
                            <div class="shrink-0">
                                @if ($inst['profile_pic'])
                                    <img src="{{ $inst['profile_pic'] }}" alt="avatar" class="wi-avatar"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                    <div class="wi-avatar--placeholder" style="display:none">📱</div>
                                @else
                                    <div class="wi-avatar--placeholder">📱</div>
                                @endif
                            </div>

                            <!-- Infos -->
                            <div class="flex-1 min-w-0 space-y-1.5">

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-gray-900 text-base">
                                        {{ $inst['profile_name'] ?? $inst['instance_name'] }}
                                    </span>
                                    <span class="wi-badge {{ $badgeClass }}">
                                        <span class="wi-dot {{ $dotClass }}"></span>
                                        {{ $statusLabel }}
                                    </span>
                                    @if (!$inst['in_db'])
                                        <span class="wi-badge wi-badge--no-db" title="Esta instância está na Evolution mas não tem registro no banco local">
                                            Sem vínculo local
                                        </span>
                                    @endif
                                </div>

                                <div class="text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-0.5">
                                    <span>
                                        <span class="font-medium text-gray-600">Instância:</span>
                                        {{ $inst['instance_name'] }}
                                    </span>
                                    @if ($inst['owner_jid'])
                                        <span>
                                            <span class="font-medium text-gray-600">Número:</span>
                                            {{ $inst['owner_jid'] }}
                                        </span>
                                    @endif
                                    @if ($inst['connected_at'])
                                        <span>
                                            <span class="font-medium text-gray-600">Conectado em:</span>
                                            {{ \Carbon\Carbon::parse($inst['connected_at'])->format('d/m/Y H:i') }}
                                        </span>
                                    @endif
                                    @if ($inst['webhook_url'])
                                        <span class="text-green-700">✓ webhook</span>
                                    @endif
                                </div>

                                @if (!$active)
                                    <div class="inline-flex items-center gap-1.5 text-xs text-red-600 bg-red-50 border border-red-200 rounded-md px-2.5 py-1 mt-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        Instância desativada — escaneie o QR Code para reconectar
                                    </div>
                                @endif

                                <!-- Token -->
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <span class="text-xs font-medium text-gray-500">Token:</span>
                                    @if ($token)
                                        <span class="wi-token" x-show="!showToken" @click="showToken=true" title="Clique para revelar">
                                            {{ str_repeat('•', 12) . substr($token, -6) }}
                                        </span>
                                        <span class="wi-token" x-show="showToken" x-cloak
                                              @click="copyToken('{{ $token }}')" title="Clique para copiar">
                                            {{ $token }}
                                        </span>
                                        <button x-show="showToken" x-cloak @click="showToken=false"
                                                class="text-xs text-gray-400 hover:text-gray-600">ocultar</button>
                                        <button class="wi-action wi-action--token"
                                                :disabled="refreshingToken"
                                                @click="refreshToken('{{ $inst['instance_name'] }}', $el)">
                                            <span x-show="!refreshingToken">↻ Sincronizar token</span>
                                            <span x-show="refreshingToken" x-cloak>Aguarde...</span>
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400 italic">não disponível</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="flex gap-5 shrink-0">
                                <div class="wi-stat">
                                    <div class="wi-stat__val">{{ number_format($inst['msg_count']) }}</div>
                                    <div class="wi-stat__lbl">Mensagens</div>
                                </div>
                                <div class="wi-stat">
                                    <div class="wi-stat__val">{{ number_format($inst['contact_count']) }}</div>
                                    <div class="wi-stat__lbl">Contatos</div>
                                </div>
                                <div class="wi-stat">
                                    <div class="wi-stat__val">{{ number_format($inst['chat_count']) }}</div>
                                    <div class="wi-stat__lbl">Chats</div>
                                </div>
                            </div>
                        </div>

                        <!-- Ações -->
                        <div class="flex flex-wrap gap-2 mt-4 pt-3 border-t border-gray-100">
                            @if ($inst['in_db'])
                                <button class="wi-action wi-action--sync"
                                        :disabled="syncing"
                                        @click="syncStatus('{{ $inst['instance_name'] }}', $el)">
                                    <span x-show="!syncing">⟳ Sincronizar status</span>
                                    <span x-show="syncing" x-cloak>Aguarde...</span>
                                </button>
                            @endif
                            @if (!$active)
                                <a href="{{ route('whatsapp.connect', $inst['instance_name']) }}"
                                   class="wi-action wi-action--qr">
                                    📷 Conectar via QR Code
                                </a>
                            @endif
                            @if ($inst['in_db'])
                                <button class="wi-action wi-action--del"
                                        @click="deleteInstance('{{ $inst['instance_name'] }}')">
                                    Deletar
                                </button>
                            @endif
                            @if (!$inst['in_db'])
                                <span class="text-xs text-amber-700 italic self-center">
                                    Sem vínculo local —
                                    <a href="{{ route('whatsapp.index') }}" class="underline">conectar via app</a>
                                    para criar o vínculo.
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
                    this.showFeedback('Não foi possível copiar. Selecione manualmente.', false);
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
                } catch {
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
                        this.showFeedback('Token sincronizado!');
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        this.showFeedback(data.error || 'Erro ao sincronizar token.', false);
                    }
                } catch {
                    this.showFeedback('Erro de rede.', false);
                } finally {
                    comp.refreshingToken = false;
                }
            },

            async deleteInstance(instance) {
                if (!confirm(`Deletar a instância "${instance}"?`)) return;
                try {
                    const resp = await fetch(`/settings/whatsapp/delete/${instance}`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (data.success || data.deleted) {
                        this.showFeedback('Instância deletada.');
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        this.showFeedback(data.error || 'Erro ao deletar.', false);
                    }
                } catch {
                    this.showFeedback('Erro de rede.', false);
                }
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
