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

                        <!-- WhatsApp -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                {{ __('WhatsApp') }}
                            </h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-gray-600 mb-4">
                                    {{ __('Configure a integração com WhatsApp usando a Evolution API.') }}
                                </p>
                                <a href="{{ route('whatsapp.index') }}" class="btn-primary inline-flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    {{ __('Configurar WhatsApp') }}
                                </a>
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
                                <div class="space-y-2">
                                    <p class="text-sm text-gray-600">
                                        {{ __('• Gerenciar usuários') }}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        {{ __('• Configurações do sistema') }}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        {{ __('• Relatórios e estatísticas') }}
                                    </p>
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
