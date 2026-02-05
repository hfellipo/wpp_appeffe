<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $automation->name }}
            </h2>
            <a href="{{ route('automacao.index') }}" class="btn-secondary">{{ __('Voltar') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="alert-success mb-6">{{ session('success') }}</div>
            @endif

            {{-- Steps (estilo ActiveCampaign) --}}
            <div class="flex items-center justify-between mb-8">
                <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'trigger']) }}" class="flex flex-col items-center flex-1 {{ $step === 'trigger' ? 'text-brand-600 font-medium' : 'text-gray-500' }}">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-sm {{ $step === 'trigger' ? 'bg-brand-100' : 'bg-gray-100' }}">1</span>
                    <span class="mt-1 text-xs">{{ __('Gatilho') }}</span>
                </a>
                <div class="flex-1 h-0.5 bg-gray-200 mx-2"></div>
                <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'condition']) }}" class="flex flex-col items-center flex-1 {{ $step === 'condition' ? 'text-brand-600 font-medium' : 'text-gray-500' }}">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-sm {{ $step === 'condition' ? 'bg-brand-100' : 'bg-gray-100' }}">2</span>
                    <span class="mt-1 text-xs">{{ __('Condição') }}</span>
                </a>
                <div class="flex-1 h-0.5 bg-gray-200 mx-2"></div>
                <a href="{{ route('automacao.edit', ['automacao' => $automation, 'step' => 'action']) }}" class="flex flex-col items-center flex-1 {{ $step === 'action' ? 'text-brand-600 font-medium' : 'text-gray-500' }}">
                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-sm {{ $step === 'action' ? 'bg-brand-100' : 'bg-gray-100' }}">3</span>
                    <span class="mt-1 text-xs">{{ __('Ação') }}</span>
                </a>
            </div>

            @if($step === 'trigger')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Quando esta automação deve disparar?') }}</h3>
                    </div>
                    <form action="{{ route('automacao.update', $automation) }}" method="POST" class="p-6">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="step" value="trigger">
                        <div class="mb-4">
                            <x-input-label :value="__('Tipo de gatilho')" />
                            <select name="trigger_type" id="trigger_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                                @foreach($triggerTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('trigger_type', $automation->trigger?->type) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="trigger-list" class="mb-4 hidden">
                            <x-input-label for="trigger_lista_id" :value="__('Lista')" />
                            <select name="trigger_lista_id" id="trigger_lista_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">{{ __('Selecione') }}</option>
                                @foreach($listas as $l)
                                    <option value="{{ $l->id }}" {{ old('trigger_lista_id', $automation->trigger?->config['lista_id'] ?? '') == $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="trigger-tag" class="mb-4 hidden">
                            <x-input-label for="trigger_tag_id" :value="__('Tag')" />
                            <select name="trigger_tag_id" id="trigger_tag_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">{{ __('Selecione') }}</option>
                                @foreach($tags as $t)
                                    <option value="{{ $t->id }}" {{ old('trigger_tag_id', $automation->trigger?->config['tag_id'] ?? '') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="trigger-schedule" class="space-y-4 hidden">
                            <div>
                                <x-input-label for="schedule_time" :value="__('Horário (HH:MM)')" />
                                <x-text-input name="schedule_time" id="schedule_time" type="text" class="mt-1 block w-full" :value="old('schedule_time', $automation->trigger?->config['time'] ?? '09:00')" placeholder="09:00" />
                            </div>
                            <div id="schedule-weekday" class="hidden">
                                <x-input-label for="schedule_weekday" :value="__('Dia da semana (0=Dom, 1=Seg, … 6=Sáb)')" />
                                <x-text-input name="schedule_weekday" id="schedule_weekday" type="number" min="0" max="6" class="mt-1 block w-full" :value="old('schedule_weekday', $automation->trigger?->config['weekday'] ?? '1')" />
                            </div>
                            <div id="schedule-day" class="hidden">
                                <x-input-label for="schedule_day" :value="__('Dia do mês (1-31)')" />
                                <x-text-input name="schedule_day" id="schedule_day" type="number" min="1" max="31" class="mt-1 block w-full" :value="old('schedule_day', $automation->trigger?->config['day'] ?? '1')" />
                            </div>
                            <div id="schedule-month" class="hidden">
                                <x-input-label for="schedule_month" :value="__('Mês (1-12)')" />
                                <x-text-input name="schedule_month" id="schedule_month" type="number" min="1" max="12" class="mt-1 block w-full" :value="old('schedule_month', $automation->trigger?->config['month'] ?? '1')" />
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
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Quem deve receber a ação? (condição Sim/Não)') }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ __('Só executa a ação se a condição for atendida.') }}</p>
                    </div>
                    <form action="{{ route('automacao.update', $automation) }}" method="POST" class="p-6">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="step" value="condition">
                        <div class="mb-4">
                            <x-input-label :value="__('Tipo de condição')" />
                            <select name="condition_type" id="condition_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                                @foreach($conditionTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('condition_type', $automation->condition?->type) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="condition-list" class="mb-4 hidden">
                            <x-input-label for="condition_lista_id" :value="__('Lista')" />
                            <select name="condition_lista_id" id="condition_lista_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">{{ __('Selecione') }}</option>
                                @foreach($listas as $l)
                                    <option value="{{ $l->id }}" {{ old('condition_lista_id', $automation->condition?->config['lista_id'] ?? '') == $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="condition-tag" class="mb-4 hidden">
                            <x-input-label for="condition_tag_id" :value="__('Tag')" />
                            <select name="condition_tag_id" id="condition_tag_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">{{ __('Selecione') }}</option>
                                @foreach($tags as $t)
                                    <option value="{{ $t->id }}" {{ old('condition_tag_id', $automation->condition?->config['tag_id'] ?? '') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button type="submit">{{ __('Salvar condição') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            @if($step === 'action')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Ações configuradas') }}</h3>
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
                                    <option value="{{ $value }}" {{ old('action_type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="action-message" class="mb-4 hidden">
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
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function showTriggerExtra() {
                var t = document.getElementById('trigger_type').value;
                document.getElementById('trigger-list').classList.toggle('hidden', t !== 'list_added');
                document.getElementById('trigger-tag').classList.toggle('hidden', t !== 'tag_added');
                var isSchedule = t && t.startsWith('schedule_');
                document.getElementById('trigger-schedule').classList.toggle('hidden', !isSchedule);
                document.getElementById('schedule-weekday').classList.toggle('hidden', t !== 'schedule_weekly');
                document.getElementById('schedule-day').classList.toggle('hidden', t !== 'schedule_monthly' && t !== 'schedule_yearly');
                document.getElementById('schedule-month').classList.toggle('hidden', t !== 'schedule_yearly');
            }
            function showConditionExtra() {
                var t = document.getElementById('condition_type').value;
                document.getElementById('condition-list').classList.toggle('hidden', t !== 'contact_in_list');
                document.getElementById('condition-tag').classList.toggle('hidden', t !== 'contact_has_tag');
            }
            function showActionExtra() {
                var t = document.getElementById('action_type').value;
                document.getElementById('action-message').classList.toggle('hidden', t !== 'send_whatsapp_message');
                document.getElementById('action-list').classList.toggle('hidden', t !== 'add_to_list');
                document.getElementById('action-tag').classList.toggle('hidden', t !== 'add_tag');
                document.getElementById('action-wait').classList.toggle('hidden', t !== 'wait_delay');
            }
            if (document.getElementById('trigger_type')) {
                showTriggerExtra();
                document.getElementById('trigger_type').addEventListener('change', showTriggerExtra);
            }
            if (document.getElementById('condition_type')) {
                showConditionExtra();
                document.getElementById('condition_type').addEventListener('change', showConditionExtra);
            }
            if (document.getElementById('action_type')) {
                showActionExtra();
                document.getElementById('action_type').addEventListener('change', showActionExtra);
            }
        });
    </script>
</x-app-layout>
