<?php

namespace App\Policies;

use App\Models\Funnel;
use App\Models\User;

class FunnelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Funnel $funnel): bool
    {
        return (int) $user->accountId() === (int) $funnel->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Funnel $funnel): bool
    {
        return (int) $user->accountId() === (int) $funnel->user_id;
    }

    public function delete(User $user, Funnel $funnel): bool
    {
        return (int) $user->accountId() === (int) $funnel->user_id;
    }
}
