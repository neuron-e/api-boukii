<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateCourseSubgroupAPIRequest;
use App\Http\Requests\API\UpdateCourseSubgroupAPIRequest;
use App\Http\Resources\API\CourseSubgroupResource;
use App\Models\BookingUser;
use App\Models\CourseGroup;
use App\Models\CourseSubgroupDate;
use App\Models\CourseSubgroup;
use AppModelsCourseIntervalMonitor;
use AppModelsCourse;
use App\Repositories\CourseSubgroupRepository;
use App\Services\CourseRepairDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class CourseSubgroupController
 */

class CourseSubgroupAPIController extends AppBaseController
{
    /** @var  CourseSubgroupRepository */
    private $courseSubgroupRepository;
    private CourseRepairDispatcher $repairDispatcher;

    public function __construct(CourseSubgroupRepository $courseSubgroupRepo, CourseRepairDispatcher $repairDispatcher)
    {
        $this->courseSubgroupRepository = $courseSubgroupRepo;
        $this->repairDispatcher = $repairDispatcher;
    }

    /**
     * @OA\Get(
     *      path="/course-subgroups",
     *      summary="getCourseSubgroupList",
     *      tags={"CourseSubgroup"},
     *      description="Get all CourseSubgroups",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/CourseSubgroup")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $courseSubgroups = $this->courseSubgroupRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($courseSubgroups, 'Course Subgroups retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/course-subgroups",
     *      summary="createCourseSubgroup",
     *      tags={"CourseSubgroup"},
     *      description="Create CourseSubgroup",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/CourseSubgroup")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/CourseSubgroup"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateCourseSubgroupAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        // Si se está asignando un monitor, verificar que no tenga NWDs en las fechas del curso
        if (isset($input['monitor_id']) && isset($input['course_date_id'])) {
            $courseDate = \App\Models\CourseDate::find($input['course_date_id']);

            if ($courseDate) {
                $date = $courseDate->date;
                $startTime = $courseDate->hour_start;
                $endTime = $courseDate->hour_end;

                // Verificar si el monitor está ocupado
                if (\App\Models\Monitor::isMonitorBusy($input['monitor_id'], $date, $startTime, $endTime)) {
                    \Illuminate\Support\Facades\Log::channel('availability')->warning('Intento de crear subgrupo con monitor ocupado', [
                        'monitor_id' => $input['monitor_id'],
                        'course_date_id' => $input['course_date_id'],
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime
                    ]);

                    return $this->sendError('El monitor no está disponible en este horario (tiene reservas, NWDs u otros cursos asignados)', 409);
                }
            }
        }

        // NUEVO: Auto-generate subgroup_dates_id for homonymous subgroups
        if (!isset($input['subgroup_dates_id']) || empty($input['subgroup_dates_id'])) {
            // Check if another subgroup with same (course_id, degree_id, course_group_id) exists
            $existingSubgroup = CourseSubgroup::where('course_id', $input['course_id'])
                ->where('degree_id', $input['degree_id'])
                ->where('course_group_id', $input['course_group_id'])
                ->whereNotNull('subgroup_dates_id')
                ->first();

            if ($existingSubgroup) {
                $input['subgroup_dates_id'] = $existingSubgroup->subgroup_dates_id;
            } else {
                // Generate new ID
                $maxNum = \Illuminate\Support\Facades\DB::table('course_subgroups')
                    ->whereNotNull('subgroup_dates_id')
                    ->get()
                    ->map(function($row) {
                        $matches = [];
                        preg_match('/SG-(\d+)/', $row->subgroup_dates_id, $matches);
                        return isset($matches[1]) ? (int)$matches[1] : 0;
                    })
                    ->max() ?? 0;

                $input['subgroup_dates_id'] = 'SG-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
            }
        }

        $courseSubgroup = $this->courseSubgroupRepository->create($input);

        // FIXED BUG #3: Auto-sync monitor assignment to CourseIntervalMonitor table
        // When a subgroup is created with a monitor AND the course uses intervals,
        // automatically create the interval assignment for the planer
        if (isset($input['monitor_id']) && $courseSubgroup->course_id) {
            $course = Course::find($courseSubgroup->course_id);

            // Check if course uses intervals
            if ($course && $course->intervals_config_mode === 'intervals') {
                // Find the interval for this subgroup's course date
                $courseDate = $courseSubgroup->courseDate;

                if ($courseDate && $courseDate->course_interval_id) {
                    // Create or update CourseIntervalMonitor assignment
                    CourseIntervalMonitor::updateOrCreate(
                        [
                            'course_interval_id' => $courseDate->course_interval_id,
                            'course_subgroup_id' => $courseSubgroup->id,
                        ],
                        [
                            'course_id' => $course->id,
                            'monitor_id' => $input['monitor_id'],
                            'active' => true,
                            'notes' => 'Auto-created from course creation',
                        ]
                    );
                }
            }
        }

        $this->repairDispatcher->dispatchForSchool(optional($courseSubgroup->course)->school_id);
        return $this->sendResponse($courseSubgroup, 'Course Subgroup saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/course-subgroups/{id}",
     *      summary="getCourseSubgroupItem",
     *      tags={"CourseSubgroup"},
     *      description="Get CourseSubgroup",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of CourseSubgroup",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/CourseSubgroup"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show($id, Request $request): JsonResponse
    {
        /** @var CourseSubgroup $courseSubgroup */
        $courseSubgroup = $this->courseSubgroupRepository->find($id, with: $request->get('with', []));

