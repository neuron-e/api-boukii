<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\AppBaseController;
use App\Http\Resources\API\BookingResource;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Response;
use Validator;

;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */

class CourseController extends AppBaseController
{

    public function __construct()
    {

    }


    /**
     * @OA\Get(
     *      path="/teach/courses/{id}",
     *      summary="getCourseWithBookings",
     *      tags={"Teach"},
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
        $monitor = $this->getMonitor($request);
        if (!$monitor) {
            return $this->sendError('Monitor not found for this user', [], 404);
        }
        $monitorId = $monitor->id;

        // Cargar las relaciones
        $course = Course::with(
            'bookingUsersActive.client.sports',
            'courseDates.courseGroups.courseSubgroups.monitor'
        )->find($id);

        if (empty($course)) {
            return $this->sendError('Course does not exist');
        }

        // Convertir a array para modificar la estructura
        $courseArray = $course->toArray();

        // Renombrar la clave bookingUsersActive a booking_users
        $courseArray['booking_users'] = $courseArray['booking_users_active'];
        unset($courseArray['booking_users_active']);

        return $this->sendResponse($courseArray, 'Course retrieved successfully');
    }

}
