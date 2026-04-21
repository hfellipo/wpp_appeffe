@php
    $stageColors = [
        'yellow' => ['bg' => 'bg-amber-500',   'light' => 'bg-amber-50',   'border' => 'border-amber-200',  'text' => 'text-amber-700',  'dot' => 'bg-amber-400'],
        'purple' => ['bg' => 'bg-purple-500',   'light' => 'bg-purple-50',  'border' => 'border-purple-200', 'text' => 'text-purple-700', 'dot' => 'bg-purple-400'],
        'green'  => ['bg' => 'bg-emerald-500',  'light' => 'bg-emerald-50', 'border' => 'border-emerald-200','text' => 'text-emerald-700','dot' => 'bg-emerald-400'],
        'blue'   => ['bg' => 'bg-blue-500',     'light' => 'bg-blue-50',    'border' => 'border-blue-200',   'text' => 'text-blue-700',   'dot' => 'bg-blue-400'],
        'gray'   => ['bg' => 'bg-gray-500',     'light' => 'bg-gray-50',    'border' => 'border-gray-200',   'text' => 'text-gray-700',   'dot' => 'bg-gray-400'],
        'red'    => ['bg' => 'bg-red-500',      'light' => 'bg-red-50',     'border' => 'border-red-200',    'text' => 'text-red-700',    'dot' => 'bg-red-400'],
        'indigo' => ['bg' => 'bg-indigo-500',   'light' => 'bg-indigo-50',  'border' => 'border-indigo-200', 'text' => 'text-indigo-700', 'dot' => 'bg-indigo-400'],
    ];
    $triggerIcons = [
        'message_status'   => '📨',
        'whatsapp_replied' => '💬',
        'specific_reply'   => '🔑',
        'tag_added'        => '🏷️',
        'list_added'       => '📋',
    ];
    $csrf = csrf_token();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2 min-w-0">
                <a href="{{ route('funis.show', $funnel) }}" class="text-gray-400 hover:text-gray-600 shrink-0" title="Kanban">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h2 class="font-semibold text-lg text-gray-800 truncate">{{ $funnel->name }}</h2>
                <span class="hidden sm:inline text-gray-300 mx-1">·</span>
                <span class="hidden sm:inline text-sm text-gray-500 font-medium">Pipeline de Disparos</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400">{{ $funnel->stages->count() }} etapas · {{ $funnel->stages->sum(fn($s) => $s->leads->count()) }} leads</span>
                <button type="button" id="deploy-all-btn"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-violet-600 rounded-lg hover:bg-violet-700 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Disparar Pipeline
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Deploy-all feedback --}}
            <div id="deploy-all-feedback" class="hidden mb-4 p-3 rounded-lg text-sm font-medium"></div>

            {{-- Pipeline steps --}}
            <div class="relative">
                @foreach($funnel->stages->sortBy('position') as $i => $stage)
                    @php
                        $color      = $stageColors[$stage->color ?? 'gray'] ?? $stageColors['gray'];
                        $leadCount  = $stage->leads->count();
                        $rulesCount = $stage->stageRules->count();
                        $disparo    = $activeDisparos[$stage->id] ?? null;
                        $isLast     = $i === $funnel->stages->count() - 1;
                        $storeRuleUrl   = route('funis.stages.rules.store',   [$funnel, $stage]);
                        $sendMsgUrl     = route('funis.stages.send-message',  [$funnel, $stage]);
                        $disparoStatUrl = route('funis.stages.disparo.status',[$funnel, $stage]);

                        // Build rules JSON for JS
                        $rulesJson = $stage->stageRules->map(function($r) use ($funnel, $stage) {
                            $extra = '';
                            if ($r->trigger_type === 'message_status' && !empty($r->trigger_config['status'])) {
                                $extra = \App\Models\FunnelStageRule::messageStatusOptions()[$r->trigger_config['status']] ?? $r->trigger_config['status'];
                            } elseif ($r->trigger_type === 'tag_added' && !empty($r->trigger_config['tag_id'])) {
                                $extra = \App\Models\Tag::find($r->trigger_config['tag_id'])?->name ?? '';
                            } elseif ($r->trigger_type === 'list_added' && !empty($r->trigger_config['lista_id'])) {
                                $extra = \App\Models\Lista::find($r->trigger_config['lista_id'])?->name ?? '';
                            }
                            return [
                                'id'                => $r->id,
                                'trigger_type'      => $r->trigger_type,
                                'trigger_label'     => \App\Models\FunnelStageRule::triggerTypes()[$r->trigger_type] ?? $r->trigger_type,
                                'trigger_extra'     => $extra,
                                'keyword'           => $r->keyword,
                                'action_type'       => $r->action_type ?? 'move',
                                'action_message'    => $r->action_message,
                                'target_stage_name' => $r->targetStage?->name ?? '',
                                'destroy_url'       => route('funis.stages.rules.destroy', [$funnel->id, $stage->id, $r->id]),
                            ];
                        })->values()->toJson();
                    @endphp

                    <div class="relative flex gap-4" data-stage-step="{{ $stage->id }}">

                        {{-- Timeline spine --}}
                        <div class="flex flex-col items-center shrink-0 w-10">
                            {{-- Step circle --}}
                            <div class="w-9 h-9 rounded-full {{ $color['bg'] }} flex items-center justify-center text-white font-bold text-sm shadow z-10 relative">
                                {{ $i + 1 }}
                            </div>
                            {{-- Connector line --}}
                            @if(!$isLast)
                                <div class="flex-1 w-0.5 bg-gray-200 mt-1 mb-0 min-h-[2rem]"></div>
                            @endif
                        </div>

                        {{-- Stage card --}}
                        <div class="flex-1 mb-6"
                             x-data="pipelineStage({
                                stageId: {{ $stage->id }},
                                storeRuleUrl: '{{ $storeRuleUrl }}',
                                sendMsgUrl:   '{{ $sendMsgUrl }}',
                                disparoStatUrl: '{{ $disparoStatUrl }}',
                                rules: {{ $rulesJson }},
                                initialDisparo: {{ $disparo ? json_encode(['id' => $disparo->id, 'status' => $disparo->status, 'sent' => $disparo->sent_count, 'failed' => $disparo->failed_count, 'total' => $disparo->total_contacts, 'percent' => $disparo->progressPercent()]) : 'null' }}
                             })">

                            {{-- Card header --}}
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $color['dot'] }} shrink-0"></span>
                                    <span class="font-semibold text-gray-800 text-sm truncate">{{ $stage->name }}</span>
                                    <span class="text-xs text-gray-400">{{ $leadCount }} lead{{ $leadCount !== 1 ? 's' : '' }}</span>
                                    @if($rulesCount > 0)
                                        <span class="inline-flex items-center gap-0.5 text-xs text-violet-600 bg-violet-50 rounded-full px-2 py-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            {{ $rulesCount }} evento{{ $rulesCount !== 1 ? 's' : '' }}
                                        </span>
                                    @endif
                                </div>
                                {{-- Disparo status badge --}}
                                <template x-if="disparo && disparo.status === 'running'">
                                    <span class="text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5 animate-pulse">⚡ Enviando</span>
                                </template>
                                <template x-if="disparo && disparo.status === 'pending'">
                                    <span class="text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5">⏳ Aguardando</span>
                                </template>
                                <template x-if="disparo && disparo.status === 'completed'">
                                    <span class="text-xs font-medium text-gray-600 bg-gray-100 border border-gray-200 rounded-full px-2 py-0.5">✅ Concluído</span>
                                </template>
                                <template x-if="disparo && disparo.status === 'scheduled'">
                                    <span class="text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-full px-2 py-0.5">📅 Agendado</span>
                                </template>
                            </div>

                            {{-- Main card body --}}
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">

                                {{-- TAB BAR --}}
                                <div class="flex border-b border-gray-100">
                                    <button type="button" @click="tab='disparo'"
                                            :class="tab==='disparo' ? 'border-b-2 border-violet-500 text-violet-700 bg-violet-50/50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                                            class="flex-1 py-2.5 text-xs font-medium transition-colors">
                                        🚀 Disparo
                                    </button>
                                    <button type="button" @click="tab='eventos'"
                                            :class="tab==='eventos' ? 'border-b-2 border-violet-500 text-violet-700 bg-violet-50/50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                                            class="flex-1 py-2.5 text-xs font-medium transition-colors">
                                        ⚡ Eventos <span x-show="rules.length > 0" class="ml-1 bg-violet-100 text-violet-600 rounded-full px-1.5 text-[10px]" x-text="rules.length"></span>
                                    </button>
                                </div>

                                {{-- TAB: DISPARO --}}
                                <div x-show="tab==='disparo'" class="p-4 space-y-3">

                                    {{-- Progress bar if active --}}
                                    <div x-show="disparo && (disparo.status === 'running' || disparo.status === 'pending')" class="mb-2">
                                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                                            <span x-text="disparo ? disparo.sent + '/' + disparo.total + ' enviados' : ''"></span>
                                            <span x-text="disparo ? disparo.percent + '%' : ''"></span>
                                        </div>
                                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-violet-500 rounded-full transition-all duration-500"
                                                 :style="'width:' + (disparo ? disparo.percent : 0) + '%'"></div>
                                        </div>
                                    </div>

                                    {{-- Message --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Mensagem</label>
                                        <textarea x-model="msg" rows="3" placeholder="Digite a mensagem para os leads desta etapa..."
                                                  class="block w-full text-sm rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40 resize-none"></textarea>
                                    </div>

                                    {{-- Image --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Imagem (opcional)</label>
                                        <div x-show="!imgPreview" class="flex items-center gap-2">
                                            <label class="cursor-pointer flex items-center gap-1.5 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                Anexar imagem
                                                <input type="file" accept="image/*" class="hidden" @change="handleImg($event)">
                                            </label>
                                        </div>
                                        <div x-show="imgPreview" class="flex items-center gap-2">
                                            <img :src="imgPreview" class="h-12 w-12 object-cover rounded border border-gray-200">
                                            <span class="text-xs text-gray-500 truncate max-w-[140px]" x-text="imgName"></span>
                                            <button type="button" @click="imgPreview=null;imgFile=null;imgName=''" class="text-xs text-red-400 hover:text-red-600">✕ Remover</button>
                                        </div>
                                    </div>

                                    {{-- Mode + Delay row --}}
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Modo de envio</label>
                                            <select x-model="mode" class="block w-full text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40">
                                                <option value="sequential">Sequencial</option>
                                                <option value="random">Aleatório</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                                Intervalo: <span class="font-semibold text-violet-600" x-text="delay === 0 ? 'imediato' : delay + 's'"></span>
                                            </label>
                                            <input type="range" min="0" max="300" step="5" x-model.number="delay"
                                                   class="w-full h-1.5 accent-violet-500">
                                        </div>
                                    </div>

                                    {{-- Schedule --}}
                                    <div>
                                        <button type="button" @click="showSched=!showSched"
                                                class="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600">
                                            <svg class="w-3.5 h-3.5 transition-transform" :class="showSched ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            <span x-text="showSched ? 'Cancelar agendamento' : 'Agendar para uma data/hora'"></span>
                                        </button>
                                        <div x-show="showSched" x-transition class="mt-2 grid grid-cols-2 gap-2">
                                            <input type="date" x-model="schedDate"
                                                   :min="new Date().toISOString().split('T')[0]"
                                                   class="text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40">
                                            <input type="time" x-model="schedTime"
                                                   class="text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40">
                                        </div>
                                    </div>

                                    {{-- Feedback --}}
                                    <div x-show="feedback" x-transition
                                         :class="feedbackOk ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700'"
                                         class="text-xs rounded-lg border px-3 py-2" x-text="feedback"></div>

                                    {{-- Dispatch button --}}
                                    <button type="button" @click="dispatch()"
                                            :disabled="loading"
                                            class="w-full flex items-center justify-center gap-1.5 py-2.5 text-sm font-semibold text-white bg-violet-600 rounded-lg hover:bg-violet-700 disabled:opacity-50 transition-colors">
                                        <svg class="w-4 h-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <template x-if="!loading"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></template>
                                            <template x-if="loading"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></template>
                                        </svg>
                                        <span x-text="loading ? 'Enviando...' : (showSched && schedDate ? 'Agendar disparo' : 'Disparar agora')"></span>
                                    </button>
                                </div>

                                {{-- TAB: EVENTOS --}}
                                <div x-show="tab==='eventos'" class="p-4 space-y-3">

                                    {{-- Rules list --}}
                                    <div>
                                        <template x-if="rules.length === 0">
                                            <p class="text-xs text-gray-400 italic py-2">Nenhum evento configurado.</p>
                                        </template>
                                        <template x-for="r in rules" :key="r.id">
                                            <div class="flex items-start justify-between gap-2 p-2.5 bg-violet-50 border border-violet-100 rounded-lg text-xs mb-2">
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-semibold text-gray-800 mb-0.5">
                                                        <span x-text="triggerIcon(r.trigger_type)"></span>
                                                        <span x-text="r.trigger_label"></span>
                                                        <span x-show="r.trigger_extra" class="text-gray-500 font-normal" x-text="' (' + r.trigger_extra + ')'"></span>
                                                        <span x-show="r.keyword" class="bg-violet-100 text-violet-700 rounded px-1 font-mono ml-1" x-text="'&quot;' + r.keyword + '&quot;'"></span>
                                                    </div>
                                                    <div class="text-violet-700">
                                                        <span x-text="actionLabel(r.action_type)"></span>
                                                        <span x-show="r.action_type !== 'send' && r.target_stage_name" class="text-gray-400"> → </span>
                                                        <span x-show="r.action_type !== 'send' && r.target_stage_name" class="font-medium" x-text="r.target_stage_name"></span>
                                                    </div>
                                                </div>
                                                <button type="button" @click="deleteRule(r)"
                                                        class="shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-gray-300 hover:text-red-500 hover:bg-red-50 font-bold text-base transition-colors">×</button>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Add rule form --}}
                                    <div class="border border-dashed border-gray-200 rounded-xl p-3 space-y-2.5">
                                        <p class="text-xs font-semibold text-gray-600">+ Nova regra de evento</p>

                                        <div>
                                            <label class="block text-[11px] text-gray-500 mb-0.5">Quando ocorrer</label>
                                            <select x-model="newRule.trigger_type" @change="newRule.status='';newRule.keyword='';newRule.tag_id='';newRule.lista_id=''"
                                                    class="block w-full text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40">
                                                @foreach(\App\Models\FunnelStageRule::triggerTypes() as $tv => $tl)
                                                    <option value="{{ $tv }}">{{ $tl }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div x-show="newRule.trigger_type === 'message_status'">
                                            <label class="block text-[11px] text-gray-500 mb-0.5">Status</label>
                                            <select x-model="newRule.status" class="block w-full text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40">
                                                <option value="">— Selecione —</option>
                                                @foreach(\App\Models\FunnelStageRule::messageStatusOptions() as $sv => $sl)
                                                    <option value="{{ $sv }}">{{ $sl }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div x-show="newRule.trigger_type === 'specific_reply'">
                                            <label class="block text-[11px] text-gray-500 mb-0.5">Palavra-chave (mensagem contém)</label>
                                            <input type="text" x-model="newRule.keyword" placeholder='Ex: "quero sair", "sim", "confirmar"'
                                                   class="block w-full text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40">
                                        </div>

                                        <div x-show="newRule.trigger_type === 'tag_added'">
                                            <label class="block text-[11px] text-gray-500 mb-0.5">Tag</label>
                                            <select x-model="newRule.tag_id" class="block w-full text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40">
                                                <option value="">— Selecione —</option>
                                                @foreach($tags as $t)
                                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div x-show="newRule.trigger_type === 'list_added'">
                                            <label class="block text-[11px] text-gray-500 mb-0.5">Lista</label>
                                            <select x-model="newRule.lista_id" class="block w-full text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40">
                                                <option value="">— Selecione —</option>
                                                @foreach($listas as $l)
                                                    <option value="{{ $l->id }}">{{ $l->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Action type --}}
                                        <div>
                                            <label class="block text-[11px] text-gray-500 mb-1">Ação</label>
                                            <div class="grid grid-cols-3 gap-1.5">
                                                @foreach(\App\Models\FunnelStageRule::actionTypes() as $av => $al)
                                                    <label class="cursor-pointer">
                                                        <input type="radio" x-model="newRule.action_type" value="{{ $av }}" class="sr-only peer">
                                                        <span class="block text-center text-[11px] py-1.5 px-1 border border-gray-200 rounded-lg
                                                                     peer-checked:border-violet-500 peer-checked:bg-violet-50 peer-checked:text-violet-700
                                                                     hover:bg-gray-50 transition-colors cursor-pointer">{{ $al }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div x-show="newRule.action_type !== 'send'">
                                            <label class="block text-[11px] text-gray-500 mb-0.5">Mover para a coluna</label>
                                            <select x-model="newRule.target_stage_id" class="block w-full text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40">
                                                <option value="">— Selecione —</option>
                                                @foreach($funnel->stages->sortBy('position') as $s)
                                                    <option value="{{ $s->id }}" {{ $s->id === $stage->id ? 'disabled' : '' }}>{{ $s->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div x-show="newRule.action_type !== 'move'">
                                            <label class="block text-[11px] text-gray-500 mb-0.5">Mensagem automática</label>
                                            <textarea x-model="newRule.action_message" rows="2"
                                                      placeholder="Mensagem enviada automaticamente ao contato..."
                                                      class="block w-full text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40 resize-none"></textarea>
                                        </div>

                                        {{-- Rule feedback --}}
                                        <div x-show="ruleFeedback" x-transition
                                             :class="ruleFeedbackOk ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700'"
                                             class="text-xs rounded-lg border px-3 py-1.5" x-text="ruleFeedback"></div>

                                        <button type="button" @click="addRule()" :disabled="ruleLoading"
                                                class="w-full py-2 text-xs font-semibold text-white bg-violet-600 rounded-lg hover:bg-violet-700 disabled:opacity-50 transition-colors">
                                            <span x-text="ruleLoading ? 'Salvando...' : '+ Adicionar evento'"></span>
                                        </button>
                                    </div>
                                </div>

                            </div>{{-- /card --}}
                        </div>{{-- /flex-1 --}}
                    </div>{{-- /flex --}}

                @endforeach

                {{-- End of pipeline marker --}}
                <div class="flex gap-4">
                    <div class="w-10 flex justify-center">
                        <div class="w-9 h-9 rounded-full bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-400 text-lg">✓</div>
                    </div>
                    <div class="flex-1 pb-4 flex items-center">
                        <span class="text-sm text-gray-400 italic">Fim da pipeline</span>
                    </div>
                </div>

            </div>{{-- /pipeline --}}

            {{-- Deploy-all panel --}}
            <div class="sticky bottom-4 mt-4">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-lg p-4 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800">Disparar toda a pipeline</p>
                        <p class="text-xs text-gray-500 mt-0.5">Envia as mensagens configuradas em <strong>cada etapa</strong> simultaneamente.</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                        <div class="flex flex-col gap-1">
                            <div class="flex gap-2">
                                <input type="date" id="global-sched-date" :min="new Date().toISOString().split('T')[0]"
                                       class="text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40"
                                       x-data placeholder="Data (opcional)">
                                <input type="time" id="global-sched-time"
                                       class="text-xs rounded-lg border-gray-200 focus:border-violet-400 focus:ring-1 focus:ring-violet-300/40"
                                       placeholder="Hora">
                            </div>
                            <p class="text-[10px] text-gray-400">Deixe em branco para disparar agora</p>
                        </div>
                        <button type="button" id="deploy-all-btn-bottom"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-white bg-violet-600 rounded-xl hover:bg-violet-700 shadow">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Disparar Pipeline
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

@push('scripts')
<script>
const _csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Per-stage Alpine component
function pipelineStage(opts) {
    return {
        stageId:        opts.stageId,
        storeRuleUrl:   opts.storeRuleUrl,
        sendMsgUrl:     opts.sendMsgUrl,
        disparoStatUrl: opts.disparoStatUrl,
        rules:          opts.rules || [],
        disparo:        opts.initialDisparo || null,

        tab:       'disparo',
        msg:       '',
        imgFile:   null,
        imgPreview: null,
        imgName:   '',
        mode:      'sequential',
        delay:     0,
        showSched: false,
        schedDate: '',
        schedTime: '',
        loading:   false,
        feedback:  '',
        feedbackOk: true,

        newRule: { trigger_type: 'whatsapp_replied', status:'', keyword:'', tag_id:'', lista_id:'', action_type:'move', target_stage_id:'', action_message:'' },
        ruleLoading:   false,
        ruleFeedback:  '',
        ruleFeedbackOk: true,

        _pollTimer: null,

        init() {
            if (this.disparo && (this.disparo.status === 'running' || this.disparo.status === 'pending')) {
                this.startPolling();
            }
        },

        handleImg(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.imgFile = file;
            this.imgName = file.name;
            const reader = new FileReader();
            reader.onload = ev => this.imgPreview = ev.target.result;
            reader.readAsDataURL(file);
        },

        async dispatch() {
            if (!this.msg.trim() && !this.imgFile) {
                this.setFeedback('Informe a mensagem ou selecione uma imagem.', false);
                return;
            }
            this.loading = true;
            this.feedback = '';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const fd = new FormData();
            fd.append('_token', csrf);
            fd.append('message', this.msg);
            fd.append('mode', this.mode);
            fd.append('delay_seconds', this.delay);
            if (this.imgFile) fd.append('image', this.imgFile);
            if (this.showSched && this.schedDate) {
                fd.append('scheduled_date', this.schedDate);
                fd.append('scheduled_time', this.schedTime || '08:00');
            }
            try {
                const res = await fetch(this.sendMsgUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.setFeedback('✅ ' + data.message, true);
                    this.disparo = { id: data.disparo_id, status: 'running', sent: 0, failed: 0, total: data.total, percent: 0 };
                    this.startPolling();
                    // Notify global deploy tracker
                    document.dispatchEvent(new CustomEvent('stage-dispatched', { detail: { stageId: this.stageId } }));
                } else {
                    this.setFeedback(data.error || 'Erro ao iniciar disparo.', false);
                }
            } catch (e) {
                this.setFeedback('Erro de conexão.', false);
            } finally {
                this.loading = false;
            }
        },

        startPolling() {
            this.stopPolling();
            this._pollTimer = setInterval(() => this.pollStatus(), 4000);
        },

        stopPolling() {
            if (this._pollTimer) { clearInterval(this._pollTimer); this._pollTimer = null; }
        },

        async pollStatus() {
            try {
                const res = await fetch(this.disparoStatUrl, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.active) {
                    this.disparo = { id: data.id, status: data.status, sent: data.sent, failed: data.failed, total: data.total, percent: data.percent };
                    if (data.status !== 'running' && data.status !== 'pending') this.stopPolling();
                } else {
                    if (this.disparo && this.disparo.status === 'running') {
                        this.disparo = { ...this.disparo, status: 'completed', percent: 100 };
                    }
                    this.stopPolling();
                }
            } catch(e) {}
        },

        setFeedback(msg, ok) {
            this.feedback = msg;
            this.feedbackOk = ok;
            if (ok) setTimeout(() => { if (this.feedback === msg) this.feedback = ''; }, 5000);
        },

        triggerIcon(type) {
            return { message_status:'📨', whatsapp_replied:'💬', specific_reply:'🔑', tag_added:'🏷️', list_added:'📋' }[type] || '⚡';
        },

        actionLabel(type) {
            return { move:'Mover', send:'Enviar msg', move_and_send:'Mover + Enviar' }[type] || type;
        },

        async addRule() {
            this.ruleLoading = true;
            this.ruleFeedback = '';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const fd = new FormData();
            fd.append('_token', csrf);
            fd.append('trigger_type',    this.newRule.trigger_type);
            fd.append('action_type',     this.newRule.action_type);
            if (this.newRule.status)          fd.append('status',          this.newRule.status);
            if (this.newRule.keyword)         fd.append('keyword',         this.newRule.keyword);
            if (this.newRule.tag_id)          fd.append('tag_id',          this.newRule.tag_id);
            if (this.newRule.lista_id)        fd.append('lista_id',        this.newRule.lista_id);
            if (this.newRule.target_stage_id) fd.append('target_stage_id', this.newRule.target_stage_id);
            if (this.newRule.action_message)  fd.append('action_message',  this.newRule.action_message);
            try {
                const res = await fetch(this.storeRuleUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.rules.push(data.rule);
                    this.ruleFeedback = '✅ Evento salvo!';
                    this.ruleFeedbackOk = true;
                    this.newRule = { trigger_type: 'whatsapp_replied', status:'', keyword:'', tag_id:'', lista_id:'', action_type:'move', target_stage_id:'', action_message:'' };
                    setTimeout(() => { this.ruleFeedback = ''; }, 3000);
                } else {
                    this.ruleFeedback = data.error || 'Erro ao salvar evento.';
                    this.ruleFeedbackOk = false;
                }
            } catch(e) {
                this.ruleFeedback = 'Erro de conexão.';
                this.ruleFeedbackOk = false;
            } finally {
                this.ruleLoading = false;
            }
        },

        async deleteRule(rule) {
            if (!confirm('Remover este evento?')) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            try {
                const res = await fetch(rule.destroy_url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: '_method=DELETE&_token=' + encodeURIComponent(csrf)
                });
                if (res.ok) {
                    this.rules = this.rules.filter(r => r.id !== rule.id);
                } else {
                    let errMsg = 'HTTP ' + res.status;
                    try { const d = await res.json(); errMsg = d.message || d.error || errMsg; } catch(_) {}
                    console.error('[deleteRule]', res.status, errMsg, rule.destroy_url);
                    this.ruleFeedback = '❌ ' + errMsg;
                    this.ruleFeedbackOk = false;
                }
            } catch(e) {
                console.error('[deleteRule] network error', e);
                this.ruleFeedback = '❌ Erro de conexão.';
                this.ruleFeedbackOk = false;
            }
        },

        // Called by global deploy to dispatch this stage
        async dispatchWithConfig(globalSchedDate, globalSchedTime) {
            if (!this.msg.trim() && !this.imgFile) return { skipped: true };
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const fd = new FormData();
            fd.append('_token', csrf);
            fd.append('message', this.msg);
            fd.append('mode', this.mode);
            fd.append('delay_seconds', this.delay);
            if (this.imgFile) fd.append('image', this.imgFile);
            const sDate = this.showSched && this.schedDate ? this.schedDate : globalSchedDate;
            const sTime = this.showSched && this.schedDate ? (this.schedTime || '08:00') : globalSchedTime;
            if (sDate) {
                fd.append('scheduled_date', sDate);
                fd.append('scheduled_time', sTime || '08:00');
            }
            const res  = await fetch(this.sendMsgUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            });
            const data = await res.json();
            if (res.ok && data.success) {
                this.setFeedback('✅ ' + data.message, true);
                this.disparo = { id: data.disparo_id, status: 'running', sent: 0, failed: 0, total: data.total, percent: 0 };
                this.startPolling();
                return { success: true };
            }
            return { success: false, error: data.error };
        }
    };
}

// Global deploy-all handler
async function deployAllPipeline() {
    const btn        = document.getElementById('deploy-all-btn-bottom');
    const feedbackEl = document.getElementById('deploy-all-feedback');
    const globalDate = document.getElementById('global-sched-date')?.value || '';
    const globalTime = document.getElementById('global-sched-time')?.value || '';

    btn.disabled = true;
    btn.textContent = 'Disparando...';
    feedbackEl.classList.remove('hidden');
    feedbackEl.className = 'mb-4 p-3 rounded-lg text-sm font-medium bg-blue-50 border border-blue-200 text-blue-700';
    feedbackEl.textContent = 'Iniciando disparos por etapa...';

    // Collect all Alpine components
    const steps = document.querySelectorAll('[data-stage-step]');
    let sent = 0, skipped = 0, failed = 0;
    const errors = [];

    for (const step of steps) {
        const stageId = step.dataset.stageStep;
        // Get the Alpine component via the inner x-data div
        const alpineEl = step.querySelector('[x-data]');
        if (!alpineEl || !alpineEl._x_dataStack) continue;
        const comp = alpineEl._x_dataStack[0];
        if (!comp || typeof comp.dispatchWithConfig !== 'function') continue;

        try {
            const result = await comp.dispatchWithConfig(globalDate, globalTime);
            if (result.skipped)       skipped++;
            else if (result.success)  sent++;
            else { failed++; if (result.error) errors.push(result.error); }
        } catch(e) {
            failed++;
        }
    }

    btn.disabled = false;
    btn.textContent = 'Disparar Pipeline';

    let msg = '';
    if (sent > 0)    msg += `✅ ${sent} etapa(s) disparada(s). `;
    if (skipped > 0) msg += `⏭️ ${skipped} etapa(s) sem mensagem configurada (ignoradas). `;
    if (failed > 0)  msg += `❌ ${failed} erro(s). `;
    if (!msg)        msg  = '⚠️ Nenhuma etapa tinha mensagem configurada.';

    feedbackEl.className = 'mb-4 p-3 rounded-lg text-sm font-medium border ' +
        (failed > 0 ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700');
    feedbackEl.textContent = msg.trim();
    feedbackEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

document.getElementById('deploy-all-btn')?.addEventListener('click', deployAllPipeline);
document.getElementById('deploy-all-btn-bottom')?.addEventListener('click', deployAllPipeline);
</script>
@endpush
</x-app-layout>
