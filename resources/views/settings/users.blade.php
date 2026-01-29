<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Usuários da Conta') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Crie usuários para acessar o mesmo sistema e os mesmos dados desta conta.') }}
                </p>
            </div>
            <a href="{{ route('settings.index') }}" class="btn-secondary">
                {{ __('Voltar') }}
            </a>
        </div>
    </x-slot>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <style>[x-cloak]{display:none!important}</style>
    @endpush

    <div class="py-12" x-data="accountUsers()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Modal: editar usuário -->
            <div x-cloak x-show="editOpen">
                <div class="modal-backdrop fade show"></div>
                <div class="modal fade show d-block"
                     tabindex="-1"
                     role="dialog"
                     aria-modal="true"
                     @keydown.escape.window="closeEdit()">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title">{{ __('Editar usuário') }}</h5>
                                    <div class="text-body-secondary small" x-text="`ID #${form.id}`"></div>
                                </div>
                                <button type="button" class="btn-close" aria-label="Close" @click="closeEdit()"></button>
                            </div>

                            <form method="POST" :action="form.action" @submit="submitting = true">
                                @csrf
                                @method('PUT')

                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">{{ __('Nome') }}</label>
                                            <input name="name"
                                                   type="text"
                                                   class="form-control form-control-lg"
                                                   x-model="form.name"
                                                   required />
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">{{ __('E-mail') }}</label>
                                            <input name="email"
                                                   type="email"
                                                   class="form-control"
                                                   x-model="form.email"
                                                   required />
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label">{{ __('Role') }}</label>
                                            <select name="role"
                                                    class="form-select"
                                                    x-model="form.role"
                                                    required>
                                                @foreach(($roles ?? []) as $role)
                                                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label">{{ __('Status') }}</label>
                                            <select name="status"
                                                    class="form-select"
                                                    x-model="form.status"
                                                    required>
                                                @foreach(($statuses ?? []) as $status)
                                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer d-flex gap-2">
                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            :disabled="submitting"
                                            @click="closeEdit()">
                                        {{ __('Cancelar') }}
                                    </button>
                                    <button type="submit"
                                            class="btn btn-success"
                                            :disabled="submitting">
                                        <span x-show="!submitting">{{ __('Salvar') }}</span>
                                        <span x-show="submitting">{{ __('Salvando...') }}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-700">
                    <div class="font-semibold">{{ __('OK') }}</div>
                    <div class="text-sm mt-1">{{ session('success') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
                    <div class="font-semibold">{{ __('Erro') }}</div>
                    <ul class="text-sm mt-2 list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Adicionar usuário') }}</h3>

                    <form method="POST"
                          action="{{ route('settings.users.store') }}"
                          class="grid grid-cols-1 md:grid-cols-3 gap-4"
                          x-data="{ submitting: false }"
                          @submit="submitting = true">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Nome') }}</label>
                            <input name="name"
                                   type="text"
                                   class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                                   value="{{ old('name') }}"
                                   required />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('E-mail') }}</label>
                            <input name="email"
                                   type="email"
                                   class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                                   value="{{ old('email') }}"
                                   required />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Senha') }}</label>
                            <input name="password"
                                   type="password"
                                   class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                                   required />
                            <div class="mt-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Confirmar senha') }}</label>
                                <input name="password_confirmation"
                                       type="password"
                                       class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                                       required />
                            </div>
                        </div>

                        <div class="md:col-span-3 flex justify-end">
                            <button type="submit" class="btn-primary inline-flex items-center" :disabled="submitting">
                                <svg x-show="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span x-show="!submitting">{{ __('Criar usuário') }}</span>
                                <span x-show="submitting">{{ __('Criando...') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Usuários cadastrados') }}</h3>
                        <span class="text-sm text-gray-500">{{ $users->count() }}</span>
                    </div>

                    @if($users->isEmpty())
                        <div class="text-sm text-gray-600">
                            {{ __('Nenhum usuário filho cadastrado ainda.') }}
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 border-b border-gray-200">
                                        <th class="py-2 pr-4">{{ __('Nome') }}</th>
                                        <th class="py-2 pr-4">{{ __('E-mail') }}</th>
                                        <th class="py-2 pr-4">{{ __('Role') }}</th>
                                        <th class="py-2 pr-4">{{ __('Status') }}</th>
                                        <th class="py-2 text-right">{{ __('Ações') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($users as $user)
                                        @php
                                            $accountId = auth()->user()->accountId();
                                            $isMaster = (int) $user->id === (int) $accountId;
                                        @endphp
                                        <tr>
                                            <td class="py-2 pr-4 font-medium text-gray-900">{{ $user->name }}</td>
                                            <td class="py-2 pr-4 text-gray-700">{{ $user->email }}</td>
                                            <td class="py-2 pr-4">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ $user->role?->label() ?? __('-') }}
                                                </span>
                                            </td>
                                            <td class="py-2 pr-4">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                                    {{ $user->status?->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                    {{ $user->status?->label() ?? __('-') }}
                                                </span>
                                            </td>
                                            <td class="py-2 text-right">
                                                @if($isMaster)
                                                    <span class="text-sm text-gray-500">{{ __('Conta principal') }}</span>
                                                @else
                                                    <button type="button"
                                                            class="text-sm text-brand-700 hover:text-brand-800"
                                                            @click="openEdit({
                                                                id: {{ $user->id }},
                                                                name: @js($user->name),
                                                                email: @js($user->email),
                                                                role: @js($user->role?->value),
                                                                status: @js($user->status?->value),
                                                                action: @js(route('settings.users.update', $user)),
                                                            })">
                                                        {{ __('Editar') }}
                                                    </button>
                                                    <span class="mx-2 text-gray-300">|</span>
                                                    <form method="POST" action="{{ route('settings.users.destroy', $user) }}"
                                                          onsubmit="return confirm('Remover este usuário?');"
                                                          class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-sm text-red-700 hover:text-red-800">
                                                            {{ __('Remover') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
        <script>
            function accountUsers() {
                return {
                    editOpen: false,
                    submitting: false,
                    form: {
                        id: null,
                        name: '',
                        email: '',
                        role: 'user',
                        status: 'active',
                        action: '',
                    },
                    openEdit(data) {
                        this.submitting = false;
                        this.form = { ...this.form, ...data };
                        this.editOpen = true;
                    },
                    closeEdit() {
                        this.editOpen = false;
                        this.submitting = false;
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>

