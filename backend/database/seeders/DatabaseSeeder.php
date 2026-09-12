<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@facelog.test'],
            [
                'name' => 'Administrador Facelog',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ],
        );

        // Defaults de negocio (docs/02-diseno.md §4 y §5) — el admin puede
        // ajustarlos después desde /api/settings sin necesitar un despliegue nuevo.
        $defaults = [
            'min_confidence_reject' => '0.50',
            'min_confidence_trust' => '0.75',
            'duplicate_window_seconds' => '30',
            'max_session_hours' => '12',
            'default_horas_meta' => '480',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }
    }
}
