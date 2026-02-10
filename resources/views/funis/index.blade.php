<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Funil de vendas') }}
            </h2>
            <a href="{{ route('funis.create') }}" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                {{ __('Novo funil') }}
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
                <div class="p-6 border-b border-gray-200">
                    <p class="text-sm text-gray-600">
                        {{ __('Crie vários funis para organizar seus leads por estágios. Clique em um funil para abrir o quadro (Kanban).') }}
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Nome') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Leads') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Valor total') }}
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">{{ __('Ações') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($funnels as $funnel)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('funis.show', $funnel) }}" class="text-sm font-medium text-brand-600 hover:text-brand-800">
                                            {{ $funnel->name }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $funnel->leads_count ?? 0 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $funnel->leads_sum_value ? number_format((float) $funnel->leads_sum_value, 2, ',', '.') : '0,00' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end items-center gap-2">
                                            <a href="{{ route('funis.show', $funnel) }}" class="text-brand-600 hover:text-brand-900">
                                                {{ __('Abrir quadro') }}
                                            </a>
                                            <a href="{{ route('funis.edit', $funnel) }}" class="text-gray-600 hover:text-gray-900">
                                                {{ __('Editar') }}
                                            </a>
                                            <form id="form-destroy-funnel-{{ $funnel->id }}" action="{{ route('funis.destroy', $funnel) }}" method="POST" class="inline" data-confirm-message="{{ __('Excluir este funil e todos os leads?') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-confirm', { detail: { name: 'confirm-modal', formId: 'form-destroy-funnel-{{ $funnel->id }}' } }))" class="text-red-600 hover:text-red-900">
                                                    {{ __('Excluir') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="text-gray-500">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                            </svg>
                                            <p class="mt-4 text-lg font-medium">{{ __('Nenhum funil ainda') }}</p>
                                            <p class="mt-2">{{ __('Crie um funil para começar a organizar seus leads.') }}</p>
                                            <div class="mt-6">
                                                <a href="{{ route('funis.create') }}" class="btn-primary">
                                                    {{ __('Novo funil') }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($funnels->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $funnels->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-confirm-modal name="confirm-modal" />
</x-app-layout>
