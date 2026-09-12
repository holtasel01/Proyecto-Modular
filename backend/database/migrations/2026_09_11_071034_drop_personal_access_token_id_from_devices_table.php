<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se corrige el diseño inicial: en vez de que `devices` apunte a un token con un FK
     * propio, `Device` usa el trait `HasApiTokens` de Sanctum directamente — la relación
     * polimórfica estándar (`personal_access_tokens.tokenable_*`) ya resuelve "qué tokens
     * tiene este device" sin necesitar esta columna.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('personal_access_token_id');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
        });
    }
};