        if (empty($courseSubgroup)) {
            return $this->sendError('Course Subgroup not found');
        }

        return $this->sendResponse($courseSubgroup, 'Course Subgroup retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/course-subgroups/{id}",
     *      summary="updateCourseSubgroup",
     *      tags={"CourseSubgroup"},
     *      description="Update CourseSubgroup",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of CourseSubgroup",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/CourseSubgroup")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/CourseSubgroup"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateCourseSubgroupAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var CourseSubgroup $courseSubgroup */
        $courseSubgroup = $this->courseSubgroupRepository->find($id, with: $request->get('with', []));

        if (empty($courseSubgroup)) {
            return $this->sendError('Course Subgroup not found');
        }

        // Si se está asignando un monitor, verificar que no tenga NWDs en las fechas del curso
        if (isset($input['monitor_id']) && $input['monitor_id'] != $courseSubgroup->monitor_id) {
            $courseSubgroup->load('courseDate');

            if ($courseSubgroup->courseDate) {
                $date = $courseSubgroup->courseDate->date;
                $startTime = $courseSubgroup->courseDate->hour_start;
                $endTime = $courseSubgroup->courseDate->hour_end;

                // Verificar si el monitor está ocupado
                if (\App\Models\Monitor::isMonitorBusy($input['monitor_id'], $date, $startTime, $endTime)) {
                    \Illuminate\Support\Facades\Log::channel('availability')->warning('Intento de asignar monitor ocupado a subgrupo', [
                        'subgroup_id' => $id,
                        'monitor_id' => $input['monitor_id'],
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime
                    ]);

                    return $this->sendError('El monitor no está disponible en este horario (tiene reservas, NWDs u otros cursos asignados)', 409);
                }
            }
        }

        // NUEVO: Ensure subgroup_dates_id is maintained or generated if needed
        if (!isset($input['subgroup_dates_id']) || empty($input['subgroup_dates_id'])) {
            if ($courseSubgroup->subgroup_dates_id) {
                // Keep existing ID
                $input['subgroup_dates_id'] = $courseSubgroup->subgroup_dates_id;
            } else {
                // Generate new ID for existing subgroup
                $existingSubgroup = CourseSubgroup::where('course_id', $courseSubgroup->course_id)
                    ->where('degree_id', $courseSubgroup->degree_id)
                    ->where('course_group_id', $courseSubgroup->course_group_id)
                    ->where('id', '!=', $id)
                    ->whereNotNull('subgroup_dates_id')
                    ->first();

                if ($existingSubgroup) {
                    $input['subgroup_dates_id'] = $existingSubgroup->subgroup_dates_id;
                } else {
                    // Generate new ID
                    $maxNum = \Illuminate\Support\Facades\DB::table('course_subgroups')
                        ->whereNotNull('subgroup_dates_id')
                        ->get()
                        ->map(function($row) {
                            $matches = [];
                            preg_match('/SG-(\d+)/', $row->subgroup_dates_id, $matches);
                            return isset($matches[1]) ? (int)$matches[1] : 0;
                        })
                        ->max() ?? 0;

                    $input['subgroup_dates_id'] = 'SG-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
                }
            }
        }

