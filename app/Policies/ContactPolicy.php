<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy extends BasePolicy
{
    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->isSalesRep();
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->isAdmin($user) || $user->isSalesRep();
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->isAdmin($user) || $user->isSalesRep();
    }
}
