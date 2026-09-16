<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Proyección personal de un estudiante hacia su meta de horas
 * (docs/09-prediccion-estudiante.md): a su ritmo actual, cuánto le falta y
 * cuándo terminaría. Reutiliza las mismas 6 métricas de patrón de asistencia
 * que el agrupamiento K-Means (AttendanceFeatureCalculator) — miden lo mismo,
 * solo que aquí es para un único estudiante viendo su propio progreso, no
 * para compararlo contra el resto.
 */
class StudentProjectionService
{
    public function __construct(private readonly AttendanceFeatureCalculator $calculator)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function project(Student $student): array
    {
        $horasMeta = (float) $student->horas_meta;
        $features = $this->calculator->calculate($student);

        if ($features === null) {
            return [
                'tiene_datos' => false,
                'horas_acumuladas' => 0.0,
                'horas_meta' => $horasMeta,
                'horas_restantes' => $horasMeta,
                'progreso_porcentaje' => 0.0,
                'meta_cumplida' => false,
                'numero_sesiones' => 0,
                'duracion_promedio_sesion_min' => null,
                'horas_promedio_semana' => null,
                'horas_promedio_dia' => null,
                'sesiones_promedio_semana' => null,
                'variabilidad_semanal' => null,
                'semanas_restantes_estimadas' => null,
                'dias_restantes_estimados' => null,
                'sesiones_restantes_estimadas' => null,
                'fecha_estimada_finalizacion' => null,
            ];
        }

        $horasAcumuladas = $features['horas_acumuladas'];
        $horasRestantes = max(0.0, round($horasMeta - $horasAcumuladas, 2));
        $metaCumplida = $horasRestantes <= 0.0;
        $progreso = $horasMeta > 0
            ? round(min(100, ($horasAcumuladas / $horasMeta) * 100), 1)
            : 0.0;

        $horasPromedioSemana = $features['horas_promedio_semana'];
        $horasPromedioDia = round($horasPromedioSemana / 7, 2);
        $duracionPromedioHoras = $features['duracion_promedio_sesion_min'] / 60;

        // Sin ritmo (0 horas/semana) o meta ya cumplida: no se puede estimar
        // una fecha, se deja en null en vez de inventar una división por
        // cero o un infinito no representable en JSON.
        $semanasRestantes = null;
        $diasRestantes = null;
        $sesionesRestantes = null;
        $fechaEstimada = null;

        if (! $metaCumplida && $horasPromedioSemana > 0) {
            $semanasRestantes = round($horasRestantes / $horasPromedioSemana, 1);
            $diasRestantes = (int) round($semanasRestantes * 7);
            $fechaEstimada = Carbon::now()->addDays($diasRestantes)->toDateString();

            if ($duracionPromedioHoras > 0) {
                $sesionesRestantes = (int) ceil($horasRestantes / $duracionPromedioHoras);
            }
        }

        return [
            'tiene_datos' => true,
            'horas_acumuladas' => $horasAcumuladas,
            'horas_meta' => $horasMeta,
            'horas_restantes' => $horasRestantes,
            'progreso_porcentaje' => $progreso,
            'meta_cumplida' => $metaCumplida,
            'numero_sesiones' => $features['numero_sesiones'],
            'duracion_promedio_sesion_min' => $features['duracion_promedio_sesion_min'],
            'horas_promedio_semana' => $horasPromedioSemana,
            'horas_promedio_dia' => $horasPromedioDia,
            'sesiones_promedio_semana' => $features['sesiones_promedio_semana'],
            'variabilidad_semanal' => $features['variabilidad_semanal'],
            'semanas_restantes_estimadas' => $semanasRestantes,
            'dias_restantes_estimados' => $diasRestantes,
            'sesiones_restantes_estimadas' => $sesionesRestantes,
            'fecha_estimada_finalizacion' => $fechaEstimada,
        ];
    }
}
