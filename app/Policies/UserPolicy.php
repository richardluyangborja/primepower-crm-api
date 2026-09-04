<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, User $subject): bool
    {
        return $this->isAdmin($user) || $user->id === $subject->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, User $subject): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, User $subject): bool
    {
        if (! $this->isAdmin($user)) {
            return false;
        }

        return $user->id !== $subject->id;
    }

    public function activate(User $user, User $subject): bool
    {
        return $this->isAdmin($user) && $user->id !== $subject->id;
    }

    public function deactivate(User $user, User $subject): bool
    {
        return $this->activate($user, $subject);
    }

    public function resetPassword(User $user, User $subject): bool
    {
        return $this->isAdmin($user) && $user->id !== $subject->id;
    }

    public function export(User $user): bool
    {
        return $this->isAdmin($user);
    }
}
