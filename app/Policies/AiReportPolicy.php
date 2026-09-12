<?php

namespace App\Policies;

use App\Models\User;

class AiReportPolicy extends BasePolicy
{
    /**
     * AI Reports are visible to administrators and managers only.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Generating an AI report is an admin/manager action.
     */
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Report history can be deleted by administrators only.
     */
    public function delete(User $user): bool
    {
        return $this->isAdmin($user);
    }
}
