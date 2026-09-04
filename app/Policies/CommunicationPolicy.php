<?php

namespace App\Policies;

use App\Models\Communication;
use App\Models\User;

class CommunicationPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Communication $communication): bool
    {
        if ($communication->user_id === null) {
            return $this->canManage($user);
        }

        return $this->canSeeOwner($user, $communication->user_id);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user) || $user->isSalesRep();
    }

    public function update(User $user, Communication $communication): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        return $communication->user_id === $user->id;
    }

    public function delete(User $user, Communication $communication): bool
    {
        return $this->update($user, $communication);
    }
}
