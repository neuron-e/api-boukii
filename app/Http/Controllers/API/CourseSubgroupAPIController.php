<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateCourseSubgroupAPIRequest;
use App\Http\Requests\API\UpdateCourseSubgroupAPIRequest;
use App\Http\Resources\API\CourseSubgroupResource;
use App\Models\CourseSubgroup;
use App\Repositories\CourseSubgroupRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class CourseSubgroupController
 */

class CourseSubgroupAPIController extends AppBaseController
{
    /** @var  CourseSubgroupRepository */
    private $courseSubgroupRepository;

    public function __construct(CourseSubgroupRepository $courseSubgroupRepo)
    {
        $this->courseSubgroupRepository = $courseSubgroupRepo;
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
                    \Illuminate\Support\Facades\Log::warning('Intento de crear subgrupo con monitor ocupado', [
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

        $courseSubgroup = $this->courseSubgroupRepository->create($input);

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
                    \Illuminate\Support\Facades\Log::warning('Intento de asignar monitor ocupado a subgrupo', [
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

        $courseSubgroup = $this->courseSubgroupRepository->update($input, $id);

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

        $courseSubgroup->delete();

        return $this->sendSuccess('Course Subgroup deleted successfully');
    }
}
