<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'entry_event_id',
        'exit_event_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function entryEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'entry_event_id');
    }

    public function exitEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'exit_event_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
