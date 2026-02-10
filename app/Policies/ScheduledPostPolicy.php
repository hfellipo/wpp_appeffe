<?php

namespace App\Policies;

use App\Models\ScheduledPost;
use App\Models\User;

class ScheduledPostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function delete(User $user, ScheduledPost $scheduledPost): bool
    {
        return (int) $user->accountId() === (int) $scheduledPost->user_id;
    }

    public function sendNow(User $user, ScheduledPost $scheduledPost): bool
    {
        return (int) $user->accountId() === (int) $scheduledPost->user_id;
    }
}
