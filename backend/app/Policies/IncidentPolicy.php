<?php

namespace App\Policies;

use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
