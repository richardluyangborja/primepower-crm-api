<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $this->canSeeOwner($user, $opportunity->assigned_to_id);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user) || $user->isSalesRep();
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        return $opportunity->assigned_to_id === $user->id;
    }

    public function updateStage(User $user, Opportunity $opportunity): bool
    {
        return $this->update($user, $opportunity);
    }

    public function win(User $user, Opportunity $opportunity): bool
    {
        return $this->update($user, $opportunity);
    }
}
