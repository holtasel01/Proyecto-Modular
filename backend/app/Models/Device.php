<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * Implementa Authenticatable (no representa a una persona) para que Sanctum
 * pueda resolverlo como `$request->user()` en las rutas del device — ver
 * routes/api.php y docs/02-diseno.md §7.
 */
class Device extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory;

    protected $fillable = [
        'nombre',
        'ubicacion',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }
}
