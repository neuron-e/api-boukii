<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateCourseGroupAPIRequest;
use App\Http\Requests\API\UpdateCourseGroupAPIRequest;
use App\Http\Resources\API\CourseGroupResource;
use App\Models\CourseGroup;
use App\Repositories\CourseGroupRepository;
use App\Services\CourseRepairDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $schoolId = optional($courseGroup->course)->school_id;
        $courseGroup->delete();
        $this->repairDispatcher->dispatchForSchool($schoolId);

        return $this->sendSuccess('Course Group deleted successfully');
    }
}
