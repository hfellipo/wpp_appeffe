<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Posts agendados') }}
            </h2>
            <a href="{{ route('automacao.agendamentos.create') }}" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                {{ __('Agendar post') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <p class="mb-4">
                <a href="{{ route('automacao.index') }}" class="text-brand-600 hover:text-brand-800 text-sm">← {{ __('Voltar para Automação') }}</a>
            </p>

            @if(session('success'))
                <div class="alert-success mb-6">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <p class="text-sm text-gray-600">
                        {{ __('Envie uma mensagem WhatsApp em data e hora definidas para um grupo, uma lista de contatos ou para quem tem uma tag.') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('Para envio automático no horário (sem precisar abrir a página), use uma das opções abaixo.') }}
                    </p>
                    <ul class="text-xs text-gray-600 mt-1 list-disc list-inside space-y-0.5">
                        <li><strong>{{ __('Servidor com cron:') }}</strong> <code class="bg-gray-100 px-1 rounded">* * * * * cd /caminho/do/app && php artisan schedule:run</code></li>
                        <li><strong>{{ __('Sem cron no servidor:') }}</strong> {{ __('Adicione no .env') }} <code class="bg-gray-100 px-1 rounded">SCHEDULED_POSTS_CRON_TOKEN=um_token_secreto</code> {{ __('e agende em') }} <a href="https://cron-job.org" target="_blank" rel="noopener" class="text-brand-600 hover:underline">cron-job.org</a> {{ __('uma chamada a cada minuto para:') }} <code class="bg-gray-100 px-1 rounded break-all">{{ url()->route('automacao.agendamentos.cron') }}?token=um_token_secreto</code></li>
                    </ul>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('Ao abrir esta página, os vencidos também são processados.') }}
                    </p>
                    <p class="text-xs text-gray-600 mt-2 pt-2 border-t border-gray-100">
                        <strong>{{ __('Horário do servidor (usado para agendamentos):') }}</strong>
                        <span class="font-mono">{{ now()->format('d/m/Y H:i:s') }}</span>
                        <span class="text-gray-500">({{ config('app.timezone') }})</span>
                        — {{ __('Se estiver errado, defina') }} <code class="bg-gray-100 px-1 rounded">APP_TIMEZONE</code> {{ __('no .env (ex.: America/Sao_Paulo).') }}
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Data/hora') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Destino') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Mensagem') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                <th class="relative px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($posts as $post)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $post->scheduled_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <span class="font-medium">{{ \App\Models\ScheduledPost::targetTypes()[$post->target_type] ?? $post->target_type }}</span>
                                        @if($post->target_type === 'group')
                                            @php
                                                $conv = \App\Models\WhatsAppConversation::find($post->target_id);
                                                $label = $conv ? (trim($conv->custom_contact_name ?? '') ?: trim($conv->contact_name ?? '') ?: $conv->peer_jid) : '#' . $post->target_id;
                                            @endphp
                                            — {{ $label }}
                                        @elseif($post->target_type === 'list')
                                            @php $lista = \App\Models\Lista::find($post->target_id); @endphp
                                            — {{ $lista ? $lista->name : '#' . $post->target_id }}
                                        @else
                                            @php $tag = \App\Models\Tag::find($post->target_id); @endphp
                                            — {{ $tag ? $tag->name : '#' . $post->target_id }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 max-w-xs">
                                        @if($post->image_path)
                                            <span class="inline-flex items-center gap-1 text-gray-500" title="{{ __('Com imagem') }}">📷</span>
                                        @endif
                                        <span class="truncate block" title="{{ $post->message }}">{{ Str::limit($post->message, 45) }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($post->sent_at)
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800">{{ __('Enviado') }} {{ $post->sent_at->format('d/m H:i') }}</span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800">{{ __('Agendado') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <div class="flex items-center justify-end gap-2 flex-wrap">
                                            @if(!$post->sent_at)
                                                <form action="{{ route('automacao.agendamentos.send-now', $post) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-brand-600 hover:text-brand-900">{{ __('Enviar agora') }}</button>
                                                </form>
                                                <span class="text-gray-300">|</span>
                                                <a href="{{ route('automacao.agendamentos.edit', $post) }}" class="text-gray-600 hover:text-gray-900">{{ __('Reconfigurar') }}</a>
                                                <span class="text-gray-300">|</span>
                                            @endif
                                            <form action="{{ route('automacao.agendamentos.duplicate', $post) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-gray-600 hover:text-gray-900">{{ __('Duplicar') }}</button>
                                            </form>
                                            <span class="text-gray-300">|</span>
                                            <form action="{{ route('automacao.agendamentos.destroy', $post) }}" method="POST" class="inline" onsubmit="return confirm('{{ $post->sent_at ? __('Remover este post da lista?') : __('Cancelar e excluir este agendamento?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Excluir') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        <p>{{ __('Nenhum post agendado.') }}</p>
                                        <a href="{{ route('automacao.agendamentos.create') }}" class="mt-2 inline-block text-brand-600 hover:underline">{{ __('Agendar primeiro post') }}</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($posts->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">{{ $posts->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
