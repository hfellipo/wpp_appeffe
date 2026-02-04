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

            /* Status do envio (checkmarks) – cores fixas para não depender do purge do Tailwind */
            .wa-tick-read { color: #22c55e !important; }
            .wa-tick-delivered { color: #0ea5e9 !important; }
            .wa-tick-sent { color: #9ca3af !important; }
            .wa-tick-sending { color: #9ca3af !important; }
            .wa-tick-failed { color: #ef4444 !important; }
            .message-time .wa-tick i { display: inline-block; min-width: 1em; }

            /* Avatares: fallback com iniciais quando não há imagem */
            .wa-avatar { position: relative; display: flex; align-items: center; justify-content: center; }
            .wa-avatar.wa-avatar--fallback { background-color: #25D366; background-image: none; color: #fff; }
            .wa-avatar-initial { font-weight: 600; user-select: none; }
            .avatar.av-m .wa-avatar-initial { font-size: 1.1rem; }
            .wa-avatar-initial--s { font-size: 0.85rem; }
            .wa-avatar-initial--l { font-size: 2.5rem; }
            /* Online e digitando */
            .wa-online-dot {
                position: absolute;
                right: 6px;
                bottom: 2px;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #22c55e;
                border: 2px solid #fff;
            }
            .wa-typing-label { font-size: 0.75rem; color: #6b7280; font-style: italic; }
            .wa-tab--active { color: var(--primary-color, #25D366) !important; font-weight: 600; border-bottom: 2px solid var(--primary-color, #25D366); margin-bottom: -1px; }

            /* Grupo: nome do remetente acima da bolha */
            .message-card-wrapper { margin-bottom: 2px; }
            .message-card-wrapper.mc-sender { display: flex; flex-direction: column; align-items: flex-end; }
            .wa-message-sender {
                font-size: 12px;
                font-weight: 600;
                margin-bottom: 2px;
                padding-left: 2px;
                padding-right: 2px;
            }
            .wa-message-sender--you { color: var(--primary-color, #25D366); }
            .wa-message-sender--member { color: #0ea5e9; }
            .message-card-wrapper.mc-sender .wa-message-sender { padding-right: 12px; }

            /* Feedback de carregamento ao clicar em outro chat – centralizado na coluna do chat */
            .messages-container { display: flex; flex-direction: column; }
            .messages-container .messages {
                position: relative;
                flex: 1;
                min-height: 100%;
            }
            .wa-chat-loading {
                position: absolute;
                inset: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 1rem;
                background: var(--secondary-bg-color, #f0f2f5);
                z-index: 2;
            }
            .wa-chat-loading__spinner {
                width: 40px;
                height: 40px;
                border: 3px solid var(--border-color, #e5e7eb);
                border-top-color: var(--primary-color, #25D366);
                border-radius: 50%;
                animation: wa-spin 0.8s linear infinite;
            }
            .wa-chat-loading__text { margin: 0; font-size: 14px; color: var(--wa-list-meta-color, #667781); }
            @keyframes wa-spin { to { transform: rotate(360deg); } }
            .messages-list { width: 100%; }

            /* Lista de conversas – layout por item (avatar | nome+hora / preview+badge) */
            .wa-conversation-item {
                display: flex;
                align-items: center;
                gap: 12px;
                width: 100%;
                padding: 10px 12px;
                cursor: pointer;
                transition: background 0.1s;
                border: none;
                border-radius: 0;
                text-align: left;
                background: transparent;
            }
            .wa-conversation-item:hover {
                background: var(--secondary-bg-color, #f0f2f5);
            }
            .wa-conversation-item.m-list-active,
            .wa-conversation-item.m-list-active:hover {
                background: var(--primary-color) !important;
            }
            .wa-conversation-item__avatar {
                flex-shrink: 0;
                width: 49px;
            }
            .wa-conversation-item__avatar .avatar.av-m {
                width: 49px;
                height: 49px;
            }
            .wa-conversation-item__body {
                flex: 1;
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            .wa-conversation-item__top {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
            }
            .wa-conversation-item__name {
                font-weight: 600;
                font-size: 16px;
                color: var(--wa-list-name-color, #111b21);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .wa-conversation-item__time {
                flex-shrink: 0;
                font-size: 12px;
                color: var(--wa-list-meta-color, #667781);
                font-weight: 400;
            }
            .wa-conversation-item__bottom {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
            }
            .wa-conversation-item__preview {
                flex: 1;
                min-width: 0;
                font-size: 14px;
                color: var(--wa-list-meta-color, #667781);
                font-weight: 400;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .wa-conversation-item__right {
                flex-shrink: 0;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .wa-conversation-item__unread {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 20px;
                height: 20px;
                padding: 0 6px;
                font-size: 11px;
                font-weight: 600;
                color: #fff;
                background: #25D366;
                border-radius: 50%;
            }
            .wa-conversation-item.m-list-active .wa-conversation-item__name,
            .wa-conversation-item.m-list-active .wa-conversation-item__time,
            .wa-conversation-item.m-list-active .wa-conversation-item__preview {
                color: #fff !important;
            }
            .wa-conversation-item.m-list-active .wa-conversation-item__unread {
                background: #fff;
                color: var(--primary-color);
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
                                <a href="#" title="Novo chat" @click.prevent="openContactPicker()">
                                    <i class="fas fa-plus"></i>
                                </a>
                            <a href="{{ route('whatsapp.index') }}" title="Configurar conexão">
                                <i class="fas fa-cog"></i>
                            </a>
                        </nav>
                    </nav>
                    <input type="text" class="messenger-search" placeholder="Search" x-model="search" />
                </div>

                <div class="m-body contacts-container">
                    <div class="show messenger-tab users-tab app-scroll" data-view="users">
                        <div class="wa-list-tabs" style="display: flex; border-bottom: 1px solid #e5e7eb; margin-bottom: 8px;">
                            <button
                                type="button"
                                class="wa-tab"
                                :class="{ 'wa-tab--active': conversationTab === 'direct' }"
                                @click="setConversationTab('direct')"
                                style="flex: 1; padding: 10px 12px; border: none; background: none; cursor: pointer; font-size: 0.9rem; color: #6b7280;"
                            >
                                <i class="fas fa-user"></i> Conversas
                            </button>
                            <button
                                type="button"
                                class="wa-tab"
                                :class="{ 'wa-tab--active': conversationTab === 'group' }"
                                @click="setConversationTab('group')"
                                style="flex: 1; padding: 10px 12px; border: none; background: none; cursor: pointer; font-size: 0.9rem; color: #6b7280;"
                            >
                                <i class="fas fa-users"></i> Grupos
                            </button>
                        </div>
                        <p class="messenger-title" style="margin-top: 0;"><span x-text="conversationTab === 'direct' ? 'Conversas' : 'Grupos'"></span></p>

                        <div style="width: 100%; height: calc(100% - 160px); position: relative;">
                            <template x-if="loadingConversations">
                                <p class="message-hint center-el"><span>Loading...</span></p>
                            </template>

                            <template x-if="!loadingConversations && filteredConversations.length === 0">
                                <p class="message-hint center-el"><span x-text="conversationTab === 'group' ? 'Nenhum grupo' : 'Nenhuma conversa'"></span></p>
                            </template>

                            <template x-for="c in filteredConversations" :key="c.id">
                                <div
                                    class="wa-conversation-item messenger-list-item"
                                    :class="activeConversation && activeConversation.id === c.id ? 'm-list-active' : ''"
                                    :data-contact="c.id"
                                    data-action="0"
                                    @click="openConversation(c)"
                                >
                                    <div class="wa-conversation-item__avatar">
                                        <div
                                            class="avatar av-m wa-avatar"
                                            :class="{ 'wa-avatar--fallback': !c.avatar_url }"
                                            :style="c.avatar_url ? ('background-image: url(' + c.avatar_url + ')') : ''"
                                        >
                                            <template x-if="!c.avatar_url">
                                                <span class="wa-avatar-initial" x-text="(c.contact_name || c.contact_number || '?').charAt(0).toUpperCase()"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="wa-conversation-item__body">
                                        <div class="wa-conversation-item__top">
                                            <span class="wa-conversation-item__name" x-text="(c.contact_name && String(c.contact_name).trim()) ? c.contact_name : formatNumber(c.contact_number)"></span>
                                            <span class="wa-conversation-item__time" x-text="formatTimeAgo(c.last_message_at)"></span>
                                        </div>
                                        <div class="wa-conversation-item__bottom">
                                            <span class="wa-conversation-item__preview" x-text="c.last_message_preview || ' '"></span>
                                            <div class="wa-conversation-item__right">
                                                <template x-if="(c.unread_count || 0) > 0">
                                                    <span class="wa-conversation-item__unread" x-text="c.unread_count"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                            <div class="chatify-d-flex chatify-align-items-center" style="position: relative;">
                                <div
                                    class="avatar av-s header-avatar wa-avatar"
                                    :class="{ 'wa-avatar--fallback': activeConversation && !activeConversation.avatar_url }"
                                    :style="activeConversation && activeConversation.avatar_url ? ('background-image: url(' + activeConversation.avatar_url + ')') : ''"
                                    style="margin: 0px 10px; margin-top: -5px; margin-bottom: -5px;"
                                >
                                    <template x-if="activeConversation && !activeConversation.avatar_url">
                                        <span class="wa-avatar-initial wa-avatar-initial--s" x-text="(activeConversation.contact_name || activeConversation.contact_number || '?').charAt(0).toUpperCase()"></span>
                                    </template>
                                </div>
                                <span
                                    x-show="activeConversation && isConversationOnline(activeConversation.id)"
                                    class="wa-online-dot"
                                    title="Online"
                                ></span>
                            </div>
                            <div class="chatify-d-flex chatify-align-items-center" style="flex: 1; min-width: 0; flex-direction: column; align-items: flex-start;">
                                <a href="#" class="user-name" x-text="activeConversation ? ((activeConversation.contact_name && String(activeConversation.contact_name).trim()) ? activeConversation.contact_name : formatNumber(activeConversation.contact_number)) : 'WhatsApp'"></a>
                                <span
                                    x-show="activeConversation && isConversationTyping(activeConversation.id)"
                                    class="wa-typing-label"
                                    x-text="'digitando...'"
                                ></span>
                            </div>
                        </div>
                        <nav class="m-header-right">
                            <a href="#" class="show-infoSide" @click.prevent="showInfo = !showInfo"><i class="fas fa-info-circle"></i></a>
                        </nav>
                    </nav>
                </div>

                <div class="m-body messages-container app-scroll" x-ref="messagesPane" @scroll="onScrollMessages()">
                    <div class="messages">
                        <template x-if="!activeConversation">
                            <p class="message-hint center-el"><span>Please select a chat to start messaging</span></p>
                        </template>

                        <template x-if="activeConversation && loadingMessages">
                            <div class="wa-chat-loading">
                                <div class="wa-chat-loading__spinner"></div>
                                <p class="wa-chat-loading__text">Carregando conversa...</p>
                            </div>
                        </template>

                        <template x-if="activeConversation && !loadingMessages">
                            <div class="messages-list">
                                <template x-for="m in messages" :key="m.id">
                                    <div class="message-card-wrapper" :class="m.direction === 'out' ? 'mc-sender' : ''">
                                        <template x-if="activeConversation && (activeConversation.kind || '') === 'group'">
                                            <div class="wa-message-sender" :class="'wa-message-sender--' + (m.direction === 'out' ? 'you' : 'member')">
                                                <span x-text="m.direction === 'out' ? 'Você' : (m.sender_name || 'Desconhecido')"></span>
                                            </div>
                                        </template>
                                        <div class="message-card" :class="m.direction === 'out' ? 'mc-sender' : ''">
                                            <div class="message-card-content">
                                                <div class="message">
                                                    <span x-text="m.body || (m.message_type ? '['+m.message_type+']' : '')"></span>
                                                    <span class="message-time">
                                                        <span class="time" x-text="formatTimeShort(m.created_at)"></span>
                                                        <template x-if="m.direction === 'out'">
                                                            <span class="wa-tick ml-1 inline-flex items-center" :class="(tickForMessage(m) || {}).colorClass || ''">
                                                                <i :class="(tickForMessage(m) || {}).iconClass || ''"></i>
                                                            </span>
                                                        </template>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="activeConversation && !loadingMessages && isConversationTyping(activeConversation.id)">
                            <div class="typing-indicator">
                                <div class="message-card typing">
                                    <div class="message">
                                        <span class="typing-dots">
                                            <span class="dot dot-1"></span>
                                            <span class="dot dot-2"></span>
                                            <span class="dot dot-3"></span>
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
            <div class="messenger-infoView app-scroll" x-show="showInfo && !isCompact" x-ref="infoView">
                <nav>
                    <p>Detalhes</p>
                    <a href="#" @click.prevent="showInfo = false"><i class="fas fa-times"></i></a>
                </nav>
                <div style="text-align:center; padding: 1rem;">
                    <div
                        class="avatar av-l wa-avatar"
                        :class="{ 'wa-avatar--fallback': activeConversation && !activeConversation.avatar_url }"
                        :style="activeConversation && activeConversation.avatar_url ? ('background-image: url(' + activeConversation.avatar_url + ')') : ''"
                        style="margin: 0 auto;"
                    >
                        <template x-if="activeConversation && !activeConversation.avatar_url">
                            <span class="wa-avatar-initial wa-avatar-initial--l" x-text="(activeConversation.contact_name || activeConversation.contact_number || '?').charAt(0).toUpperCase()"></span>
                        </template>
                    </div>
                    <p class="info-name" style="margin-top: 1rem;" x-text="activeConversation ? ((activeConversation.contact_name && String(activeConversation.contact_name).trim()) ? activeConversation.contact_name : formatNumber(activeConversation.contact_number)) : '-'"></p>
                </div>
                {{-- Dados da tabela contacts --}}
                <div class="wa-info-contact-section" style="border-top: 1px solid var(--border-color, #e9edef); padding: 1rem; text-align: left;">
                    <p class="wa-info-section-title" style="font-weight: 600; margin-bottom: 0.75rem; font-size: 0.9rem;">Tabela Contacts</p>
                    <template x-if="!activeConversation">
                        <p class="wa-info-muted" style="color: var(--wa-list-meta-color, #667781); font-size: 0.85rem;">Selecione uma conversa.</p>
                    </template>
                    <template x-if="activeConversation && appContactLoading">
                        <p class="wa-info-muted" style="color: var(--wa-list-meta-color, #667781); font-size: 0.85rem;">Carregando...</p>
                    </template>
                    <template x-if="activeConversation && !appContactLoading && appContactMessage">
                        <p class="wa-info-muted" style="color: var(--wa-list-meta-color, #667781); font-size: 0.85rem;" x-text="appContactMessage"></p>
                    </template>
                    <template x-if="activeConversation && !appContactLoading && appContact && appContact.found">
                        <div class="wa-info-contact-fields">
                            <dl style="margin: 0; font-size: 0.85rem;">
                                <template x-if="appContact.contact.name">
                                    <div style="margin-bottom: 0.5rem;">
                                        <dt style="font-weight: 600; color: var(--wa-list-meta-color, #667781); margin-bottom: 0.15rem;">Nome</dt>
                                        <dd style="margin: 0;" x-text="appContact.contact.name"></dd>
                                    </div>
                                </template>
                                <template x-if="appContact.contact.phone">
                                    <div style="margin-bottom: 0.5rem;">
                                        <dt style="font-weight: 600; color: var(--wa-list-meta-color, #667781); margin-bottom: 0.15rem;">Telefone</dt>
                                        <dd style="margin: 0;" x-text="appContact.contact.phone"></dd>
                                    </div>
                                </template>
                                <template x-if="appContact.contact.email">
                                    <div style="margin-bottom: 0.5rem;">
                                        <dt style="font-weight: 600; color: var(--wa-list-meta-color, #667781); margin-bottom: 0.15rem;">E-mail</dt>
                                        <dd style="margin: 0;" x-text="appContact.contact.email"></dd>
                                    </div>
                                </template>
                                <template x-if="appContact.contact.notes">
                                    <div style="margin-bottom: 0.5rem;">
                                        <dt style="font-weight: 600; color: var(--wa-list-meta-color, #667781); margin-bottom: 0.15rem;">Notas</dt>
                                        <dd style="margin: 0; white-space: pre-wrap;" x-text="appContact.contact.notes"></dd>
                                    </div>
                                </template>
                                <template x-if="appContact.contact.custom_fields && appContact.contact.custom_fields.length">
                                    <template x-for="f in appContact.contact.custom_fields" :key="f.field_name + (f.value || '')">
                                        <div style="margin-bottom: 0.5rem;">
                                            <dt style="font-weight: 600; color: var(--wa-list-meta-color, #667781); margin-bottom: 0.15rem;" x-text="f.field_name"></dt>
                                            <dd style="margin: 0;" x-text="f.formatted_value || f.value || '-'"></dd>
                                        </div>
                                    </template>
                                </template>
                            </dl>
                            <template x-if="appContact.contact.name === null && appContact.contact.phone === null && appContact.contact.email === null && appContact.contact.notes === null && (!appContact.contact.custom_fields || !appContact.contact.custom_fields.length)">
                                <p class="wa-info-muted" style="color: var(--wa-list-meta-color, #667781); font-size: 0.85rem;">Registro encontrado, mas sem dados preenchidos.</p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        <!-- Contact picker modal (must be inside x-data scope) -->
        <div
            x-show="showContactPicker"
            style="display:none"
            class="fixed inset-0 z-[9999]"
            @keydown.escape.window="closeContactPicker()"
        >
            <div class="absolute inset-0 bg-black/40" @click="closeContactPicker()"></div>
            <div class="absolute inset-x-0 top-16 mx-auto w-full max-w-lg px-4">
                <div class="bg-white rounded-xl shadow-xl overflow-hidden border border-gray-200">
                    <div class="px-4 py-3 flex items-center justify-between border-b border-gray-100">
                        <div class="font-semibold text-gray-900">Selecionar contato</div>
                        <button type="button" class="text-gray-500 hover:text-gray-900" @click="closeContactPicker()">✕</button>
                    </div>
                    <div class="p-4 space-y-3">
                        <input
                            type="text"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                            placeholder="Buscar por nome, telefone..."
                            x-model="contactSearch"
                            @input="onContactSearchInput()"
                        />

                        <template x-if="contactsLoading">
                            <div class="text-sm text-gray-600">Carregando...</div>
                        </template>

                        <template x-if="!contactsLoading && contacts.length === 0">
                            <div class="text-sm text-gray-600">Nenhum contato encontrado.</div>
                        </template>

                        <div class="max-h-96 overflow-auto divide-y divide-gray-100">
                            <template x-for="ct in contacts" :key="ct.id">
                                <button
                                    type="button"
                                    class="w-full text-left px-2 py-3 hover:bg-gray-50"
                                    @click="startConversationFromContact(ct)"
                                >
                                    <div class="font-medium text-gray-900" x-text="ct.name || '-'"></div>
                                    <div class="text-sm text-gray-600" x-text="ct.phone || ct.raw_phone || ''"></div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/chatify/font.awesome.min.js') }}"></script>
    @endpush
</x-app-layout>

