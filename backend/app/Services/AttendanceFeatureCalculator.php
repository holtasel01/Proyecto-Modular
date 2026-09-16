<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Calcula, a partir de las sesiones de asistencia cerradas de un estudiante,
 * las mismas 6 métricas de patrón de asistencia que usan tanto el
 * agrupamiento K-Means (docs/08-mineria-datos.md) como la predicción
 * personal del estudiante (docs/09-prediccion-estudiante.md) — un solo
 * lugar para esta lógica, para que ambas features midan exactamente lo mismo.
 */
class AttendanceFeatureCalculator
{
    /**
     * @return array{horas_acumuladas: float, numero_sesiones: int, duracion_promedio_sesion_min: float, horas_promedio_semana: float, sesiones_promedio_semana: float, variabilidad_semanal: float}|null
     *         null si el estudiante no tiene ninguna sesión cerrada — sin eso
     *         no hay forma honesta de calcular duración/frecuencia/variabilidad.
     */
    public function calculate(Student $student): ?array
    {
        $sessions = $student->attendanceSessions()
            ->where('status', 'closed')
            ->orderBy('started_at')
            ->get(['started_at', 'duration_minutes']);

        if ($sessions->isEmpty()) {
            return null;
        }

        $totalMinutes = (int) $sessions->sum('duration_minutes');
        $numSesiones = $sessions->count();

        /** @var Carbon $first */
        $first = $sessions->first()->started_at;
        /** @var Carbon $last */
        $last = $sessions->last()->started_at;

        // +1 para contar inclusivamente el propio día de la primera sesión;
        // mínimo 1 semana para no dividir entre cero cuando todo ocurrió el
        // mismo día.
        $daysSpan = (int) $first->diffInDays($last);
        $weeksActive = max(1, (int) ceil(($daysSpan + 1) / 7));

        $horasAcumuladas = round($totalMinutes / 60, 2);
        $duracionPromedioMin = round($totalMinutes / $numSesiones, 1);
        $horasPromedioSemana = round($horasAcumuladas / $weeksActive, 2);
        $sesionesPromedioSemana = round($numSesiones / $weeksActive, 2);
        $variabilidadSemanal = round($this->weeklyHoursStdDev($sessions, $first, $weeksActive), 2);

        return [
            'horas_acumuladas' => $horasAcumuladas,
            'numero_sesiones' => $numSesiones,
            'duracion_promedio_sesion_min' => $duracionPromedioMin,
            'horas_promedio_semana' => $horasPromedioSemana,
            'sesiones_promedio_semana' => $sesionesPromedioSemana,
            'variabilidad_semanal' => $variabilidadSemanal,
        ];
    }

    /**
     * Desviación estándar (poblacional) de las horas trabajadas por semana,
     * contando también las semanas sin ninguna sesión dentro del rango de
     * actividad del estudiante — un estudiante constante tiene un valor
     * bajo; uno que trabaja en ráfagas, uno alto.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, AttendanceSession>  $sessions
     */
    private function weeklyHoursStdDev($sessions, Carbon $first, int $weeksActive): float
    {
        $minutesPerWeek = array_fill(0, $weeksActive, 0);

        foreach ($sessions as $session) {
            $weekIndex = (int) floor($first->diffInDays($session->started_at) / 7);
            $weekIndex = min($weekIndex, $weeksActive - 1);
            $minutesPerWeek[$weekIndex] += (int) $session->duration_minutes;
        }

        $hoursPerWeek = array_map(fn (int $minutes) => $minutes / 60, $minutesPerWeek);
        $mean = array_sum($hoursPerWeek) / count($hoursPerWeek);

        $variance = array_sum(array_map(fn (float $h) => ($h - $mean) ** 2, $hoursPerWeek)) / count($hoursPerWeek);

        return sqrt($variance);
    }
}
