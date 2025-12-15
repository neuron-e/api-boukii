<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateCourseDateAPIRequest;
use App\Http\Requests\API\UpdateCourseDateAPIRequest;
use App\Http\Resources\API\CourseDateResource;
use App\Models\CourseDate;
use App\Repositories\CourseDateRepository;
use App\Services\CourseRepairDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class CourseDateController
 */

class CourseDateAPIController extends AppBaseController
{
    /** @var  CourseDateRepository */
    private $courseDateRepository;
    private CourseRepairDispatcher $repairDispatcher;

    public function __construct(CourseDateRepository $courseDateRepo, CourseRepairDispatcher $repairDispatcher)
    {
        $this->courseDateRepository = $courseDateRepo;
        $this->repairDispatcher = $repairDispatcher;
    }

    /**
     * @OA\Get(
     *      path="/course-dates",
     *      summary="getCourseDateList",
     *      tags={"CourseDate"},
     *      description="Get all CourseDates",
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
     *                  @OA\Items(ref="#/components/schemas/CourseDate")
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
        $courseDates = $this->courseDateRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($courseDates, 'Course Dates retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/course-dates",
     *      summary="createCourseDate",
     *      tags={"CourseDate"},
     *      description="Create CourseDate",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/CourseDate")
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
     *                  ref="#/components/schemas/CourseDate"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateCourseDateAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        try {
            $courseDate = DB::transaction(function () use ($input) {
                return $this->courseDateRepository->create($input);
            });

            $this->repairDispatcher->dispatchForSchool(optional($courseDate->course)->school_id);
            return $this->sendResponse($courseDate, 'Course Date saved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error creating course date: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *      path="/course-dates/{id}",
     *      summary="getCourseDateItem",
     *      tags={"CourseDate"},
     *      description="Get CourseDate",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of CourseDate",
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
     *                  ref="#/components/schemas/CourseDate"
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
        /** @var CourseDate $courseDate */
        $courseDate = $this->courseDateRepository->find($id, with: $request->get('with', []));

        if (empty($courseDate)) {
            return $this->sendError('Course Date not found');
        }

        return $this->sendResponse($courseDate, 'Course Date retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/course-dates/{id}",
     *      summary="updateCourseDate",
     *      tags={"CourseDate"},
     *      description="Update CourseDate",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of CourseDate",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/CourseDate")
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
     *                  ref="#/components/schemas/CourseDate"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateCourseDateAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var CourseDate $courseDate */
        $courseDate = $this->courseDateRepository->find($id, with: $request->get('with', []));

        if (empty($courseDate)) {
            return $this->sendError('Course Date not found');
        }

        try {
            $courseDate = DB::transaction(function () use ($input, $id) {
                return $this->courseDateRepository->update($input, $id);
            });
            $this->repairDispatcher->dispatchForSchool(optional($courseDate->course)->school_id);

            return $this->sendResponse(new CourseDateResource($courseDate), 'CourseDate updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating course date: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *      path="/course-dates/{id}",
     *      summary="deleteCourseDate",
     *      tags={"CourseDate"},
     *      description="Delete CourseDate",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of CourseDate",
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
        /** @var CourseDate $courseDate */
        $courseDate = $this->courseDateRepository->find($id);

        if (empty($courseDate)) {
            return $this->sendError('Course Date not found');
        }

        try {
            $schoolId = optional($courseDate->course)->school_id;
            DB::transaction(function () use ($courseDate) {
                $courseDate->delete();
            });
            $this->repairDispatcher->dispatchForSchool($schoolId);

            return $this->sendSuccess('Course Date deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting course date: ' . $e->getMessage());
        }
    }
}
