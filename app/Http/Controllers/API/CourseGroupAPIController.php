<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateCourseGroupAPIRequest;
use App\Http\Requests\API\UpdateCourseGroupAPIRequest;
use App\Http\Resources\API\CourseGroupResource;
use App\Models\BookingUser;
use App\Models\CourseGroup;
use App\Models\CourseSubgroup;
use App\Models\CourseSubgroupDate;
use App\Models\CourseDate;
use App\Repositories\CourseGroupRepository;
use App\Services\CourseRepairDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class CourseGroupController
 */

class CourseGroupAPIController extends AppBaseController
{
    /** @var  CourseGroupRepository */
    private $courseGroupRepository;
    private CourseRepairDispatcher $repairDispatcher;

    public function __construct(CourseGroupRepository $courseGroupRepo, CourseRepairDispatcher $repairDispatcher)
    {
        $this->courseGroupRepository = $courseGroupRepo;
        $this->repairDispatcher = $repairDispatcher;
    }

    /**
     * @OA\Get(
     *      path="/course-groups",
     *      summary="getCourseGroupList",
     *      tags={"CourseGroup"},
     *      description="Get all CourseGroups",
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
     *                  @OA\Items(ref="#/components/schemas/CourseGroup")
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
        $courseGroups = $this->courseGroupRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($courseGroups, 'Course Groups retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/course-groups",
     *      summary="createCourseGroup",
     *      tags={"CourseGroup"},
     *      description="Create CourseGroup",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/CourseGroup")
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
     *                  ref="#/components/schemas/CourseGroup"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateCourseGroupAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $courseGroup = $this->courseGroupRepository->create($input);
        $this->repairDispatcher->dispatchForSchool(optional($courseGroup->course)->school_id);

        return $this->sendResponse($courseGroup, 'Course Group saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/course-groups/{id}",
     *      summary="getCourseGroupItem",
     *      tags={"CourseGroup"},
     *      description="Get CourseGroup",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of CourseGroup",
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
     *                  ref="#/components/schemas/CourseGroup"
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
        /** @var CourseGroup $courseGroup */
        $courseGroup = $this->courseGroupRepository->find($id, with: $request->get('with', []));

        if (empty($courseGroup)) {
            return $this->sendError('Course Group not found');
        }

        return $this->sendResponse($courseGroup, 'Course Group retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/course-groups/{id}",
     *      summary="updateCourseGroup",
     *      tags={"CourseGroup"},
     *      description="Update CourseGroup",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of CourseGroup",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/CourseGroup")
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
     *                  ref="#/components/schemas/CourseGroup"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateCourseGroupAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var CourseGroup $courseGroup */
        $courseGroup = $this->courseGroupRepository->find($id, with: $request->get('with', []));

        if (empty($courseGroup)) {
            return $this->sendError('Course Group not found');
        }

        $courseGroup = $this->courseGroupRepository->update($input, $id);
        $this->repairDispatcher->dispatchForSchool(optional($courseGroup->course)->school_id);

