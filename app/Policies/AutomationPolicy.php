<?php

namespace App\Policies;

use App\Models\Automation;
use App\Models\User;

class AutomationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Automation $automation): bool
    {
        return (int) $user->accountId() === (int) $automation->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Automation $automation): bool
    {
        return (int) $user->accountId() === (int) $automation->user_id;
    }

    public function delete(User $user, Automation $automation): bool
    {
        return (int) $user->accountId() === (int) $automation->user_id;
    }
}
