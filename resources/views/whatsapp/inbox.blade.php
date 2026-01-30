<x-app-layout>
    @push('styles')
        <link href="{{ asset('css/chatify/style.css') }}" rel="stylesheet" />
        <link href="{{ asset('css/chatify/light.mode.css') }}" rel="stylesheet" />
        <style>
            :root {
                --primary-color: #25D366; /* WhatsApp green */
                --app-topbar-height: 4rem; /* height of layouts.navigation */
            }

            /* Let Alpine x-show work (no !important) */
            .messenger-sendCard { display: block; }

            /* Full-height inside app layout (no footer on these routes) */
            .messenger { height: calc(100vh - var(--app-topbar-height)) !important; }

            /* Keep Chatify responsive rules; only tune desktop widths */
            @media (min-width: 1061px) {
                .messenger-listView { width: 30%; min-width: 320px; }
                .messenger-infoView { width: 30%; min-width: 260px; }
            }

            /* Chatify responsive uses top:0; offset to keep app header visible */
            @media (max-width: 1060px) {
                .messenger-infoView { top: var(--app-topbar-height) !important; }
            }
            @media (max-width: 980px) {
                .messenger-listView { top: var(--app-topbar-height) !important; }
            }
            @media (max-width: 680px) {
                .messenger-messagingView {
                    top: var(--app-topbar-height) !important;
                    height: calc(100vh - var(--app-topbar-height)) !important;
                }
                .messenger-listView {
                    top: var(--app-topbar-height) !important;
                    height: calc(100vh - var(--app-topbar-height)) !important;
                }
                .messenger-infoView { top: var(--app-topbar-height) !important; }
            }
        </style>
    @endpush

    <div x-data="waInboxChatify()" x-init="init()">
        <div class="messenger">
            {{-- List side --}}
            <div class="messenger-listView" :class="activeConversation ? 'conversation-active' : ''">
                <div class="m-header">
                    <nav>
                        <a href="#">
                            <i class="fas fa-inbox"></i>
                            <span class="messenger-headTitle">MESSAGES</span>
                        </a>
                        <nav class="m-header-right">
                            <a href="{{ route('whatsapp.index') }}" title="Configurar conexão">
                                <i class="fas fa-cog"></i>
                            </a>
                        </nav>
                    </nav>
                    <input type="text" class="messenger-search" placeholder="Search" x-model="search" />
                </div>

                <div class="m-body contacts-container">
                    <div class="show messenger-tab users-tab app-scroll" data-view="users">
                        <p class="messenger-title"><span>All Messages</span></p>

                        <div style="width: 100%; height: calc(100% - 120px); position: relative;">
                            <template x-if="loadingConversations">
                                <p class="message-hint center-el"><span>Loading...</span></p>
                            </template>

                            <template x-if="!loadingConversations && filteredConversations.length === 0">
                                <p class="message-hint center-el"><span>No conversations yet</span></p>
                            </template>

                            <template x-for="c in filteredConversations" :key="c.id">
                                <table
                                    class="messenger-list-item"
                                    :class="activeConversation && activeConversation.id === c.id ? 'm-list-active' : ''"
                                    :data-contact="c.id"
                                >
                                    <tr data-action="0" @click="openConversation(c)">
                                        <td style="position: relative">
                                            <div class="avatar av-m" style="background-image: url('');"></div>
                                        </td>
                                        <td>
                                            <p>
                                                <span x-text="c.contact_name || formatNumber(c.contact_number)"></span>
                                                <span class="contact-item-time" x-text="formatTimeAgo(c.last_message_at)"></span>
                                            </p>
                                            <span x-text="c.last_message_preview || ''"></span>
                                            <template x-if="(c.unread_count || 0) > 0">
                                                <b x-text="c.unread_count"></b>
                                            </template>
                                        </td>
                                    </tr>
                                </table>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Messaging side --}}
            <div class="messenger-messagingView">
                <div class="m-header m-header-messaging">
                    <nav class="chatify-d-flex chatify-justify-content-between chatify-align-items-center">
                        <div class="chatify-d-flex chatify-justify-content-between chatify-align-items-center">
                            <a href="#" class="show-listView" @click.prevent="activeConversation = null"><i class="fas fa-arrow-left"></i></a>
                            <div class="avatar av-s header-avatar" style="margin: 0px 10px; margin-top: -5px; margin-bottom: -5px;"></div>
                            <a href="#" class="user-name" x-text="activeConversation ? (activeConversation.contact_name || formatNumber(activeConversation.contact_number)) : 'WhatsApp'"></a>
                        </div>
                        <nav class="m-header-right">
                            <a href="#" class="show-infoSide" @click.prevent="showInfo = !showInfo"><i class="fas fa-info-circle"></i></a>
                        </nav>
                    </nav>
                    <div class="internet-connection" style="display:block">
                        <span class="ic-connected" x-show="connected">Connected</span>
                        <span class="ic-noInternet" x-show="!connected">No connection</span>
                    </div>
                </div>

                <div class="m-body messages-container app-scroll" x-ref="messagesPane" @scroll="onScrollMessages()">
                    <div class="messages">
                        <template x-if="!activeConversation">
                            <p class="message-hint center-el"><span>Please select a chat to start messaging</span></p>
                        </template>

                        <template x-if="activeConversation && loadingMessages">
                            <p class="message-hint center-el"><span>Loading...</span></p>
                        </template>

                        <template x-for="m in messages" :key="m.id">
                            <div class="message-card" :class="m.direction === 'out' ? 'mc-sender' : ''">
                                <div class="message-card-content">
                                    <div class="message">
                                        <span x-text="m.body || (m.message_type ? '['+m.message_type+']' : '')"></span>
                                        <span class="message-time">
                                            <span class="time" x-text="formatTimeShort(m.created_at)"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="messenger-sendCard" x-show="!!activeConversation">
                    <form @submit.prevent="sendMessage()">
                        <button type="button" title="Emoji (em breve)" disabled><span class="fas fa-smile"></span></button>
                        <textarea
                            name="message"
                            class="m-send app-scroll"
                            placeholder="Type a message.."
                            x-model="draft"
                            @keydown.enter.prevent="maybeSend($event)"
                            :disabled="!connected || sending"
                        ></textarea>
                        <button class="send-button" type="submit" :disabled="!connected || sending || !draft.trim()">
                            <span class="fas fa-paper-plane"></span>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Info side --}}
            <div class="messenger-infoView app-scroll" x-show="showInfo && !isCompact">
                <nav>
                    <p>User Details</p>
                    <a href="#" @click.prevent="showInfo = false"><i class="fas fa-times"></i></a>
                </nav>
                <div style="text-align:center; padding: 1rem;">
                    <div class="avatar av-l" style="margin: 0 auto;"></div>
                    <p class="info-name" style="margin-top: 1rem;" x-text="activeConversation ? (activeConversation.contact_name || formatNumber(activeConversation.contact_number)) : '-'"></p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/chatify/font.awesome.min.js') }}"></script>
        <script>
            function waInboxChatify() {
                return {
                    connected: false,
                    instanceName: '',

                    loadingConversations: false,
                    loadingMessages: false,
                    sending: false,

                    search: '',
                    conversations: [],
                    activeConversation: null,
                    messages: [],
                    draft: '',
                    showInfo: true,
                    isCompact: window.matchMedia('(max-width: 680px)').matches,

                    _pollTimer: null,
                    _statusTimer: null,
                    _isAtBottom: true,
                    _lastMessageId: 0,
                    _pollInFlight: false,
                    _timersRunning: false,

                    get filteredConversations() {
                        const q = String(this.search || '').toLowerCase().trim();
                        if (!q) return this.conversations;
                        return this.conversations.filter((c) => {
                            const name = String(c.contact_name || '').toLowerCase();
                            const num = String(c.contact_number || '').toLowerCase();
                            const prev = String(c.last_message_preview || '').toLowerCase();
                            return name.includes(q) || num.includes(q) || prev.includes(q);
                        });
                    },

                    async init() {
                        // Track compact mode (mobile)
                        const mq = window.matchMedia('(max-width: 680px)');
                        const onMq = (e) => {
                            this.isCompact = !!e.matches;
                            if (this.isCompact) this.showInfo = false;
                        };
                        if (mq.addEventListener) mq.addEventListener('change', onMq);
                        else mq.addListener(onMq);

                        if (this.isCompact) this.showInfo = false;

                        await this.refreshStatus();
                        await this.refreshConversations();

                        this.startTimers();

                        // Pause polling when the tab is hidden (avoids hammering DB)
                        document.addEventListener('visibilitychange', () => {
                            if (document.hidden) this.stopTimers();
                            else this.startTimers();
                        });
                    },

                    startTimers() {
                        if (this._timersRunning) return;
                        this._timersRunning = true;
                        this._pollTimer = setInterval(() => this.poll(), 7000);
                        this._statusTimer = setInterval(() => this.refreshStatus(), 15000);
                    },

                    stopTimers() {
                        this._timersRunning = false;
                        if (this._pollTimer) clearInterval(this._pollTimer);
                        if (this._statusTimer) clearInterval(this._statusTimer);
                        this._pollTimer = null;
                        this._statusTimer = null;
                    },

                    async poll() {
                        if (this._pollInFlight) return;
                        this._pollInFlight = true;
                        try {
                            await this.refreshConversations(true);
                            if (this.activeConversation) {
                                await this.pollMessages(this.activeConversation);
                            }
                        } finally {
                            this._pollInFlight = false;
                        }
                    },

                    async refreshStatus() {
                        try {
                            const resp = await fetch('/settings/whatsapp/status', { headers: { 'Accept': 'application/json' } });
                            const data = await resp.json().catch(() => ({}));
                            const state = data.state || data.status || null;
                            const inst = data.instanceName || '';

                            this.instanceName = inst ? String(inst) : '';
                            this.connected = ['open', 'connected', 'online', 'ready'].includes(String(state || '').toLowerCase());

                            window.dispatchEvent(new CustomEvent('whatsapp-connection-changed', {
                                detail: { connected: this.connected },
                            }));
                        } catch (e) {
                            this.connected = false;
                        }
                    },

                    async refreshConversations(silent = false) {
                        if (!silent) this.loadingConversations = true;
                        try {
                            const resp = await fetch('/whatsapp/api/conversations', { headers: { 'Accept': 'application/json' } });
                            const data = await resp.json().catch(() => ({}));
                            this.conversations = Array.isArray(data.items) ? data.items : [];
                        } finally {
                            if (!silent) this.loadingConversations = false;
                        }
                    },

                    async openConversation(c) {
                        this.activeConversation = c;
                        if (this.isCompact) this.showInfo = false;
                        await this.refreshMessages(c);
                    },

                    async refreshMessages(c) {
                        this.loadingMessages = true;
                        try {
                            const resp = await fetch(`/whatsapp/api/conversations/${c.id}/messages`, { headers: { 'Accept': 'application/json' } });
                            const data = await resp.json().catch(() => ({}));
                            this.messages = Array.isArray(data.items) ? data.items : [];
                            this._lastMessageId = this.messages.length ? (this.messages[this.messages.length - 1].id || 0) : 0;
                            this.$nextTick(() => this.scrollToBottom(true));
                        } finally {
                            this.loadingMessages = false;
                        }
                    },

                    async pollMessages(c) {
                        try {
                            const after = this._lastMessageId || 0;
                            const resp = await fetch(`/whatsapp/api/conversations/${c.id}/messages?after=${encodeURIComponent(after)}`, { headers: { 'Accept': 'application/json' } });
                            const data = await resp.json().catch(() => ({}));
                            const items = Array.isArray(data.items) ? data.items : [];
                            if (!items.length) return;
                            for (const m of items) {
                                this.messages.push(m);
                                this._lastMessageId = m.id || this._lastMessageId;
                            }
                            this.$nextTick(() => this.scrollToBottom(false));
                        } catch (e) {}
                    },

                    async sendMessage() {
                        if (!this.activeConversation) return;
                        const text = String(this.draft || '').trim();
                        if (!text) return;

                        this.sending = true;
                        try {
                            const resp = await fetch(`/whatsapp/api/conversations/${this.activeConversation.id}/send`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                                },
                                body: JSON.stringify({ text }),
                            });
                            const data = await resp.json().catch(() => ({}));
                            if (!resp.ok || data.success === false) return;

                            this.draft = '';
                            await this.refreshMessages(this.activeConversation);
                            await this.refreshConversations();
                        } finally {
                            this.sending = false;
                        }
                    },

                    maybeSend(e) {
                        if (e.shiftKey) return;
                        this.sendMessage();
                    },

                    onScrollMessages() {
                        const el = this.$refs.messagesPane;
                        if (!el) return;
                        const threshold = 80;
                        this._isAtBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) < threshold;
                    },

                    scrollToBottom(force) {
                        const el = this.$refs.messagesPane;
                        if (!el) return;
                        if (!force && !this._isAtBottom) return;
                        el.scrollTop = el.scrollHeight;
                    },

                    formatNumber(n) {
                        const s = String(n || '');
                        if (!s) return '';
                        return '+' + s;
                    },

                    formatTimeShort(v) {
                        if (!v) return '';
                        const d = new Date(v);
                        if (Number.isNaN(d.getTime())) return '';
                        return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
                    },

                    formatTimeAgo(v) {
                        if (!v) return '';
                        const d = new Date(v);
                        if (Number.isNaN(d.getTime())) return '';
                        return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>

