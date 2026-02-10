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
                                <div class="h-0.5 w-8 rounded-full {{ $sc['bar'] }} mb-1.5"></div>
                                <p class="text-xs font-medium text-gray-600 truncate">{{ $stage->name }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 funnel-stage-count" data-stage-id="{{ $stage->id }}" data-lead-label="{{ __('lead') }}" data-leads-label="{{ __('leads') }}">{{ $stageLeadCount }} {{ $stageLeadCount === 1 ? __('lead') : __('leads') }}</p>
                            </div>
                        <div class="funnel-stage-cards p-2 flex-1 overflow-y-auto space-y-2 min-h-0">
                            @foreach($stage->leads as $lead)
                                <div class="funnel-lead-card bg-white rounded-md border border-gray-100 p-2.5 text-sm shadow-sm cursor-grab active:cursor-grabbing select-none" draggable="true" data-move-url="{{ route('funis.leads.move', [$funnel, $lead]) }}" data-stage-id="{{ $stage->id }}">
                                    <div class="flex justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-medium text-gray-800 truncate text-sm">{{ $lead->name }}</p>
                                            @if($lead->title)
                                                <p class="text-gray-500 truncate text-xs">{{ $lead->title }}</p>
                                            @endif
                                            @if($lead->value > 0)
                                                <p class="text-gray-700 text-xs mt-0.5">R$ {{ number_format((float) $lead->value, 2, ',', '.') }}</p>
                                            @endif
                                            @if($lead->due_date)
                                                <p class="text-gray-400 text-xs mt-0.5">{{ $lead->due_date->format('d/m/Y') }}</p>
                                            @endif
                                        </div>
                                        <form id="form-destroy-lead-{{ $lead->id }}" action="{{ route('funis.leads.destroy', [$funnel, $lead]) }}" method="POST" class="shrink-0" data-confirm-message="{{ __('Remover lead?') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-confirm', { detail: { name: 'confirm-modal', formId: 'form-destroy-lead-{{ $lead->id }}' } }))" class="text-gray-300 hover:text-red-500 p-0.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                    <form action="{{ route('funis.leads.move', [$funnel, $lead]) }}" method="POST" class="mt-2 pt-2 border-t border-gray-50">
                                        @csrf
                                        <select name="funnel_stage_id" class="block w-full text-xs rounded border-gray-100 focus:border-gray-200 focus:ring-0 py-1" onchange="this.form.submit()">
                                            @foreach($funnel->stages as $s)
                                                <option value="{{ $s->id }}" {{ $s->id == $stage->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
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

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var draggedCard = null;
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