        $courseSubgroup = $this->courseSubgroupRepository->update($input, $id);

        // FIXED BUG #3: Auto-sync monitor assignment to CourseIntervalMonitor table
        // When a subgroup is updated with a monitor AND the course uses intervals,
        // automatically update the interval assignment for the planer
        if (isset($input['monitor_id']) && $courseSubgroup->course_id) {
            $course = Course::find($courseSubgroup->course_id);

            // Check if course uses intervals
            if ($course && $course->intervals_config_mode === 'intervals') {
                // Find the interval for this subgroup's course date
                $courseDate = $courseSubgroup->courseDate;

                if ($courseDate && $courseDate->course_interval_id) {
                    // Create or update CourseIntervalMonitor assignment
                    CourseIntervalMonitor::updateOrCreate(
                        [
                            'course_interval_id' => $courseDate->course_interval_id,
                            'course_subgroup_id' => $courseSubgroup->id,
                        ],
                        [
                            'course_id' => $course->id,
                            'monitor_id' => $input['monitor_id'],
                            'active' => true,
                            'notes' => 'Updated from course edit',
                        ]
                    );
                }
            }
        }

        $this->repairDispatcher->dispatchForSchool(optional($courseSubgroup->course)->school_id);
        return $this->sendResponse(new CourseSubgroupResource($courseSubgroup), 'CourseSubgroup updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/course-subgroups/{id}",
     *      summary="deleteCourseSubgroup",
     *      tags={"CourseSubgroup"},
     *      description="Delete CourseSubgroup",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of CourseSubgroup",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function destroy($id): JsonResponse
    {
        /** @var CourseSubgroup $courseSubgroup */
        $courseSubgroup = $this->courseSubgroupRepository->find($id);

        if (empty($courseSubgroup)) {
            return $this->sendError('Course Subgroup not found');
        }

        $bookingUsersQuery = BookingUser::where('course_subgroup_id', $courseSubgroup->id)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2);
            });

        $activeBookings = (clone $bookingUsersQuery)->where('status', 1)->count();
        if ($activeBookings > 0) {
            return $this->sendError('No se puede eliminar este subgrupo porque tiene reservas activas asociadas', 409);
        }

        $existingBookings = $bookingUsersQuery->count();
        if ($existingBookings > 0) {
            return $this->sendError('No se puede eliminar este subgrupo porque tiene reservas asociadas', 409);
        }

        $schoolId = optional($courseSubgroup->course)->school_id;
        $courseSubgroup->delete();
        $this->repairDispatcher->dispatchForSchool($schoolId);

        return $this->sendSuccess('Course Subgroup deleted successfully');
    }

    public function destroyMultiple(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return $this->sendError('Missing course subgroup ids', ['ids' => ['Ids array is required']], 422);
        }

        $subgroups = CourseSubgroup::with('course')
            ->whereIn('id', $ids)
            ->get();

        if ($subgroups->isEmpty()) {
            return $this->sendError('Course Subgroups not found', ['ids' => $ids], 404);
        }

        $blocked = [];
        foreach ($subgroups as $subgroup) {
            $bookingUsersQuery = BookingUser::where('course_subgroup_id', $subgroup->id)
                ->whereHas('booking', function ($query) {
                    $query->where('status', '!=', 2);
                });

            $activeBookings = (clone $bookingUsersQuery)->where('status', 1)->count();
            if ($activeBookings > 0) {
                $blocked[] = [
                    'id' => $subgroup->id,
                    'reason' => 'active_bookings'
                ];
                continue;
            }

            $existingBookings = $bookingUsersQuery->count();
            if ($existingBookings > 0) {
                $blocked[] = [
                    'id' => $subgroup->id,
                    'reason' => 'bookings'
                ];
            }
        }

        if (!empty($blocked)) {
            return $this->sendError('No se pueden eliminar algunos subgrupos porque tienen reservas asociadas', $blocked, 409);
        }

        $schoolIds = [];
        foreach ($subgroups as $subgroup) {
            $schoolId = optional($subgroup->course)->school_id;
            if ($schoolId) {
                $schoolIds[$schoolId] = true;
            }
            $subgroup->delete();
        }

