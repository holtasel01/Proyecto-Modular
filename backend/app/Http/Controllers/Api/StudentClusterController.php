<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\StudentClusteringException;
use App\Http\Controllers\Controller;
use App\Services\StudentClusteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minería de datos: agrupa estudiantes activos por patrón de asistencia con
 * K-Means (docs/08-mineria-datos.md). Solo admin — no hay Policy formal
 * porque, igual que AuditLogController, este recurso no tiene un modelo
 * propio al que atarle una (docs/03-seguridad.md §3).
 */
class StudentClusterController extends Controller
{
    public function __construct(private readonly StudentClusteringService $clusteringService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        try {
            // El resultado se cachea por unos minutos (StudentClusteringService)
            // porque recalcular implica arrancar Python + scikit-learn desde
            // cero en cada request — "Recalcular" en el frontend manda
            // ?refresh=1 para saltarse esa caché a propósito.
            $result = $this->clusteringService->cluster(forceRefresh: $request->boolean('refresh'));
        } catch (StudentClusteringException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json($result);
    }
}
