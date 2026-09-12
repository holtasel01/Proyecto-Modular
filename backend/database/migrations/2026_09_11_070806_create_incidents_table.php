<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->foreignId('attendance_session_id')->nullable()->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('attendance_event_id')->nullable()->constrained('attendance_events')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });

        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_type_check CHECK (type IN ('entrada_sin_salida', 'duplicado', 'baja_confianza', 'salida_sin_entrada', 'corregido_manualmente'))");
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_status_check CHECK (status IN ('open', 'resolved'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
