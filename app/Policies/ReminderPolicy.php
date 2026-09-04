<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Reminder $reminder): bool
    {
        return $this->canSeeOwner($user, $reminder->user_id ?? 0);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user) || $user->isSalesRep();
    }

    public function update(User $user, Reminder $reminder): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        return $reminder->user_id === $user->id;
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->canManage($user);
    }
}
