<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CourseIntervalGroup;
use App\Models\CourseIntervalSubgroup;
use App\Models\CourseInterval;
use App\Models\CourseGroup;
use App\Models\CourseSubgroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CourseIntervalGroupAPIController extends Controller
{
    /**
     * Get groups configuration for a specific interval.
     */
    public function indexForInterval(Request $request, string $intervalId)
    {
        $interval = CourseInterval::find($intervalId);

        if (!$interval) {
            return response()->json([
                'success' => false,
                'message' => 'Intervalo no encontrado',
            ], 404);
        }

        $intervalGroups = CourseIntervalGroup::where('course_interval_id', $intervalId)
            ->with([
                'courseGroup' => function($query) {
                    $query->with('degree');
                },
                'intervalSubgroups.courseSubgroup'
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $intervalGroups,
        ]);
    }

    /**
     * Get groups configuration for a course (all intervals).
     */
    public function indexForCourse(Request $request, string $courseId)
    {
        $intervalGroups = CourseIntervalGroup::where('course_id', $courseId)
            ->with([
                'courseInterval',
                'courseGroup.degree',
                'intervalSubgroups.courseSubgroup'
            ])
            ->get();

        // Group by interval
        $grouped = $intervalGroups->groupBy('course_interval_id');

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * Store or update groups configuration for an interval.
     *
     * Expected payload:
     * {
     *   "course_id": 1,
     *   "course_interval_id": 1,
     *   "groups": [
     *     {
     *       "course_group_id": 1,
     *       "max_participants": 10,
     *       "active": true,
     *       "subgroups": [
     *         { "course_subgroup_id": 1, "max_participants": 5, "active": true }
     *       ]
     *     }
     *   ]
     * }
     */
    public function storeForInterval(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'course_interval_id' => 'required|exists:course_intervals,id',
            'groups' => 'required|array',
            'groups.*.course_group_id' => 'required|exists:course_groups,id',
            'groups.*.max_participants' => 'nullable|integer|min:1',
            'groups.*.active' => 'boolean',
            'groups.*.subgroups' => 'nullable|array',
            'groups.*.subgroups.*.course_subgroup_id' => 'required|exists:course_subgroups,id',
            'groups.*.subgroups.*.max_participants' => 'nullable|integer|min:1',
            'groups.*.subgroups.*.active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $courseId = $request->course_id;
            $intervalId = $request->course_interval_id;

            $interval = CourseInterval::where('id', $intervalId)
                ->where('course_id', $courseId)
                ->first();

            if (!$interval) {
                return response()->json([
                    'success' => false,
                    'message' => 'El intervalo no pertenece al curso indicado.',
                ], 422);
            }

            $courseGroups = CourseGroup::where('course_id', $courseId)
                ->get()
                ->keyBy('id');

            if ($courseGroups->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El curso no tiene grupos configurados.',
                ], 422);
            }

            $courseSubgroups = CourseSubgroup::whereIn('course_group_id', $courseGroups->keys())
                ->get()
                ->groupBy('course_group_id');

            // Delete existing groups for this interval
            CourseIntervalGroup::where('course_id', $courseId)
                ->where('course_interval_id', $intervalId)
                ->delete();

            $createdGroups = [];

            // Create new groups
            foreach ($request->groups as $groupData) {
                if (!$courseGroups->has($groupData['course_group_id'])) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "El grupo {$groupData['course_group_id']} no pertenece al curso.",
                    ], 422);
                }

                $intervalGroup = CourseIntervalGroup::create([
                    'course_id' => $courseId,
                    'course_interval_id' => $intervalId,
                    'course_group_id' => $groupData['course_group_id'],
                    'max_participants' => $groupData['max_participants'] ?? null,
                    'active' => $groupData['active'] ?? true,
                ]);

                // Create subgroups
                if (isset($groupData['subgroups'])) {
                    foreach ($groupData['subgroups'] as $subgroupData) {
                        $groupId = $groupData['course_group_id'];
                        $validSubgroups = $courseSubgroups->get($groupId) ?? collect();
                        if (!$validSubgroups->contains('id', $subgroupData['course_subgroup_id'])) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "El subgrupo {$subgroupData['course_subgroup_id']} no pertenece al grupo {$groupId}.",
                            ], 422);
                        }

                        CourseIntervalSubgroup::create([
                            'course_interval_group_id' => $intervalGroup->id,
                            'course_subgroup_id' => $subgroupData['course_subgroup_id'],
                            'max_participants' => $subgroupData['max_participants'] ?? null,
                            'active' => $subgroupData['active'] ?? true,
                        ]);
                    }
                }

                $createdGroups[] = $intervalGroup->load('intervalSubgroups.courseSubgroup');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Grupos del intervalo configurados exitosamente',
                'data' => $createdGroups,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al configurar grupos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply course-level groups to all intervals (global configuration).
     */
    public function applyGlobalGroups(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'groups' => 'required|array',
            'groups.*.course_group_id' => 'required|exists:course_groups,id',
            'groups.*.max_participants' => 'nullable|integer|min:1',
            'groups.*.active' => 'boolean',
            'groups.*.subgroups' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $courseId = $request->course_id;

            $courseGroups = CourseGroup::where('course_id', $courseId)
                ->get()
                ->keyBy('id');

            if ($courseGroups->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El curso no tiene grupos configurados.',
                ], 422);
            }

            $courseSubgroups = CourseSubgroup::whereIn('course_group_id', $courseGroups->keys())
                ->get()
                ->groupBy('course_group_id');

            // Get all intervals for this course
            $intervals = CourseInterval::where('course_id', $courseId)->get();

            if ($intervals->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay intervalos definidos para este curso',
                ], 400);
            }

            // Delete all existing interval groups for this course
            CourseIntervalGroup::where('course_id', $courseId)->delete();

            $totalCreated = 0;

            // Apply configuration to each interval
            foreach ($intervals as $interval) {
                foreach ($request->groups as $groupData) {
                    if (!$courseGroups->has($groupData['course_group_id'])) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "El grupo {$groupData['course_group_id']} no pertenece al curso.",
                        ], 422);
                    }

                    $intervalGroup = CourseIntervalGroup::create([
                        'course_id' => $courseId,
                        'course_interval_id' => $interval->id,
                        'course_group_id' => $groupData['course_group_id'],
                        'max_participants' => $groupData['max_participants'] ?? null,
                        'active' => $groupData['active'] ?? true,
                    ]);

                    if (isset($groupData['subgroups'])) {
                        foreach ($groupData['subgroups'] as $subgroupData) {
                            $groupId = $groupData['course_group_id'];
                            $validSubgroups = $courseSubgroups->get($groupId) ?? collect();
                            if (!$validSubgroups->contains('id', $subgroupData['course_subgroup_id'])) {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => "El subgrupo {$subgroupData['course_subgroup_id']} no pertenece al grupo {$groupId}.",
                                ], 422);
                            }

                            CourseIntervalSubgroup::create([
                                'course_interval_group_id' => $intervalGroup->id,
                                'course_subgroup_id' => $subgroupData['course_subgroup_id'],
                                'max_participants' => $subgroupData['max_participants'] ?? null,
                                'active' => $subgroupData['active'] ?? true,
                            ]);
                        }
                    }

                    $totalCreated++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Configuración global aplicada a todos los intervalos',
                'data' => [
                    'intervals_configured' => $intervals->count(),
                    'groups_created' => $totalCreated,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al aplicar configuración global: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete groups configuration for a specific interval.
     */
    public function deleteForInterval(Request $request, string $intervalId)
    {
        try {
            DB::beginTransaction();

            $deleted = CourseIntervalGroup::where('course_interval_id', $intervalId)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Configuración de grupos eliminada',
                'data' => ['deleted_count' => $deleted],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar configuración: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a course has interval-specific groups configuration.
     */
    public function hasIntervalGroups(Request $request, string $courseId)
    {
        $count = CourseIntervalGroup::where('course_id', $courseId)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'has_interval_groups' => $count > 0,
                'total_configurations' => $count,
            ],
        ]);
    }
}
