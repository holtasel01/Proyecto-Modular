<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Único punto de escritura de audit_logs (docs/02-diseno.md §4.5 y §8).
 * Nunca se debe insertar en audit_logs directamente fuera de esta clase,
 * para garantizar que toda modificación sensible quede registrada igual.
 */
class AuditLogger
{
    public function log(User $actor, Model $auditable, string $action, array $oldValues, array $newValues): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $actor->id,
            'auditable_type' => class_basename($auditable),
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
