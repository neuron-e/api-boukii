<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\AppBaseController;
use App\Http\Resources\API\BookingResource;
use App\Http\Resources\API\BookingUserResource;
use App\Http\Resources\Teach\HomeAgendaResource;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\MonitorNwd;
use App\Models\MonitorObservation;
use App\Models\MonitorSportsDegree;
use App\Models\MonitorsSchool;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Response;
use Validator;

;

/**
 * Class HomeController
 * @package App\Http\Controllers\Teach
 */

class MonitorController extends AppBaseController
{

    public function __construct()
    {

    }


    /**
     * @OA\Put(
     *      path="/teach/monitor",
     *      summary="Update Monitor",
     *      tags={"Teach"},
     *      description="Update Monitor",
     *      @OA\Response(
     *           response=200,
     *           description="successful operation",
     *           @OA\JsonContent(
     *               type="object",
     *               @OA\Property(
     *                   property="success",
     *                   type="boolean"
     *               ),
     *               @OA\Property(
     *                   property="data",
     *                   type="array",
     *                   @OA\Items(ref="#/components/schemas/Monitor")
     *               ),
     *               @OA\Property(
     *                   property="message",
     *                   type="string"
     *               )
     *           )
     *       )
     *  )
     */

    public function update(Request $request): JsonResponse
    {

        $validatedData = $request->validate([
            // Define las reglas de validación para los campos del monitor aquí
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            // Agrega reglas de validación para otros campos del monitor si es necesario
        ]);

        // Crea una instancia de Monitor con los datos validados
        $monitor = $this->getMonitor($request);
        if (!$monitor) {
            return $this->sendError('Monitor not found for this user', [], 404);
        }

        // Si hay relaciones que debes editar o eliminar, puedes hacerlo aquí
        // Por ejemplo, para monitorObservations:
        $monitorObservationsData = $request->input('monitor_observations');
        $monitor->monitorObservations()->delete(); // Elimina todas las observaciones existentes
        foreach ($monitorObservationsData as $observationData) {
            $monitorObservation = new MonitorObservation($observationData);
            $monitor->monitorObservations()->save($monitorObservation);
        }

        // Para monitorSportsDegrees:
        $monitorSportsDegreesData = $request->input('monitor_sports_degrees');
        $monitor->monitorSportsDegrees()->delete(); // Elimina todos los grados deportivos existentes
        foreach ($monitorSportsDegreesData as $sportsDegreeData) {
            $monitorSportsDegree = new MonitorSportsDegree($sportsDegreeData);
            $monitor->monitorSportsDegrees()->save($monitorSportsDegree);
        }

        // Para monitorSchools:
        $monitorSchoolsData = $request->input('monitor_schools');
        $monitor->monitorSchools()->delete(); // Elimina todas las escuelas existentes
        foreach ($monitorSchoolsData as $schoolData) {
            $monitorSchool = new MonitorsSchool($schoolData);
            $monitor->monitorSchools()->save($monitorSchool);
        }

        return $this->sendResponse($monitor, 'Agenda retrieved successfully');
    }

    /**
     * @OA\Get(
     *      path="/teach/monitor/pastBookings",
     *      summary="getPastBookings",
     *      tags={"Teach"},
     *      description="Get past Bookings of Monitor",
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
     *                  @OA\Items(ref="#/components/schemas/Booking")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function getPastBookings(Request $request): JsonResponse
    {
        $monitor = $this->getMonitor($request);
        if (!$monitor) {
            return $this->sendError('Monitor not found for this user', [], 404);
        }

        $seasonStart = Season::where('school_id', $request->school_id)->where('is_active', 1)->select('start_date')->first();


        $bookingQuery = BookingUser::with('booking', 'course.courseDates', 'client')
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->where('status', 1)
            ->where('school_id', $monitor->active_school)
            ->byMonitor($monitor->id)
            ->where('date', '<=', Carbon::today());

        if ($seasonStart) {
            $bookingQuery->orWhere('date', '>', $seasonStart->date_start);
        }

        $bookings = $bookingQuery
            ->selectRaw('MIN(id) as id, booking_id, MAX(date) as date, MAX(hour_start) as hour_start') // Ajusta esto según tus necesidades
            ->orderBy('date')
            ->orderBy('hour_start')
            ->groupBy('booking_id')
            ->get();

        return $this->sendResponse($bookings, 'Bookings returned successfully');
    }

}
