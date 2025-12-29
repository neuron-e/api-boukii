<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Services\Admin\DashboardMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardMetricsController extends AppBaseController
{
    protected DashboardMetricsService $metricsService;

    public function __construct(DashboardMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * GET /admin/dashboard/metrics
     *
     * Endpoint unificado y optimizado para todas las métricas del dashboard
     * Reduce ~20 llamadas API a 1 sola, con queries SQL directas y cache
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Asegurar que school_id esté en el request desde el bearer token
        $this->ensureSchoolInRequest($request);

        $school = $this->getSchool($request);

        if (!$school) {
            return $this->sendError('School not found', [], 404);
        }

        $schoolId = $school->id;
        $date = $request->input('date'); // Optional, defaults to today

        $metrics = $this->metricsService->getMetrics($schoolId, $date);

        return $this->sendResponse($metrics, 'Dashboard metrics retrieved successfully');
    }
}
