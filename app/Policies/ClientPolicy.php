<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return $this->canSeeOwner($user, $client->assigned_to_id);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user) || $user->isSalesRep();
    }

    public function update(User $user, Client $client): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        return $client->assigned_to_id === $user->id;
    }

    public function updateStatus(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }

    public function reassign(User $user, Client $client): bool
    {
        return $this->canManage($user);
    }
}
