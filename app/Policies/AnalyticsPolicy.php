<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class AnalyticsPolicy extends BasePolicy
{
    public function view(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Module insights are available to every staff role; data is scoped to
     * what the role can already see on the module's own page.
     */
    public function viewOpportunities(User $user): bool
    {
        return in_array($user->role, [UserRole::ADMIN, UserRole::MANAGER, UserRole::SALES_REP], true);
    }

    /**
     * Satisfaction insights follow the same role scoping.
     */
    public function viewSatisfaction(User $user): bool
    {
        return in_array($user->role, [UserRole::ADMIN, UserRole::MANAGER, UserRole::SALES_REP], true);
    }
}
