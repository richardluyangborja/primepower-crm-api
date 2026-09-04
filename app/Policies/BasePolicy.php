<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

abstract class BasePolicy
{
    protected function isAdmin(User $user): bool
    {
        return $user->role === UserRole::ADMIN;
    }

    protected function isManager(User $user): bool
    {
        return $user->role === UserRole::MANAGER;
    }

    protected function canManage(User $user): bool
    {
        return in_array($user->role, [UserRole::ADMIN, UserRole::MANAGER], true);
    }

    protected function canSeeOwner(User $user, int $ownerId): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($ownerId === $user->id) {
            return true;
        }

        if ($this->isManager($user)) {
            return $user->visibleUserIds()->contains($ownerId);
        }

        return false;
    }
}
