<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'matricula',
        'nombre',
        'carrera',
        'horas_meta',
        'estado',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function faceEmbedding(): HasOne
    {
        return $this->hasOne(FaceEmbedding::class);
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function isActive(): bool
    {
        return $this->estado === 'activo';
    }
}
