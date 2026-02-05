<?php

namespace App\Policies;

use App\Models\Lista;
use App\Models\User;

class ListaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lista $lista): bool
    {
        return (int) $user->accountId() === (int) $lista->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lista $lista): bool
    {
        return (int) $user->accountId() === (int) $lista->user_id;
    }

    public function delete(User $user, Lista $lista): bool
    {
        return (int) $user->accountId() === (int) $lista->user_id;
    }
}
