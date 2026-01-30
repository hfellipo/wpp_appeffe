<nav
    x-data="{ open: false, waConnected: @json((bool) ($whatsappConnected ?? false)) }"
    x-on:whatsapp-connection-changed.window="waConnected = !!($event.detail && $event.detail.connected)"
    class="bg-gradient-to-r from-brand-700 to-brand-600 shadow-lg"
>
    @php
        $whatsappConnected = (bool) ($whatsappConnected ?? false);
    @endphp
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
                        <x-application-logo class="block h-9 w-auto fill-current text-white" />
                        <span class="text-white font-bold text-xl hidden sm:block">Secretário</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('contacts.index')" :active="request()->routeIs('contacts.*')">
                        {{ __('Contatos') }}
                    </x-nav-link>
                    @if(Route::has('whatsapp.inbox.index'))
                        <x-nav-link :href="route('whatsapp.inbox.index')" :active="request()->is('whatsapp*')">
                            {{ __('WhatsApp') }}
                        </x-nav-link>
                    @endif
                    <x-nav-link :href="route(config('chatify.routes.prefix'))" :active="request()->is(config('chatify.routes.prefix') . '*')">
                        {{ __('Chat') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <!-- User Role Badge -->
                @if(Auth::user()->isAdmin())
                    <span class="badge-admin mr-3">Admin</span>
                @endif

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-brand-500 text-sm leading-4 font-medium rounded-md text-white bg-brand-600 hover:bg-brand-500 hover:border-brand-400 focus:outline-none focus:ring-2 focus:ring-golden-400 transition ease-in-out duration-150">
                            <div class="flex items-center gap-2">
                                <span>{{ Auth::user()->name }}</span>
                                <span
                                    x-show="waConnected"
                                    style="display:none"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-600 bg-opacity-20 px-2 py-0.5 text-[10px] font-semibold text-white ring-1 ring-emerald-200 ring-opacity-30"
                                    title="WhatsApp conectado"
                                    aria-label="WhatsApp conectado"
                                >
                                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-white ring-opacity-30"></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" class="shrink-0 text-white opacity-90" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                        <path d="M20.52 3.49A11.82 11.82 0 0 0 12.01 0C5.39 0 .01 5.38.01 12c0 2.12.55 4.2 1.6 6.04L0 24l6.13-1.6A11.9 11.9 0 0 0 12.01 24C18.63 24 24 18.62 24 12c0-3.2-1.25-6.2-3.48-8.51Zm-8.5 18.44c-1.8 0-3.57-.49-5.12-1.42l-.37-.22-3.64.95.97-3.55-.24-.37A9.86 9.86 0 0 1 2.1 12C2.1 6.55 6.56 2.09 12.01 2.09c2.64 0 5.12 1.03 6.98 2.9A9.8 9.8 0 0 1 21.9 12c0 5.45-4.46 9.93-9.88 9.93Zm5.74-7.34c-.31-.16-1.83-.9-2.11-1-.28-.1-.49-.16-.7.16-.21.31-.8 1-.98 1.2-.18.21-.36.23-.67.08-.31-.16-1.31-.48-2.5-1.53-.92-.82-1.54-1.83-1.72-2.14-.18-.31-.02-.48.14-.64.14-.14.31-.36.47-.54.16-.18.21-.31.31-.52.1-.21.05-.39-.03-.54-.08-.16-.7-1.68-.95-2.3-.25-.6-.5-.52-.7-.52h-.6c-.21 0-.54.08-.83.39-.28.31-1.09 1.06-1.09 2.6s1.12 3.03 1.27 3.24c.16.21 2.21 3.38 5.35 4.74.75.32 1.33.51 1.78.65.75.24 1.44.21 1.98.13.6-.09 1.83-.75 2.09-1.47.26-.72.26-1.33.18-1.47-.08-.13-.28-.21-.6-.37Z"/>
                                    </svg>
                                </span>
                            </div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <x-dropdown-link :href="route('settings.index')">
                            {{ __('Configurações') }}
                        </x-dropdown-link>

                        @if(Auth::user()->isAdmin() && Route::has('settings.users.index'))
                            <x-dropdown-link :href="route('settings.users.index')">
                                {{ __('Gerenciar usuários') }}
                            </x-dropdown-link>
                        @endif

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-brand-200 hover:text-white hover:bg-brand-600 focus:outline-none focus:bg-brand-600 focus:text-white transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-brand-800">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('contacts.index')" :active="request()->routeIs('contacts.*')">
                {{ __('Contatos') }}
            </x-responsive-nav-link>
            @if(Route::has('whatsapp.inbox.index'))
                <x-responsive-nav-link :href="route('whatsapp.inbox.index')" :active="request()->is('whatsapp*')">
                    {{ __('WhatsApp') }}
                </x-responsive-nav-link>
            @endif
            <x-responsive-nav-link :href="route(config('chatify.routes.prefix'))" :active="request()->is(config('chatify.routes.prefix') . '*')">
                {{ __('Chat') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-brand-600">
            <div class="px-4 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 font-medium text-base text-white">
                        <span>{{ Auth::user()->name }}</span>
                        <span
                            x-show="waConnected"
                            style="display:none"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-600 bg-opacity-20 px-2 py-0.5 text-[10px] font-semibold text-white"
                            title="WhatsApp conectado"
                            aria-label="WhatsApp conectado"
                        >
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-white ring-opacity-30"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" class="shrink-0 text-white opacity-90" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                <path d="M20.52 3.49A11.82 11.82 0 0 0 12.01 0C5.39 0 .01 5.38.01 12c0 2.12.55 4.2 1.6 6.04L0 24l6.13-1.6A11.9 11.9 0 0 0 12.01 24C18.63 24 24 18.62 24 12c0-3.2-1.25-6.2-3.48-8.51Zm-8.5 18.44c-1.8 0-3.57-.49-5.12-1.42l-.37-.22-3.64.95.97-3.55-.24-.37A9.86 9.86 0 0 1 2.1 12C2.1 6.55 6.56 2.09 12.01 2.09c2.64 0 5.12 1.03 6.98 2.9A9.8 9.8 0 0 1 21.9 12c0 5.45-4.46 9.93-9.88 9.93Zm5.74-7.34c-.31-.16-1.83-.9-2.11-1-.28-.1-.49-.16-.7.16-.21.31-.8 1-.98 1.2-.18.21-.36.23-.67.08-.31-.16-1.31-.48-2.5-1.53-.92-.82-1.54-1.83-1.72-2.14-.18-.31-.02-.48.14-.64.14-.14.31-.36.47-.54.16-.18.21-.31.31-.52.1-.21.05-.39-.03-.54-.08-.16-.7-1.68-.95-2.3-.25-.6-.5-.52-.7-.52h-.6c-.21 0-.54.08-.83.39-.28.31-1.09 1.06-1.09 2.6s1.12 3.03 1.27 3.24c.16.21 2.21 3.38 5.35 4.74.75.32 1.33.51 1.78.65.75.24 1.44.21 1.98.13.6-.09 1.83-.75 2.09-1.47.26-.72.26-1.33.18-1.47-.08-.13-.28-.21-.6-.37Z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="font-medium text-sm text-brand-300">{{ Auth::user()->email }}</div>
                </div>
                <div class="flex items-center gap-2">
                @if(Auth::user()->isAdmin())
                    <span class="badge-admin">Admin</span>
                @endif
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('settings.index')">
                    {{ __('Configurações') }}
                </x-responsive-nav-link>

                @if(Auth::user()->isAdmin() && Route::has('settings.users.index'))
                    <x-responsive-nav-link :href="route('settings.users.index')">
                        {{ __('Gerenciar usuários') }}
                    </x-responsive-nav-link>
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
