<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// docs/02-diseno.md §4.4 — corre cada noche para detectar sesiones abandonadas.
Schedule::command('attendance:close-stale-sessions')->dailyAt('02:00');
