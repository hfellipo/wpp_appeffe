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

            /* Message media (image / video / document) */
            .wa-message-media { margin-bottom: 4px; }
            .wa-message-media__link { display: block; border-radius: 8px; overflow: hidden; max-width: 280px; }
            .wa-message-media__img-wrap { position: relative; min-height: 120px; display: block; }
            /* Overlay "Carregando imagem..." sobre o preview até a imagem carregar */
            .wa-message-media__loading {
                position: absolute;
                inset: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 8px;
                background: rgba(0,0,0,0.5);
                border-radius: 8px;
                z-index: 1;
            }
            .wa-message-media__loading-spinner {
                width: 32px;
                height: 32px;
                border: 3px solid rgba(255,255,255,0.3);
                border-top-color: #fff;
                border-radius: 50%;
                animation: wa-spin 0.8s linear infinite;
            }
            .wa-message-media__loading-text { font-size: 13px; color: #fff; margin: 0; }
            .message-card.mc-sender .wa-message-media__loading { background: rgba(0,0,0,0.4); }
            .message-card.mc-sender .wa-message-media__loading-text { color: #fff; }
            /* Preview simples: sem processamento no servidor; lazy load + tamanho limitado */
            .wa-message-media__img { max-width: 100%; max-height: 320px; width: auto; height: auto; object-fit: contain; display: block; vertical-align: top; }
            .wa-message-media__video-wrap { max-width: 280px; }
            .wa-message-media__video { max-width: 100%; height: auto; display: block; border-radius: 8px; }
            .wa-message-media__caption, .wa-message-doc__caption { font-size: 0.95em; margin-top: 4px; margin-bottom: 0; color: inherit; }
            .wa-message-doc { margin-bottom: 4px; }
            .wa-message-doc__link { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; background: rgba(0,0,0,0.06); border-radius: 8px; color: inherit; text-decoration: none; max-width: 100%; }
            .wa-message-doc__link:hover { background: rgba(0,0,0,0.1); }
            .wa-message-doc__icon { color: #667781; font-size: 1.2rem; }
            .wa-message-doc__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            /* Card de documento (estilo desejado: ícone + nome + tipo • tamanho + seta download) */
            .wa-message-doc-card { margin-bottom: 4px; }
            .wa-message-doc-card__link {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 12px;
                background: rgba(0,0,0,0.06);
                border-radius: 8px;
                color: inherit;
                text-decoration: none;
                max-width: 280px;
            }
            .wa-message-doc-card__link:hover { background: rgba(0,0,0,0.1); }
            .wa-message-doc-card__icon {
                flex-shrink: 0;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(0,0,0,0.08);
                border-radius: 8px;
                font-size: 1.1rem;
                color: #667781;
            }
            .wa-message-doc-card__info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
            .wa-message-doc-card__name { font-size: 0.9rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .wa-message-doc-card__meta { font-size: 0.75rem; color: #667781; }
            .wa-message-doc-card__download { flex-shrink: 0; color: #667781; font-size: 1rem; }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-doc-card__link { background: rgba(255,255,255,0.2); }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-doc-card__link:hover { background: rgba(255,255,255,0.3); }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-doc-card__icon { background: rgba(255,255,255,0.2); color: #fff; }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-doc-card__meta { color: rgba(255,255,255,0.85); }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-doc-card__download { color: #fff; }
            .wa-message-doc-card--placeholder .wa-message-doc-card__link { cursor: default; }
            .wa-message--has-media .message-time { margin-top: 4px; }
            /* Placeholder quando mídia recebida sem URL (ou enviando) */
            .wa-message-media-placeholder {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 16px;
                background: rgba(0,0,0,0.06);
                border-radius: 8px;
                margin-bottom: 4px;
            }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-media-placeholder {
                background: rgba(255,255,255,0.2);
            }
            .wa-message-media-placeholder__icon { font-size: 1.25rem; color: #667781; }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-media-placeholder__icon { color: rgba(255,255,255,0.9); }
            .wa-message-media-placeholder__label { font-size: 0.9rem; color: #374151; }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-media-placeholder__label { color: #fff; }
            /* Mensagem enviada com mídia: balão verde envolve imagem/vídeo/documento + hora */
            .message-card.mc-sender .message-card-content.wa-sender-media {
                background: var(--primary-color, #25D366);
                color: #fff;
                border-radius: 12px;
                padding: 6px 10px 4px;
                max-width: 85%;
            }
            .message-card.mc-sender .message-card-content.wa-sender-media .message {
                background: transparent !important;
                padding: 0 0 2px 0;
            }
            .message-card.mc-sender .message-card-content.wa-sender-media .message .message-time {
                color: rgba(255, 255, 255, 0.85);
            }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-media__caption,
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-doc__caption {
                color: rgba(255, 255, 255, 0.95);
            }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-doc__link {
                background: rgba(255, 255, 255, 0.2);
                color: #fff;
            }
            .message-card.mc-sender .message-card-content.wa-sender-media .wa-message-doc__link:hover {
                background: rgba(255, 255, 255, 0.3);
            }
            /* Mensagem recebida com mídia: imagem/vídeo/documento dentro do balão (como no envio) */
            .message-card:not(.mc-sender) .message-card-content.wa-received-media {
                background: var(--message-card-color, #fff);
                border-radius: 12px;
                padding: 6px 10px 4px;
                max-width: 85%;
            }
            .message-card:not(.mc-sender) .message-card-content.wa-received-media .message {
                background: transparent !important;
                padding: 0 0 2px 0;
            }
            .wa-emoji-trigger.wa-attach-trigger { margin-left: 0; }

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

            /* Emoji picker – enviar e receber emojis */
            .wa-send-card { position: relative; }
            .wa-emoji-picker {
                position: absolute;
                bottom: 100%;
                left: 0;
                right: 0;
                max-height: 220px;
                overflow-y: auto;
                background: var(--secondary-bg-color, #fff);
                border: 1px solid var(--border-color, #e5e7eb);
                border-radius: 12px 12px 0 0;
                padding: 10px;
                margin-bottom: 4px;
                box-shadow: 0 -4px 12px rgba(0,0,0,0.08);
                z-index: 10;
            }
            .wa-emoji-picker__grid {
                display: grid;
                grid-template-columns: repeat(8, 1fr);
                gap: 4px;
            }
            .wa-emoji-picker__btn {
                font-size: 24px;
                line-height: 1.2;
                padding: 6px;
                border: none;
                border-radius: 8px;
                background: transparent;
                cursor: pointer;
                transition: background 0.15s;
            }
            .wa-emoji-picker__btn:hover {
                background: var(--secondary-bg-color, #f0f2f5);
            }
            .wa-emoji-trigger {
                color: var(--wa-list-meta-color, #667781);
            }
            .wa-emoji-trigger--open {
                color: var(--primary-color, #25D366);
            }
            [x-cloak] { display: none !important; }

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
            .wa-groups-sections { padding-bottom: 12px; }
            .wa-groups-section { margin-bottom: 16px; }
            .wa-groups-section-title {
                font-size: 0.75rem;
                font-weight: 600;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.02em;
                margin: 0 12px 8px;
                padding-bottom: 4px;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .wa-groups-section-title i { opacity: 0.9; }
            .wa-conversation-item__top { position: relative; }
            .wa-conversation-item__meta {
                display: inline-flex;
                align-items: center;
                gap: 2px;
            }
            .wa-group-menu-trigger {
                padding: 2px 4px;
                border: none;
                background: none;
                color: inherit;
                opacity: 0.6;
                cursor: pointer;
                border-radius: 3px;
                font-size: 0.7rem;
            }
            .wa-group-menu-trigger:hover,
            .wa-group-menu-trigger.wa-group-menu-trigger--open { opacity: 1; }
            .wa-conversation-item.m-list-active .wa-group-menu-trigger { color: #fff; }
            .wa-group-dropdown {
                position: absolute;
                top: 100%;
                right: 0;
                margin-top: 2px;
                min-width: 200px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.12);
                z-index: 50;
                padding: 4px 0;
            }
            .wa-group-dropdown button {
                display: flex;
                align-items: center;
                gap: 8px;
                width: 100%;
                padding: 8px 12px;
                border: none;
                background: none;
                text-align: left;
                font-size: 0.85rem;
                color: #374151;
                cursor: pointer;
            }
            .wa-group-dropdown button:hover { background: #f3f4f6; }
            .wa-group-dropdown button:disabled { opacity: 0.6; cursor: not-allowed; }
            .wa-group-dropdown button i { width: 16px; opacity: 0.8; }
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

                        <div style="width: 100%; height: calc(100% - 160px); position: relative; overflow-y: auto;">
                            <template x-if="loadingConversations">
                                <p class="message-hint center-el"><span>Loading...</span></p>
                            </template>

                            <template x-if="!loadingConversations && conversationTab === 'direct' && filteredConversations.length === 0">
                                <p class="message-hint center-el"><span>Nenhuma conversa</span></p>
                            </template>

                            <template x-if="!loadingConversations && conversationTab === 'group' && filteredGroupConversationsOwned.length === 0 && filteredGroupConversationsMember.length === 0">
                                <p class="message-hint center-el"><span>Nenhum grupo</span></p>
                            </template>

                            <template x-if="!loadingConversations && conversationTab === 'direct'">
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
                            </template>

                            <template x-if="!loadingConversations && conversationTab === 'group'">
                                <div class="wa-groups-sections">
                                    <template x-if="filteredGroupConversationsOwned.length > 0">
                                        <div class="wa-groups-section">
                                            <p class="wa-groups-section-title"><i class="fas fa-crown"></i> Grupos que criei</p>
                                            <template x-for="c in filteredGroupConversationsOwned" :key="c.id">
                                                <div
                                                    class="wa-conversation-item messenger-list-item wa-conversation-item--group"
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
                                                            <span class="wa-conversation-item__meta" @click.outside="closeGroupMenu()">
                                                                <span class="wa-conversation-item__time" x-text="formatTimeAgo(c.last_message_at)"></span>
                                                                <button type="button" class="wa-group-menu-trigger" @click.stop="toggleGroupMenu(c)" title="Opções do grupo" :class="{ 'wa-group-menu-trigger--open': isGroupMenuOpen(c) }">
                                                                    <i class="fas fa-chevron-down"></i>
                                                                </button>
                                                                <div class="wa-group-dropdown" x-show="isGroupMenuOpen(c)" @click.stop x-transition>
                                                                    <button type="button" @click="updateGroupName(c)"><i class="fas fa-pen"></i> Alterar nome do grupo</button>
                                                                    <button type="button" @click="extractGroupMembers(c)" :disabled="extractingMembers"><i class="fas fa-users-cog"></i> Extrair membros</button>
                                                                    <button type="button" @click="toggleGroupOwner(c)"><i class="fas fa-user-minus"></i> Desmarcar como criado por mim</button>
                                                                </div>
                                                            </span>
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
                                    </template>
                                    <template x-if="filteredGroupConversationsMember.length > 0">
                                        <div class="wa-groups-section">
                                            <p class="wa-groups-section-title"><i class="fas fa-users"></i> Grupos que participo</p>
                                            <template x-for="c in filteredGroupConversationsMember" :key="c.id">
                                                <div
                                                    class="wa-conversation-item messenger-list-item wa-conversation-item--group"
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
                                                            <span class="wa-conversation-item__meta" @click.outside="closeGroupMenu()">
                                                                <span class="wa-conversation-item__time" x-text="formatTimeAgo(c.last_message_at)"></span>
                                                                <button type="button" class="wa-group-menu-trigger" @click.stop="toggleGroupMenu(c)" title="Opções do grupo" :class="{ 'wa-group-menu-trigger--open': isGroupMenuOpen(c) }">
                                                                    <i class="fas fa-chevron-down"></i>
                                                                </button>
                                                                <div class="wa-group-dropdown" x-show="isGroupMenuOpen(c)" @click.stop x-transition>
                                                                    <button type="button" @click="updateGroupName(c)"><i class="fas fa-pen"></i> Alterar nome do grupo</button>
                                                                    <button type="button" @click="extractGroupMembers(c)" :disabled="extractingMembers"><i class="fas fa-users-cog"></i> Extrair membros</button>
                                                                    <button type="button" @click="toggleGroupOwner(c)"><i class="fas fa-crown"></i> Marcar como criado por mim</button>
                                                                </div>
                                                            </span>
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
                                    </template>
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
                                            <div class="message-card-content" :class="{
                                                'wa-sender-media': m.direction === 'out' && (m.attachment && (m.attachment.type === 'image' || m.attachment.type === 'video' || m.attachment.type === 'document') || ['image','video','document'].includes(m.message_type)),
                                                'wa-received-media': m.direction === 'in' && (m.attachment && (m.attachment.type === 'image' || m.attachment.type === 'video' || m.attachment.type === 'document') || ['image','video','document'].includes(m.message_type))
                                            }">
                                                <!-- Imagem: preview + feedback "Carregando..." até abrir; fallback se falhar. -->
                                                <template x-if="m.attachment && m.attachment.type === 'image' && m.attachment.url && !imageLoadFail[m.id]">
                                                    <div class="wa-message-media wa-message-media--img">
                                                        <a :href="m.attachment.url" target="_blank" rel="noopener" class="wa-message-media__link wa-message-media__img-wrap" :title="'Clique para abrir'">
                                                            <div class="wa-message-media__loading" x-show="imageLoading[m.id] !== false">
                                                                <span class="wa-message-media__loading-spinner"></span>
                                                                <span class="wa-message-media__loading-text">Carregando imagem...</span>
                                                            </div>
                                                            <img class="wa-message-media__img" :src="m.attachment.url" :alt="m.attachment.caption_preview || 'Imagem'" loading="lazy"
                                                                @@load="setImageLoaded(m.id)"
                                                                @@error="setImageLoadFail(m.id)"
                                                            />
                                                        </a>
                                                        <p x-show="m.body && !['[Imagem]','[Vídeo]','[Documento]','[image]','[video]','[document]'].includes(m.body)" class="wa-message-media__caption" x-text="m.body"></p>
                                                    </div>
                                                </template>
                                                <!-- Vídeo: preview -->
                                                <template x-if="m.attachment && m.attachment.type === 'video' && m.attachment.url">
                                                    <div class="wa-message-media">
                                                        <a :href="m.attachment.url" target="_blank" rel="noopener" class="wa-message-media__link">
                                                            <div class="wa-message-media__video-wrap">
                                                                <video class="wa-message-media__video" :src="m.attachment.url" controls preload="metadata"></video>
                                                            </div>
                                                        </a>
                                                        <p x-show="m.body && !['[Imagem]','[Vídeo]','[Documento]','[image]','[video]','[document]'].includes(m.body)" class="wa-message-media__caption" x-text="m.body"></p>
                                                    </div>
                                                </template>
                                                <!-- Documento: card com ícone, nome, tipo • tamanho e download (como desejado) -->
                                                <template x-if="m.attachment && m.attachment.type === 'document' && m.attachment.url">
                                                    <div class="wa-message-doc-card">
                                                        <a :href="m.attachment.url" target="_blank" rel="noopener" download class="wa-message-doc-card__link">
                                                            <span class="wa-message-doc-card__icon"><i class="fas fa-file-alt"></i></span>
                                                            <div class="wa-message-doc-card__info">
                                                                <span class="wa-message-doc-card__name" :title="m.attachment.caption_preview || 'Documento'" x-text="m.attachment.caption_preview || 'Documento'"></span>
                                                                <span class="wa-message-doc-card__meta" x-text="(m.attachment.mime ? m.attachment.mime.split('/').pop().toUpperCase() : 'Arquivo') + (m.attachment.size ? (' • ' + formatFileSize(m.attachment.size)) : '')"></span>
                                                            </div>
                                                            <span class="wa-message-doc-card__download"><i class="fas fa-download"></i></span>
                                                        </a>
                                                        <p x-show="m.body && !['[Imagem]','[Vídeo]','[Documento]','[image]','[video]','[document]'].includes(m.body)" class="wa-message-doc__caption" x-text="m.body"></p>
                                                    </div>
                                                </template>
                                                <!-- Documento sem URL: card placeholder -->
                                                <template x-if="m.attachment && m.attachment.type === 'document' && !m.attachment.url">
                                                    <div class="wa-message-doc-card wa-message-doc-card--placeholder">
                                                        <div class="wa-message-doc-card__link">
                                                            <span class="wa-message-doc-card__icon"><i class="fas fa-file-alt"></i></span>
                                                            <div class="wa-message-doc-card__info">
                                                                <span class="wa-message-doc-card__name">Documento</span>
                                                                <span class="wa-message-doc-card__meta">Indisponível</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <!-- Placeholder quando imagem/vídeo/documento sem preview (sem URL ou imagem falhou ao carregar) -->
                                                <template x-if="(['image','video'].includes(m.message_type) && (!m.attachment || !m.attachment.url || (m.attachment.type === 'image' && imageLoadFail[m.id]))) || (m.message_type === 'document' && !m.attachment)">
                                                    <div class="wa-message-media-placeholder">
                                                        <span class="wa-message-media-placeholder__icon" x-show="m.message_type === 'image'"><i class="fas fa-image"></i></span>
                                                        <span class="wa-message-media-placeholder__icon" x-show="m.message_type === 'video'"><i class="fas fa-video"></i></span>
                                                        <span class="wa-message-media-placeholder__icon" x-show="m.message_type === 'document'"><i class="fas fa-file-alt"></i></span>
                                                        <span class="wa-message-media-placeholder__label" x-text="m.message_type === 'image' ? (m.attachment && !m.attachment.url ? 'Carregando imagem...' : (imageLoadFail[m.id] ? 'Imagem indisponível' : 'Imagem')) : (m.message_type === 'video' ? 'Vídeo' : 'Documento')"></span>
                                                    </div>
                                                </template>
                                                <div class="message" :class="{ 'wa-message--has-media': (m.attachment && (m.attachment.type === 'image' || m.attachment.type === 'video' || m.attachment.type === 'document')) || ['image','video','document'].includes(m.message_type) }">
                                                    <!-- Texto só quando não for mídia (evita exibir [image] / [Imagem]) -->
                                                    <template x-if="!['image','video','document'].includes(m.message_type) || (m.body && !['[Imagem]','[Vídeo]','[Documento]','[image]','[video]','[document]'].includes(m.body))">
                                                        <span x-text="m.body || (m.message_type && !['image','video','document'].includes(m.message_type) ? '['+m.message_type+']' : '')"></span>
                                                    </template>
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

                <div class="messenger-sendCard wa-send-card" x-show="!!activeConversation">
                    {{-- Emoji picker (acima do campo de texto) --}}
                    <div class="wa-emoji-picker" x-show="showEmojiPicker" x-cloak
                         @click.outside="showEmojiPicker = false">
                        <div class="wa-emoji-picker__grid">
                            <template x-for="(emoji, i) in emojiList" :key="i">
                                <button type="button" class="wa-emoji-picker__btn"
                                        x-text="emoji"
                                        @click="insertEmoji(emoji)"></button>
                            </template>
                        </div>
                    </div>
                    <form @submit.prevent="sendMessage()">
                        <input type="file" class="hidden" x-ref="attachInput" accept="*/*"
                            @change="onAttachSelected($event)"
                        />
                        <button type="button" class="wa-emoji-trigger wa-attach-trigger" title="Anexar arquivo"
                                @click.prevent="$refs.attachInput && $refs.attachInput.click()"
                                :disabled="!connected || sending">
                            <span class="fas fa-paperclip"></span>
                        </button>
                        <button type="button" class="wa-emoji-trigger" title="Emoji"
                                :class="{ 'wa-emoji-trigger--open': showEmojiPicker }"
                                @click.prevent="showEmojiPicker = !showEmojiPicker">
                            <span class="fas fa-smile"></span>
                        </button>
                        <textarea
                            name="message"
                            class="m-send app-scroll"
                            placeholder="Digite uma mensagem..."
                            x-model="draft"
                            x-ref="draftInput"
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
        <!-- Modal: após extrair membros, perguntar se adicionar a lista e/ou tag -->
        <div
            x-show="showExtractedModal"
            x-cloak
            style="display:none"
            class="fixed inset-0 z-[9998]"
            @keydown.escape.window="closeExtractedModal()"
        >
            <div class="absolute inset-0 bg-black/40" @click="closeExtractedModal()"></div>
            <div class="absolute inset-x-0 top-16 mx-auto w-full max-w-md px-4">
                <div class="bg-white rounded-xl shadow-xl overflow-hidden border border-gray-200">
                    <div class="px-4 py-3 flex items-center justify-between border-b border-gray-100">
                        <div class="font-semibold text-gray-900">Extrair membros</div>
                        <button type="button" class="text-gray-500 hover:text-gray-900" @click="closeExtractedModal()">✕</button>
                    </div>
                    <div class="p-4 space-y-4">
                        <p class="text-sm text-gray-700" x-text="extractedSummary"></p>

                        <div>
                            <p class="text-sm font-medium text-gray-800 mb-2">Deseja adicionar os contatos a uma lista?</p>
                            <div class="flex gap-2 items-center flex-wrap">
                                <label class="inline-flex items-center gap-1"><input type="radio" x-model="addToList" :value="false"> Não</label>
                                <label class="inline-flex items-center gap-1"><input type="radio" x-model="addToList" :value="true"> Sim</label>
                            </div>
                            <template x-if="addToList">
                                <div class="mt-2 space-y-2">
                                    <select
                                        x-model="selectedListId"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                                    >
                                        <option value="">— Selecione uma lista —</option>
                                        <option value="new">— Criar nova lista —</option>
                                        <template x-for="l in listas" :key="l.id">
                                            <option :value="l.id" x-text="l.name"></option>
                                        </template>
                                    </select>
                                    <input
                                        x-show="selectedListId === 'new'"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                                        placeholder="Nome da nova lista"
                                        x-model="newListName"
                                    />
                                </div>
                            </template>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-800 mb-2">Deseja adicionar uma tag?</p>
                            <div class="flex gap-2 items-center flex-wrap">
                                <label class="inline-flex items-center gap-1"><input type="radio" x-model="addToTag" :value="false"> Não</label>
                                <label class="inline-flex items-center gap-1"><input type="radio" x-model="addToTag" :value="true"> Sim</label>
                            </div>
                            <template x-if="addToTag">
                                <div class="mt-2 space-y-2">
                                    <select
                                        x-model="selectedTagId"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                                    >
                                        <option value="">— Selecione uma tag —</option>
                                        <option value="new">— Criar nova tag —</option>
                                        <template x-for="t in tags" :key="t.id">
                                            <option :value="t.id" x-text="t.name"></option>
                                        </template>
                                    </select>
                                    <input
                                        x-show="selectedTagId === 'new'"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                                        placeholder="Nome da nova tag"
                                        x-model="newTagName"
                                    />
                                </div>
                            </template>
                        </div>

                        <template x-if="listasTagsLoading">
                            <div class="text-sm text-gray-500">Carregando listas e tags...</div>
                        </template>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="px-3 py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 text-sm" @click="closeExtractedModal()">Cancelar</button>
                            <button type="button" class="px-3 py-2 rounded-lg bg-brand-500 text-white hover:bg-brand-600 text-sm disabled:opacity-50" @click="applyExtracted()" :disabled="applyingExtracted" x-text="applyingExtracted ? 'Aplicando...' : 'Aplicar'"></button>
                        </div>
                    </div>
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

