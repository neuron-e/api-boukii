<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\AppBaseController;
use App\Http\Resources\API\BookingResource;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\CourseSubgroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Response;
use Validator;

;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */
class ClientsController extends AppBaseController
{

    public function __construct()
    {

    }


    /**
     * @OA\Get(
     *      path="/teach/clients",
     *      summary="getClientList",
     *      tags={"Teach"},
     *      description="Get all Clients of monitor",
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
     *                  @OA\Items(ref="#/components/schemas/Client")
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
        $monitor = $this->getMonitor($request);
        if (!$monitor) {
            return $this->sendError('Monitor not found for this user', [], 404);
        }
        $monitorId = $monitor->id;

        $clients = Client::with(['sports', 'utilizers', 'main', 'evaluations.degree',
            'evaluations.evaluationFulfilledGoals', 'observations'])
            ->whereHas('bookingUsers', function ($query) use ($monitorId) {
                $query->where('monitor_id', $monitorId);
            })->distinct()->get();


        return response()->json($clients);
    }

    /**
     * @OA\Get(
     *      path="/teach/clients/{id}",
     *      summary="getClientItemMonitor",
     *      tags={"Teach"},
     *      description="Get Client",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Client",
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
     *                  ref="#/components/schemas/Client"
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

        // Comprueba si el cliente principal tiene booking_users asociados con el ID del monitor
        $client = Client::with('sports', 'utilizers', 'main',
            'evaluations.degree', 'evaluations.evaluationFulfilledGoals',
            'observations')->whereHas('bookingUsers', function ($query) use ($monitorId) {
            $query->where('monitor_id', $monitorId);
        })->find($id);

        if (empty($client)) {
            return $this->sendError('Client does not have booking_users with the specified monitor');
        }

        return $this->sendResponse($client, 'Client retrieved successfully');
    }

    /**
     * @OA\Get(
     *      path="/teach/clients/{id}/bookings",
     *      summary="getClientBookingsList",
     *      tags={"Teach"},
     *      description="Get all Client bookings",
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
     *                  @OA\Items(ref="#/components/schemas/BookingUser")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function getBookings($id, Request $request): JsonResponse
    {
        $monitor = $this->getMonitor($request);
        if (!$monitor) {
            return $this->sendError('Monitor not found for this user', [], 404);
        }
        $monitorId = $monitor->id;
        $dateStart = $request->input('date_start');
        $dateEnd = $request->input('date_end');

        $bookingQuery = BookingUser::with('booking', 'course.courseDates')
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->where('status', 1)
            ->where('client_id', $id);

        if ($dateStart && $dateEnd) {
            // Busca en el rango de fechas proporcionado para las reservas
            $bookingQuery->whereBetween('date', [$dateStart, $dateEnd]);
        }

        $bookings = $bookingQuery->get();

        return $this->sendResponse($bookings, 'Bookings returned successfully');
    }


}
