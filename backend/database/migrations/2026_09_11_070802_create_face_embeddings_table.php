<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            $table->string('modelo', 50);
            $table->timestamp('created_at')->useCurrent();
        });

        // Array nativo de PostgreSQL (float4[] / "real[]") — ver docs/02-diseno.md §3.
        // No hay un tipo de columna portable en el Schema Builder de Laravel para esto,
        // así que se agrega con SQL directo (esta migración ya es específica de PostgreSQL).
        DB::statement('ALTER TABLE face_embeddings ADD COLUMN vector real[] NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('face_embeddings');
    }
};
