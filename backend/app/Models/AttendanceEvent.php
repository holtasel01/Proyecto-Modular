<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'device_id',
        'type',
        'confidence',
        'occurred_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
