<?php

namespace App\Policies;

use App\Models\SurveyTemplate;
use App\Models\User;

class SurveyTemplatePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        // Templates are read-only org policy, visible so the "send survey"
        // flow can list them across all three role surfaces.
        return true;
    }

    public function view(User $user, SurveyTemplate $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, SurveyTemplate $template): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, SurveyTemplate $template): bool
    {
        return $this->isAdmin($user);
    }
}
