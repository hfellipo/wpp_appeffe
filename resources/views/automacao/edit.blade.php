<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $automation->name }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('automacao.jornada', $automation) }}" class="btn-secondary inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    {{ __('Ver jornada') }}
                </a>
                <a href="{{ route('automacao.index') }}" class="btn-secondary">{{ __('Voltar') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="alert-success mb-6">{{ session('success') }}</div>
            @endif

            <div class="flex items-center justify-between mb-8">
                <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'trigger']) }}" class="flex flex-col items-center flex-1 {{ $step === 'trigger' ? 'text-brand-600 font-medium' : 'text-gray-500' }}">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-sm {{ $step === 'trigger' ? 'bg-brand-100' : 'bg-gray-100' }}">1</span>
                    <span class="mt-1 text-xs">{{ __('Gatilho') }}</span>
                </a>
                <div class="flex-1 h-0.5 bg-gray-200 mx-2"></div>
                <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'condition']) }}" class="flex flex-col items-center flex-1 {{ $step === 'condition' ? 'text-brand-600 font-medium' : 'text-gray-500' }}">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-sm {{ $step === 'condition' ? 'bg-brand-100' : 'bg-gray-100' }}">2</span>
                    <span class="mt-1 text-xs">{{ __('Condições') }}</span>
                </a>
                <div class="flex-1 h-0.5 bg-gray-200 mx-2"></div>
                <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'action']) }}" class="flex flex-col items-center flex-1 {{ $step === 'action' ? 'text-brand-600 font-medium' : 'text-gray-500' }}">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-sm {{ $step === 'action' ? 'bg-brand-100' : 'bg-gray-100' }}">3</span>
                    <span class="mt-1 text-xs">{{ __('Ações') }}</span>
                </a>
            </div>

            @if($step === 'trigger')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Quando disparar esta automação?') }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ __('Escolha o evento que inicia a automação para cada contato.') }}</p>
                    </div>
                    <form action="{{ route('automacao.update', $automation) }}" method="POST" class="p-6">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="step" value="trigger">
                        <div class="mb-4">
                            <x-input-label :value="__('Gatilho')" />
                            <select name="trigger_type" id="trigger_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                                @foreach($triggerTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('trigger_type', $automation->trigger?->type) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="trigger-list" class="mb-4 {{ old('trigger_type', $automation->trigger?->type) !== 'list_added' ? 'hidden' : '' }}">
                            <x-input-label for="trigger_lista_id" :value="__('Lista')" />
                            <select name="trigger_lista_id" id="trigger_lista_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">{{ __('Selecione a lista') }}</option>
                                @foreach($listas as $l)
                                    <option value="{{ $l->id }}" {{ old('trigger_lista_id', $automation->trigger?->config['lista_id'] ?? '') == $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="trigger-tag" class="mb-4 {{ old('trigger_type', $automation->trigger?->type) !== 'tag_added' ? 'hidden' : '' }}">
                            <x-input-label for="trigger_tag_id" :value="__('Tag')" />
                            <select name="trigger_tag_id" id="trigger_tag_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">{{ __('Selecione a tag') }}</option>
                                @foreach($tags as $t)
                                    <option value="{{ $t->id }}" {{ old('trigger_tag_id', $automation->trigger?->config['tag_id'] ?? '') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-4">
                            <x-input-label for="interval_minutes" :value="__('Verificar a cada (tempo que o cron dispara em busca desta automação)')" />
                            <select name="interval_minutes" id="interval_minutes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                                <option value="5" {{ old('interval_minutes', $automation->interval_minutes ?? 15) == 5 ? 'selected' : '' }}>{{ __('5 minutos') }}</option>
                                <option value="15" {{ old('interval_minutes', $automation->interval_minutes ?? 15) == 15 ? 'selected' : '' }}>{{ __('15 minutos') }}</option>
                                <option value="30" {{ old('interval_minutes', $automation->interval_minutes ?? 15) == 30 ? 'selected' : '' }}>{{ __('30 minutos') }}</option>
                                <option value="60" {{ old('interval_minutes', $automation->interval_minutes ?? 15) == 60 ? 'selected' : '' }}>{{ __('60 minutos') }}</option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">{{ __('O sistema verifica a cada minuto; esta automação só será executada após esse intervalo desde a última verificação.') }}</p>
                        </div>
                        <div class="mb-4">
                            <x-input-label :value="__('Quantas vezes pode rodar para o mesmo contato?')" />
                            <div class="mt-2 space-y-2">
                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="run_once_per_contact" value="1" {{ old('run_once_per_contact', $automation->run_once_per_contact ?? true) ? 'checked' : '' }} class="mt-1 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span class="text-sm">{{ __('Esta automação poderá rodar apenas uma vez por contato.') }}</span>
                                </label>
                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="run_once_per_contact" value="0" {{ old('run_once_per_contact', $automation->run_once_per_contact ?? true) ? '' : 'checked' }} class="mt-1 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span class="text-sm">{{ __('Esta automação poderá rodar todas as vezes que o contato atender às condições programadas.') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button type="submit">{{ __('Salvar gatilho') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            @if($step === 'condition')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Quem deve receber as ações?') }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ __('Deixe passar todos ou defina regras: atributos do contato, campos personalizados ou status da última mensagem enviada (entregue, lida) com lógica E ou OU.') }}</p>
                    </div>
                    <form action="{{ route('automacao.update', $automation) }}" method="POST" class="p-6" id="form-conditions">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="step" value="condition">

                        <div class="space-y-4 mb-6">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="condition_mode" value="all" {{ old('condition_mode', $automation->condition_logic === null ? 'all' : 'rules') == 'all' ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                <span class="font-medium">{{ __('Todos os contatos que acionarem o gatilho') }}</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="condition_mode" value="rules" {{ old('condition_mode', $automation->condition_logic !== null ? 'rules' : 'all') == 'rules' ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                <span class="font-medium">{{ __('Se: só quem atender às regras abaixo') }}</span>
                            </label>
                        </div>

                        <div id="condition-rules-wrap" class="{{ $automation->condition_logic === null && !old('condition_mode') ? 'hidden' : '' }} {{ old('condition_mode') === 'rules' ? '' : 'hidden' }}">
                            <div class="mb-4">
                                <x-input-label :value="__('Lógica entre as regras')" />
                                <select name="condition_logic" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="and" {{ old('condition_logic', $automation->condition_logic) === 'and' ? 'selected' : '' }}>{{ __('E (todas as regras devem ser verdadeiras)') }}</option>
                                    <option value="or" {{ old('condition_logic', $automation->condition_logic) === 'or' ? 'selected' : '' }}>{{ __('OU (pelo menos uma regra deve ser verdadeira)') }}</option>
                                </select>
                            </div>

                            <div class="space-y-4" id="condition-rules-list">
                                @foreach(old('conditions', $automation->conditions) as $idx => $rule)
                                    @php
                                        $rule = is_array($rule) ? $rule : (array) $rule;
                                        $rule = array_merge(['field_type' => 'attribute', 'field_key' => 'name', 'operator' => 'equals', 'value' => ''], $rule);
                                        $isMessageStatus = ($rule['field_type'] ?? '') === 'message_status';
                                    @endphp
                                    <div class="condition-rule flex flex-wrap items-end gap-3 p-4 bg-gray-50 rounded-lg" data-index="{{ $idx }}">
                                        <div class="flex-1 min-w-[120px]">
                                            <x-input-label :value="__('Se (campo ou status)')" class="text-xs" />
                                            <select name="conditions[{{ $idx }}][field_type]" class="rule-field-type mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" data-index="{{ $idx }}">
                                                <optgroup label="{{ __('Atributos') }}">
                                                    @foreach($attributeFields as $key => $label)
                                                        <option value="attribute" data-key="{{ $key }}" {{ ($rule['field_type'] ?? '') === 'attribute' && ($rule['field_key'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                    @endforeach
                                                </optgroup>
                                                @if($customFields->count() > 0)
                                                    <optgroup label="{{ __('Campos personalizados') }}">
                                                        @foreach($customFields as $cf)
                                                            <option value="custom" data-field-id="{{ $cf->id }}" {{ ($rule['field_type'] ?? '') === 'custom' && ($rule['contact_field_id'] ?? '') == $cf->id ? 'selected' : '' }}>{{ $cf->name }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endif
                                                <optgroup label="{{ __('Status da mensagem') }}">
                                                    <option value="message_status" {{ $isMessageStatus ? 'selected' : '' }}>{{ __('Última mensagem enviada ao contato') }}</option>
                                                </optgroup>
                                            </select>
                                            <input type="hidden" name="conditions[{{ $idx }}][field_key]" class="rule-field-key" value="{{ $rule['field_key'] ?? 'name' }}">
                                            <input type="hidden" name="conditions[{{ $idx }}][contact_field_id]" class="rule-contact-field-id" value="{{ $rule['contact_field_id'] ?? '' }}">
                                        </div>
                                        <div class="w-44">
                                            <x-input-label :value="__('Operador')" class="text-xs" />
                                            <select name="conditions[{{ $idx }}][operator]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm rule-operator">
                                                @foreach($conditionOperators as $op => $label)
                                                    <option value="{{ $op }}" data-operator-group="field" {{ !$isMessageStatus && ($rule['operator'] ?? '') === $op ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                                @foreach($messageStatusOperators ?? [] as $op => $label)
                                                    <option value="{{ $op }}" data-operator-group="message_status" {{ $isMessageStatus && ($rule['operator'] ?? '') === $op ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="rule-value-wrap flex-1 min-w-[140px] {{ ($isMessageStatus || in_array($rule['operator'] ?? '', ['is_empty', 'is_not_empty'])) ? 'hidden' : '' }}">
                                            <x-input-label :value="__('Valor')" class="text-xs" />
                                            <input type="text" name="conditions[{{ $idx }}][value]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" value="{{ $rule['value'] ?? '' }}" placeholder="{{ __('Valor') }}">
                                        </div>
                                        <button type="button" class="remove-rule text-red-600 hover:text-red-800 text-sm py-1">{{ __('Remover') }}</button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" id="add-condition-rule" class="mt-2 text-brand-600 hover:text-brand-800 text-sm font-medium">{{ __('+ Adicionar regra') }}</button>
                        </div>

                        <div class="flex justify-end mt-6">
                            <x-primary-button type="submit">{{ __('Salvar condições') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            @if($step === 'action')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Ações em sequência') }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ __('O que fazer com cada contato que passar pelo gatilho e condições.') }}</p>
                    </div>
                    <div class="p-6">
                        @forelse($automation->actions as $act)
                            <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                                <div>
                                    <span class="font-medium">{{ $actionTypes[$act->type] ?? $act->type }}</span>
                                    @if($act->type === 'send_whatsapp_message' && !empty($act->config['message']))
                                        <span class="text-gray-500 text-sm ml-2">— {{ Str::limit($act->config['message'], 40) }}</span>
                                    @endif
                                    @if($act->type === 'add_to_list' && !empty($act->config['lista_id']))
                                        @php $lista = $listas->firstWhere('id', $act->config['lista_id']); @endphp
                                        <span class="text-gray-500 text-sm ml-2">— {{ $lista?->name ?? $act->config['lista_id'] }}</span>
                                    @endif
                                    @if($act->type === 'add_tag' && !empty($act->config['tag_id']))
                                        @php $tag = $tags->firstWhere('id', $act->config['tag_id']); @endphp
                                        <span class="text-gray-500 text-sm ml-2">— {{ $tag?->name ?? $act->config['tag_id'] }}</span>
                                    @endif
                                    @if($act->type === 'wait_delay' && !empty($act->config['minutes']))
                                        <span class="text-gray-500 text-sm ml-2">— {{ $act->config['minutes'] }} min</span>
                                    @endif
                                </div>
                                <form action="{{ route('automacao.actions.destroy', [$automation, $act]) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('Remover esta ação?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">{{ __('Remover') }}</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">{{ __('Nenhuma ação ainda. Adicione abaixo.') }}</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Adicionar ação') }}</h3>
                    </div>
                    <form action="{{ route('automacao.update', $automation) }}" method="POST" class="p-6">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="step" value="action">
                        <div class="mb-4">
                            <x-input-label :value="__('Tipo de ação')" />
                            <select name="action_type" id="action_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                                @foreach($actionTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('action_type', 'send_whatsapp_message') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="action-message" class="mb-4">
                            <x-input-label for="action_message" :value="__('Mensagem WhatsApp')" />
                            <textarea name="action_message" id="action_message" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="{{ __('Digite a mensagem...') }}">{{ old('action_message') }}</textarea>
                        </div>
                        <div id="action-list" class="mb-4 hidden">
                            <x-input-label for="action_lista_id" :value="__('Lista')" />
                            <select name="action_lista_id" id="action_lista_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">{{ __('Selecione') }}</option>
                                @foreach($listas as $l)
                                    <option value="{{ $l->id }}" {{ old('action_lista_id') == $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="action-tag" class="mb-4 hidden">
                            <x-input-label for="action_tag_id" :value="__('Tag')" />
                            <select name="action_tag_id" id="action_tag_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">{{ __('Selecione') }}</option>
                                @foreach($tags as $t)
                                    <option value="{{ $t->id }}" {{ old('action_tag_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="action-wait" class="mb-4 hidden">
                            <x-input-label for="action_wait_minutes" :value="__('Aguardar (minutos)')" />
                            <x-text-input name="action_wait_minutes" id="action_wait_minutes" type="number" min="1" max="10080" class="mt-1 block w-full" :value="old('action_wait_minutes', 60)" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button type="submit">{{ __('Adicionar ação') }}</x-primary-button>
                        </div>
                    </form>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                    <a href="{{ route('automacao.test', $automation) }}" class="btn-secondary inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Testar com um contato') }}
                    </a>
                    <a href="{{ route('automacao.index') }}" class="btn-primary inline-flex items-center gap-2">
                        <span>{{ __('Concluir e voltar à lista') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var triggerType = document.getElementById('trigger_type');
            if (triggerType) {
                function showTrigger() {
                    var t = triggerType.value;
                    document.getElementById('trigger-list').classList.toggle('hidden', t !== 'list_added');
                    document.getElementById('trigger-tag').classList.toggle('hidden', t !== 'tag_added');
                }
                showTrigger();
                triggerType.addEventListener('change', showTrigger);
            }

            var conditionModeRadios = document.querySelectorAll('input[name="condition_mode"]');
            var rulesWrap = document.getElementById('condition-rules-wrap');
            if (conditionModeRadios.length && rulesWrap) {
                function toggleRules() {
                    var mode = document.querySelector('input[name="condition_mode"]:checked');
                    rulesWrap.classList.toggle('hidden', !mode || mode.value !== 'rules');
                }
                conditionModeRadios.forEach(function(r) { r.addEventListener('change', toggleRules); });
                toggleRules();
            }

            var rulesList = document.getElementById('condition-rules-list');
            var addRuleBtn = document.getElementById('add-condition-rule');
            var attributeFields = @json($attributeFields ?? []);
            var customFields = @json($customFields->keyBy('id')->map(fn($f) => ['id' => $f->id, 'name' => $f->name]) ?? []);
            var operators = @json($conditionOperators ?? []);
            var messageStatusOperators = @json($messageStatusOperators ?? []);

            function syncOperatorOptions(opSelect, fieldType) {
                var isMsg = fieldType === 'message_status';
                var group = isMsg ? 'message_status' : 'field';
                for (var i = 0; i < opSelect.options.length; i++) {
                    var opt = opSelect.options[i];
                    var optGroup = opt.getAttribute('data-operator-group');
                    opt.hidden = optGroup !== group;
                }
                var firstVisible = Array.from(opSelect.options).find(function(o) { return !o.hidden; });
                if (firstVisible) opSelect.value = firstVisible.value;
            }

            function ruleHtml(index) {
                var optgroups = '<optgroup label="{{ __("Atributos") }}">';
                for (var k in attributeFields) { optgroups += '<option value="attribute" data-key="'+k+'">'+attributeFields[k]+'</option>'; }
                optgroups += '</optgroup>';
                if (Object.keys(customFields).length) {
                    optgroups += '<optgroup label="{{ __("Campos personalizados") }}">';
                    for (var id in customFields) { optgroups += '<option value="custom" data-field-id="'+id+'">'+customFields[id].name+'</option>'; }
                    optgroups += '</optgroup>';
                }
                optgroups += '<optgroup label="{{ __("Status da mensagem") }}"><option value="message_status">{{ __("Última mensagem enviada ao contato") }}</option></optgroup>';
                var opOptions = '';
                for (var o in operators) { opOptions += '<option value="'+o+'" data-operator-group="field">'+operators[o]+'</option>'; }
                for (var o in messageStatusOperators) { opOptions += '<option value="'+o+'" data-operator-group="message_status">'+messageStatusOperators[o]+'</option>'; }
                return '<div class="condition-rule flex flex-wrap items-end gap-3 p-4 bg-gray-50 rounded-lg" data-index="'+index+'">'+
                    '<div class="flex-1 min-w-[120px]">'+
                    '<label class="text-xs block text-gray-700">{{ __("Se (campo ou status)") }}</label>'+
                    '<select name="conditions['+index+'][field_type]" class="rule-field-type mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" data-index="'+index+'">'+optgroups+'</select>'+
                    '<input type="hidden" name="conditions['+index+'][field_key]" class="rule-field-key" value="name">'+
                    '<input type="hidden" name="conditions['+index+'][contact_field_id]" class="rule-contact-field-id" value="">'+
                    '</div>'+
                    '<div class="w-44">'+
                    '<label class="text-xs block text-gray-700">{{ __("Operador") }}</label>'+
                    '<select name="conditions['+index+'][operator]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm rule-operator">'+opOptions+'</select>'+
                    '</div>'+
                    '<div class="rule-value-wrap flex-1 min-w-[140px]">'+
                    '<label class="text-xs block text-gray-700">{{ __("Valor") }}</label>'+
                    '<input type="text" name="conditions['+index+'][value]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="{{ __("Valor") }}">'+
                    '</div>'+
                    '<button type="button" class="remove-rule text-red-600 hover:text-red-800 text-sm py-1">{{ __("Remover") }}</button>'+
                    '</div>';
            }

            if (addRuleBtn && rulesList) {
                addRuleBtn.addEventListener('click', function() {
                    var count = rulesList.querySelectorAll('.condition-rule').length;
                    rulesList.insertAdjacentHTML('beforeend', ruleHtml(count));
                    bindRuleEvents();
                });

                function bindRuleEvents() {
                    rulesList.querySelectorAll('.condition-rule').forEach(function(block) {
                        var fieldSelect = block.querySelector('.rule-field-type');
                        var opSelect = block.querySelector('.rule-operator');
                        var keyInput = block.querySelector('.rule-field-key');
                        var fieldIdInput = block.querySelector('.rule-contact-field-id');
                        var valueWrap = block.querySelector('.rule-value-wrap');

                        if (fieldSelect && !fieldSelect.dataset.bound) {
                            fieldSelect.dataset.bound = '1';
                            fieldSelect.addEventListener('change', function() {
                                var opt = this.options[this.selectedIndex];
                                var ft = opt.value;
                                if (ft === 'attribute') {
                                    keyInput.value = opt.getAttribute('data-key') || 'name';
                                    fieldIdInput.value = '';
                                } else if (ft === 'custom') {
                                    keyInput.value = '';
                                    fieldIdInput.value = opt.getAttribute('data-field-id') || '';
                                } else {
                                    keyInput.value = '';
                                    fieldIdInput.value = '';
                                }
                                syncOperatorOptions(opSelect, ft);
                                valueWrap.classList.toggle('hidden', ft === 'message_status');
                            });
                        }
                        if (opSelect && !opSelect.dataset.bound) {
                            opSelect.dataset.bound = '1';
                            opSelect.addEventListener('change', function() {
                                var fieldType = this.closest('.condition-rule').querySelector('.rule-field-type').value;
                                if (fieldType !== 'message_status') {
                                    var hide = ['is_empty','is_not_empty'].indexOf(this.value) >= 0;
                                    valueWrap.classList.toggle('hidden', hide);
                                }
                            });
                        }
                    });
                    rulesList.querySelectorAll('.condition-rule').forEach(function(block) {
                        var fieldSelect = block.querySelector('.rule-field-type');
                        var opSelect = block.querySelector('.rule-operator');
                        var valueWrap = block.querySelector('.rule-value-wrap');
                        if (fieldSelect && opSelect && valueWrap) {
                            var ft = fieldSelect.value;
                            syncOperatorOptions(opSelect, ft);
                            if (ft === 'message_status') valueWrap.classList.add('hidden');
                        }
                    });
                    rulesList.querySelectorAll('.remove-rule').forEach(function(btn) {
                        if (btn.dataset.bound) return;
                        btn.dataset.bound = '1';
                        btn.addEventListener('click', function() {
                            this.closest('.condition-rule').remove();
                        });
                    });
                }
                bindRuleEvents();
            }

            var actionType = document.getElementById('action_type');
            if (actionType) {
                function showAction() {
                    var t = actionType.value;
                    document.getElementById('action-message').classList.toggle('hidden', t !== 'send_whatsapp_message');
                    document.getElementById('action-list').classList.toggle('hidden', t !== 'add_to_list');
                    document.getElementById('action-tag').classList.toggle('hidden', t !== 'add_tag');
                    document.getElementById('action-wait').classList.toggle('hidden', t !== 'wait_delay');
                }
                showAction();
                actionType.addEventListener('change', showAction);
            }
        });
    </script>
</x-app-layout>
