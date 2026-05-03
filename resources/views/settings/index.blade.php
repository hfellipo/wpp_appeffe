<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Configurações') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="space-y-6">
                        <!-- Informações da Conta -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                {{ __('Informações da Conta') }}
                            </h3>
                            <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Nome') }}:</span>
                                    <span class="font-medium">{{ Auth::user()->name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('E-mail') }}:</span>
                                    <span class="font-medium">{{ Auth::user()->email }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Perfil') }}:</span>
                                    <span class="font-medium">
                                        @if(Auth::user()->isAdmin())
                                            <span class="badge-admin">Admin</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Usuário</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Status') }}:</span>
                                    <span class="font-medium">
                                        @if(Auth::user()->isActive())
                                            <span class="text-brand-600">{{ __('Ativo') }}</span>
                                        @else
                                            <span class="text-red-600">{{ __('Inativo') }}</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('profile.edit') }}" class="btn-secondary inline-flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    {{ __('Editar Perfil') }}
                                </a>
                            </div>
                        </div>

                        <!-- Campos Personalizados -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                {{ __('Campos Personalizados') }}
                            </h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-gray-600 mb-4">
                                    {{ __('Gerencie os campos personalizados dos seus contatos.') }}
                                </p>
                                <a href="{{ route('contacts.fields.index') }}" class="btn-primary inline-flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    {{ __('Gerenciar Campos') }}
                                </a>
                            </div>
                        </div>

                        <!-- Importação -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                {{ __('Importação de Contatos') }}
                            </h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-gray-600 mb-4">
                                    {{ __('Importe contatos de planilhas Excel ou CSV.') }}
                                </p>
                                <a href="{{ route('contacts.import.index') }}" class="btn-primary inline-flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                    </svg>
                                    {{ __('Importar Contatos') }}
                                </a>
                            </div>
                        </div>

                        <!-- Inteligência Artificial -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                                {{ __('Inteligência Artificial') }}
                            </h3>

                            {{-- Toast de sucesso --}}
                            @if(session('ai_success'))
                                <div id="ai-toast"
                                     style="position:fixed;top:20px;right:20px;z-index:9999;display:flex;align-items:center;gap:10px;background:#111827;color:#fff;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.25);">
                                    <svg style="width:18px;height:18px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    {{ session('ai_success') }}
                                </div>
                                <script>setTimeout(()=>{const t=document.getElementById('ai-toast');if(t){t.style.transition='opacity .4s';t.style.opacity='0';setTimeout(()=>t.remove(),400);}},3000);</script>
                            @endif

                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-5">
                                <p class="text-gray-600 mb-5 text-sm">
                                    {{ __('Configure sua chave da API OpenAI para usar o nó de Resposta com IA nas automações. A chave é armazenada de forma criptografada.') }}
                                </p>

                                <form method="POST" action="{{ route('settings.ai.save') }}" id="ai-config-form" class="space-y-5">
                                    @csrf

                                    {{-- API Key --}}
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                                            {{ __('Chave da API OpenAI') }}
                                        </label>
                                        <div class="relative">
                                            <input
                                                type="password"
                                                id="openai_key_input"
                                                name="openai_api_key"
                                                placeholder="{{ $aiConfig->openai_api_key ? '••••••••••••••••••••••••' : 'sk-proj-...' }}"
                                                class="w-full pr-10 border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500"
                                                autocomplete="off"
                                            >
                                            {{-- toggle show/hide --}}
                                            <button type="button" onclick="(function(){var i=document.getElementById('openai_key_input');i.type=i.type==='password'?'text':'password';})()"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>
                                        </div>
                                        @if($aiConfig->openai_api_key)
                                            <p class="mt-1.5 text-xs text-green-600 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                {{ __('Chave configurada — deixe em branco para manter a atual.') }}
                                            </p>
                                        @else
                                            <p class="mt-1.5 text-xs text-gray-500">{{ __('Obtenha sua chave em platform.openai.com') }}</p>
                                        @endif
                                        @error('openai_api_key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>

                                    {{-- Configurações avançadas --}}
                                    <details class="group">
                                        <summary class="cursor-pointer text-xs text-purple-700 font-semibold select-none flex items-center gap-1.5 list-none">
                                            <svg class="w-3.5 h-3.5 transition-transform duration-200 group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                            {{ __('Configurações avançadas') }}
                                            <span class="text-gray-400 font-normal">(modelo, temperatura, tokens)</span>
                                        </summary>
                                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 pl-1">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('Modelo padrão') }}</label>
                                                <select name="default_model" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
                                                    @foreach(\App\Models\AiConfig::availableModels() as $value => $label)
                                                        <option value="{{ $value }}" {{ ($aiConfig->default_model ?? 'gpt-3.5-turbo') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('Temperatura') }} <span class="text-gray-400 font-normal">(0 = preciso, 1 = criativo)</span></label>
                                                <input type="number" name="temperature" value="{{ $aiConfig->temperature ?? 0.70 }}" min="0" max="1" step="0.05"
                                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('Máx. tokens por resposta') }}</label>
                                                <input type="number" name="max_tokens" value="{{ $aiConfig->max_tokens ?? 500 }}" min="50" max="4000" step="50"
                                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
                                            </div>
                                        </div>
                                    </details>

                                    {{-- Botão salvar --}}
                                    <div class="pt-1 flex items-center gap-4">
                                        <button
                                            type="submit"
                                            id="ai-save-btn"
                                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-gray-900 hover:bg-black active:bg-gray-800 text-white text-sm font-semibold rounded-lg shadow-sm transition-all duration-150"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            {{ __('Salvar configurações') }}
                                        </button>

                                        <a href="{{ route('ai-agents.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-700 hover:text-black font-medium transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m8-4a4 4 0 10-8 0 4 4 0 008 0zM15 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                            {{ __('Gerenciar Agentes de IA') }}
                                        </a>
                                    </div>
                                </form>

                                {{-- Loading state no submit --}}
                                <script>
                                    document.getElementById('ai-config-form').addEventListener('submit', function() {
                                        var btn = document.getElementById('ai-save-btn');
                                        btn.disabled = true;
                                        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg> Salvando...';
                                    });
                                </script>
                            </div>
                        </div>

                        <!-- WhatsApp -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                </svg>
                                {{ __('WhatsApp') }}
                            </h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-gray-600 mb-4">
                                    {{ __('Conecte sua conta do WhatsApp via Evolution API para enviar mensagens.') }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('whatsapp.index') }}" class="btn-primary inline-flex items-center bg-green-600 hover:bg-green-700 focus:ring-green-500">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        {{ __('Conectar WhatsApp') }}
                                    </a>
                                    <a href="{{ route('whatsapp.instances') }}" class="btn-secondary inline-flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/></svg>
                                        {{ __('Instâncias') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        @if(Auth::user()->isAdmin())
                        <!-- Área Administrativa -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-golden-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                {{ __('Área Administrativa') }}
                            </h3>
                            <div class="bg-golden-50 border border-golden-200 rounded-lg p-4">
                                <p class="text-gray-700 mb-4">
                                    {{ __('Funcionalidades administrativas disponíveis.') }}
                                </p>
                                <div class="space-y-3">
                                    <div class="text-sm text-gray-700">
                                        {{ __('Gerencie usuários que acessam os mesmos dados desta conta.') }}
                                    </div>
                                    <a href="{{ route('settings.users.index') }}" class="btn-primary inline-flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m8-4a4 4 0 10-8 0 4 4 0 008 0zM15 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                        </svg>
                                        {{ __('Gerenciar usuários') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
