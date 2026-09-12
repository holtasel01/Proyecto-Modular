<?php

namespace App\Models;

use App\Casts\PostgresFloatArray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceEmbedding extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'vector',
        'modelo',
    ];

    protected function casts(): array
    {
        return [
            'vector' => PostgresFloatArray::class,
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
