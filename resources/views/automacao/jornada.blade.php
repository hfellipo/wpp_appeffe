<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <div class="flex items-center gap-3">
                <a href="{{ route('automacao.index') }}" class="text-gray-500 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100 transition-colors" title="{{ __('Voltar') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ $automation->name }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">{{ __('Visão da jornada') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'trigger']) }}" class="btn-secondary text-sm">
                    {{ __('Editar') }}
                </a>
                <a href="{{ route('automacao.test', $automation) }}" class="btn-primary text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Testar') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="alert-success mb-6">{{ session('success') }}</div>
            @endif

            {{-- Status badge --}}
            <div class="mb-8 flex items-center justify-center">
                @if($automation->is_active)
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-50 text-emerald-800 text-sm font-medium border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ __('Automação ativa') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gray-100 text-gray-600 text-sm font-medium">
                        {{ __('Pausada') }}
                    </span>
                @endif
            </div>

            {{-- Fluxo visual: Trigger → Condições → Ações --}}
            <div class="space-y-0">
                {{-- 1. GATILHO --}}
                <div class="flex flex-col items-center">
                    <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'trigger']) }}" class="journey-card journey-card-trigger group block w-full max-w-md">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white shadow-lg group-hover:scale-105 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="text-xs font-semibold uppercase tracking-wider text-brand-600">{{ __('Gatilho') }}</span>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900">
                                    @if($automation->trigger)
                                        {{ \App\Models\Automation::triggerTypes()[$automation->trigger->type] ?? $automation->trigger->type }}
                                    @else
                                        {{ __('Não configurado') }}
                                    @endif
                                </h3>
                                @if($automation->trigger)
                                    <p class="mt-2 text-sm text-gray-600">
                                        @if($automation->trigger->type === 'tag_added' && !empty($automation->trigger->config['tag_id']))
                                            @php $tag = $tags->firstWhere('id', $automation->trigger->config['tag_id']); @endphp
                                            {{ __('Tag') }}: <span class="font-medium text-gray-800">{{ $tag?->name ?? '—' }}</span>
                                        @elseif($automation->trigger->type === 'list_added' && !empty($automation->trigger->config['lista_id']))
                                            @php $lista = $listas->firstWhere('id', $automation->trigger->config['lista_id']); @endphp
                                            {{ __('Lista') }}: <span class="font-medium text-gray-800">{{ $lista?->name ?? '—' }}</span>
                                        @endif
                                        · {{ __('Verificar a cada') }} {{ $automation->interval_minutes ?? 15 }} min
                                        · {{ $automation->run_once_per_contact ? __('Uma vez por contato') : __('Sempre que atender') }}
                                    </p>
                                @endif
                                <span class="mt-2 inline-flex items-center text-xs text-gray-400 group-hover:text-brand-600 transition-colors">
                                    {{ __('Editar') }}
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                    <div class="w-0.5 h-8 bg-gradient-to-b from-brand-200 to-indigo-200 my-1 rounded-full"></div>
                </div>

                {{-- 2. CONDIÇÕES --}}
                <div class="flex flex-col items-center">
                    <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'condition']) }}" class="journey-card journey-card-condition group block w-full max-w-md">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white shadow-lg group-hover:scale-105 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600">{{ __('Condições') }}</span>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900">
                                    @if($automation->condition_logic === null)
                                        {{ __('Todos os contatos') }}
                                    @else
                                        {{ $automation->conditions->count() }} {{ __('regra(s)') }} ({{ $automation->condition_logic === 'and' ? __('E') : __('OU') }})
                                    @endif
                                </h3>
                                @if($automation->conditions->isNotEmpty())
                                    <ul class="mt-2 space-y-1 text-sm text-gray-600">
                                        @foreach($automation->conditions->take(3) as $c)
                                            <li class="flex items-center gap-2">
                                                <span class="text-gray-400">•</span>
                                                @if($c->field_type === 'attribute')
                                                    {{ \App\Models\Automation::attributeFields()[$c->field_key] ?? $c->field_key }}
                                                @else
                                                    {{ $c->contactField?->name ?? __('Campo') }}
                                                @endif
                                                {{ \App\Models\Automation::conditionOperators()[$c->operator] ?? $c->operator }}
                                                @if(!in_array($c->operator, ['is_empty', 'is_not_empty']))
                                                    <span class="font-medium text-gray-800">"{{ Str::limit($c->value, 20) }}"</span>
                                                @endif
                                            </li>
                                        @endforeach
                                        @if($automation->conditions->count() > 3)
                                            <li class="text-gray-400">+ {{ $automation->conditions->count() - 3 }} {{ __('mais') }}</li>
                                        @endif
                                    </ul>
                                @else
                                    <p class="mt-2 text-sm text-gray-500">{{ __('Quem acionar o gatilho passa.') }}</p>
                                @endif
                                <span class="mt-2 inline-flex items-center text-xs text-gray-400 group-hover:text-indigo-600 transition-colors">
                                    {{ __('Editar') }}
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                    <div class="w-0.5 h-8 bg-gradient-to-b from-indigo-200 to-amber-200 my-1 rounded-full"></div>
                </div>

                {{-- 3. AÇÕES (sequência) --}}
                <div class="flex flex-col items-center">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 mb-3">{{ __('Ações em sequência') }}</span>
                    @forelse($automation->actions as $index => $act)
                        <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'action']) }}" class="journey-card journey-card-action group block w-full max-w-md">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-white text-sm font-bold shadow-md group-hover:scale-105 transition-transform">
                                    {{ $index + 1 }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-base font-semibold text-gray-900">
                                        {{ $actionTypes[$act->type] ?? $act->type }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-600">
                                        @if($act->type === 'send_whatsapp_message' && !empty($act->config['message']))
                                            {{ Str::limit($act->config['message'], 60) }}
                                        @endif
                                        @if($act->type === 'add_to_list' && !empty($act->config['lista_id']))
                                            @php $lista = $listas->firstWhere('id', $act->config['lista_id']); @endphp
                                            → {{ $lista?->name ?? $act->config['lista_id'] }}
                                        @endif
                                        @if($act->type === 'add_tag' && !empty($act->config['tag_id']))
                                            @php $tag = $tags->firstWhere('id', $act->config['tag_id']); @endphp
                                            → {{ $tag?->name ?? $act->config['tag_id'] }}
                                        @endif
                                        @if($act->type === 'wait_delay' && !empty($act->config['minutes']))
                                            {{ __('Aguardar') }} {{ $act->config['minutes'] }} min
                                        @endif
                                    </p>
                                    <span class="mt-1 inline-flex items-center text-xs text-gray-400 group-hover:text-amber-600 transition-colors">
                                        {{ __('Editar ações') }}
                                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </span>
                                </div>
                            </div>
                        </a>
                        @if(!$loop->last)
                            <div class="w-0.5 h-6 bg-amber-200 my-1 rounded-full"></div>
                        @endif
                    @empty
                        <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'action']) }}" class="journey-card journey-card-action journey-card-empty group block w-full max-w-md border-2 border-dashed border-gray-300 hover:border-amber-400 hover:bg-amber-50/50 transition-colors">
                            <div class="flex items-center justify-center gap-3 py-6 text-gray-500 group-hover:text-amber-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                <span class="font-medium">{{ __('Nenhuma ação ainda. Clique para adicionar.') }}</span>
                            </div>
                        </a>
                    @endforelse
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-gray-200 text-center text-sm text-gray-500 space-y-1">
                <p>{{ __('Cron de automação jornada:') }}</p>
                <p><code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">php artisan automations:run-jornada</code></p>
                <p class="mt-2">{{ __('Ou por URL (use o mesmo token dos posts agendados: SCHEDULED_POSTS_CRON_TOKEN):') }} <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs break-all">{{ url()->route('automacao.jornada.cron') }}?token=...</code></p>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        .journey-card {
            @apply rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200;
            @apply hover:shadow-md hover:border-gray-300;
        }
        .journey-card-trigger:hover { border-color: rgba(46, 204, 113, 0.4); box-shadow: 0 4px 14px rgba(46, 204, 113, 0.15); }
        .journey-card-condition:hover { border-color: rgba(99, 102, 241, 0.4); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.15); }
        .journey-card-action:hover { border-color: rgba(245, 158, 11, 0.4); box-shadow: 0 4px 14px rgba(245, 158, 11, 0.15); }
    </style>
    @endpush
</x-app-layout>