/*        foreach (array_keys($schoolIds) as $schoolId) {
            $this->repairDispatcher->dispatchForSchool($schoolId);
        }*/

        return $this->sendResponse(['ids' => $subgroups->pluck('id')->values()], 'Course Subgroups deleted successfully');
    }

    public function createMultiple(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return $this->sendError('Missing course subgroup items', ['items' => ['Items array is required']], 422);
        }

        $requestedDatesId = $request->input('subgroup_dates_id');
        $subgroupDatesId = $requestedDatesId ?: $this->generateNewSubgroupDatesId();
        $created = [];
        $schoolIds = [];
        $junctionRows = [];
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($items as $input) {
                if (!is_array($input)) {
                    continue;
                }

                $courseGroup = null;
                if (!empty($input['course_group_id'])) {
                    $courseGroup = CourseGroup::where('id', $input['course_group_id'])->first();
                }
                if (!$courseGroup && !empty($input['course_date_id']) && !empty($input['degree_id'])) {
                    $courseGroup = CourseGroup::where('course_date_id', $input['course_date_id'])
                        ->where('degree_id', $input['degree_id'])
                        ->first();
                    if ($courseGroup) {
                        $input['course_group_id'] = $courseGroup->id;
                    }
                }
                if (!$courseGroup) {
                    DB::rollBack();
                    return $this->sendError(
                        'Course group not found for subgroup',
                        ['course_date_id' => $input['course_date_id'] ?? null, 'degree_id' => $input['degree_id'] ?? null],
                        409
                    );
                }
                if (!empty($courseGroup->course_id)) {
                    $input['course_id'] = $courseGroup->course_id;
                }

                $input['subgroup_dates_id'] = $subgroupDatesId;

                if (isset($input['monitor_id']) && isset($input['course_date_id'])) {
                    $courseDate = \App\Models\CourseDate::find($input['course_date_id']);

                    if ($courseDate) {
                        $date = $courseDate->date;
                        $startTime = $courseDate->hour_start;
                        $endTime = $courseDate->hour_end;

                        if (\App\Models\Monitor::isMonitorBusy($input['monitor_id'], $date, $startTime, $endTime)) {
                            DB::rollBack();
                            return $this->sendError(
                                'El monitor no est\u00e1 disponible en este horario (tiene reservas, NWDs u otros cursos asignados)',
                                409
                            );
                        }
                    }
                }

                $courseSubgroup = CourseSubgroup::create($input);
                $created[] = $courseSubgroup->id;
                $junctionRows[] = [
                    'course_subgroup_id' => $courseSubgroup->id,
                    'course_date_id' => $courseSubgroup->course_date_id,
                    'order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $schoolId = optional($courseSubgroup->course)->school_id;
                if ($schoolId) {
                    $schoolIds[$schoolId] = true;
                }
            }

            if (!empty($junctionRows)) {
                CourseSubgroupDate::insert($junctionRows);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendError('Course Subgroups could not be created', ['error' => $e->getMessage()], 500);
        }

  /*      foreach (array_keys($schoolIds) as $schoolId) {
            $this->repairDispatcher->dispatchForSchool($schoolId);
        }*/

        return $this->sendResponse($created, 'Course Subgroups created successfully');
    }

    private function generateNewSubgroupDatesId(): string
    {
        $maxNum = DB::table('course_subgroups')
            ->whereNotNull('subgroup_dates_id')
            ->get()
            ->map(function ($row) {
                $matches = [];
                preg_match('/SG-(\d+)/', $row->subgroup_dates_id, $matches);
                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        return 'SG-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
    }
}

