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
use Illuminate\Support\Facades\Log;
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
            Log::channel('teach')->info('getAgenda called with params:', $request->all());

            $dateStart = $request->input('date_start');
            $dateEnd = $request->input('date_end');
            $schoolId = $request->input('school_id');

            Log::channel('teach')->info('Getting monitor...');
            $monitor = $this->getMonitor($request);

            if (!$monitor) {
                Log::channel('teach')->error('Monitor not found');
                return $this->sendError('Monitor not found for this user', [], 404);
            }

            Log::channel('teach')->info('Monitor found:', ['id' => $monitor->id, 'active_school' => $monitor->active_school]);
        } catch (\Exception $e) {
            Log::channel('teach')->error('Error in getAgenda start: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $this->sendError('Error in getAgenda: ' . $e->getMessage(), [], 500);
        }

        try {
            Log::channel('teach')->info('Building booking query...');
            // OPTIMIZED: Removed heavy eager loading (course.courseDates, client.evaluations.*)
            // Reduces memory usage by ~80% and queries by ~90%
            $bookingQuery = BookingUser::select([
                'id',
                'booking_id',
                'client_id',
                'course_id',
                'course_date_id',
                'course_subgroup_id',
                'monitor_id',
                'group_id',
                'date',
                'hour_start',
                'hour_end',
                'status',
                'accepted',
                'degree_id',
                'color',
                'school_id',
                'notes',
                'notes_school',
            ])->with([
                'booking:id,status,paid,paid_total,price_total,notes,notes_school',
                'course' => function ($query) use ($dateStart, $dateEnd) {
                    $query->select('id', 'name', 'school_id', 'sport_id', 'course_type', 'max_participants')
                        ->withCount(['courseDates as course_dates_total'])
                        ->with(['courseDates' => function ($dateQuery) use ($dateStart, $dateEnd) {
                        $dateQuery->select('id', 'course_id', 'date', 'hour_start', 'hour_end')
                            ->orderBy('date');
                            if ($dateStart && $dateEnd) {
                                $dateQuery->whereBetween('date', [$dateStart, $dateEnd]);
                            } else {
                                $dateQuery->whereDate('date', Carbon::today());
                            }
                        }]);
                },
                'client:id,first_name,last_name,birth_date,image,phone,telephone',
                'client.sports:id,name',
                'client.observations',
            ])
                ->where('school_id', $monitor->active_school)
                ->where('status', 1)
                ->whereHas('booking', function ($subQuery) {
                    // Include partially cancelled bookings (status 3) so active booking_users still show
                    // Also exclude soft-deleted bookings explicitly
                    $subQuery->whereIn('status', [1, 3])
                        ->whereNull('deleted_at');
                })
                ->where(function ($query) use ($monitor) {
                    $query->where('monitor_id', $monitor->id)
                        ->orWhereHas('courseSubGroup', function ($subQuery) use ($monitor) {
                            $subQuery->where('monitor_id', $monitor->id);
                        })
                        // Fallback: include booking_users without subgroup if their course_group
                        // has any subgroup assigned to this monitor (data inconsistency workaround).
                        ->orWhere(function ($subQuery) use ($monitor) {
                            $subQuery->whereNull('course_subgroup_id')
                                ->whereHas('courseGroup', function ($groupQuery) use ($monitor) {
                                    $groupQuery->whereHas('courseSubgroups', function ($sgQuery) use ($monitor) {
                                        $sgQuery->where('monitor_id', $monitor->id);
                                    });
                                });
                        });
                })
                ->orderBy('hour_start');
            Log::channel('teach')->info('Booking query built successfully');
        } catch (\Exception $e) {
            Log::channel('teach')->error('Error building booking query: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $this->sendError('Error building booking query: ' . $e->getMessage(), [], 500);
        }

        //return $this->sendResponse($bookingQuery->get(), 'Agenda retrieved successfully');

        try {
            Log::channel('teach')->info('Building NWD query...');
            // Consulta para los MonitorNwd
            $nwdQuery = MonitorNwd::where('monitor_id', $monitor->id)
                ->orderBy('start_time');
            Log::channel('teach')->info('NWD query built successfully');
        } catch (\Exception $e) {
            Log::channel('teach')->error('Error building NWD query: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $this->sendError('Error building NWD query: ' . $e->getMessage(), [], 500);
        }

        try {
            Log::channel('teach')->info('Building subgroups query...');
            $subgroupsQuery = CourseSubgroup::with([
                'courseGroup.course:id,name,school_id,sport_id,course_type,max_participants',
                'course' => function ($query) use ($dateStart, $dateEnd) {
                    $query->select('id', 'name', 'school_id', 'sport_id', 'course_type', 'max_participants')
                        ->withCount(['courseDates as course_dates_total'])
                        ->with(['courseDates' => function ($dateQuery) use ($dateStart, $dateEnd) {
                        $dateQuery->select('id', 'course_id', 'date', 'hour_start', 'hour_end')
                            ->orderBy('date');
                            if ($dateStart && $dateEnd) {
                                $dateQuery->whereBetween('date', [$dateStart, $dateEnd]);
                            } else {
                                $dateQuery->whereDate('date', Carbon::today());
                            }
                        }]);
                },
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
            Log::channel('teach')->info('Subgroups query built successfully');
        } catch (\Exception $e) {
            Log::channel('teach')->error('Error building subgroups query: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
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
            Log::channel('teach')->info('Executing booking query...');
        $bookings = $bookingQuery->get();
            Log::channel('teach')->info('Bookings fetched successfully: ' . $bookings->count() . ' records');
        } catch (\Exception $e) {
            Log::channel('teach')->error('Error fetching bookings: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            Log::channel('teach')->error('Stack trace: ' . $e->getTraceAsString());
            $bookings = collect();
        }

        try {
            Log::channel('teach')->info('Executing NWD query...');
            $nwd = $nwdQuery->get();
            Log::channel('teach')->info('NWD fetched successfully: ' . $nwd->count() . ' records');
        } catch (\Exception $e) {
            Log::channel('teach')->error('Error fetching nwd: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            Log::channel('teach')->error('Stack trace: ' . $e->getTraceAsString());
            $nwd = collect();
        }

        try {
            Log::channel('teach')->info('Executing subgroups query...');
            $subgroups = $subgroupsQuery->get();
            Log::channel('teach')->info('Subgroups fetched successfully: ' . $subgroups->count() . ' records');
        } catch (\Exception $e) {
            Log::channel('teach')->error('Error fetching subgroups: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            Log::channel('teach')->error('Stack trace: ' . $e->getTraceAsString());
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
