<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CourseMonitorService;
use App\Models\CourseSubgroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * CourseIntervalMonitorController
 *
 * Gestiona la asignación de monitores a subgrupos por intervalo de fechas.
 */
class CourseIntervalMonitorController extends Controller
{
    protected CourseMonitorService $monitorService;

    public function __construct(CourseMonitorService $monitorService)
    {
        $this->monitorService = $monitorService;
    }

    /**
     * Asignar monitor a un intervalo
     * POST /api/admin/course-intervals/{intervalId}/monitors
     *
     * @param Request $request
     * @param int $intervalId
     * @return JsonResponse
     */
    public function store(Request $request, int $intervalId): JsonResponse
    {
        $validated = $request->validate([
            'course_subgroup_id' => 'required|integer|exists:course_subgroups,id',
            'monitor_id' => 'required|integer|exists:monitors,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $assignment = $this->monitorService->assignMonitorToInterval(
                $intervalId,
                $validated['course_subgroup_id'],
                $validated['monitor_id'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $assignment->load(['monitor', 'courseInterval', 'courseSubgroup']),
                'message' => 'Monitor asignado correctamente al intervalo'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Listar asignaciones de un intervalo
     * GET /api/admin/course-intervals/{intervalId}/monitors
     *
     * @param int $intervalId
     * @return JsonResponse
     */
    public function index(int $intervalId): JsonResponse
    {
        $assignments = $this->monitorService->getMonitorAssignmentsForInterval($intervalId);

        return response()->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    /**
     * Eliminar asignación
     * DELETE /api/admin/course-intervals/{intervalId}/monitors/{subgroupId}
     *
     * @param int $intervalId
     * @param int $subgroupId
     * @return JsonResponse
     */
    public function destroy(int $intervalId, int $subgroupId): JsonResponse
    {
        $deleted = $this->monitorService->removeMonitorFromInterval($intervalId, $subgroupId);

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => 'Asignación eliminada correctamente'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Asignación no encontrada'
        ], 404);
    }

    /**
     * Obtener monitor de un subgrupo para una fecha específica
     * GET /api/admin/course-subgroups/{subgroupId}/monitor?date=2025-10-17
     *
     * @param Request $request
     * @param int $subgroupId
     * @return JsonResponse
     */
    public function getMonitorForDate(Request $request, int $subgroupId): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d'
        ]);

        try {
            $subgroup = CourseSubgroup::findOrFail($subgroupId);
            $date = $request->input('date');

            $details = $this->monitorService->getMonitorDetailsForDate($subgroup, $date);

            return response()->json([
                'success' => true,
                'data' => [
                    'subgroup_id' => $subgroupId,
                    'date' => $date,
                    'monitor' => $details['monitor'],
                    'source' => $details['source'],
                    'interval_id' => $details['interval_id'],
                    'interval_name' => $details['interval_name'],
                    'assignment_id' => $details['assignment_id'],
                    'notes' => $details['notes'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Obtener calendario de monitores para un curso
     * GET /api/admin/courses/{courseId}/monitor-schedule?start_date=2025-10-01&end_date=2025-10-31
     *
     * @param Request $request
     * @param int $courseId
     * @return JsonResponse
     */
    public function getSchedule(Request $request, int $courseId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $schedule = $this->monitorService->getMonitorSchedule($courseId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => [
                'course_id' => $courseId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'schedule' => $schedule
            ]
        ]);
    }

    /**
     * Listar todas las asignaciones de un subgrupo
     * GET /api/admin/course-subgroups/{subgroupId}/monitor-assignments
     *
     * @param int $subgroupId
     * @return JsonResponse
     */
    public function getSubgroupAssignments(int $subgroupId): JsonResponse
    {
        $assignments = $this->monitorService->getMonitorAssignmentsForSubgroup($subgroupId);

        return response()->json([
            'success' => true,
            'data' => [
                'subgroup_id' => $subgroupId,
                'assignments' => $assignments
            ]
        ]);
    }
}
