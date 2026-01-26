<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Secretário') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gradient-to-br from-brand-700 via-brand-600 to-brand-500">
            <!-- Navigation -->
            <nav class="bg-transparent">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16 items-center">
                        <div class="flex items-center">
                            <x-application-logo class="block h-10 w-auto fill-current text-white" />
                            <span class="ml-3 text-2xl font-bold text-white">Secretário</span>
                        </div>
                        @if (Route::has('login'))
                            <div class="flex items-center space-x-4">
                                @auth
                                    <a href="{{ url('/dashboard') }}" class="text-white hover:text-brand-100 font-medium transition">
                                        Dashboard
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="text-white hover:text-brand-100 font-medium transition">
                                        Entrar
                                    </a>
                                    @if (Route::has('register'))
                                        <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 bg-golden-500 border border-transparent rounded-md font-semibold text-xs text-gray-900 uppercase tracking-widest hover:bg-golden-400 transition">
                                            Criar Conta
                                        </a>
                                    @endif
                                @endauth
                            </div>
                        @endif
                    </div>
                </div>
            </nav>

            <!-- Hero Section -->
            <div class="relative overflow-hidden">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
                    <div class="text-center">
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white leading-tight">
                            Organize sua vida com
                            <span class="text-golden-400">inteligência</span>
                        </h1>
                        <p class="mt-6 text-xl text-brand-100 max-w-2xl mx-auto">
                            O Secretário é seu assistente pessoal para gerenciar tarefas, compromissos e muito mais. 
                            Simplifique seu dia a dia com nossa plataforma moderna e intuitiva.
                        </p>
                        <div class="mt-10 flex flex-col sm:flex-row justify-center gap-4">
                            @guest
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-3 bg-golden-500 border border-transparent rounded-lg font-semibold text-gray-900 hover:bg-golden-400 focus:outline-none focus:ring-2 focus:ring-golden-400 focus:ring-offset-2 focus:ring-offset-brand-600 transition text-lg">
                                    Começar Agora
                                    <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                </a>
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-8 py-3 bg-transparent border-2 border-white rounded-lg font-semibold text-white hover:bg-white hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-brand-600 transition text-lg">
                                    Já tenho conta
                                </a>
                            @else
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-8 py-3 bg-golden-500 border border-transparent rounded-lg font-semibold text-gray-900 hover:bg-golden-400 focus:outline-none focus:ring-2 focus:ring-golden-400 focus:ring-offset-2 focus:ring-offset-brand-600 transition text-lg">
                                    Ir para o Dashboard
                                    <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                </a>
                            @endguest
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Section -->
            <div class="bg-white py-24">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-16">
                        <h2 class="text-3xl font-bold text-gray-900">Por que escolher o Secretário?</h2>
                        <p class="mt-4 text-lg text-gray-600">Recursos pensados para facilitar sua rotina</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Feature 1 -->
                        <div class="text-center p-6 rounded-xl bg-brand-50 border border-brand-100">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-brand-500 rounded-full mb-6">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Gestão de Tarefas</h3>
                            <p class="text-gray-600">Organize suas tarefas de forma simples e eficiente. Nunca mais esqueça um compromisso importante.</p>
                        </div>

                        <!-- Feature 2 -->
                        <div class="text-center p-6 rounded-xl bg-lime-50 border border-lime-100">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-lime-400 rounded-full mb-6">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Lembretes Inteligentes</h3>
                            <p class="text-gray-600">Receba notificações no momento certo. Nosso sistema aprende seus hábitos e otimiza seus lembretes.</p>
                        </div>

                        <!-- Feature 3 -->
                        <div class="text-center p-6 rounded-xl bg-golden-50 border border-golden-100">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-golden-500 rounded-full mb-6">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Relatórios Detalhados</h3>
                            <p class="text-gray-600">Acompanhe sua produtividade com gráficos e estatísticas. Entenda onde você pode melhorar.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="bg-brand-800 py-16">
                <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                    <h2 class="text-3xl font-bold text-white mb-4">Pronto para começar?</h2>
                    <p class="text-brand-200 text-lg mb-8">Junte-se a milhares de usuários que já organizaram suas vidas com o Secretário.</p>
                    @guest
                        <a href="{{ route('register') }}" class="inline-flex items-center px-8 py-3 bg-golden-500 border border-transparent rounded-lg font-semibold text-gray-900 hover:bg-golden-400 transition text-lg">
                            Criar Conta Gratuita
                        </a>
                    @endguest
                </div>
            </div>

            <!-- Footer -->
            <footer class="bg-brand-900 py-8">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <div class="flex items-center mb-4 md:mb-0">
                            <x-application-logo class="block h-8 w-auto fill-current text-brand-400" />
                            <span class="ml-2 text-lg font-semibold text-white">Secretário</span>
                        </div>
                        <p class="text-brand-400 text-sm">
                            &copy; {{ date('Y') }} Secretário App. Todos os direitos reservados.
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
