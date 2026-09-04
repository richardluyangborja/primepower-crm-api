<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->canManage($user);
    }

    public function export(User $user): bool
    {
        return $this->isAdmin($user);
    }
}
