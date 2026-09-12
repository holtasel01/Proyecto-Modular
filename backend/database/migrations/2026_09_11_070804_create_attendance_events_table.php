<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('type', 10);
            $table->decimal('confidence', 5, 4);
            $table->timestamp('occurred_at');
            $table->string('source', 20)->default('face_recognition');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'occurred_at']);
        });

        DB::statement("ALTER TABLE attendance_events ADD CONSTRAINT attendance_events_type_check CHECK (type IN ('entry', 'exit'))");
        DB::statement("ALTER TABLE attendance_events ADD CONSTRAINT attendance_events_source_check CHECK (source IN ('face_recognition', 'manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
