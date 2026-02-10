@php
    $stageColors = [
        'yellow' => ['bg' => 'bg-amber-50', 'bar' => 'bg-amber-400'],
        'purple' => ['bg' => 'bg-purple-50', 'bar' => 'bg-purple-400'],
        'green' => ['bg' => 'bg-emerald-50', 'bar' => 'bg-emerald-400'],
        'blue' => ['bg' => 'bg-blue-50', 'bar' => 'bg-blue-400'],
        'gray' => ['bg' => 'bg-gray-50', 'bar' => 'bg-gray-400'],
        'red' => ['bg' => 'bg-red-50', 'bar' => 'bg-red-400'],
        'indigo' => ['bg' => 'bg-indigo-50', 'bar' => 'bg-indigo-400'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <a href="{{ route('funis.index') }}" class="text-gray-400 hover:text-gray-600 shrink-0" title="{{ __('Lista de funis') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="font-medium text-lg text-gray-800 truncate">{{ $funnel->name }}</h2>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs text-gray-500">{{ $funnel->leads_count }} leads · R$ {{ number_format((float) ($funnel->leads_sum_value ?? 0), 2, ',', '.') }}</span>
                <a href="{{ route('funis.edit', $funnel) }}" class="text-xs text-gray-500 hover:text-gray-700">{{ __('Editar funil') }}</a>
                <div class="flex items-center gap-1.5">
                    <button type="button" onclick="document.getElementById('colunas-panel').classList.toggle('hidden')" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-md hover:bg-gray-50" title="{{ __('Colunas') }}">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        {{ __('Editar') }}
                    </button>
                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'novo-lead-modal' }))" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-white bg-brand-600 rounded-md hover:bg-brand-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        {{ __('Novo lead') }}
                    </button>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <p class="text-sm text-emerald-600 mb-3">{{ session('success') }}</p>
            @endif
            @if(session('error'))
                <p class="text-sm text-red-600 mb-3">{{ session('error') }}</p>
            @endif

            {{-- Painel Editar colunas (abre pelo botão Editar) --}}
            <div id="colunas-panel" class="hidden mb-4 p-4 bg-gray-50/80 rounded-lg border border-gray-100">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">{{ __('Colunas do funil') }}</p>
                <div class="space-y-3">
                    @foreach($funnel->stages as $stage)
                        <div class="flex items-center gap-2 flex-wrap">
                            <form action="{{ route('funis.stages.update', [$funnel, $stage]) }}" method="POST" class="flex items-center gap-2 flex-1 min-w-0">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ old('name', $stage->name) }}" required class="text-sm w-40 max-w-full rounded border-gray-200 focus:border-gray-300 focus:ring-0" placeholder="{{ __('Nome') }}" />
                                <select name="color" class="text-sm rounded border-gray-200 focus:ring-0 text-gray-600">
                                    @foreach(array_keys($stageColors) as $c)
                                        <option value="{{ $c }}" {{ ($stage->color ?? 'gray') === $c ? 'selected' : '' }}>{{ ucfirst($c) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-xs text-gray-500 hover:text-gray-700">{{ __('Salvar') }}</button>
                            </form>
                            @if($funnel->stages->count() > 1)
                                <form id="form-destroy-stage-{{ $stage->id }}" action="{{ route('funis.stages.destroy', [$funnel, $stage]) }}" method="POST" class="inline" data-confirm-message="{{ __('Remover esta coluna? Os leads vão para a primeira.') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-confirm', { detail: { name: 'confirm-modal', formId: 'form-destroy-stage-{{ $stage->id }}' } }))" class="text-gray-400 hover:text-red-500 p-1" title="{{ __('Remover') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
                <form action="{{ route('funis.stages.store', $funnel) }}" method="POST" class="mt-3 pt-3 border-t border-gray-200 flex items-center gap-2 flex-wrap">
                    @csrf
                    <input type="text" name="name" required class="text-sm w-40 rounded border-gray-200 focus:border-gray-300 focus:ring-0" placeholder="{{ __('Nova coluna') }}" />
                    <select name="color" class="text-sm rounded border-gray-200 focus:ring-0 text-gray-600">
                        @foreach(array_keys($stageColors) as $c)
                            <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="text-xs font-medium text-gray-600 hover:text-gray-800">{{ __('Adicionar coluna') }}</button>
                </form>
            </div>

            {{-- Modal Novo lead (lista, tag, combinação ou um só) --}}
            <x-modal name="novo-lead-modal" :show="false" maxWidth="lg">
                <div class="p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('Novo lead') }}</h3>

                    {{-- Em massa: lista(s), tag(s), combinação --}}
                    <form action="{{ route('funis.leads.bulk', $funnel) }}" method="POST" class="mb-6">
                        @csrf
                        <p class="text-xs text-gray-500 mb-3">{{ __('Adicionar vários: escolha lista(s) e/ou tag(s).') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">{{ __('Estágio') }}</label>
                                <select name="funnel_stage_id" required class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0">
                                    @foreach($funnel->stages as $s)
                                        <option value="{{ $s->id }}" {{ old('funnel_stage_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">{{ __('Lista(s)') }}</label>
                                <select name="list_ids[]" multiple class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0 h-20">
                                    @foreach($listas as $lista)
                                        <option value="{{ $lista->id }}" {{ in_array($lista->id, old('list_ids', [])) ? 'selected' : '' }}>{{ $lista->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-400 mt-0.5">{{ __('Segure Ctrl para várias') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">{{ __('Tag(s)') }}</label>
                                <select name="tag_ids[]" multiple class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0 h-20">
                                    @foreach($tags as $tag)
                                        <option value="{{ $tag->id }}" {{ in_array($tag->id, old('tag_ids', [])) ? 'selected' : '' }}>{{ $tag->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-400 mt-0.5">{{ __('Segure Ctrl para várias') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">{{ __('Quando tiver lista e tag') }}</label>
                                <div class="flex gap-4 pt-1">
                                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                                        <input type="radio" name="logic" value="or" {{ old('logic', 'or') === 'or' ? 'checked' : '' }} />
                                        {{ __('OU') }} <span class="text-gray-400 text-xs">(lista ou tag)</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600">
                                        <input type="radio" name="logic" value="and" {{ old('logic') === 'and' ? 'checked' : '' }} />
                                        {{ __('E') }} <span class="text-gray-400 text-xs">(lista e tag)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-brand-600 rounded hover:bg-brand-700">{{ __('Adicionar leads') }}</button>
                        </div>
                    </form>

                    <div class="border-t border-gray-100 pt-4">
                        <p class="text-xs text-gray-500 mb-3">{{ __('Ou adicionar um lead só:') }}</p>
                        <form action="{{ route('funis.leads.store', $funnel) }}" method="POST" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 items-end">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">{{ __('Estágio') }}</label>
                                    <select name="funnel_stage_id" required class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0">
                                        @foreach($funnel->stages as $s)
                                            <option value="{{ $s->id }}" {{ old('funnel_stage_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">{{ __('Nome') }}</label>
                                    <input type="text" name="name" value="{{ old('name') }}" required class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0" placeholder="{{ __('Nome') }}" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">{{ __('Serviço') }}</label>
                                    <input type="text" name="title" value="{{ old('title') }}" class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0" placeholder="{{ __('Opcional') }}" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">{{ __('Valor') }}</label>
                                    <input type="number" name="value" step="0.01" min="0" value="{{ old('value') }}" class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0" placeholder="0" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">{{ __('Contato') }}</label>
                                    <select name="contact_id" class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0">
                                        <option value="">{{ __('—') }}</option>
                                        @foreach($contacts as $c)
                                            <option value="{{ $c->id }}" {{ old('contact_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-brand-600 rounded hover:bg-brand-700">{{ __('Adicionar') }}</button>
                                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'novo-lead-modal' }))" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700">{{ __('Fechar') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </x-modal>

            {{-- Kanban: máx 5 colunas + metade da 6ª, scroll lateral --}}
            @php
                $colWidth = 220;
                $colGap = 12;
                $visibleColumns = 5.5;
                $boardMaxWidth = (int) ($visibleColumns * $colWidth + ($visibleColumns - 1) * $colGap);
            @endphp
            <div class="overflow-x-auto pb-4 -mx-1 px-1 mx-auto w-full" style="max-width: {{ $boardMaxWidth }}px;">
                <div class="grid gap-3 min-h-[360px] shrink-0" style="grid-template-columns: repeat({{ $funnel->stages->count() }}, {{ $colWidth }}px); min-width: min-content;">
                    @foreach($funnel->stages as $stage)
                        @php
                            $sc = $stageColors[$stage->color ?? 'gray'] ?? $stageColors['gray'];
                            $stageLeadCount = $stage->leads->count();
                        @endphp
                        <div class="funnel-drop-zone flex flex-col rounded-lg border border-gray-200 {{ $sc['bg'] }} transition-colors flex-shrink-0" style="min-height: 320px; width: {{ $colWidth }}px;" data-stage-id="{{ $stage->id }}">
                            <div class="px-3 py-2 border-b border-gray-200/80">
                                <div class="flex items-center justify-between gap-1 mb-1.5">
                                    <div class="h-0.5 w-8 rounded-full {{ $sc['bar'] }}"></div>
                                    <button type="button" class="stage-automation-btn p-1 rounded {{ $stage->automation_id ? 'text-blue-600 hover:bg-blue-50' : 'text-red-500 hover:bg-red-50' }}" title="{{ $stage->automation_id ? __('Automação configurada') : __('Sem automação') }}" data-stage-id="{{ $stage->id }}" data-stage-name="{{ e($stage->name) }}" data-automation-id="{{ $stage->automation_id ?? '' }}" data-update-url="{{ route('funis.stages.automation.update', [$funnel, $stage]) }}" data-run-url="{{ route('funis.stages.automation.run', [$funnel, $stage]) }}" data-contacts-url="{{ route('funis.stages.contacts', [$funnel, $stage]) }}" data-send-message-url="{{ route('funis.stages.send-message', [$funnel, $stage]) }}" data-automation-name="{{ $stage->automation ? e($stage->automation->name) : '' }}" data-automation-actions-count="{{ $stage->automation ? $stage->automation->actions->count() : 0 }}" data-automation-trigger-label="{{ $stage->automation && $stage->automation->trigger ? (\App\Models\Automation::triggerTypes()[$stage->automation->trigger->type] ?? $stage->automation->trigger->type) : '' }}" data-automation-edit-url="{{ $stage->automation ? route('automacao.edit', $stage->automation) : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </button>
                                </div>
                                <p class="text-xs font-medium text-gray-600 truncate">{{ $stage->name }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 funnel-stage-count" data-stage-id="{{ $stage->id }}" data-lead-label="{{ __('lead') }}" data-leads-label="{{ __('leads') }}">{{ $stageLeadCount }} {{ $stageLeadCount === 1 ? __('lead') : __('leads') }}</p>
                            </div>
                        <div class="funnel-stage-cards p-2 flex-1 overflow-y-auto space-y-2 min-h-0">
                            @foreach($stage->leads as $lead)
                                @php
                                    $displayName = $lead->name;
                                    if ($lead->contact) {
                                        $displayName = trim($lead->contact->name) !== '' ? $lead->contact->name : $lead->contact->phone;
                                    }
                                    $initial = mb_strtoupper(mb_substr($displayName, 0, 1)) ?: '?';
                                    $tempoParado = $lead->updated_at->startOfDay()->diffInDays(now()->startOfDay());
                                    $tempoParadoText = $tempoParado === 0 ? __('Hoje') : ($tempoParado === 1 ? '1d' : $tempoParado . 'd');
                                    $contactTags = $lead->contact ? $lead->contact->tags : collect();
                                    $contactListas = $lead->contact ? $lead->contact->listas : collect();
                                    $msgStatus = ($lead->contact_id && isset($stageMessageStatus[$stage->id][$lead->contact_id])) ? $stageMessageStatus[$stage->id][$lead->contact_id] : null;
                                @endphp
                                <div class="funnel-lead-card group bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow hover:border-gray-200/80 cursor-grab active:cursor-grabbing select-none transition-shadow" draggable="true" data-move-url="{{ route('funis.leads.move', [$funnel, $lead]) }}" data-stage-id="{{ $stage->id }}">
                                    <div class="p-2.5">
                                        <div class="flex gap-2 items-center">
                                            <div class="shrink-0 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 font-medium text-xs" title="{{ $displayName }}">{{ $initial }}</div>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-gray-900 truncate text-xs leading-tight">{{ $displayName }}</p>
                                                @if($lead->title)
                                                    <p class="text-gray-500 truncate text-xs leading-tight mt-0.5">{{ $lead->title }}</p>
                                                @endif
                                            </div>
                                            <span class="text-[10px] text-gray-400 shrink-0">{{ $lead->updated_at->format('d/m/Y') }}</span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                            @if($lead->value > 0)
                                                <span class="text-xs font-medium text-gray-700">R$ {{ number_format((float) $lead->value, 2, ',', '.') }}</span>
                                            @endif
                                            @foreach($contactTags as $tag)
                                                <span class="inline-flex px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-gray-100 text-gray-500">{{ $tag->name }}</span>
                                            @endforeach
                                            @foreach($contactListas as $lista)
                                                <span class="inline-flex px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-gray-100 text-gray-400">{{ $lista->name }}</span>
                                            @endforeach
                                        </div>
                                        @if($msgStatus)
                                            <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                                @if($msgStatus === 'responded')
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800" title="{{ __('Contato respondeu à mensagem da automação') }}">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                                        {{ __('Respondido') }}
                                                    </span>
                                                @elseif($msgStatus === 'read')
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800" title="{{ __('Mensagem lida') }}">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                        {{ __('Lido') }}
                                                    </span>
                                                @elseif($msgStatus === 'delivered')
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700" title="{{ __('Entregue (recebida, não aberta)') }}">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                        {{ __('Entregue') }}
                                                    </span>
                                                @elseif($msgStatus === 'sent')
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800" title="{{ __('Enviado, aguardando entrega') }}">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                                        {{ __('Enviado') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-800" title="{{ __('Não chegou ao destinatário') }}">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        {{ __('Não chegou') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="flex items-center justify-between gap-2 mt-2 pt-2 border-t border-gray-100" x-data="{ open: false }">
                                            <span class="text-[10px] text-amber-600 flex items-center gap-1 shrink-0" title="{{ __('Tempo nesta etapa') }}">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                                {{ $tempoParadoText }}
                                            </span>
                                            <div class="relative shrink-0">
                                                <button type="button" @click="open = !open" class="p-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100" title="{{ __('Ações') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </button>
                                                <div x-show="open" x-cloak x-transition @click.outside="open = false" class="absolute right-0 top-full mt-1 py-1 bg-white rounded-lg shadow-lg border border-gray-200 z-20 min-w-[160px]">
                                                    <p class="px-3 py-1.5 text-[10px] font-medium text-gray-400 uppercase tracking-wide">{{ __('Mover para') }}</p>
                                                    @foreach($funnel->stages as $s)
                                                        <form action="{{ route('funis.leads.move', [$funnel, $lead]) }}" method="POST" class="block">
                                                            @csrf
                                                            <input type="hidden" name="funnel_stage_id" value="{{ $s->id }}">
                                                            <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 {{ $s->id == $stage->id ? 'font-medium text-brand-600' : '' }}">
                                                                {{ $s->name }}
                                                            </button>
                                                        </form>
                                                    @endforeach
                                                    <div class="border-t border-gray-100 my-1"></div>
                                                    <button type="button" class="edit-lead-btn w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2" data-lead-id="{{ $lead->id }}" data-name="{{ e($lead->name) }}" data-title="{{ e($lead->title ?? '') }}" data-value="{{ $lead->value }}" data-contact-id="{{ $lead->contact_id ?? '' }}" data-update-url="{{ route('funis.leads.update', [$funnel, $lead]) }}" @click="open = false">
                                                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        {{ __('Editar') }}
                                                    </button>
                                                    <form id="form-destroy-lead-{{ $lead->id }}" action="{{ route('funis.leads.destroy', [$funnel, $lead]) }}" method="POST" class="block" data-confirm-message="{{ __('Remover lead?') }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" @click="open = false; window.dispatchEvent(new CustomEvent('open-confirm', { detail: { name: 'confirm-modal', formId: 'form-destroy-lead-{{ $lead->id }}' } }))" class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                            {{ __('Remover') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                </div>
            </div>
        </div>
    </div>

    <x-confirm-modal name="confirm-modal" />

    {{-- Modal Automação da coluna --}}
    <x-modal name="stage-automation-modal" :show="false" maxWidth="5xl">
        <div class="flex flex-col max-h-[85vh]">
            <input type="hidden" id="stage-automation-edit-url" value="">
            <input type="hidden" id="stage-contacts-url" value="">
            <input type="hidden" id="stage-send-message-url" value="">
            {{-- Painel resumo: mensagem para coluna + vincular automação + configurado --}}
            <div id="stage-automation-resumo" class="p-6 overflow-y-auto">
                <h3 class="text-sm font-semibold text-gray-800 mb-1" id="stage-automation-modal-title">{{ __('Automação da coluna') }}</h3>
                <p class="text-xs text-gray-500 mb-4" id="stage-automation-modal-subtitle"></p>

                {{-- Mensagem para a coluna (imagem + texto + agendar) --}}
                <div class="mb-6 pb-4 border-b border-gray-200">
                    <p class="text-xs font-medium text-gray-700 mb-3">{{ __('Mensagem para a coluna') }}</p>
                    <p class="text-xs text-gray-500 mb-3">{{ __('Só os contatos desta coluna receberão. Ao disparar ou agendar, você verá a lista de destinatários.') }}</p>
                    <form id="form-stage-send-message" method="POST" action="" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="send_now" id="stage-send-now-value" value="0">
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-0.5">{{ __('Data (para agendar)') }}</label>
                                <input type="date" name="scheduled_date" id="stage-scheduled-date" min="{{ date('Y-m-d') }}" class="block w-full text-sm rounded-md border-gray-200 focus:border-brand-400 focus:ring-1 focus:ring-brand-400/30">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-0.5">{{ __('Horário') }}</label>
                                <input type="time" name="scheduled_time" id="stage-scheduled-time" class="block w-full text-sm rounded-md border-gray-200 focus:border-brand-400 focus:ring-1 focus:ring-brand-400/30">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs text-gray-500 mb-0.5">{{ __('Imagem (opcional)') }}</label>
                            <input type="file" name="image" id="stage-message-image" accept="image/jpeg,image/png,image/gif,image/webp" class="block w-full text-sm text-gray-600 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-brand-50 file:text-brand-700 file:text-xs">
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs text-gray-500 mb-0.5">{{ __('Texto da mensagem') }}</label>
                            <textarea name="message" id="stage-message-text" rows="4" class="block w-full text-sm rounded-md border-gray-200 focus:border-brand-400 focus:ring-1 focus:ring-brand-400/30" placeholder="{{ __('Digite o texto. Com imagem, vira legenda.') }}"></textarea>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="stage-btn-disparar" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">{{ __('Disparar agora') }}</button>
                            <button type="button" id="stage-btn-agendar" class="px-3 py-1.5 text-xs font-medium text-white bg-brand-600 rounded hover:bg-brand-700">{{ __('Agendar') }}</button>
                        </div>
                    </form>
                </div>

                {{-- Preview: contatos que receberão (mostrado antes de confirmar) --}}
                <div id="stage-automation-preview" class="hidden mb-6 pb-4 border-b border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-medium text-gray-700">{{ __('Estes contatos receberão a mensagem') }}<span id="stage-preview-when" class="text-gray-500 font-normal"></span></p>
                        <button type="button" id="stage-preview-back" class="text-xs text-gray-500 hover:text-gray-700">{{ __('← Voltar') }}</button>
                    </div>
                    <ul id="stage-preview-contacts" class="max-h-40 overflow-y-auto rounded border border-gray-100 bg-gray-50/50 p-2 space-y-1 text-xs text-gray-700"></ul>
                    <p class="text-xs text-gray-500 mt-1" id="stage-preview-count"></p>
                    <div class="mt-2">
                        <button type="button" id="stage-preview-confirm-btn" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded"></button>
                    </div>
                </div>

                {{-- Vincular automação existente --}}
                <form id="form-stage-automation-assign" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="flex gap-2 items-end mb-4">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 mb-0.5">{{ __('Vincular automação') }}</label>
                            <select name="automation_id" id="stage-automation-select" class="block w-full text-sm rounded-md border-gray-200 focus:border-brand-400 focus:ring-1 focus:ring-brand-400/30">
                                <option value="">{{ __('— Nenhuma —') }}</option>
                                @foreach($automations as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}{{ $a->is_active ? '' : ' (' . __('pausada') . ')' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-brand-600 rounded hover:bg-brand-700">{{ __('Salvar') }}</button>
                    </div>
                </form>
                <div id="stage-automation-configured" class="hidden border-t border-gray-100 pt-4">
                    <p class="text-xs font-medium text-gray-700 mb-1">{{ __('Configurado') }}</p>
                    <p class="text-xs text-gray-600 mb-2" id="stage-automation-config-summary"></p>
                    <div class="flex flex-wrap gap-2 items-center">
                        <button type="button" id="stage-automation-config-btn" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-brand-600 rounded hover:bg-brand-700">{{ __('Configurar automação') }}</button>
                        <a id="stage-automation-edit-link" href="#" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 rounded">{{ __('Abrir em nova aba') }}</a>
                        <form id="form-stage-automation-run" method="POST" action="" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700">{{ __('Disparar agora') }}</button>
                        </form>
                        <a href="{{ route('automacao.agendamentos.index') }}" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded">{{ __('Agendar') }}</a>
                    </div>
                </div>
            </div>
            {{-- Painel configurar: iframe com a edição da automação --}}
            <div id="stage-automation-configurar" class="hidden flex-1 flex flex-col min-h-0 border-t border-gray-200">
                <div class="flex items-center gap-2 px-4 py-2 bg-gray-50 border-b border-gray-200">
                    <button type="button" id="stage-automation-back-resumo" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        {{ __('Voltar ao resumo') }}
                    </button>
                </div>
                <div class="flex-1 min-h-0 p-2">
                    <iframe id="stage-automation-iframe" class="w-full border border-gray-200 rounded-lg bg-white" style="height: 65vh;" title="{{ __('Configurar automação') }}"></iframe>
                </div>
            </div>
        </div>
    </x-modal>

    {{-- Modal Editar lead --}}
    <x-modal name="edit-lead-modal" :show="false" maxWidth="sm">
        <div class="p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('Editar lead') }}</h3>
            <form id="form-edit-lead" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">{{ __('Nome') }}</label>
                        <input type="text" name="name" id="edit-lead-name" required class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">{{ __('Serviço / negócio') }}</label>
                        <input type="text" name="title" id="edit-lead-title" class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">{{ __('Valor (R$)') }}</label>
                        <input type="number" name="value" id="edit-lead-value" step="0.01" min="0" class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-0.5">{{ __('Contato') }}</label>
                        <select name="contact_id" id="edit-lead-contact" class="block w-full text-sm rounded border-gray-200 focus:border-gray-300 focus:ring-0">
                            <option value="">{{ __('— Nenhum —') }}</option>
                            @foreach($contacts as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} @if($c->phone)({{ $c->phone }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'edit-lead-modal' }))" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700">{{ __('Cancelar') }}</button>
                    <button type="submit" class="px-3 py-1.5 text-xs font-medium text-white bg-brand-600 rounded hover:bg-brand-700">{{ __('Salvar') }}</button>
                </div>
            </form>
        </div>
    </x-modal>

    @push('scripts')
    <script>
    function openEditLeadModal(btn) {
        var el = btn.target && btn.target.closest ? btn.target.closest('.edit-lead-btn') : btn;
        if (!el || !el.dataset.updateUrl) return;
        var form = document.getElementById('form-edit-lead');
        if (!form) return;
        form.action = el.dataset.updateUrl;
        document.getElementById('edit-lead-name').value = el.dataset.name || '';
        document.getElementById('edit-lead-title').value = el.dataset.title || '';
        document.getElementById('edit-lead-value').value = el.dataset.value || '';
        var contactSelect = document.getElementById('edit-lead-contact');
        if (contactSelect) {
            contactSelect.value = el.dataset.contactId || '';
        }
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'edit-lead-modal' }));
    }
    function openStageAutomationModal(btn) {
        var el = btn.target && btn.target.closest ? btn.target.closest('.stage-automation-btn') : btn;
        if (!el || !el.dataset.updateUrl) return;
        var formAssign = document.getElementById('form-stage-automation-assign');
        var formRun = document.getElementById('form-stage-automation-run');
        var titleEl = document.getElementById('stage-automation-modal-title');
        var subtitleEl = document.getElementById('stage-automation-modal-subtitle');
        var selectEl = document.getElementById('stage-automation-select');
        var configuredEl = document.getElementById('stage-automation-configured');
        var summaryEl = document.getElementById('stage-automation-config-summary');
        var editLink = document.getElementById('stage-automation-edit-link');
        var editUrlInput = document.getElementById('stage-automation-edit-url');
        var resumoEl = document.getElementById('stage-automation-resumo');
        var configurarEl = document.getElementById('stage-automation-configurar');
        var iframeEl = document.getElementById('stage-automation-iframe');
        if (!formAssign || !formRun) return;
        formAssign.action = el.dataset.updateUrl;
        formRun.action = el.dataset.runUrl;
        if (editUrlInput) editUrlInput.value = el.dataset.automationEditUrl || '';
        var contactsUrlEl = document.getElementById('stage-contacts-url');
        var sendMessageUrlEl = document.getElementById('stage-send-message-url');
        if (contactsUrlEl) contactsUrlEl.value = el.dataset.contactsUrl || '';
        if (sendMessageUrlEl) sendMessageUrlEl.value = el.dataset.sendMessageUrl || '';
        if (titleEl) titleEl.textContent = '{{ __("Automação") }}: ' + (el.dataset.stageName || '');
        if (subtitleEl) subtitleEl.textContent = '{{ __("Só os contatos desta coluna serão considerados ao disparar.") }}';
        if (selectEl) selectEl.value = el.dataset.automationId || '';
        if (el.dataset.automationId && el.dataset.automationName) {
            configuredEl.classList.remove('hidden');
            if (summaryEl) summaryEl.textContent = (el.dataset.automationName || '') + (el.dataset.automationTriggerLabel ? ' · ' + el.dataset.automationTriggerLabel : '') + (el.dataset.automationActionsCount ? ' · ' + el.dataset.automationActionsCount + ' {{ __("ação(ões)") }}' : '');
            if (editLink) editLink.href = el.dataset.automationEditUrl || ('/automacao/' + (el.dataset.automationId || '') + '/edit');
        } else {
            configuredEl.classList.add('hidden');
        }
        if (resumoEl) resumoEl.classList.remove('hidden');
        if (configurarEl) configurarEl.classList.add('hidden');
        if (iframeEl) iframeEl.src = '';
        var previewEl = document.getElementById('stage-automation-preview');
        if (previewEl) previewEl.classList.add('hidden');
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'stage-automation-modal' }));
    }
    function showStageAutomationConfigurar() {
        var url = document.getElementById('stage-automation-edit-url')?.value;
        if (!url) return;
        var resumoEl = document.getElementById('stage-automation-resumo');
        var configurarEl = document.getElementById('stage-automation-configurar');
        var iframeEl = document.getElementById('stage-automation-iframe');
        if (resumoEl) resumoEl.classList.add('hidden');
        if (configurarEl) configurarEl.classList.remove('hidden');
        if (iframeEl) iframeEl.src = url;
    }
    function showStageAutomationResumo() {
        var resumoEl = document.getElementById('stage-automation-resumo');
        var configurarEl = document.getElementById('stage-automation-configurar');
        var iframeEl = document.getElementById('stage-automation-iframe');
        if (configurarEl) configurarEl.classList.add('hidden');
        if (resumoEl) resumoEl.classList.remove('hidden');
        if (iframeEl) iframeEl.src = '';
    }
    document.addEventListener('DOMContentLoaded', function() {
        var draggedCard = null;
        document.querySelectorAll('.edit-lead-btn').forEach(function(btn) {
            btn.addEventListener('click', openEditLeadModal);
        });
        document.querySelectorAll('.stage-automation-btn').forEach(function(btn) {
            btn.addEventListener('click', openStageAutomationModal);
        });
        var configBtn = document.getElementById('stage-automation-config-btn');
        if (configBtn) configBtn.addEventListener('click', showStageAutomationConfigurar);
        var backResumoBtn = document.getElementById('stage-automation-back-resumo');
        if (backResumoBtn) backResumoBtn.addEventListener('click', showStageAutomationResumo);

        (function() {
            var formSend = document.getElementById('form-stage-send-message');
            var contactsUrlInput = document.getElementById('stage-contacts-url');
            var sendMessageUrlInput = document.getElementById('stage-send-message-url');
            var sendNowInput = document.getElementById('stage-send-now-value');
            var previewPanel = document.getElementById('stage-automation-preview');
            var previewContacts = document.getElementById('stage-preview-contacts');
            var previewWhen = document.getElementById('stage-preview-when');
            var previewCount = document.getElementById('stage-preview-count');
            var previewBack = document.getElementById('stage-preview-back');
            var previewConfirmBtn = document.getElementById('stage-preview-confirm-btn');
            var msgSection = formSend && formSend.closest('.border-b');
            var isDispararMode = false;

            function showPreview(contacts, whenText, confirmLabel) {
                if (!previewPanel || !previewContacts) return;
                previewContacts.innerHTML = '';
                contacts.forEach(function(c) {
                    var li = document.createElement('li');
                    li.className = 'flex items-center gap-2';
                    li.textContent = (c.name || c.phone || __('Contato')) + (c.phone ? ' — ' + c.phone : '');
                    previewContacts.appendChild(li);
                });
                if (previewWhen) previewWhen.textContent = whenText;
                if (previewCount) previewCount.textContent = contacts.length === 1 ? '1 {{ __("contato") }}' : contacts.length + ' {{ __("contatos") }}';
                if (previewConfirmBtn) previewConfirmBtn.textContent = confirmLabel;
                if (msgSection) msgSection.classList.add('hidden');
                previewPanel.classList.remove('hidden');
            }
            function hidePreview() {
                if (previewPanel) previewPanel.classList.add('hidden');
                if (msgSection) msgSection.classList.remove('hidden');
            }
            function validateMessage() {
                var text = (document.getElementById('stage-message-text') && document.getElementById('stage-message-text').value || '').trim();
                var file = document.getElementById('stage-message-image');
                var hasFile = file && file.files && file.files.length > 0;
                return text !== '' || hasFile;
            }
            function validateSchedule() {
                var d = document.getElementById('stage-scheduled-date') && document.getElementById('stage-scheduled-date').value;
                var t = document.getElementById('stage-scheduled-time') && document.getElementById('stage-scheduled-time').value;
                return d && t;
            }

            document.getElementById('stage-btn-disparar') && document.getElementById('stage-btn-disparar').addEventListener('click', function() {
                if (!validateMessage()) { alert('{{ __("Informe o texto da mensagem ou envie uma imagem.") }}'); return; }
                var url = contactsUrlInput && contactsUrlInput.value;
                if (!url) return;
                isDispararMode = true;
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var contacts = data.contacts || [];
                        if (contacts.length === 0) { alert('{{ __("Nenhum lead desta coluna tem contato vinculado.") }}'); return; }
                        showPreview(contacts, '', '{{ __("Confirmar e disparar") }}');
                    })
                    .catch(function() { alert('{{ __("Erro ao carregar contatos.") }}'); });
            });

            document.getElementById('stage-btn-agendar') && document.getElementById('stage-btn-agendar').addEventListener('click', function() {
                if (!validateMessage()) { alert('{{ __("Informe o texto da mensagem ou envie uma imagem.") }}'); return; }
                if (!validateSchedule()) { alert('{{ __("Informe data e horário para agendar.") }}'); return; }
                var url = contactsUrlInput && contactsUrlInput.value;
                if (!url) return;
                isDispararMode = false;
                var d = document.getElementById('stage-scheduled-date').value;
                var t = document.getElementById('stage-scheduled-time').value;
                var whenStr = ' {{ __("em") }} ' + d + ' ' + t;
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var contacts = data.contacts || [];
                        if (contacts.length === 0) { alert('{{ __("Nenhum lead desta coluna tem contato vinculado.") }}'); return; }
                        showPreview(contacts, whenStr, '{{ __("Confirmar agendamento") }}');
                    })
                    .catch(function() { alert('{{ __("Erro ao carregar contatos.") }}'); });
            });

            previewBack && previewBack.addEventListener('click', hidePreview);

            previewConfirmBtn && previewConfirmBtn.addEventListener('click', function() {
                if (!formSend || !sendMessageUrlInput) return;
                formSend.action = sendMessageUrlInput.value;
                sendNowInput.value = isDispararMode ? '1' : '0';
                formSend.submit();
            });
        })();

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrf) return;

        document.querySelectorAll('.funnel-lead-card').forEach(function(card) {
            card.addEventListener('dragstart', function(e) {
                draggedCard = card;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('application/json', JSON.stringify({
                    moveUrl: card.dataset.moveUrl,
                    stageId: card.dataset.stageId
                }));
                e.dataTransfer.setData('text/plain', card.dataset.moveUrl);
                card.classList.add('opacity-50');
                setTimeout(function() { card.classList.add('invisible'); }, 0);
            });
            card.addEventListener('dragend', function() {
                card.classList.remove('opacity-50', 'invisible');
                draggedCard = null;
            });
        });

        document.querySelectorAll('.funnel-drop-zone').forEach(function(zone) {
            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                zone.classList.add('ring-2', 'ring-brand-400', 'ring-inset');
            });
            zone.addEventListener('dragleave', function(e) {
                if (!zone.contains(e.relatedTarget)) {
                    zone.classList.remove('ring-2', 'ring-brand-400', 'ring-inset');
                }
            });
            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                zone.classList.remove('ring-2', 'ring-brand-400', 'ring-inset');
                var data;
                try {
                    data = JSON.parse(e.dataTransfer.getData('application/json'));
                } catch (err) {
                    return;
                }
                var targetStageId = zone.dataset.stageId;
                if (!data.moveUrl || data.stageId === targetStageId) {
                    draggedCard = null;
                    return;
                }
                var card = draggedCard;
                draggedCard = null;
                if (!card) return;
                var targetContainer = zone.querySelector('.funnel-stage-cards');
                if (!targetContainer) return;
                var originalContainer = card.parentNode;
                var originalStageId = data.stageId;
                card.dataset.stageId = targetStageId;
                card.classList.remove('opacity-50', 'invisible');
                targetContainer.appendChild(card);
                var sel = card.querySelector('select[name="funnel_stage_id"]');
                if (sel) sel.value = targetStageId;
                function updateStageCount(stageId, delta) {
                    var el = document.querySelector('.funnel-stage-count[data-stage-id="' + stageId + '"]');
                    if (!el) return;
                    var m = el.textContent.match(/^(\d+)/);
                    var n = (m ? parseInt(m[1], 10) : 0) + delta;
                    var label = n === 1 ? (el.dataset.leadLabel || 'lead') : (el.dataset.leadsLabel || 'leads');
                    el.textContent = n + ' ' + label;
                }
                updateStageCount(originalStageId, -1);
                updateStageCount(targetStageId, 1);
                fetch(data.moveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        funnel_stage_id: targetStageId,
                        _token: csrf
                    })
                }).then(function(r) {
                    if (!r.ok && originalContainer) {
                        card.dataset.stageId = originalStageId;
                        originalContainer.appendChild(card);
                        if (sel) sel.value = originalStageId;
                        updateStageCount(originalStageId, 1);
                        updateStageCount(targetStageId, -1);
                    }
                });
            });
        });
    });
    </script>
    @endpush
</x-app-layout>