        return $this->sendResponse(new CourseGroupResource($courseGroup), 'CourseGroup updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/course-groups/{id}",
     *      summary="deleteCourseGroup",
     *      tags={"CourseGroup"},
     *      description="Delete CourseGroup",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of CourseGroup",
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
        /** @var CourseGroup $courseGroup */
        $courseGroup = $this->courseGroupRepository->find($id);

        if (empty($courseGroup)) {
            return $this->sendError('Course Group not found');
        }

        $bookingUsersQuery = BookingUser::where('course_group_id', $courseGroup->id)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2);
            });

        $activeBookings = (clone $bookingUsersQuery)->where('status', 1)->count();
        if ($activeBookings > 0) {
            return $this->sendError('No se puede eliminar este nivel porque tiene reservas activas asociadas', 409);
        }

        $existingBookings = $bookingUsersQuery->count();
        if ($existingBookings > 0) {
            return $this->sendError('No se puede eliminar este nivel porque tiene reservas asociadas', 409);
        }

        $schoolId = optional($courseGroup->course)->school_id;
        $courseGroup->delete();
        $this->repairDispatcher->dispatchForSchool($schoolId);

        return $this->sendSuccess('Course Group deleted successfully');
    }

    public function createMultiple(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return $this->sendError('Missing course group items', ['items' => ['Items array is required']], 422);
        }

        $created = [];
        $schoolIds = [];

        DB::beginTransaction();
        try {
            foreach ($items as $input) {
                if (!is_array($input)) {
                    continue;
                }

                if (empty($input['course_id']) && !empty($input['course_date_id'])) {
                    $courseDate = CourseDate::find($input['course_date_id']);
                    if ($courseDate) {
                        $input['course_id'] = $courseDate->course_id;
                    }
                }

                if (empty($input['course_id']) || empty($input['course_date_id']) || empty($input['degree_id'])) {
                    DB::rollBack();
                    return $this->sendError('Course group data is incomplete', $input, 422);
                }

                $existing = CourseGroup::where('course_date_id', $input['course_date_id'])
                    ->where('degree_id', $input['degree_id'])
                    ->first();

                if ($existing) {
                    $existing->fill($input);
                    $existing->save();
                    $courseGroup = $existing;
                } else {
                    $courseGroup = CourseGroup::create($input);
                }

                $created[] = $courseGroup;
                $schoolId = optional($courseGroup->course)->school_id;
                if ($schoolId) {
                    $schoolIds[$schoolId] = true;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendError('Course Groups could not be created', ['error' => $e->getMessage()], 500);
        }

        foreach (array_keys($schoolIds) as $schoolId) {
            $this->repairDispatcher->dispatchForSchool($schoolId);
        }

        return $this->sendResponse($created, 'Course Groups created successfully');
    }

    public function destroyMultiple(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $items = $request->input('items', []);

        if ((!is_array($ids) || empty($ids)) && (!is_array($items) || empty($items))) {
            return $this->sendError('Missing course group identifiers', ['ids' => ['Ids array or items are required']], 422);
        }

        if ((!is_array($ids) || empty($ids)) && is_array($items)) {
            $resolved = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $courseDateId = $item['course_date_id'] ?? null;
                $degreeId = $item['degree_id'] ?? null;
                if (!$courseDateId || !$degreeId) {
                    continue;
                }
                $group = CourseGroup::where('course_date_id', $courseDateId)
                    ->where('degree_id', $degreeId)
                    ->first();
                if ($group) {
                    $resolved[] = $group->id;
                }
            }
            $ids = $resolved;
        }

        if (empty($ids)) {
            return $this->sendError('Course Groups not found', ['ids' => $ids], 404);
        }

        $groups = CourseGroup::with('course')->whereIn('id', $ids)->get();
        if ($groups->isEmpty()) {
            return $this->sendError('Course Groups not found', ['ids' => $ids], 404);
        }

        $blocked = [];

        $bookingUsersQuery = BookingUser::whereIn('course_group_id', $ids)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2);
            });

        $activeBookings = (clone $bookingUsersQuery)->where('status', 1)->count();
        if ($activeBookings > 0) {
            return $this->sendError('No se pueden eliminar algunos niveles porque tienen reservas activas asociadas', ['reason' => 'active_bookings'], 409);
        }

        $existingBookings = $bookingUsersQuery->count();
        if ($existingBookings > 0) {
            return $this->sendError('No se pueden eliminar algunos niveles porque tienen reservas asociadas', ['reason' => 'bookings'], 409);
        }

        $subgroupIds = CourseSubgroup::whereIn('course_group_id', $ids)->pluck('id')->values();
        if ($subgroupIds->isNotEmpty()) {
            $subgroupBookingsQuery = BookingUser::whereIn('course_subgroup_id', $subgroupIds)
                ->whereHas('booking', function ($query) {
                    $query->where('status', '!=', 2);
                });

            $activeSubgroupBookings = (clone $subgroupBookingsQuery)->where('status', 1)->count();
            if ($activeSubgroupBookings > 0) {
                $blocked[] = ['reason' => 'active_subgroup_bookings'];
            }

            $existingSubgroupBookings = $subgroupBookingsQuery->count();
            if ($existingSubgroupBookings > 0) {
                $blocked[] = ['reason' => 'subgroup_bookings'];
            }
        }

        if (!empty($blocked)) {
            return $this->sendError('No se pueden eliminar algunos niveles porque tienen reservas asociadas', $blocked, 409);
        }

        $schoolIds = [];

        DB::beginTransaction();
        try {
            if ($subgroupIds->isNotEmpty()) {
                CourseSubgroupDate::whereIn('course_subgroup_id', $subgroupIds)->delete();
                CourseSubgroup::whereIn('id', $subgroupIds)->delete();
            }

            foreach ($groups as $group) {
                $schoolId = optional($group->course)->school_id;
                if ($schoolId) {
                    $schoolIds[$schoolId] = true;
                }
                $group->delete();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendError('Course Groups could not be deleted', ['error' => $e->getMessage()], 500);
        }

        foreach (array_keys($schoolIds) as $schoolId) {
            $this->repairDispatcher->dispatchForSchool($schoolId);
        }

        return $this->sendResponse(['ids' => $groups->pluck('id')->values()], 'Course Groups deleted successfully');
    }
}
