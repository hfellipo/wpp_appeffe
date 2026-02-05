<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Automação') }}
            </h2>
            <a href="{{ route('automacao.create') }}" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                {{ __('Nova automação') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="alert-success mb-6">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <p class="text-sm text-gray-600">
                        {{ __('Automações disparam ações (ex: enviar mensagem WhatsApp) com base em gatilhos e condições.') }}
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Nome') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Gatilho') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Condição') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Ações') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                <th class="relative px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($automations as $a)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('automacao.edit', $a) }}" class="text-sm font-medium text-brand-600 hover:text-brand-800">{{ $a->name }}</a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        @if($a->trigger)
                                            {{ \App\Models\Automation::triggerTypes()[$a->trigger->type] ?? $a->trigger->type }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        @if($a->condition)
                                            {{ \App\Models\Automation::conditionTypes()[$a->condition->type] ?? $a->condition->type }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $a->actions->count() }} {{ __('ação(ões)') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($a->is_active)
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800">{{ __('Ativa') }}</span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600">{{ __('Pausada') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('automacao.edit', $a) }}" class="text-brand-600 hover:text-brand-900 mr-3">{{ __('Editar') }}</a>
                                        <form action="{{ route('automacao.toggle', $a) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-gray-600 hover:text-gray-900">
                                                {{ $a->is_active ? __('Pausar') : __('Ativar') }}
                                            </button>
                                        </form>
                                        <form action="{{ route('automacao.destroy', $a) }}" method="POST" class="inline ml-2" onsubmit="return confirm('{{ __('Excluir esta automação?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Excluir') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                        <p class="mt-4">{{ __('Nenhuma automação ainda.') }}</p>
                                        <a href="{{ route('automacao.create') }}" class="mt-2 inline-block text-brand-600 hover:underline">{{ __('Criar primeira automação') }}</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($automations->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">{{ $automations->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
