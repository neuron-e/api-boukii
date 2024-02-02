<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\AppBaseController;
use App\Models\BookingUser;
use App\Models\CourseSubgroup;
use App\Models\MonitorNwd;
use App\Models\Station;
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

class HomeController extends AppBaseController
{

    public function __construct()
    {

    }


    /**
     * @OA\Get(
     *      path="/teach/getAgenda",
     *      summary="Get Monitor Agenda",
     *      tags={"Teach"},
     *      description="Get Monitor agenda",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="bookings",
     *                      type="array",
     *                      @OA\Items(
     *                          ref="#/components/schemas/Booking"
     *                      )
     *                  ),
     *                  @OA\Property(
     *                      property="nwds",
     *                      type="array",
     *                      @OA\Items(
     *                          ref="#/components/schemas/MonitorNwd"
     *                      )
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    public function getAgenda(Request $request): JsonResponse
    {
        $dateStart = $request->input('date_start');
        $dateEnd = $request->input('date_end');
        $schoolId = $request->input('school_id');

        $monitor = $this->getMonitor($request);

        // Consulta para las reservas (BookingUser)
        $bookingQuery = BookingUser::with('booking', 'course.courseDates', 'client.sports',
            'client.evaluations.degree', 'client.evaluations.evaluationFulfilledGoals')
            ->where('school_id', $monitor->active_school)
            ->byMonitor($monitor->id)
            ->orderBy('hour_start');

        // Consulta para los MonitorNwd
        $nwdQuery = MonitorNwd::where('monitor_id', $monitor->id)
            ->orderBy('start_time');

        $subgroupsQuery = CourseSubgroup::with(['courseGroup.course', 'bookingUsers.client.sports',
            'bookingUsers.client.evaluations.degree', 'bookingUsers.client.evaluations.evaluationFulfilledGoals'])
            ->whereHas('courseGroup.course', function ($query) use ($schoolId) {
                // Agrega la comprobación de la escuela aquí
                if($schoolId) {
                    $query->where('school_id', $schoolId);
                }
            })
            ->whereHas('courseDate', function ($query) use ($dateStart, $dateEnd) {
                if ($dateStart && $dateEnd) {
                    // Filtra las fechas del subgrupo en el rango proporcionado
                    $query->whereBetween('date', [$dateStart, $dateEnd])->where('active', 1);
                } else {
                    $today = Carbon::today();

                    // Busca en el día de hoy para las reservas
                    $query->whereDate('date', $today)->where('active', 1);
                }
            })
            ->with('bookingUsers', function ($query) {
                // Agregar la restricción para traer solo las booking_users con status = 1
                $query->where('status', 1);
            })->where('monitor_id', $monitor->id);

        if($schoolId) {
            $bookingQuery->where('school_id', $schoolId);

            $nwdQuery->where('school_id', $schoolId);
        }

        // Si se proporcionaron date_start y date_end, busca en el rango de fechas
        if ($dateStart && $dateEnd) {
            // Busca en el rango de fechas proporcionado para las reservas
            $bookingQuery->whereBetween('date', [$dateStart, $dateEnd]);

            // Busca en el rango de fechas proporcionado para los MonitorNwd
            $nwdQuery->whereBetween('start_date', [$dateStart, $dateEnd])
                ->whereBetween('end_date', [$dateStart, $dateEnd]);
        } else {
            // Si no se proporcionan fechas, busca en el día de hoy
            $today = Carbon::today();

            // Busca en el día de hoy para las reservas
            $bookingQuery->whereDate('date', $today);

            // Busca en el día de hoy para los MonitorNwd
            $nwdQuery->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today);
        }

        // Obtén los resultados para las reservas y los MonitorNwd
        $bookings = $bookingQuery->get();
        $nwd = $nwdQuery->get();
        $subgroups = $subgroupsQuery->get();

        $data = ['bookings' => $bookings, 'nwd' => $nwd, 'subgroups' => $subgroups];


        return $this->sendResponse($data, 'Agenda retrieved successfully');
    }

    public function get12HourlyForecastByStation(Request $request)
    {
        $forecast = [];

        $station = Station::find($request->station_id);

        if ($station)
        {
            // Pick its Station coordinates:
            // TODO TBD what about Schools located at _several_ Stations ??
            // As of 2022-11 just forecast the first one
            $accuweatherData = ($station && $station->accuweather) ?
                json_decode($station->accuweather, true) : [];
            $forecast = $accuweatherData['12HoursForecast'] ?? [];
        }

        return $this->sendResponse($forecast, 'Weather send correctly');
    }

    public function get5DaysForecastByStation(Request $request)
    {
        $forecast = [];

        $station = Station::find($request->station_id);

        if ($station)
        {
            // Pick its Station coordinates:
            // TODO TBD what about Schools located at _several_ Stations ??
            // As of 2022-11 just forecast the first one
            $accuweatherData = ($station && $station->accuweather) ?
                json_decode($station->accuweather, true) : [];
            $forecast = $accuweatherData['5DaysForecast'] ?? [];
        }

        return $this->sendResponse($forecast, 'Weather send correctly');
    }

}
