<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy extends BasePolicy
{
    public function update(User $user, Company $company): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($user->isSalesRep()) {
            return $company->leads()->where('assigned_to_id', $user->id)->exists()
                || ($company->client && $company->client->assigned_to_id === $user->id);
        }

        return false;
    }
}
