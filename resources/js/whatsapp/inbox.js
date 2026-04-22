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
        hasInstance: null,   // null = ainda carregando, false = sem instância, true = tem instância
        waConfigured: null,  // null = ainda carregando, false = API não configurada

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

        // infinite scroll (messages)
        _loadingOlder: false,
        _hasOlder: true,

        // conversation list pagination
        _hasMoreConversations: false,
        _loadingMoreConversations: false,
        _conversationsOffset: 0,

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

        // detalhes: painel completo (listas, tags, automações)
        detailsLoading: false,
        detailsData: null,
        // listas
        _detailsListsOpen: true,
        _detailsListInput: '',
        _detailsListMode: 'select', // 'select' | 'create'
        _detailsListSelectedId: '',
        _detailsListCreating: false,
        // tags
        _detailsTagsOpen: true,
        _detailsTagInput: '',
        _detailsTagMode: 'select',
        _detailsTagSelectedId: '',
        _detailsTagCreating: false,
        // automações
        _detailsAutoOpen: true,
        _detailsAutoSelectedId: '',
        _detailsAutoRunning: false,
        _detailsAutoResult: null,
        // seção info
        _detailsInfoOpen: true,

        // audio recording
        isRecording: false,
        recordingSeconds: 0,
        _recordingTimer: null,
        _mediaRecorder: null,
        _audioChunks: [],

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

        extractingMembers: false,
        // Modal após extrair membros: adicionar a lista e/ou tag
        showExtractedModal: false,
        extractedContactIds: [],
        extractedSummary: '',
        listas: [],
        tags: [],
        listasTagsLoading: false,
        addToList: false,
        addToTag: false,
        selectedListId: '',
        newListName: '',
        selectedTagId: '',
        newTagName: '',
        applyingExtracted: false,

        async extractGroupMembers(c) {
            this.closeGroupMenu();
            if (this.extractingMembers) return;
            this.extractingMembers = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            try {
                const resp = await fetch(`/whatsapp/api/conversations/${c.id}/extract-members`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                    },
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) {
                    alert(data.error || 'Não foi possível extrair os membros.');
                    return;
                }
                const count = Array.isArray(data.contact_ids) ? data.contact_ids.length : (data.participants_count || 0);
                const created = data.contacts_created || 0;
                const summary = `${count} contato(s) extraído(s).${created > 0 ? ' ' + created + ' contato(s) criado(s).' : ''}`;
                this.extractedContactIds = data.contact_ids || [];
                this.extractedSummary = summary;
                this.addToList = false;
                this.addToTag = false;
                this.selectedListId = '';
                this.newListName = '';
                this.selectedTagId = '';
                this.newTagName = '';
                this.showExtractedModal = true;
                this.loadListasAndTags();
            } finally {
                this.extractingMembers = false;
            }
        },

        closeExtractedModal() {
            this.showExtractedModal = false;
            this.extractedContactIds = [];
        },

        async loadListasAndTags() {
            this.listasTagsLoading = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' };
            try {
                const [listasRes, tagsRes] = await Promise.all([
                    fetch('/whatsapp/api/listas', { headers }),
                    fetch('/whatsapp/api/tags', { headers }),
                ]);
                const listasData = await listasRes.json().catch(() => ({}));
                const tagsData = await tagsRes.json().catch(() => ({}));
                this.listas = listasData.listas || [];
                this.tags = tagsData.tags || [];
            } finally {
                this.listasTagsLoading = false;
            }
        },

        async applyExtracted() {
            if (this.applyingExtracted || this.extractedContactIds.length === 0) return;
            const listId = this.addToList && this.selectedListId && this.selectedListId !== 'new' ? parseInt(this.selectedListId, 10) : null;
            const listName = this.addToList && this.selectedListId === 'new' && this.newListName.trim() ? this.newListName.trim() : null;
            const tagId = this.addToTag && this.selectedTagId && this.selectedTagId !== 'new' ? parseInt(this.selectedTagId, 10) : null;
            const tagName = this.addToTag && this.selectedTagId === 'new' && this.newTagName.trim() ? this.newTagName.trim() : null;
            if (!listId && !listName && !tagId && !tagName) {
                this.closeExtractedModal();
                return;
            }
            this.applyingExtracted = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            try {
                const body = {
                    contact_ids: this.extractedContactIds,
                    list_id: listId || undefined,
                    list_name: listName || undefined,
                    tag_id: tagId || undefined,
                    tag_name: tagName || undefined,
                };
                const resp = await fetch('/whatsapp/api/apply-extracted', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                    },
                    body: JSON.stringify(body),
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) {
                    alert(data.message || data.error || 'Não foi possível aplicar.');
                    return;
                }
                const parts = [];
                if (data.added_to_list) parts.push('adicionados à lista');
                if (data.added_to_tag) parts.push('tag aplicada');
                if (parts.length) alert(data.contacts_count + ' contato(s): ' + parts.join(' e ') + '.');
                this.closeExtractedModal();
            } finally {
                this.applyingExtracted = false;
            }
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
                if (value && this.activeConversation) {
                    this.fetchAppContact();
                    this.fetchContactDetails();
                }
                if (!value) {
                    this.appContact = null;
                    this.appContactMessage = null;
                    this.detailsData = null;
                }
            });
            this.$watch('activeConversation', (value) => {
                if (!value) {
                    this.loadingMessages = false;
                    this.messages = [];
                    this.imageLoadFail = {};
                    this.imageLoading = {};
                    this.appContact = null;
                    this.appContactMessage = null;
                    this.detailsData = null;
                    return;
                }
                if (this.showInfo) {
                    this.fetchAppContact();
                    this.fetchContactDetails();
                }
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

        async fetchContactDetails() {
            const c = this.activeConversation;
            if (!c || !c.id) { this.detailsData = null; return; }
            this.detailsLoading = true;
            this.detailsData = null;
            this._detailsAutoResult = null;
            this._detailsListSelectedId = '';
            this._detailsListMode = 'select';
            this._detailsListInput = '';
            this._detailsTagSelectedId = '';
            this._detailsTagMode = 'select';
            this._detailsTagInput = '';
            this._detailsAutoSelectedId = '';
            try {
                const resp = await fetch(`/whatsapp/api/conversations/${c.id}/contact-details`, { headers: { Accept: 'application/json' } });
                const data = await resp.json().catch(() => ({}));
                if (data.success) this.detailsData = data;
            } finally {
                this.detailsLoading = false;
            }
        },

        async detailsAddToList() {
            const c = this.activeConversation;
            if (!c) return;
            const listId = this._detailsListSelectedId;
            const listName = this._detailsListInput.trim();
            if (!listId && !listName) return;
            const body = listId === 'new' || listId === ''
                ? { list_name: listName }
                : { list_id: parseInt(listId) };
            this._detailsListCreating = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const resp = await fetch(`/whatsapp/api/conversations/${c.id}/add-to-list`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await resp.json().catch(() => ({}));
                if (data.success && data.list) {
                    if (!this.detailsData) this.detailsData = { found: true, contact_lists: [], contact_tags: [], automations: [] };
                    const already = (this.detailsData.contact_lists || []).find((l) => l.id === data.list.id);
                    if (!already) this.detailsData.contact_lists = [...(this.detailsData.contact_lists || []), data.list];
                    this._detailsListSelectedId = '';
                    this._detailsListInput = '';
                    this._detailsListMode = 'select';
                }
            } finally {
                this._detailsListCreating = false;
            }
        },

        async detailsRemoveFromList(listId) {
            const c = this.activeConversation;
            if (!c) return;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const resp = await fetch(`/whatsapp/api/conversations/${c.id}/remove-from-list`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: JSON.stringify({ list_id: listId }),
            });
            const data = await resp.json().catch(() => ({}));
            if (data.success && this.detailsData) {
                this.detailsData.contact_lists = (this.detailsData.contact_lists || []).filter((l) => l.id !== listId);
            }
        },

        async detailsAddTag() {
            const c = this.activeConversation;
            if (!c) return;
            const tagId = this._detailsTagSelectedId;
            const tagName = this._detailsTagInput.trim();
            if (!tagId && !tagName) return;
            const body = tagId === 'new' || tagId === ''
                ? { tag_name: tagName }
                : { tag_id: parseInt(tagId) };
            this._detailsTagCreating = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const resp = await fetch(`/whatsapp/api/conversations/${c.id}/add-tag`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await resp.json().catch(() => ({}));
                if (data.success && data.tag) {
                    if (!this.detailsData) this.detailsData = { found: true, contact_lists: [], contact_tags: [], automations: [] };
                    const already = (this.detailsData.contact_tags || []).find((t) => t.id === data.tag.id);
                    if (!already) this.detailsData.contact_tags = [...(this.detailsData.contact_tags || []), data.tag];
                    this._detailsTagSelectedId = '';
                    this._detailsTagInput = '';
                    this._detailsTagMode = 'select';
                }
            } finally {
                this._detailsTagCreating = false;
            }
        },

        async detailsRemoveTag(tagId) {
            const c = this.activeConversation;
            if (!c) return;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const resp = await fetch(`/whatsapp/api/conversations/${c.id}/remove-tag`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: JSON.stringify({ tag_id: tagId }),
            });
            const data = await resp.json().catch(() => ({}));
            if (data.success && this.detailsData) {
                this.detailsData.contact_tags = (this.detailsData.contact_tags || []).filter((t) => t.id !== tagId);
            }
        },

        async detailsRunAutomation() {
            const c = this.activeConversation;
            if (!c || !this._detailsAutoSelectedId) return;
            this._detailsAutoRunning = true;
            this._detailsAutoResult = null;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const resp = await fetch(`/whatsapp/api/conversations/${c.id}/run-automation`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                    body: JSON.stringify({ automation_id: parseInt(this._detailsAutoSelectedId) }),
                });
                const data = await resp.json().catch(() => ({}));
                this._detailsAutoResult = data.success ? 'ok' : (data.error || 'Erro');
            } finally {
                this._detailsAutoRunning = false;
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

            es.addEventListener('wa.message.reaction', (evt) => {
                const data = safeJsonParse(evt.data) || {};
                const msgId = String(data.message_id || '');
                const reactions = data.reactions || null;
                if (!msgId) return;
                const m = this.messages.find((x) => String(x.id) === msgId);
                if (m) {
                    m.reactions = reactions && reactions.length > 0 ? reactions : null;
                    this.messages = [...this.messages];
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
            const ctrl = new AbortController();
            const t = setTimeout(() => ctrl.abort(), 8000);
            try {
                const resp = await fetch('/settings/whatsapp/status', {
                    headers: { 'Accept': 'application/json' },
                    signal: ctrl.signal,
                });
                const data = await resp.json().catch(() => ({}));
                const state = data.state || data.status || null;
                const inst = data.instanceName || '';

                this.waConfigured = data.configured !== false;
                this.hasInstance = data.hasInstance === true;
                this.instanceName = inst ? String(inst) : '';
                this.connected = ['open', 'connected', 'online', 'ready'].includes(String(state || '').toLowerCase());

                window.dispatchEvent(new CustomEvent('whatsapp-connection-changed', {
                    detail: { connected: this.connected },
                }));
            } catch (e) {
                this.connected = false;
                // Não alterar hasInstance/waConfigured em timeout para não piscar o banner
            } finally {
                clearTimeout(t);
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
            if (!silent) {
                this.loadingConversations = true;
                this._conversationsOffset = 0;
            }
            const ctrl = new AbortController();
            const t = setTimeout(() => ctrl.abort(), 10000);
            try {
                const resp = await fetch('/whatsapp/api/conversations', {
                    headers: { 'Accept': 'application/json' },
                    signal: ctrl.signal,
                });
                const data = await resp.json().catch(() => ({}));
                const items = Array.isArray(data.items) ? data.items : [];

                if (!silent) {
                    // Full reset: replace list and update pagination state
                    this.conversations = this.deduplicateConversations(items);
                    this._hasMoreConversations = data.has_more || false;
                    this.fetchAvatarsForList();
                } else {
                    // Silent poll: merge incoming items into existing list without resetting
                    let changed = false;
                    const existingMap = new Map(this.conversations.map((c) => [String(c.id), c]));
                    for (const item of items) {
                        if (existingMap.has(String(item.id))) {
                            const ex = existingMap.get(String(item.id));
                            if (ex.last_message_at !== item.last_message_at ||
                                ex.unread_count !== item.unread_count ||
                                ex.last_message_preview !== item.last_message_preview ||
                                ex.last_message_status !== item.last_message_status) {
                                Object.assign(ex, item);
                                changed = true;
                            }
                        } else {
                            // New conversation discovered: prepend it
                            this.conversations.unshift(item);
                            existingMap.set(String(item.id), item);
                            changed = true;
                        }
                    }
                    if (changed) {
                        // Re-sort by recency so newly updated conversations rise to top
                        this.conversations.sort((a, b) => {
                            const ta = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
                            const tb = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
                            return tb - ta;
                        });
                    }
                }
                this.syncConversationLists();
            } catch (e) {
                // timeout ou erro de rede: não travar o spinner
            } finally {
                clearTimeout(t);
                if (!silent) this.loadingConversations = false;
            }
        },

        async loadMoreConversations() {
            if (this._loadingMoreConversations || !this._hasMoreConversations) return;
            const nextOffset = this._conversationsOffset + 50;
            this._loadingMoreConversations = true;
            const ctrl = new AbortController();
            const t = setTimeout(() => ctrl.abort(), 10000);
            try {
                const resp = await fetch(`/whatsapp/api/conversations?offset=${nextOffset}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: ctrl.signal,
                });
                const data = await resp.json().catch(() => ({}));
                const items = Array.isArray(data.items) ? data.items : [];
                const existingIds = new Set(this.conversations.map((c) => String(c.id)));
                const newOnes = items.filter((c) => !existingIds.has(String(c.id)));
                if (newOnes.length > 0) {
                    this.conversations = this.deduplicateConversations([...this.conversations, ...newOnes]);
                    this.syncConversationLists();
                }
                this._conversationsOffset = nextOffset;
                this._hasMoreConversations = data.has_more || false;
            } catch (e) {}
            finally {
                clearTimeout(t);
                this._loadingMoreConversations = false;
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
            const ctrl = new AbortController();
            const t = setTimeout(() => ctrl.abort(), 8000);
            try {
                const resp = await fetch(`/whatsapp/api/conversations/${c.id}/messages`, {
                    headers: { 'Accept': 'application/json' },
                    signal: ctrl.signal,
                });
                const data = await resp.json().catch(() => ({}));
                this.messages = Array.isArray(data.items) ? data.items : [];
                this._lastMessageId = this.messages.length ? (this.messages[this.messages.length - 1].id || 0) : 0;
                this.$nextTick(() => this.scrollToBottom(true));
            } catch (e) {
                this.messages = [];
            } finally {
                clearTimeout(t);
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
            const mediaType = file.type.startsWith('image/') ? 'image'
                : file.type.startsWith('video/') ? 'video'
                : file.type.startsWith('audio/') ? 'audio'
                : 'document';
            const optimistic = {
                id: tmpId,
                direction: 'out',
                message_type: mediaType,
                body: caption || '',
                status: 'sending',
                sent_at: nowIso(),
                created_at: nowIso(),
                attachment: { type: mediaType, url: null, caption_preview: caption || file.name, mime: file.type, size: file.size },
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

        async startRecording() {
            if (this.isRecording || !this.activeConversation) return;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this._audioChunks = [];
                // Prefer mp4/aac (unambiguously audio on all platforms), fallback to ogg/webm
                const mimeType = MediaRecorder.isTypeSupported('audio/mp4')
                    ? 'audio/mp4'
                    : MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')
                        ? 'audio/ogg;codecs=opus'
                        : MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                            ? 'audio/webm;codecs=opus'
                            : 'audio/webm';
                this._mediaRecorder = new MediaRecorder(stream, { mimeType });
                this._mediaRecorder.ondataavailable = (e) => {
                    if (e.data && e.data.size > 0) this._audioChunks.push(e.data);
                };
                this._mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    const blob = new Blob(this._audioChunks, { type: mimeType });
                    const ext = mimeType.includes('mp4') ? 'm4a'
                              : mimeType.includes('ogg') ? 'ogg' : 'webm';
                    const file = new File([blob], `audio_${Date.now()}.${ext}`, { type: mimeType });
                    this.sendMediaFile(file);
                };
                this._mediaRecorder.start(250);
                this.isRecording = true;
                this.recordingSeconds = 0;
                this._recordingTimer = setInterval(() => { this.recordingSeconds++; }, 1000);
            } catch (err) {
                alert('Não foi possível acessar o microfone: ' + (err.message || err));
            }
        },

        stopRecording() {
            if (!this.isRecording || !this._mediaRecorder) return;
            this._mediaRecorder.stop();
            clearInterval(this._recordingTimer);
            this._recordingTimer = null;
            this.isRecording = false;
        },

        cancelRecording() {
            if (!this._mediaRecorder) return;
            this._mediaRecorder.onstop = null;  // prevent sending
            this._mediaRecorder.stream?.getTracks().forEach(t => t.stop());
            try { this._mediaRecorder.stop(); } catch(_) {}
            clearInterval(this._recordingTimer);
            this._recordingTimer = null;
            this._audioChunks = [];
            this._mediaRecorder = null;
            this.isRecording = false;
            this.recordingSeconds = 0;
        },

        formatRecordingTime(secs) {
            const m = Math.floor(secs / 60).toString().padStart(2, '0');
            const s = (secs % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
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

        groupReactions(reactions) {
            const groups = {};
            for (const r of (reactions || [])) {
                const e = r.emoji || '';
                if (!e) continue;
                if (!groups[e]) groups[e] = { emoji: e, count: 0, fromMe: false };
                groups[e].count++;
                if (r.from_me) groups[e].fromMe = true;
            }
            return Object.values(groups);
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

        /**
         * Return { iconClass, colorClass } for conversation list checkmarks (last sent message).
         */
        tickForStatus(status) {
            const s = String(status || '').toLowerCase();
            if (!s) return null;
            const hasRead = ['read', 'seen', 'read_ack', '3'].includes(s) || s.includes('read') || s.includes('seen');
            const hasDelivered = ['delivered', 'delivery_ack', 'delivered_ack', 'received', '2'].includes(s) || s.includes('deliver');
            if (hasRead) return { iconClass: 'fas fa-check-double', colorClass: 'wa-tick-read' };
            if (hasDelivered) return { iconClass: 'fas fa-check-double', colorClass: 'wa-tick-delivered' };
            if (['sent', 'server_ack', 'ack', '1'].includes(s)) return { iconClass: 'fas fa-check', colorClass: 'wa-tick-sent' };
            return null;
        },
    };
};

