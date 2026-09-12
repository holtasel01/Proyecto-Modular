<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('matricula', 20)->unique();
            $table->string('nombre', 150);
            $table->string('carrera', 100)->nullable();
            $table->unsignedInteger('horas_meta');
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_estado_check CHECK (estado IN ('activo', 'inactivo'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
