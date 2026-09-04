<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->canSeeOwner($user, $lead->assigned_to_id);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user) || $user->isSalesRep();
    }

    public function update(User $user, Lead $lead): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        return $lead->assigned_to_id === $user->id;
    }

    public function updateStatus(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead);
    }

    public function reassign(User $user, Lead $lead): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $this->canManage($user);
    }
}
