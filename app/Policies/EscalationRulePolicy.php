<?php

namespace App\Policies;

use App\Models\EscalationRule;
use App\Models\User;

class EscalationRulePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        // Rules are read-only org policy, visible across all three role surfaces.
        return true;
    }

    public function view(User $user, EscalationRule $rule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, EscalationRule $rule): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, EscalationRule $rule): bool
    {
        return $this->isAdmin($user);
    }
}
