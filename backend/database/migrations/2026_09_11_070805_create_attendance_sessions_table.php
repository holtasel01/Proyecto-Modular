<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('entry_event_id')->constrained('attendance_events')->cascadeOnDelete();
            $table->foreignId('exit_event_id')->nullable()->constrained('attendance_events')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        DB::statement("ALTER TABLE attendance_sessions ADD CONSTRAINT attendance_sessions_status_check CHECK (status IN ('open', 'closed', 'inconsistent'))");

        // Defensa en profundidad adicional a la lógica de AttendanceService: a nivel de base
        // de datos, un estudiante no puede tener dos sesiones "open" simultáneas (evita
        // condiciones de carrera si llegaran dos eventos casi al mismo tiempo).
        DB::statement('CREATE UNIQUE INDEX attendance_sessions_one_open_per_student ON attendance_sessions (student_id) WHERE status = \'open\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
