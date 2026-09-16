<?php

namespace App\Services;

use App\Exceptions\StudentClusteringException;
use App\Models\Student;
use Illuminate\Support\Facades\Process;

/**
 * Agrupa estudiantes activos por patrón de asistencia usando K-Means
 * (docs/08-mineria-datos.md). Laravel arma las 6 características (con
 * AttendanceFeatureCalculator) a partir de `attendance_sessions` — el propio
 * agrupamiento lo hace scikit-learn en recognition-app/scripts/compute_clusters.py,
 * invocado como subproceso (mismo patrón que FaceEmbeddingComputer para el
 * enrolamiento).
 */
class StudentClusteringService
{
    private const MIN_STUDENTS_FOR_CLUSTERING = 3;

    public function __construct(private readonly AttendanceFeatureCalculator $calculator)
    {
    }

    /**
     * @return array{clusters: array<int, array<string, mixed>>, centroids: array<int, array<string, float>>}
     */
    public function cluster(): array
    {
        $features = $this->buildFeatureVectors();

        if (count($features) < self::MIN_STUDENTS_FOR_CLUSTERING) {
            throw new StudentClusteringException(
                'Hacen falta al menos '.self::MIN_STUDENTS_FOR_CLUSTERING.
                ' estudiantes con al menos una sesión de asistencia cerrada para poder agruparlos. '.
                'Hoy hay '.count($features).'.',
            );
        }

        $result = Process::timeout((int) config('facelog.clustering_timeout_seconds'))
            ->input(json_encode(['students' => $features]))
            ->run([config('facelog.python_bin'), config('facelog.compute_clusters_script')]);

        $output = json_decode($result->output() ?: $result->errorOutput(), associative: true);

        if (! $result->successful() || ! is_array($output) || isset($output['error'])) {
            $message = is_array($output) && isset($output['error'])
                ? $output['error']
                : 'No se pudo calcular el agrupamiento de estudiantes.';

            throw new StudentClusteringException($message);
        }

        if (! isset($output['clusters'], $output['centroids'])) {
            throw new StudentClusteringException('Respuesta inesperada del script de agrupamiento.');
        }

        return $output;
    }

    /**
     * Una fila por estudiante activo con al menos una sesión cerrada — sin
     * eso no hay forma honesta de calcular duración/frecuencia/variabilidad,
     * así que esos estudiantes simplemente no entran al agrupamiento (no se
     * inventa un valor "0" que los haría verse todos idénticos).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildFeatureVectors(): array
    {
        $students = Student::query()->where('estado', 'activo')->get();

        $rows = [];

        foreach ($students as $student) {
            $features = $this->calculator->calculate($student);

            if ($features === null) {
                continue;
            }

            $rows[] = [
                'student_id' => $student->id,
                'matricula' => $student->matricula,
                'nombre' => $student->nombre,
                'features' => $features,
            ];
        }

        return $rows;
    }
}
