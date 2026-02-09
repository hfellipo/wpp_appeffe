// WhatsApp Inbox realtime UI (Alpine component)
// Loaded only on /whatsapp.

function safeJsonParse(text) {
    try {
        return JSON.parse(text);
    } catch (e) {
        return null;
    }
}

function nowIso() {
    return new Date().toISOString();
}

window.waInboxChatify = function waInboxChatify() {
    return {
        connected: false,
        instanceName: '',

        loadingConversations: false,
        loadingMessages: false,
        sending: false,

        search: '',
        conversationTab: 'direct',
        conversations: [],
        directConversations: [],
        groupConversations: [],
        activeConversation: null,
        messages: [],
        draft: '',
        showInfo: true,
        isCompact: window.matchMedia('(max-width: 680px)').matches,

        // polling (fallback)
        _pollTimer: null,
        _statusTimer: null,
        _isAtBottom: true,
        _lastMessageId: 0,
        _pollInFlight: false,
        _timersRunning: false,

        // realtime (SSE)
        _es: null,
        _esLastId: 0,
        _esConnected: false,
        _esRetryMs: 1000,
        _esRetryTimer: null,

        // infinite scroll
        _loadingOlder: false,
        _hasOlder: true,

        // contacts picker
        showContactPicker: false,
        contactsLoading: false,
        contacts: [],
        contactSearch: '',
        _contactSearchTimer: null,

        // presence (online / typing) per conversation
        presenceMap: {},
        _presenceTimeout: null,

        // detalhes: contato da tabela contacts (coluna direita)
        appContactLoading: false,
        appContact: null,
        appContactMessage: null,

        // emoji picker
        showEmojiPicker: false,
        emojiList: ['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','😉','😍','🥰','😘','😋','😜','🤔','🤗','👍','👎','👏','🙌','👋','❤️','🧡','💛','💚','💙','💜','🖤','💯','🔥','⭐','✨','🎉','🙏','✌️','🤝','💪','😎','🥳','😢','😭','😤','😡','🤬','😷','🤒','🤕','💀','💩','🤡','👻','🙈','🙉','🙊','💋','💌','💐','🌸','🌺','🌻','🌹','🥀','🌷','🍀','☀️','🌈','⚡','❄️','🔥','💧','🌊'],

        // fallback quando preview de imagem falha ao carregar
        imageLoadFail: {},
        // true/undefined = carregando, false = carregou (para mostrar feedback "Carregando...")
        imageLoading: {},

        // dropdown do grupo (opções: alterar nome, marcar criado por mim)
        openGroupMenuId: null,

        get filteredConversations() {
            const list = this.conversationTab === 'group' ? this.groupConversations : this.directConversations;
            const q = String(this.search || '').toLowerCase().trim();
            if (!q) return list;
            return list.filter((c) => {
                const name = String(c.contact_name || '').toLowerCase();
                const num = String(c.contact_number || '').toLowerCase();
                const prev = String(c.last_message_preview || '').toLowerCase();
                return name.includes(q) || num.includes(q) || prev.includes(q);
            });
        },

        get filteredGroupConversationsOwned() {
            const list = this.groupConversations.filter((c) => c.is_owner === true);
            const q = String(this.search || '').toLowerCase().trim();
            if (!q) return list;
            return list.filter((c) => {
                const name = String(c.contact_name || '').toLowerCase();
                const num = String(c.contact_number || '').toLowerCase();
                const prev = String(c.last_message_preview || '').toLowerCase();
                return name.includes(q) || num.includes(q) || prev.includes(q);
            });
        },

        get filteredGroupConversationsMember() {
            const list = this.groupConversations.filter((c) => c.is_owner !== true);
            const q = String(this.search || '').toLowerCase().trim();
            if (!q) return list;
            return list.filter((c) => {
                const name = String(c.contact_name || '').toLowerCase();
                const num = String(c.contact_number || '').toLowerCase();
                const prev = String(c.last_message_preview || '').toLowerCase();
                return name.includes(q) || num.includes(q) || prev.includes(q);
            });
        },

        setConversationTab(tab) {
            this.conversationTab = tab;
            if (this.activeConversation && (this.activeConversation.kind || 'direct') !== tab) {
                this.activeConversation = null;
                this.loadingMessages = false;
                this.messages = [];
            }
        },

        syncConversationLists() {
            this.directConversations = this.conversations.filter((c) => String(c.kind || 'direct') === 'direct');
            this.groupConversations = this.conversations.filter((c) => String(c.kind) === 'group');
        },

        toggleGroupMenu(c) {
            this.openGroupMenuId = this.openGroupMenuId === c.id ? null : c.id;
        },
        closeGroupMenu() {
            this.openGroupMenuId = null;
        },
        isGroupMenuOpen(c) {
            return this.openGroupMenuId === c.id;
        },
        async updateGroupName(c) {
            this.closeGroupMenu();
            const current = (c.contact_name && String(c.contact_name).trim()) || '';
            const name = window.prompt('Nome do grupo', current);
            if (name === null) return;
            const trimmed = String(name).trim();
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const resp = await fetch(`/whatsapp/api/conversations/${c.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token || '',
                },
                body: JSON.stringify({ custom_contact_name: trimmed || null }),
            });
            if (!resp.ok) return;
            await this.refreshConversations(true);
            if (this.activeConversation && this.activeConversation.id === c.id) {
                this.activeConversation.contact_name = trimmed || this.activeConversation.contact_name;
            }
        },
        async toggleGroupOwner(c) {
            this.closeGroupMenu();
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const newVal = !(c.is_owner === true);
            const resp = await fetch(`/whatsapp/api/conversations/${c.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token || '',
                },
                body: JSON.stringify({ user_marked_owner: newVal }),
            });
            if (!resp.ok) return;
            await this.refreshConversations(true);
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

            this.$watch('showInfo', (value) => {
                if (value && this.activeConversation) this.fetchAppContact();
                if (!value) { this.appContact = null; this.appContactMessage = null; }
            });
            this.$watch('activeConversation', (value) => {
                if (!value) {
                    this.loadingMessages = false;
                    this.messages = [];
                    this.imageLoadFail = {};
                    this.imageLoading = {};
                    this.appContact = null;
                    this.appContactMessage = null;
                    return;
                }
                if (this.showInfo) this.fetchAppContact();
            });

            // Start realtime first (so UI updates instantly)
            this.connectStream();

            await this.refreshStatus();
            await this.refreshConversations();

            // Poll as fallback (SSE can drop on some proxies)
            this.startTimers();

            // Pause polling when the tab is hidden (avoids hammering DB)
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) this.stopTimers();
                else this.startTimers();
            });
        },

        openContactPicker() {
            this.showContactPicker = true;
            this.contactSearch = '';
            this.contacts = [];
            this.fetchContacts('');
        },

        closeContactPicker() {
            this.showContactPicker = false;
        },

        async fetchAppContact() {
            const c = this.activeConversation;
            if (!c || !c.id) {
                this.appContact = null;
                this.appContactMessage = null;
                return;
            }
            this.appContactLoading = true;
            this.appContact = null;
            this.appContactMessage = null;
            try {
                const resp = await fetch(`/whatsapp/api/conversations/${c.id}/app-contact`, { headers: { Accept: 'application/json' } });
                const data = await resp.json().catch(() => ({}));
                if (data.success) {
                    this.appContact = data;
                    if (!data.found && data.message) this.appContactMessage = data.message;
                }
            } finally {
                this.appContactLoading = false;
            }
        },

        onContactSearchInput() {
            if (this._contactSearchTimer) clearTimeout(this._contactSearchTimer);
            this._contactSearchTimer = setTimeout(() => {
                this.fetchContacts(this.contactSearch);
            }, 250);
        },

        async fetchContacts(search) {
            this.contactsLoading = true;
            try {
                const q = String(search || '').trim();
                const url = q ? `/whatsapp/api/contacts?search=${encodeURIComponent(q)}` : '/whatsapp/api/contacts';
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await resp.json().catch(() => ({}));
                this.contacts = Array.isArray(data.items) ? data.items : [];
            } finally {
                this.contactsLoading = false;
            }
        },

        async startConversationFromContact(ct) {
            if (!ct || !ct.id) return;
            try {
                const resp = await fetch('/whatsapp/api/conversations/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({ contact_id: ct.id }),
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || data.success === false) return;

                const conv = data.conversation || null;
                if (conv) {
                    // Upsert to top
                    const existing = this.conversations.find((x) => String(x.id) === String(conv.id));
                    if (existing) Object.assign(existing, conv);
                    else this.conversations.unshift(conv);

                    this.conversations = [
                        conv,
                        ...this.conversations.filter((x) => String(x.id) !== String(conv.id)),
                    ];
                    this.syncConversationLists();
                    this.closeContactPicker();
                    await this.openConversation(conv);
                }
            } catch (e) {}
        },

        startTimers() {
            if (this._timersRunning) return;
            this._timersRunning = true;
            // Conversations are also updated by SSE, but keep a light poll as safety-net
            this._pollTimer = setInterval(() => this.poll(), 12000);
            this._statusTimer = setInterval(() => this.refreshStatus(), 20000);
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

        connectStream() {
            if (typeof window.EventSource === 'undefined') return;

            // Close existing
            if (this._es) {
                try { this._es.close(); } catch (e) {}
                this._es = null;
            }
            if (this._esRetryTimer) {
                clearTimeout(this._esRetryTimer);
                this._esRetryTimer = null;
            }

            const url = `/whatsapp/stream?last_id=${encodeURIComponent(String(this._esLastId || 0))}`;
            const es = new EventSource(url);
            this._es = es;

            es.onopen = () => {
                this._esConnected = true;
                this._esRetryMs = 1000;
            };
            es.onerror = () => {
                this._esConnected = false;
                try { es.close(); } catch (e) {}
                this._es = null;
                this.scheduleStreamReconnect();
            };

            es.addEventListener('wa.ready', (evt) => {
                const data = safeJsonParse(evt.data) || {};
                if (data.last_id) this._esLastId = Number(data.last_id) || this._esLastId;
            });

            es.addEventListener('wa.connection.update', (evt) => {
                const data = safeJsonParse(evt.data) || {};
                const state = String(data.state || '');
                const inst = String(data.instance_name || '');
                if (inst) this.instanceName = inst;
                this.connected = ['open', 'connected', 'online', 'ready'].includes(state.toLowerCase());
                window.dispatchEvent(new CustomEvent('whatsapp-connection-changed', {
                    detail: { connected: this.connected },
                }));
                this.bumpLastEventId(data);
            });

            es.addEventListener('wa.conversation.read', (evt) => {
                const data = safeJsonParse(evt.data) || {};
                const convId = String(data.conversation_id || '');
                if (!convId) return;
                const c = this.conversations.find((x) => String(x.id) === convId);
                if (c) c.unread_count = 0;
                this.bumpLastEventId(data);
            });

            es.addEventListener('wa.message.created', (evt) => {
                const data = safeJsonParse(evt.data) || {};
                this.handleIncomingMessageEvent(data);
                this.bumpLastEventId(data);
            });

            es.addEventListener('wa.conversation.created', (evt) => {
                const data = safeJsonParse(evt.data) || {};
                const conv = data.conversation || null;
                if (conv && conv.id) {
                    const existing = this.conversations.find((x) => String(x.id) === String(conv.id));
                    if (existing) Object.assign(existing, conv);
                    else this.conversations.unshift(conv);
                    this.conversations = this.deduplicateConversations([
                        conv,
                        ...this.conversations.filter((x) => String(x.id) !== String(conv.id)),
                    ]);
                    this.syncConversationLists();
                }
                this.bumpLastEventId(data);
            });

            es.addEventListener('wa.message.updated', (evt) => {
                const data = safeJsonParse(evt.data) || {};
                const msg = data.message || {};
                const msgId = String(msg.id || '');
                if (!msgId) return;
                const m = this.messages.find((x) => String(x.id) === msgId);
                if (m) {
                    m.status = msg.status ?? m.status;
                    m.delivered_at = msg.delivered_at ?? m.delivered_at;
                    m.read_at = msg.read_at ?? m.read_at;
                }
                this.bumpLastEventId(data);
            });

            es.addEventListener('wa.message.deleted', (evt) => {
                const data = safeJsonParse(evt.data) || {};
                const msg = data.message || {};
                const msgId = String(msg.id || '');
                if (!msgId) return;
                const m = this.messages.find((x) => String(x.id) === msgId);
                if (m) {
                    m.message_type = 'deleted';
                    m.body = null;
                }
                this.bumpLastEventId(data);
            });

            es.addEventListener('wa.presence', (evt) => {
                const data = safeJsonParse(evt.data) || {};
                const convId = String(data.conversation_id || '');
                const presence = String(data.presence || '').toLowerCase();
                if (!convId) return;
                this.presenceMap[convId] = { presence, at: Date.now() };
                this.presenceMap = { ...this.presenceMap };
                this.clearPresenceAfterDelay(convId);
                this.bumpLastEventId(data);
            });
        },

        clearPresenceAfterDelay(convId) {
            if (this._presenceTimeout) clearTimeout(this._presenceTimeout);
            this._presenceTimeout = setTimeout(() => {
                this._presenceTimeout = null;
            }, 6000);
        },

        getPresenceForConversation(convId) {
            if (!convId) return null;
            const p = this.presenceMap[String(convId)];
            if (!p) return null;
            const age = Date.now() - (p.at || 0);
            if (age > 12000) return null;
            return p.presence || null;
        },

        isConversationOnline(convId) {
            const pres = this.getPresenceForConversation(convId);
            return pres === 'available' || pres === 'composing' || pres === 'recording' || pres === 'paused';
        },

        isConversationTyping(convId) {
            const pres = this.getPresenceForConversation(convId);
            return pres === 'composing' || pres === 'recording';
        },

        bumpLastEventId(data) {
            const id = Number(data._event_id || 0);
            if (id > (this._esLastId || 0)) this._esLastId = id;
        },

        scheduleStreamReconnect() {
            if (this._esRetryTimer) return;
            const wait = Math.min(15000, Math.max(1000, this._esRetryMs || 1000));
            this._esRetryMs = Math.min(15000, wait * 1.6);
            this._esRetryTimer = setTimeout(() => {
                this._esRetryTimer = null;
                this.connectStream();
            }, wait);
        },

        handleIncomingMessageEvent(data) {
            const conv = data.conversation || null;
            const msg = data.message || null;
            const convId = String(data.conversation_id || (conv ? conv.id : '') || '');
            if (!convId || !msg) return;

            // Update conversation list item
            let c = this.conversations.find((x) => String(x.id) === convId);
            if (!c) {
                // New conversation (create minimal and refresh in background)
                c = conv || { id: convId, unread_count: 0 };
                this.conversations.unshift(c);
            } else if (conv) {
                Object.assign(c, conv);
            }

            // Move to top e deduplicar (um único item por chat)
            const rest = this.conversations.filter((x) => String(x.id) !== String(c.id));
            this.conversations = this.deduplicateConversations([c, ...rest]);
            this.syncConversationLists();

            // If active conversation, append message immediately
            if (this.activeConversation && String(this.activeConversation.id) === convId) {
                const exists = this.messages.some((m) => String(m.id) === String(msg.id));
                if (!exists) {
                    this.messages.push(msg);
                    this._lastMessageId = msg.id || this._lastMessageId;
                    this.$nextTick(() => this.scrollToBottom(false));
                }
                // When viewing, keep it read
                c.unread_count = 0;
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

        conversationUniqueKey(c) {
            const kind = String(c.kind || 'direct');
            if (kind === 'group') return `g|${c.instance_name || ''}|${c.peer_jid || c.contact_number || c.id}`;
            const num = String(c.contact_number || '').replace(/\D/g, '').replace(/^0+/, '') || c.id;
            return `d|${c.instance_name || ''}|${num}`;
        },

        deduplicateConversations(list) {
            const seen = new Set();
            return list.filter((c) => {
                const key = this.conversationUniqueKey(c);
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            });
        },

        async refreshConversations(silent = false) {
            if (!silent) this.loadingConversations = true;
            try {
                const resp = await fetch('/whatsapp/api/conversations', { headers: { 'Accept': 'application/json' } });
                const data = await resp.json().catch(() => ({}));
                const items = Array.isArray(data.items) ? data.items : [];
                this.conversations = this.deduplicateConversations(items);
                this.directConversations = this.conversations.filter((c) => String(c.kind || 'direct') === 'direct');
                this.groupConversations = this.conversations.filter((c) => String(c.kind) === 'group');
                this.fetchAvatarsForList();
            } finally {
                if (!silent) this.loadingConversations = false;
            }
        },

        async fetchAvatarForConversation(c) {
            if (!c || !c.id || c.avatar_url) return;
            try {
                const resp = await fetch(`/whatsapp/api/conversations/${c.id}/avatar`, { headers: { 'Accept': 'application/json' } });
                const data = await resp.json().catch(() => ({}));
                if (data.success && data.avatar_url) {
                    const conv = this.conversations.find((x) => String(x.id) === String(c.id));
                    if (conv) conv.avatar_url = data.avatar_url;
                    if (this.activeConversation && String(this.activeConversation.id) === String(c.id)) {
                        this.activeConversation.avatar_url = data.avatar_url;
                    }
                }
            } catch (e) {}
        },

        fetchAvatarsForList() {
            const withoutAvatar = this.conversations.filter((c) => !c.avatar_url).slice(0, 10);
            withoutAvatar.forEach((c, i) => {
                setTimeout(() => this.fetchAvatarForConversation(c), i * 400);
            });
        },

        async openConversation(c) {
            // Imediato ao clicar: mostrar carregando e limpar mensagens (evita ver conversa anterior)
            this.loadingMessages = true;
            this.messages = [];
            this.imageLoadFail = {};
            this.imageLoading = {};
            this.activeConversation = c;
            if (this.isCompact) this.showInfo = false;
            this._hasOlder = true;
            if (!c.avatar_url) this.fetchAvatarForConversation(c); // não await: não bloquear feedback
            await this.refreshMessages(c);
        },

        async refreshMessages(c) {
            if (!this.loadingMessages) this.loadingMessages = true;
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

        async loadOlder() {
            if (!this.activeConversation) return;
            if (this._loadingOlder || !this._hasOlder) return;
            if (!this.messages.length) return;

            const before = this.messages[0].id;
            if (!before) return;

            this._loadingOlder = true;
            const pane = this.$refs.messagesPane;
            const prevScroll = pane ? pane.scrollHeight : 0;

            try {
                const url = `/whatsapp/api/conversations/${this.activeConversation.id}/messages?before=${encodeURIComponent(before)}&limit=60`;
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await resp.json().catch(() => ({}));
                const items = Array.isArray(data.items) ? data.items : [];
                if (!items.length) {
                    this._hasOlder = false;
                    return;
                }
                // Prepend older messages
                const existing = new Set(this.messages.map((m) => String(m.id)));
                const toAdd = items.filter((m) => !existing.has(String(m.id)));
                this.messages = [...toAdd, ...this.messages];

                this.$nextTick(() => {
                    if (!pane) return;
                    const newScroll = pane.scrollHeight;
                    pane.scrollTop = newScroll - prevScroll;
                });
            } finally {
                this._loadingOlder = false;
            }
        },

        insertEmoji(emoji) {
            const el = this.$refs.draftInput;
            const current = String(this.draft || '');
            if (el && typeof el.selectionStart === 'number') {
                const start = el.selectionStart;
                const end = typeof el.selectionEnd === 'number' ? el.selectionEnd : start;
                this.draft = current.slice(0, start) + emoji + current.slice(end);
                this.$nextTick(() => {
                    if (!this.$refs.draftInput) return;
                    const pos = start + emoji.length;
                    this.$refs.draftInput.setSelectionRange(pos, pos);
                    this.$refs.draftInput.focus();
                });
            } else {
                this.draft = current + emoji;
            }
        },

        async sendMessage() {
            if (!this.activeConversation) return;
            const text = String(this.draft || '').trim();
            if (!text) return;

            const conversationId = String(this.activeConversation.id);

            // Optimistic UI (like WhatsApp Web)
            const tmpId = `tmp-${nowIso()}-${Math.random().toString(16).slice(2)}`;
            const optimistic = {
                id: tmpId,
                direction: 'out',
                message_type: 'text',
                body: text,
                status: 'sending',
                sent_at: nowIso(),
                created_at: nowIso(),
            };
            this.messages.push(optimistic);
            this.draft = '';
            this.$nextTick(() => this.scrollToBottom(true));

            this.sending = true;
            const url = `/whatsapp/api/conversations/${conversationId}/send`;
            const payload = { text };
            console.log('[WhatsApp send] Request:', {
                url,
                method: 'POST',
                conversationId,
                payload,
                activeConversation: this.activeConversation ? {
                    id: this.activeConversation.id,
                    instance_name: this.activeConversation.instance_name,
                    contact_number: this.activeConversation.contact_number,
                    contact_name: this.activeConversation.contact_name,
                } : null,
            });
            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json().catch(() => ({}));
                console.log('[WhatsApp send] Response:', { ok: resp.ok, status: resp.status, data });

                // Só aplica resultado se ainda estiver na mesma conversa (evita aplicar em chat errado após troca)
                const stillSameChat = this.activeConversation && String(this.activeConversation.id) === conversationId;

                if (!resp.ok || data.success === false) {
                    if (stillSameChat) {
                        optimistic.status = 'failed';
                        let msg = data.error || data.message || (data.errors && data.errors.text && data.errors.text[0]) || data.details?.message || 'Falha ao enviar. Tente novamente.';
                        if (data.attempted_number_formatted) {
                            msg += `\n\nNúmero tentado: ${data.attempted_number_formatted}`;
                        }
                        if (typeof alert !== 'undefined') alert(msg);
                    } else {
                        const idx = this.messages.findIndex((m) => String(m.id) === String(tmpId));
                        if (idx >= 0) this.messages.splice(idx, 1);
                    }
                    return;
                }

                if (!stillSameChat) {
                    const idx = this.messages.findIndex((m) => String(m.id) === String(tmpId));
                    if (idx >= 0) this.messages.splice(idx, 1);
                    return;
                }

                // Replace tmp message with server message
                const serverMsg = data.message || null;
                if (serverMsg) {
                    const serverId = String(serverMsg.id || '');
                    const serverExists = serverId !== '' && this.messages.some((m) => String(m.id) === serverId);
                    const idxTmp = this.messages.findIndex((m) => String(m.id) === String(tmpId));

                    if (idxTmp >= 0) {
                        if (serverExists) this.messages.splice(idxTmp, 1);
                        else this.messages.splice(idxTmp, 1, serverMsg);
                    } else if (!serverExists) {
                        this.messages.push(serverMsg);
                    }
                    this._lastMessageId = serverMsg.id || this._lastMessageId;
                }

                await this.refreshConversations(true);
            } finally {
                this.sending = false;
            }
        },

        onAttachSelected(evt) {
            const file = evt.target?.files?.[0];
            if (!file || !this.activeConversation) return;
            evt.target.value = '';
            this.sendMediaFile(file);
        },

        async sendMediaFile(file) {
            if (!this.activeConversation) return;
            const conversationId = String(this.activeConversation.id);
            const caption = String(this.draft || '').trim();

            const form = new FormData();
            form.append('file', file);
            if (caption) form.append('caption', caption);

            const tmpId = `tmp-media-${nowIso()}-${Math.random().toString(16).slice(2)}`;
            const mediaType = file.type.startsWith('image/') ? 'image' : (file.type.startsWith('video/') ? 'video' : 'document');
            const optimistic = {
                id: tmpId,
                direction: 'out',
                message_type: mediaType,
                body: caption || '',
                status: 'sending',
                sent_at: nowIso(),
                created_at: nowIso(),
                attachment: { type: mediaType, url: null, caption_preview: caption || file.name },
            };
            this.messages.push(optimistic);
            if (caption) this.draft = '';
            this.$nextTick(() => this.scrollToBottom(true));

            this.sending = true;
            const url = `/whatsapp/api/conversations/${conversationId}/send-media`;
            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                    },
                    body: form,
                });
                const data = await resp.json().catch(() => ({}));

                const stillSameChat = this.activeConversation && String(this.activeConversation.id) === conversationId;
                if (!resp.ok || data.success === false) {
                    if (stillSameChat) {
                        const idx = this.messages.findIndex((m) => String(m.id) === tmpId);
                        if (idx >= 0) this.messages[idx].status = 'failed';
                        if (typeof alert !== 'undefined') alert(data.error || 'Falha ao enviar mídia. Tente novamente.');
                    } else {
                        const idx = this.messages.findIndex((m) => String(m.id) === tmpId);
                        if (idx >= 0) this.messages.splice(idx, 1);
                    }
                    return;
                }

                if (!stillSameChat) {
                    const idx = this.messages.findIndex((m) => String(m.id) === tmpId);
                    if (idx >= 0) this.messages.splice(idx, 1);
                    return;
                }

                const serverMsg = data.message || null;
                if (serverMsg) {
                    const serverId = String(serverMsg.id || '');
                    const idxTmp = this.messages.findIndex((m) => String(m.id) === tmpId);
                    if (idxTmp >= 0) {
                        const serverExists = this.messages.some((m) => String(m.id) === serverId);
                        if (serverExists) this.messages.splice(idxTmp, 1);
                        else this.messages.splice(idxTmp, 1, serverMsg);
                    } else if (!this.messages.some((m) => String(m.id) === serverId)) {
                        this.messages.push(serverMsg);
                    }
                    this._lastMessageId = serverMsg.id || this._lastMessageId;
                }
                await this.refreshConversations(true);
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

            // infinite scroll up
            if (el.scrollTop < 60) {
                this.loadOlder();
            }

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

        formatFileSize(bytes) {
            if (bytes == null || bytes === '') return '';
            const n = Number(bytes);
            if (Number.isNaN(n) || n < 0) return '';
            if (n < 1024) return n + ' B';
            if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
            return (n / (1024 * 1024)).toFixed(1) + ' MB';
        },

        setImageLoadFail(msgId) {
            if (msgId) {
                this.imageLoadFail[msgId] = true;
                this.imageLoading[msgId] = false;
            }
            this.imageLoadFail = { ...this.imageLoadFail };
            this.imageLoading = { ...this.imageLoading };
        },

        setImageLoaded(msgId) {
            if (msgId) this.imageLoading[msgId] = false;
            this.imageLoading = { ...this.imageLoading };
        },

        formatTimeAgo(v) {
            if (!v) return '';
            const d = new Date(v);
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        },

        /**
         * Return { iconClass, colorClass } for outgoing message ticks.
         */
        tickForMessage(m) {
            if (!m || String(m.direction) !== 'out') return null;

            const status = String(m.status || '').toLowerCase();
            const hasDelivered = !!m.delivered_at || ['delivered', 'delivery_ack', 'delivered_ack', 'received', '2'].includes(status) || status.includes('deliver');
            const hasRead = !!m.read_at || ['read', 'seen', 'read_ack', '3'].includes(status) || status.includes('read') || status.includes('seen');

            if (status === 'failed') {
                return { iconClass: 'fas fa-exclamation-circle', colorClass: 'wa-tick-failed' };
            }
            if (status === 'sending') {
                return { iconClass: 'fas fa-circle-notch fa-spin', colorClass: 'wa-tick-sending' };
            }
            if (hasRead) {
                return { iconClass: 'fas fa-check-double', colorClass: 'wa-tick-read' };
            }
            if (hasDelivered) {
                return { iconClass: 'fas fa-check-double', colorClass: 'wa-tick-delivered' };
            }
            if (status === 'sent' || status === 'server_ack' || status === 'ack') {
                return { iconClass: 'fas fa-check', colorClass: 'wa-tick-sent' };
            }

            // Default: show single check for outgoing messages once persisted
            return { iconClass: 'fas fa-check', colorClass: 'wa-tick-sent' };
        },
    };
};

