<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class AccountUsersController extends Controller
{
    public function index(): View
    {
        $accountId = auth()->user()->accountId();

        $users = User::query()
            ->where('account_id', $accountId)
            // mostra o master primeiro (id == account_id), depois os demais
            ->orderByRaw('CASE WHEN id = account_id THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status', 'role', 'created_at']);

        return view('settings.users', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = auth()->user()->accountId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'account_id' => $accountId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::User,
            'status' => UserStatus::Active,
        ]);

        return back()->with('success', 'Usuário criado com sucesso!');
    }

    public function destroy(User $user): RedirectResponse
    {
        $accountId = auth()->user()->accountId();

        // Só permite remover usuários "filho" desta conta
        if ((int) $user->account_id !== (int) $accountId || (int) $user->id === (int) $accountId) {
            abort(404);
        }

        $user->delete();

        return back()->with('success', 'Usuário removido com sucesso!');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $accountId = auth()->user()->accountId();

        // Só permite editar usuários "filho" desta conta
        if ((int) $user->account_id !== (int) $accountId || (int) $user->id === (int) $accountId) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::in(UserRole::values())],
            'status' => ['required', Rule::in(UserStatus::values())],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Usuário atualizado com sucesso!');
    }
}

