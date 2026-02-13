<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Enviando post') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <p class="mb-4">
                <a href="{{ route('automacao.agendamentos.index') }}" class="text-brand-600 hover:text-brand-800 text-sm">← {{ __('Voltar para Posts agendados') }}</a>
            </p>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div id="state-loading" class="text-center py-8">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-brand-100 text-brand-600 mb-4">
                            <svg class="w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </div>
                        <p class="text-gray-600">{{ __('Conectando e preparando envio...') }}</p>
                    </div>

                    <div id="state-progress" class="hidden">
                        <div class="mb-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span id="progress-label">{{ __('Enviando para') }}: <strong id="current-contact">—</strong></span>
                                <span id="progress-percent">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                <div id="progress-bar" class="h-full bg-brand-500 transition-all duration-300" style="width: 0%"></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500"><span id="progress-count">0</span> / <span id="progress-total">0</span> {{ __('contatos') }}</p>
                        </div>
                        <div class="border-t border-gray-100 pt-4">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">{{ __('Resultado por contato') }}</p>
                            <ul id="results-list" class="space-y-1 max-h-64 overflow-y-auto text-sm"></ul>
                        </div>
                    </div>

                    <div id="state-done" class="hidden">
                        <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 mb-4">
                            <p class="font-medium text-emerald-800">{{ __('Envio concluído') }}</p>
                            <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                                <span class="text-emerald-700">{{ __('Enviados') }}: <strong id="summary-sent">0</strong></span>
                                <span class="text-red-700">{{ __('Falhas') }}: <strong id="summary-failed">0</strong></span>
                                <span class="text-gray-700">{{ __('Entregues') }}: <strong id="summary-delivered">—</strong></span>
                                <span class="text-gray-700">{{ __('Lidos') }}: <strong id="summary-read">—</strong></span>
                            </div>
                            <p class="mt-2 text-xs text-gray-600">{{ __('Entregues e lidos são atualizados pelo WhatsApp ao longo do tempo.') }}</p>
                        </div>
                        <div class="border-t border-gray-100 pt-4">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">{{ __('Quem recebeu / quem não recebeu') }}</p>
                            <ul id="summary-list" class="space-y-1 max-h-64 overflow-y-auto text-sm"></ul>
                        </div>
                        <div class="mt-6">
                            <a href="{{ route('automacao.agendamentos.index') }}" class="btn-primary">{{ __('Voltar à lista de posts') }}</a>
                        </div>
                    </div>

                    <div id="state-error" class="hidden rounded-lg bg-red-50 border border-red-200 p-4">
                        <p class="font-medium text-red-800">{{ __('Erro no envio') }}</p>
                        <p id="error-message" class="mt-1 text-sm text-red-700"></p>
                        <a href="{{ route('automacao.agendamentos.index') }}" class="mt-3 inline-block text-sm text-red-600 hover:text-red-800 font-medium">{{ __('Voltar') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var postId = {{ $post->id }};
            var streamUrl = '{{ route('automacao.agendamentos.send-now-stream', $post) }}';
            var stateLoading = document.getElementById('state-loading');
            var stateProgress = document.getElementById('state-progress');
            var stateDone = document.getElementById('state-done');
            var stateError = document.getElementById('state-error');
            var progressBar = document.getElementById('progress-bar');
            var progressPercent = document.getElementById('progress-percent');
            var currentContact = document.getElementById('current-contact');
            var progressCount = document.getElementById('progress-count');
            var progressTotal = document.getElementById('progress-total');
            var resultsList = document.getElementById('results-list');
            var summarySent = document.getElementById('summary-sent');
            var summaryFailed = document.getElementById('summary-failed');
            var summaryDelivered = document.getElementById('summary-delivered');
            var summaryRead = document.getElementById('summary-read');
            var summaryList = document.getElementById('summary-list');
            var errorMessage = document.getElementById('error-message');

            function showProgress() {
                stateLoading.classList.add('hidden');
                stateError.classList.add('hidden');
                stateDone.classList.add('hidden');
                stateProgress.classList.remove('hidden');
            }
            function showDone(summary) {
                stateProgress.classList.add('hidden');
                stateDone.classList.remove('hidden');
                if (summary) {
                    summarySent.textContent = summary.sent || 0;
                    summaryFailed.textContent = summary.failed || 0;
                    summaryDelivered.textContent = summary.delivered !== undefined ? summary.delivered : '—';
                    summaryRead.textContent = summary.read !== undefined ? summary.read : '—';
                    var list = summary.results || [];
                    summaryList.innerHTML = list.map(function (r) {
                        var icon = r.status === 'sent' ? '✓' : '✗';
                        var cls = r.status === 'sent' ? 'text-emerald-600' : 'text-red-600';
                        return '<li class="' + cls + '">' + icon + ' ' + escapeHtml(r.name) + (r.status === 'failed' ? ' ({{ __("falha") }})' : '') + '</li>';
                    }).join('');
                }
            }
            function showError(msg) {
                stateLoading.classList.add('hidden');
                stateProgress.classList.add('hidden');
                stateDone.classList.add('hidden');
                stateError.classList.remove('hidden');
                errorMessage.textContent = msg || '{{ __("Erro desconhecido.") }}';
            }
            function escapeHtml(s) {
                var div = document.createElement('div');
                div.textContent = s;
                return div.innerHTML;
            }

            var es = new EventSource(streamUrl);
            es.onopen = function () {
                showProgress();
            };
            es.onmessage = function (e) {
                var data;
                try {
                    data = JSON.parse(e.data);
                } catch (err) {
                    return;
                }
                if (data.type === 'progress') {
                    progressBar.style.width = (data.percent || 0) + '%';
                    progressPercent.textContent = (data.percent || 0) + '%';
                    currentContact.textContent = data.contact_name || '—';
                    progressCount.textContent = data.current || 0;
                    progressTotal.textContent = data.total || 0;
                } else if (data.type === 'result') {
                    var li = document.createElement('li');
                    var icon = data.status === 'sent' ? '✓' : '✗';
                    li.className = data.status === 'sent' ? 'text-emerald-600' : 'text-red-600';
                    li.textContent = icon + ' ' + (data.contact_name || '') + (data.status === 'failed' ? ' ({{ __("falha") }})' : '');
                    resultsList.appendChild(li);
                } else if (data.type === 'done') {
                    es.close();
                    showDone(data.summary || {});
                } else if (data.type === 'error') {
                    es.close();
                    showError(data.message);
                }
            };
            es.onerror = function () {
                es.close();
                if (!stateDone.classList.contains('hidden') || stateError.classList.contains('hidden')) return;
                showError('{{ __("Conexão interrompida. Verifique se o envio foi concluído na lista de posts.") }}');
            };
        });
    </script>
</x-app-layout>
