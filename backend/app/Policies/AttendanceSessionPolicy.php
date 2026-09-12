<?php

namespace App\Policies;

use App\Models\User;

class AttendanceSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Corrección manual (PATCH) — siempre admin, siempre auditada
     * (ver AuditLogger, docs/02-diseno.md §4.5).
     */
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
