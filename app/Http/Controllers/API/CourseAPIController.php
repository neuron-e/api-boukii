<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateCourseAPIRequest;
use App\Http\Requests\API\UpdateCourseAPIRequest;
use App\Http\Resources\API\CourseResource;
use App\Models\Course;
use App\Repositories\CourseRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Class CourseController
 */

class CourseAPIController extends AppBaseController
{
    /** @var  CourseRepository */
    private $courseRepository;

    public function __construct(CourseRepository $courseRepo)
    {
        $this->courseRepository = $courseRepo;
    }

    /**
     * @OA\Get(
     *      path="/courses",
     *      summary="getCourseList",
     *      tags={"Course"},
     *      description="Get all Courses",
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
     *                  @OA\Items(ref="#/components/schemas/Course")
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
        $courses = $this->courseRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with', 'include_archived']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id'),
            additionalConditions: function ($query) use ($request) {
                // Excluir cursos archivados por defecto (a menos que se pida explícitamente incluirlos)
                if (!$request->get('include_archived', false)) {
                    $query->whereNull('archived_at');
                }

                if ($request->has('courseType')) {
                    $courseTypes = explode(',', $request->courseType); // Dividir en un array por comas

                    $query->whereIn('course_type', $courseTypes);
                }
            }
        );

        return $this->sendResponse($courses, 'Courses retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/courses",
     *      summary="createCourse",
     *      tags={"Course"},
     *      description="Create Course",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Course")
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
     *                  ref="#/components/schemas/Course"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateCourseAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        if(!empty($input['image'])) {
            $base64Image = $request->input('image');

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                $type = strtolower($type[1]);
                $imageData = base64_decode($imageData);

                if ($imageData === false) {
                    $this->sendError('base64_decode failed');
                }
            } else {
                $this->sendError('did not match data URI with image data');
            }

            $imageName = 'course/image_'.time().'.'.$type;
            Storage::disk('public')->put($imageName, $imageData);
            $input['image'] = url(Storage::url($imageName));
        }

        $course = $this->courseRepository->create($input);

        return $this->sendResponse($course, 'Course saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/courses/{id}",
     *      summary="getCourseItem",
     *      tags={"Course"},
     *      description="Get Course",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Course",
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
     *                  ref="#/components/schemas/Course"
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
        /** @var Course $course */
        $with = $request->get('with', []);

        // Always include courseIntervals in the response
        if (!in_array('courseIntervals', $with)) {
            $with[] = 'courseIntervals';
        }

        $course = $this->courseRepository->find($id, with: $with);

        if (empty($course)) {
            return $this->sendError('Course not found');
        }

        return $this->sendResponse($course, 'Course retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/courses/{id}",
     *      summary="updateCourse",
     *      tags={"Course"},
     *      description="Update Course",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Course",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Course")
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
     *                  ref="#/components/schemas/Course"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateCourseAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Course $course */
        $course = $this->courseRepository->find($id, with: $request->get('with', []));

        if (empty($course)) {
            return $this->sendError('Course not found');
        }

        if(!empty($input['image'])) {
            $base64Image = $request->input('image');

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                $type = strtolower($type[1]);
                $imageData = base64_decode($imageData);

                if ($imageData === false) {
                    $this->sendError('base64_decode failed');
                }
                $imageName = 'course/image_'.time().'.'.$type;
                Storage::disk('public')->put($imageName, $imageData);
                $input['image'] = url(url(Storage::url($imageName)));
            } else {
                $this->sendError('did not match data URI with image data');
            }
        } else {
            $input = $request->except('image');
        }

        $course = $this->courseRepository->update($input, $id);

        return $this->sendResponse(new CourseResource($course), 'Course updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/courses/{id}",
     *      summary="deleteCourse",
     *      tags={"Course"},
     *      description="Delete Course",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Course",
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
        /** @var Course $course */
        $course = $this->courseRepository->find($id);

        if (empty($course)) {
            return $this->sendError('Course not found');
        }

        // Verificar si tiene reservas activas
        if ($course->hasActiveBookings()) {
            return $this->sendError(
                'No se puede eliminar el curso porque tiene reservas activas. Cancela o anula todas las reservas primero.',
                400
            );
        }

        // Si tiene solo reservas anuladas, archivar en lugar de eliminar
        if ($course->hasOnlyCancelledBookings()) {
            $course->archive();
            return $this->sendResponse(
                $course,
                'El curso tiene reservas anuladas y ha sido archivado para mantener la trazabilidad. Puedes restaurarlo desde la lista de cursos archivados.'
            );
        }

        // Si no tiene reservas, eliminar normalmente (soft delete)
        $course->delete();

        return $this->sendSuccess('Course deleted successfully');
    }

    /**
     * Archiva un curso manualmente
     */
    public function archive($id): JsonResponse
    {
        /** @var Course $course */
        $course = $this->courseRepository->find($id);

        if (empty($course)) {
            return $this->sendError('Course not found');
        }

        if ($course->isArchived()) {
            return $this->sendError('El curso ya está archivado');
        }

        $course->archive();

        return $this->sendResponse($course, 'Curso archivado exitosamente');
    }

    /**
     * Restaura un curso archivado
     */
    public function unarchive($id): JsonResponse
    {
        /** @var Course $course */
        $course = $this->courseRepository->find($id);

        if (empty($course)) {
            return $this->sendError('Course not found');
        }

        if (!$course->isArchived()) {
            return $this->sendError('El curso no está archivado');
        }

        $course->unarchive();

        return $this->sendResponse($course, 'Curso restaurado exitosamente');
    }
}
