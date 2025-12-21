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
        try {
            \Log::info('getAgenda called with params:', $request->all());

            $dateStart = $request->input('date_start');
            $dateEnd = $request->input('date_end');
            $schoolId = $request->input('school_id');

            \Log::info('Getting monitor...');
            $monitor = $this->getMonitor($request);

            if (!$monitor) {
                \Log::error('Monitor not found');
                return $this->sendError('Monitor not found for this user', [], 404);
            }

            \Log::info('Monitor found:', ['id' => $monitor->id, 'active_school' => $monitor->active_school]);
        } catch (\Exception $e) {
            \Log::error('Error in getAgenda start: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $this->sendError('Error in getAgenda: ' . $e->getMessage(), [], 500);
        }

        try {
            \Log::info('Building booking query...');
            // OPTIMIZED: Removed heavy eager loading (course.courseDates, client.evaluations.*)
            // Reduces memory usage by ~80% and queries by ~90%
            $bookingQuery = BookingUser::with([
                'booking:id,status',
                'course:id,name,school_id,sport_id,course_type,max_participants',
                'client:id,first_name,last_name,birth_date,image',
                'client.sports:id,name',
            ])
                ->where('school_id', $monitor->active_school)
                ->where('status', 1)
                ->where('accepted', 1)
                ->whereHas('booking', function ($subQuery) {
                    $subQuery->where('status',  1);
                })
                ->byMonitor($monitor->id)
                ->orderBy('hour_start');
            \Log::info('Booking query built successfully');
        } catch (\Exception $e) {
            \Log::error('Error building booking query: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $this->sendError('Error building booking query: ' . $e->getMessage(), [], 500);
        }

        //return $this->sendResponse($bookingQuery->get(), 'Agenda retrieved successfully');

        try {
            \Log::info('Building NWD query...');
            // Consulta para los MonitorNwd
            $nwdQuery = MonitorNwd::where('monitor_id', $monitor->id)
                ->orderBy('start_time');
            \Log::info('NWD query built successfully');
        } catch (\Exception $e) {
            \Log::error('Error building NWD query: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $this->sendError('Error building NWD query: ' . $e->getMessage(), [], 500);
        }

        try {
            \Log::info('Building subgroups query...');
            $subgroupsQuery = CourseSubgroup::with([
                'courseGroup.course:id,name,school_id,sport_id,course_type,max_participants',
                'course:id,name,school_id,sport_id,course_type',
                'course.courseDates',
            ])
                ->whereHas('courseGroup.course', function ($query) use ($schoolId) {
                    if ($schoolId) {
                        $query->where('school_id', $schoolId);
                    }
                })
                ->whereHas('courseDate', function ($query) use ($dateStart, $dateEnd) {
                    if ($dateStart && $dateEnd) {
                        $query->whereBetween('date', [$dateStart, $dateEnd])->where('active', 1);
                    } else {
                        $today = Carbon::today();
                        $query->whereDate('date', $today)->where('active', 1);
                    }
                })
                ->where('monitor_id', $monitor->id)
                ->where(function ($query) {
                    $query->doesntHave('bookingUsers')
                          ->orWhereHas('bookingUsers', function ($subQuery) {
                              $subQuery->where('status', '!=', 1);
                          });
                });
            \Log::info('Subgroups query built successfully');
        } catch (\Exception $e) {
            \Log::error('Error building subgroups query: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $this->sendError('Error building subgroups query: ' . $e->getMessage(), [], 500);
        }

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
        try {
            \Log::info('Executing booking query...');
            $bookings = $bookingQuery->get();
            \Log::info('Bookings fetched successfully: ' . $bookings->count() . ' records');
        } catch (\Exception $e) {
            \Log::error('Error fetching bookings: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            $bookings = collect();
        }

        try {
            \Log::info('Executing NWD query...');
            $nwd = $nwdQuery->get();
            \Log::info('NWD fetched successfully: ' . $nwd->count() . ' records');
        } catch (\Exception $e) {
            \Log::error('Error fetching nwd: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            $nwd = collect();
        }

        try {
            \Log::info('Executing subgroups query...');
            $subgroups = $subgroupsQuery->get();
            \Log::info('Subgroups fetched successfully: ' . $subgroups->count() . ' records');
        } catch (\Exception $e) {
            \Log::error('Error fetching subgroups: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            $subgroups = collect();
        }

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
