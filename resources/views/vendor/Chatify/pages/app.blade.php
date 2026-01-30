<x-app-layout>
    @push('styles')
        @include('Chatify::layouts.headLinks')
    @endpush

    <div class="h-[calc(100vh-4rem)] overflow-hidden">
        <div class="messenger h-full">
            {{-- ----------------------Users/Groups lists side---------------------- --}}
            <div class="messenger-listView {{ !!$id ? 'conversation-active' : '' }}">
                {{-- Header and search bar --}}
                <div class="m-header">
                    <nav>
                        <a href="#"><i class="fas fa-inbox"></i> <span class="messenger-headTitle">MESSAGES</span> </a>
                        {{-- header buttons --}}
                        <nav class="m-header-right">
                            <a href="#"><i class="fas fa-cog settings-btn"></i></a>
                            <a href="#" class="listView-x"><i class="fas fa-times"></i></a>
                        </nav>
                    </nav>
                    {{-- Search input --}}
                    <input type="text" class="messenger-search" placeholder="Search" />
                    {{-- Tabs --}}
                    {{-- <div class="messenger-listView-tabs">
                        <a href="#" class="active-tab" data-view="users">
                            <span class="far fa-user"></span> Contacts</a>
                    </div> --}}
                </div>
                {{-- tabs and lists --}}
                <div class="m-body contacts-container">
                   {{-- Lists [Users/Group] --}}
                   {{-- ---------------- [ User Tab ] ---------------- --}}
                   <div class="show messenger-tab users-tab app-scroll" data-view="users">
                       {{-- Favorites --}}
                       <div class="favorites-section">
                        <p class="messenger-title"><span>Favorites</span></p>
                        <div class="messenger-favorites app-scroll-hidden"></div>
                       </div>
                       {{-- Saved Messages --}}
                       <p class="messenger-title"><span>Your Space</span></p>
                       {!! view('Chatify::layouts.listItem', ['get' => 'saved']) !!}
                       {{-- Contact --}}
                       <p class="messenger-title"><span>All Messages</span></p>
                       <div class="listOfContacts" style="width: 100%;height: calc(100% - 272px);position: relative;"></div>
                   </div>
                     {{-- ---------------- [ Search Tab ] ---------------- --}}
                   <div class="messenger-tab search-tab app-scroll" data-view="search">
                        {{-- items --}}
                        <p class="messenger-title"><span>Search</span></p>
                        <div class="search-records">
                            <p class="message-hint center-el"><span>Type to search..</span></p>
                        </div>
                     </div>
                </div>
            </div>

            {{-- ----------------------Messaging side---------------------- --}}
            <div class="messenger-messagingView">
                {{-- header title [conversation name] amd buttons --}}
                <div class="m-header m-header-messaging">
                    <nav class="chatify-d-flex chatify-justify-content-between chatify-align-items-center">
                        {{-- header back button, avatar and user name --}}
                        <div class="chatify-d-flex chatify-justify-content-between chatify-align-items-center">
                            <a href="#" class="show-listView"><i class="fas fa-arrow-left"></i></a>
                            <div class="avatar av-s header-avatar" style="margin: 0px 10px; margin-top: -5px; margin-bottom: -5px;">
                            </div>
                            <a href="#" class="user-name">{{ config('chatify.name') }}</a>
                        </div>
                        {{-- header buttons --}}
                        <nav class="m-header-right">
                            <a href="#" class="add-to-favorite"><i class="fas fa-star"></i></a>
                            <a href="/"><i class="fas fa-home"></i></a>
                            <a href="#" class="show-infoSide"><i class="fas fa-info-circle"></i></a>
                        </nav>
                    </nav>
                    {{-- Internet connection --}}
                    <div class="internet-connection">
                        <span class="ic-connected">Connected</span>
                        <span class="ic-connecting">Connecting...</span>
                        <span class="ic-noInternet">No internet access</span>
                    </div>
                </div>

                {{-- Messaging area --}}
                <div class="m-body messages-container app-scroll">
                    <div class="messages">
                        <p class="message-hint center-el"><span>Please select a chat to start messaging</span></p>
                    </div>
                    {{-- Typing indicator --}}
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

                </div>
                {{-- Send Message Form --}}
                @include('Chatify::layouts.sendForm')
            </div>
            {{-- ---------------------- Info side ---------------------- --}}
            <div class="messenger-infoView app-scroll">
                {{-- nav actions --}}
                <nav>
                    <p>User Details</p>
                    <a href="#"><i class="fas fa-times"></i></a>
                </nav>
                {!! view('Chatify::layouts.info')->render() !!}
            </div>
        </div>
    </div>

    @include('Chatify::layouts.modals')

    @push('scripts')
        <script>
            document.title = @json(config('chatify.name'));
        </script>
        <script>
            // WhatsApp (Evolution) - ao abrir o Chat:
            // - identifica a última instância do usuário
            // - checa o estado na Evolution
            // - se não estiver conectado, tenta "connect" (pode exigir QR) e re-checa
            // - atualiza o header (badge) via evento whatsapp-connection-changed
            (function () {
                const connectedStates = new Set(['connected', 'open', 'online', 'ready']);

                const isConnected = (state) => {
                    const s = String(state || '').trim().toLowerCase();
                    return connectedStates.has(s);
                };

                const emitHeader = (connected) => {
                    window.dispatchEvent(new CustomEvent('whatsapp-connection-changed', {
                        detail: { connected: !!connected },
                    }));
                };

                const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

                const fetchJson = async (url) => {
                    const resp = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const payload = await resp.json().catch(() => ({}));
                    return { ok: resp.ok, status: resp.status, payload };
                };

                const run = async () => {
                    try {
                        // 1) status (qual instância usar)
                        const statusUrl = @json('/settings/whatsapp/status');
                        const statusRes = await fetchJson(statusUrl);
                        const data = statusRes.payload || {};

                        if (!statusRes.ok) {
                            emitHeader(false);
                            return;
                        }

                        const inst = data.instanceName ? String(data.instanceName) : '';
                        if (!inst) {
                            emitHeader(false);
                            return;
                        }

                        // 2) sempre re-checa o estado real na Evolution via endpoint existente
                        const stateUrlBase = @json('/settings/whatsapp/state');
                        const state1 = await fetchJson(stateUrlBase + '/' + encodeURIComponent(inst));
                        const stateVal1 = state1.payload?.state ?? data.state ?? null;

                        if (isConnected(stateVal1)) {
                            window.__activeWhatsAppInstance = inst;
                            emitHeader(true);
                            return;
                        }

                        // 3) tentativa best-effort de "connect" (pode gerar QR; não garante conexão automática)
                        const connectUrlBase = @json('/settings/whatsapp/connect');
                        await fetchJson(connectUrlBase + '/' + encodeURIComponent(inst));

                        // 4) re-checa após pequeno delay
                        await sleep(900);
                        const state2 = await fetchJson(stateUrlBase + '/' + encodeURIComponent(inst));
                        const stateVal2 = state2.payload?.state ?? null;

                        const connected = isConnected(stateVal2);
                        if (connected) {
                            window.__activeWhatsAppInstance = inst;
                        }
                        emitHeader(connected);
                    } catch (e) {
                        emitHeader(false);
                    }
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', run, { once: true });
                } else {
                    run();
                }
            })();
        </script>
        @include('Chatify::layouts.footerLinks')
    @endpush
</x-app-layout>
