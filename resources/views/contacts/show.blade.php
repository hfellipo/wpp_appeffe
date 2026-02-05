<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $contact->name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('contacts.edit', $contact) }}" class="btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    {{ __('Editar') }}
                </a>
                <a href="{{ route('contacts.index') }}" class="btn-secondary">
                    {{ __('Voltar') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                {{-- Coluna esquerda: Dados do contato --}}
                <div class="lg:col-span-3 order-1 space-y-6">
                {{-- 1. Dados do contato --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Dados do contato') }}</h3>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Nome') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $contact->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Telefone') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <a href="tel:{{ $contact->raw_phone }}" class="text-brand-600 hover:text-brand-800">{{ $contact->formatted_phone }}</a>
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">{{ __('E-mail') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($contact->email)
                                        <a href="mailto:{{ $contact->email }}" class="text-brand-600 hover:text-brand-800">{{ $contact->email }}</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </dd>
                            </div>
                            @if($contact->notes)
                                <div class="sm:col-span-2">
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Observações') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $contact->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- 2. Campos personalizados --}}
                @if($customFields->count() > 0)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Campos personalizados') }}</h3>
                        </div>
                        <div class="p-6">
                            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @foreach($customFields as $field)
                                    @php $value = $contact->getFieldValue($field); @endphp
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ $field->name }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $value ?? '—' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                @endif

                {{-- 3. Listas e Tags (lado a lado) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Listas') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Listas em que este contato está incluído.') }}</p>
                        </div>
                        <div class="p-6">
                            @if($contact->listas->isEmpty())
                                <p class="text-sm text-gray-500">{{ __('Este contato não está em nenhuma lista.') }} {{ __('Adicione em') }} <a href="{{ route('listas.index') }}" class="text-brand-600 hover:underline">{{ __('Listas') }}</a>.</p>
                            @else
                                <ul class="space-y-2">
                                    @foreach($contact->listas as $lista)
                                        <li>
                                            <a href="{{ route('listas.show', $lista) }}" class="text-sm font-medium text-brand-600 hover:text-brand-800">{{ $lista->name }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Tags') }}</h3>
                        </div>
                        <div class="p-6">
                            @if($contact->tags->isEmpty())
                                <p class="text-sm text-gray-500">{{ __('Nenhuma tag atribuída.') }} <a href="{{ route('contacts.edit', $contact) }}" class="text-brand-600 hover:underline">{{ __('Editar contato') }}</a> {{ __('para adicionar tags.') }}</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach($contact->tags as $tag)
                                        <a href="{{ route('tags.index', ['tag' => $tag->id]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium {{ $tag->color ? '' : 'bg-gray-100 text-gray-800' }}" @if($tag->color) style="background-color: {{ $tag->color }}20; color: {{ $tag->color }};" @endif>
                                            @if($tag->color)
                                                <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $tag->color }}"></span>
                                            @endif
                                            {{ $tag->name }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                </div>

                {{-- Coluna direita: Histórico de eventos (WhatsApp + automações) --}}
                <div class="lg:col-span-1 order-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg sticky top-4">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="text-sm font-medium text-gray-900">{{ __('Histórico de eventos') }}</h3>
                        </div>
                        <div class="p-4 min-h-[200px] max-h-[70vh] overflow-y-auto space-y-3">
                            @if($events->isEmpty())
                                <p class="text-sm text-gray-400 text-center py-4">{{ __('Nenhum evento ainda.') }}<br>{{ __('Mensagens e execuções de automação aparecem aqui.') }}</p>
                            @else
                                @foreach($events as $event)
                                    @if($event['type'] === 'message')
                                        @php $msg = $event['message']; @endphp
                                        <div class="flex {{ $msg->direction === 'out' ? 'justify-end' : 'justify-start' }}">
                                            <div class="max-w-[85%] rounded-lg px-3 py-2 {{ $msg->direction === 'out' ? 'bg-green-100 text-gray-900 rounded-br-none' : 'bg-gray-100 text-gray-900 rounded-bl-none' }}">
                                                <p class="text-sm whitespace-pre-wrap break-words">{{ $msg->body ?? '—' }}</p>
                                                <div class="flex items-center justify-end gap-1 mt-1">
                                                    @if($msg->automationRun && $msg->automationRun->automation)
                                                        <span class="text-xs text-gray-500" title="{{ __('Enviado pela automação') }}">{{ __('Via') }} {{ $msg->automationRun->automation->name }}</span>
                                                    @endif
                                                    <span class="text-xs text-gray-500">{{ $event['at']->format('d/m H:i') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        @php $run = $event['run']; @endphp
                                        <div class="flex items-start gap-2 rounded-lg border border-gray-200 bg-amber-50/50 px-3 py-2">
                                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-900">{{ __('Automação') }}: {{ $run->automation->name ?? __('Sem nome') }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    {{ $event['at']->format('d/m/Y H:i') }}
                                                    @if($run->status === 'failed')
                                                        <span class="text-red-600"> · {{ __('Falhou') }}</span>
                                                    @elseif($run->status === 'partial')
                                                        <span class="text-amber-600"> · {{ __('Parcial') }}</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
