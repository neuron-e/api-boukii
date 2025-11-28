<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\BookingUser;
use App\Models\CourseDate;
use App\Models\CourseSubgroup;
use App\Models\CourseSubgroupDate;
use App\Models\Monitor;
use App\Models\MonitorNwd;
use App\Models\MonitorSportAuthorizedDegree;
use App\Models\MonitorsSchool;
use App\Models\Station;
use App\Services\MonitorNotificationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Response;
use Validator;

;

/**
 * Class HomeController
 * @package App\Http\Controllers\Teach
 */

class PlannerController extends AppBaseController
{

    public function __construct()
    {

    }


    /**
     * @OA\Get(
     *      path="/admin/getPlanner",
     *      summary="Get Planner for all monitors",
     *      tags={"Admin"},
     *      description="Get planner",
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

    public function getPlanner(Request $request): JsonResponse
    {
        $schoolId = $this->getSchool($request)->id;
        return $this->sendResponse($this->performPlannerQuery($request), 'Planner retrieved successfully');
    }

    public function performPlannerQuery(Request $request): \Illuminate\Support\Collection
    {
        $dateStart = $request->input('date_start');
        $dateEnd = $request->input('date_end');
        $monitorId = $request->input('monitor_id');
        $languagesInput = $request->input('languages');
        $languageIds = [];
        if (!empty($languagesInput)) {
            if (is_string($languagesInput)) {
                $languagesInput = array_map('trim', explode(',', $languagesInput));
            }
            if (!is_array($languagesInput)) {
                $languagesInput = [$languagesInput];
            }
            $languageIds = array_filter(array_map('intval', $languagesInput));
        }

        $schoolId = $this->getSchool($request)->id;

        // OPTIMIZACION: Cargar solo campos necesarios y eliminar evaluations pesadas
        $subgroupsQuery = CourseSubgroup::with([
            'courseGroup:id,course_id',
            'courseGroup.course:id,name,sport_id,course_type,max_participants,date_start,date_end',
            'bookingUsers' => function ($query) {
                $query->select('id', 'booking_id', 'client_id', 'course_id', 'course_date_id', 'course_subgroup_id',
                    'monitor_id', 'group_id', 'date', 'hour_start', 'hour_end', 'status', 'accepted',
                    'degree_id', 'color', 'school_id')
                    ->where('status', 1)
                    ->whereHas('booking')
                    ->with([
                        'booking:id,user_id,paid',
                        'booking.user:id,first_name,last_name',
                        'client:id,first_name,last_name,birth_date,language1_id',
                        'client.sports' => function ($query) {
                            $query->select('sports.id', 'sports.name');
                            // withPivot('degree_id') ya est en el modelo, se incluye automticamente
                        }
                    ]);
            }
        ])
            ->select('id', 'course_group_id', 'course_date_id', 'course_id', 'monitor_id',
                'degree_id', 'max_participants')
            ->whereHas('courseGroup.course', function ($query) use ($schoolId) {
                // Agrega la comprobacin de la escuela aqu
                $query->where('school_id', $schoolId)->where('active', 1);
            })
            ->whereHas('courseDate', function ($query) use ($dateStart, $dateEnd) {
                if ($dateStart && $dateEnd) {
                    // Filtra las fechas del subgrupo en el rango proporcionado
                    // Usamos whereDate para comparar solo la fecha, ignorando la hora
                    $query->whereDate('date', '>=', $dateStart)
                        ->whereDate('date', '<=', $dateEnd)
                        ->where('active', 1);
                } else {
                    $today = Carbon::today();

                    // Busca en el da de hoy para las reservas
                    $query->whereDate('date', $today)->where('active', 1);
                }
            });

        // Consulta para las reservas (BookingUser)
        // OPTIMIZACION: Cargar solo campos necesarios y eliminar evaluations pesadas
        $bookingQuery = BookingUser::with([
            'booking:id,user_id,paid',
            'booking.user:id,first_name,last_name',
            'course:id,name,sport_id,course_type,max_participants,date_start,date_end',
            'course.courseDates:id,course_id,date,hour_start,hour_end',
            'client:id,first_name,last_name,birth_date,language1_id',
            'client.sports' => function ($query) {
                $query->select('sports.id', 'sports.name');
                // withPivot('degree_id') ya est en el modelo, se incluye automticamente
            }
        ])
            ->select('id', 'booking_id', 'client_id', 'course_id', 'course_date_id', 'course_subgroup_id',
                'monitor_id', 'group_id', 'date', 'hour_start', 'hour_end', 'status', 'accepted',
                'degree_id', 'color', 'school_id')
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->where('school_id', $schoolId)
            ->where('course_subgroup_id', null)
            ->where('status', 1)
            ->orderBy('hour_start');

        // Consulta para los MonitorNwd
        // OPTIMIZACION: Cargar solo campos necesarios
        $nwdQuery = MonitorNwd::select('id', 'monitor_id', 'school_id', 'station_id',
            'start_date', 'end_date', 'start_time', 'end_time', 'full_day',
            'user_nwd_subtype_id', 'description', 'color')
            ->where('school_id', $schoolId) // Filtra por school_id
            ->orderBy('start_time');

        if ($schoolId) {
            $bookingQuery->where('school_id', $schoolId);

            $nwdQuery->where('school_id', $schoolId);
        }

        // Si se proporcionaron date_start y date_end, busca en el rango de fechas
        if ($dateStart && $dateEnd) {
            // Busca en el rango de fechas proporcionado para las reservas
            // Usamos whereDate para comparar solo la fecha, ignorando la hora
            $bookingQuery->whereDate('date', '>=', $dateStart)
                ->whereDate('date', '<=', $dateEnd);

            // Busca en el rango de fechas proporcionado para los MonitorNwd
            $nwdQuery->whereDate('start_date', '>=', $dateStart)
                ->whereDate('start_date', '<=', $dateEnd)
                ->whereDate('end_date', '>=', $dateStart)
                ->whereDate('end_date', '<=', $dateEnd);
        } else {
            // Si no se proporcionan fechas, busca en el da de hoy
            $today = Carbon::today();

            // Busca en el da de hoy para las reservas
            $bookingQuery->whereDate('date', $today);

            // Busca en el da de hoy para los MonitorNwd
            $nwdQuery->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today);
        }


        if ($monitorId) {
            // Filtra solo las reservas y los NWD para el monitor especfico
            $bookingQuery->where('monitor_id', $monitorId);
            $nwdQuery->where('monitor_id', $monitorId);
            $subgroupsQuery->where('monitor_id', $monitorId);

            // Obtn solo el monitor especfico
            // OPTIMIZACION: Cargar solo campos necesarios del monitor
            $monitors = MonitorsSchool::with([
                'monitor:id,first_name,last_name',
                'monitor.sports' => function ($query) use ($schoolId) {
                    $query->select('sports.id', 'sports.name', 'sports.icon_selected')
                        ->where('monitor_sports_degrees.school_id', $schoolId);
                }
            ])
                ->where('school_id', $schoolId)
                ->whereHas('monitor', function ($query) use ($monitorId, $languageIds) {
                    $query->where('id', $monitorId);
                    if (!empty($languageIds)) {
                        $query->where(function ($q) use ($languageIds) {
                            $q->whereIn('language1_id', $languageIds)
                                ->orWhereIn('language2_id', $languageIds)
                                ->orWhereIn('language3_id', $languageIds)
                                ->orWhereIn('language4_id', $languageIds)
                                ->orWhereIn('language5_id', $languageIds)
                                ->orWhereIn('language6_id', $languageIds);
                        });
                    }
                })
                ->get()
                ->pluck('monitor');
        } else {
            // Si no se proporcion monitor_id, obtn todos los monitores como antes
            // OPTIMIZACION: Cargar solo campos necesarios del monitor
            $monitorSchools = MonitorsSchool::with([
                'monitor:id,first_name,last_name',
                'monitor.sports' => function ($query) use ($schoolId) {
                    $query->select('sports.id', 'sports.name', 'sports.icon_selected')
                        ->where('monitor_sports_degrees.school_id', $schoolId);
                }
            ])
                ->where('school_id', $schoolId)
                ->where('active_school', 1)
                ->when(!empty($languageIds), function ($query) use ($languageIds) {
                    $query->whereHas('monitor', function ($q) use ($languageIds) {
                        $q->where(function ($q2) use ($languageIds) {
                            $q2->whereIn('language1_id', $languageIds)
                                ->orWhereIn('language2_id', $languageIds)
                                ->orWhereIn('language3_id', $languageIds)
                                ->orWhereIn('language4_id', $languageIds)
                                ->orWhereIn('language5_id', $languageIds)
                                ->orWhereIn('language6_id', $languageIds);
                        });
                    });
                })
                ->get();
            $monitors = $monitorSchools->pluck('monitor');
        }

        // OPTIMIZACION: Cargar authorized degrees para todos los monitores en una sola query
        $monitorIds = $monitors->pluck('id')->toArray();
        if (!empty($monitorIds)) {
            $authorizedDegreesByMonitorSport = MonitorSportAuthorizedDegree::with('degree')
                ->whereHas('monitorSport', function ($q) use ($schoolId, $monitorIds) {
                    $q->where('school_id', $schoolId)
                        ->whereIn('monitor_id', $monitorIds);
                })
                ->get()
                ->groupBy(function ($item) {
                    return $item->monitorSport->monitor_id . '-' . $item->monitorSport->sport_id;
                });

            foreach ($monitors as $monitor) {
                foreach ($monitor->sports as $sport) {
                    $key = $monitor->id . '-' . $sport->id;
                    $sport->authorizedDegrees = $authorizedDegreesByMonitorSport->get($key, collect());
                }
            }
        }

        // Obtn los resultados para las reservas y los MonitorNwd
        $nwd = $nwdQuery->get();
        $subgroups = $subgroupsQuery->get();
        $bookings = $bookingQuery->get();

        // Attach booking user id to each booking user for planner consumers
        $bookings->each(function ($bookingUser) {
            if ($bookingUser->relationLoaded('booking') && $bookingUser->booking) {
                $bookingUser->user_id = $bookingUser->booking->user_id;
            }
        });
        // OPTIMIZACION: Filtrar subgroups por school_id antes de contar
        $subgroupsPerGroup = CourseSubgroup::select('course_group_id', DB::raw('COUNT(*) as total'))
            ->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
            ->join('courses', 'course_groups.course_id', '=', 'courses.id')
            ->where('courses.school_id', $schoolId)
            ->groupBy('course_group_id')
            ->pluck('total', 'course_group_id');

        $groupedData = collect([]);

        // OPTIMIZACION: Calcular full_day NWDs para todos los monitores en una sola query
        $monitorFullDayNwds = collect();
        if ($dateStart && $dateEnd && !empty($monitorIds)) {
            $daysWithinRange = CarbonPeriod::create($dateStart, $dateEnd)->toArray();

            // Obtener todos los NWDs de full_day para los monitores en el rango de fechas
            $fullDayNwds = MonitorNwd::where('school_id', $schoolId)
                ->whereIn('monitor_id', $monitorIds)
                ->where('full_day', true)
                ->where('user_nwd_subtype_id', 1)
                ->where('start_date', '<=', $dateEnd)
                ->where('end_date', '>=', $dateStart)
                ->get();

            foreach ($monitorIds as $mId) {
                $allDaysMeetCriteria = true;
                foreach ($daysWithinRange as $day) {
                    $hasFullDayNwd = $fullDayNwds->where('monitor_id', $mId)
                        ->where('start_date', '<=', $day)
                        ->where('end_date', '>=', $day)
                        ->isNotEmpty();

                    if (!$hasFullDayNwd) {
                        $allDaysMeetCriteria = false;
                        break;
                    }
                }
                $monitorFullDayNwds[$mId] = $allDaysMeetCriteria;
            }
        }

        foreach ($monitors as $monitor) {
            $monitorBookings = $bookings->where('monitor_id', $monitor->id)
                ->groupBy(function ($booking) use ($subgroupsPerGroup) {
                    // Diferencia la agrupacin basada en el course_type
                    if ($booking->course->course_type == 2 || $booking->course->course_type == 3) {
                        // Agrupa por booking.course_id y booking.course_date_id para el tipo 2
                        return $booking->course_id . '-' . $booking->course_date_id;
                    }
                });

            $monitor->hasFullDayNwd = $monitorFullDayNwds->get($monitor->id, false);

            $subgroupsWithMonitor = $subgroups->where('monitor_id', $monitor->id);

            $subgroupsArray = [];

            $subgroupsWithMonitor->each(function ($subgroup) use (&$subgroupsArray, $subgroupsPerGroup) {
                $subgroupId = $subgroup->id;
                $courseDateId = $subgroup->course_date_id;
                $courseId = $subgroup->course_id;

                $totalSubgroups = $subgroupsPerGroup[$subgroup->course_group_id] ?? 1;
                $subgroupPosition = CourseSubgroup::where('course_group_id', $subgroup->course_group_id)
                    ->where('id', '<=', $subgroupId)
                    ->count();

                $subgroup->subgroup_number = $subgroupPosition;
                $subgroup->total_subgroups = $totalSubgroups;

                // OPTIMIZACION: Cargar solo campos necesarios en relaciones faltantes
                $subgroup->loadMissing([
                    'course' => function ($query) {
                        $query->select('id', 'name', 'sport_id', 'course_type', 'max_participants', 'date_start', 'date_end');
                    },
                    'course.courseDates' => function ($query) {
                        $query->select('id', 'course_id', 'date', 'hour_start', 'hour_end');
                    },
                    'courseGroup' => function ($query) {
                        $query->select('id', 'course_id');
                    }
                ]);

                // Define la misma nomenclatura que en los bookings
                $nomenclature = $courseId . '-' . $courseDateId . '-' . $subgroupId;

                // Agrega el subgrupo al array con la nomenclatura como ndice
                $subgroupsArray[$nomenclature] = $subgroup;
            });

            $allBookings = $monitorBookings->concat($subgroupsArray);


            $monitorNwd = $nwd->where('monitor_id', $monitor->id);

            $groupedData[$monitor->id] = [
                'monitor' => $monitor,
                'bookings' => $allBookings,
                'nwds' => $monitorNwd,
                /*'subgroups' => $availableSubgroups,*/
            ];
        }
        $bookingsWithoutMonitor = $bookings->whereNull('monitor_id')->groupBy(function ($booking) use ($subgroupsPerGroup) {
            if ($booking->course->course_type == 2 || $booking->course->course_type == 3) {
                // Si tiene group_id, agrpalo por course_id, course_date_id y group_id
                if ($booking->group_id) {
                    return $booking->course_id . '-' . $booking->course_date_id . '-' . $booking->booking_id . '-' . $booking->group_id;
                }
                // Si no tiene group_id, agrupa por course_id y course_date_id
                return $booking->course_id . '-' . $booking->course_date_id . '-' . $booking->booking_id;
            }
        });


        $subgroupsWithoutMonitor = $subgroups->where('monitor_id', null);

        $subgroupsArray = [];

        $subgroupsWithoutMonitor->each(function ($subgroup) use (&$subgroupsArray, $subgroupsPerGroup) {
            $subgroupId = $subgroup->id;
            $courseDateId = $subgroup->course_date_id;
            $courseId = $subgroup->course_id;

            $totalSubgroups = $subgroupsPerGroup[$subgroup->course_group_id] ?? 1;
            $subgroupPosition = CourseSubgroup::where('course_group_id', $subgroup->course_group_id)
                ->where('id', '<=', $subgroupId)
                ->count();

            $subgroup->subgroup_number = $subgroupPosition;
            $subgroup->total_subgroups = $totalSubgroups;

            // OPTIMIZACION: Cargar solo campos necesarios en relaciones faltantes
            $subgroup->loadMissing([
                'course' => function ($query) {
                    $query->select('id', 'name', 'sport_id', 'course_type', 'max_participants', 'date_start', 'date_end');
                },
                'course.courseDates' => function ($query) {
                    $query->select('id', 'course_id', 'date', 'hour_start', 'hour_end');
                },
                'courseGroup' => function ($query) {
                    $query->select('id', 'course_id');
                }
            ]);

            // Define la misma nomenclatura que en los bookings
            $nomenclature = $courseId . '-' . $courseDateId . '-' . $subgroupId;

            // Agrega el subgrupo al array con la nomenclatura como ndice
            $subgroupsArray[$nomenclature] = $subgroup;
        });

        $allBookings = $bookingsWithoutMonitor->concat($subgroupsArray);

        if ($allBookings->isNotEmpty()) {
            $groupedData['no_monitor'] = [
                'monitor' => null,
                'bookings' => $allBookings,
                'nwds' => collect([]),
                /* 'subgroups' => $subgroupsWithoutMonitor,*/
            ];
        }

        return $groupedData;
    }

    /**
     * @OA\Post(
     *      path="/admin/planner/monitors/transfer",
     *      summary="Transfer Monitor",
     *      tags={"Admin"},
     *      description="Transfer a monitor to multiple booking users and update their course subgroups if applicable.",
     *      @OA\RequestBody(
     *          required=true,
     *          description="Request body for transferring a monitor.",
     *          @OA\JsonContent(
     *              required={"monitor_id", "booking_users"},
     *              @OA\Property(property="monitor_id", type="integer", description="The ID of the monitor to transfer."),
     *              @OA\Property(property="booking_users", type="array", description="Array of booking users to update.",
     *                  @OA\Items(
     *                      @OA\Property(property="id", type="integer", description="The ID of the booking user."),
     *                      @OA\Property(property="date", type="string", format="date", description="The date of the booking user."),
     *                      @OA\Property(property="hour_start", type="string", format="time", description="The start time of the booking user."),
     *                      @OA\Property(property="hour_end", type="string", format="time", description="The end time of the booking user."),
     *                      @OA\Property(property="course_subgroup_id", type="integer", description="The ID of the course subgroup if applicable."),
     *                  )
     *              ),
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(
     *                  property="data",
     *                  type="string",
     *                  description="Message indicating a successful transfer.",
     *              ),
     *          ),
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad request",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(
     *                  property="error",
     *                  type="string",
     *                  description="Error message if the request is invalid.",
     *              ),
     *          ),
     *      )
     * )
     */

    public function transferMonitor(Request $request)
    {
        $monitorId = $request->input('monitor_id');
        $bookingUserIds = (array)$request->input('booking_users', []);
        $scope = $request->input('scope'); // single|interval|all|from|range
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $courseId = $request->input('course_id');
        $bookingId = $request->input('booking_id');
        $courseSubgroupId = $request->input('subgroup_id');     // en front lo llamis subgroup_id
        $courseDateId = $request->input('course_date_id');
        $degreeIdInput = $request->input('degree_id') ?: optional(CourseSubgroup::find($courseSubgroupId))->degree_id;
        $providedSubgroupIds = collect($request->input('subgroup_ids', []) ?? [])
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int)$id)
            ->unique()
            ->values();
        $school = $this->getSchool($request);
        $schoolSettings = $this->getSchoolSettings($school);
        $monitorNotificationService = app(MonitorNotificationService::class);
        $notifications = [];

        if ($providedSubgroupIds->isNotEmpty() && !$degreeIdInput) {
            $degreeIdInput = CourseSubgroup::whereIn('id', $providedSubgroupIds)->pluck('degree_id')->first();
        }

        // 0) Validaciones base
        $monitor = null;
        if ($monitorId !== null) {
            $monitor = Monitor::find($monitorId);
            if (!$monitor) return $this->sendError('Monitor not found');
        }

        // 1) Preparar conjunto vlido de course_dates (no eliminadas)
        $validCourseDateIds = collect();
        if ($courseId) {
            $cdQuery = CourseDate::where('course_id', $courseId)->whereNull('deleted_at');
            switch ($scope) {
                case 'all':
                    break;
                case 'from':
                    if ($startDate) $cdQuery->whereDate('date', '>=', $startDate);
                    break;
                case 'range':
                    if ($startDate && $endDate) $cdQuery->whereBetween('date', [$startDate, $endDate]);
                    break;
                case 'single':
                case 'interval':
                    // se resolvern por date/subgrupo
                    break;
            }
            $validCourseDateIds = $cdQuery->pluck('id');
        }

        if ($scope === 'single' && $courseDateId) {
            $cd = CourseDate::where('id', $courseDateId)->whereNull('deleted_at')->first();
            if (!$cd) return $this->sendError('CourseDate not found or deleted');
        }

        if ($scope === 'interval' && $courseSubgroupId) {
            $sg = CourseSubgroup::with('courseDate')->find($courseSubgroupId);
            if (!$sg) return $this->sendError('CourseSubgroup not found');
            if (!$sg->courseDate || $sg->courseDate->deleted_at) {
                return $this->sendError('CourseSubgroup has deleted or missing CourseDate');
            }
        }

        // 2) Resolver BookingUsers objetivo (si no llegan explcitos)
        $targets = collect();

        if (!empty($bookingUserIds)) {
            $targets = BookingUser::with(['courseDate', 'courseSubgroup'])
                ->whereIn('id', $bookingUserIds)
                ->whereHas('courseDate', fn($q) => $q->whereNull('deleted_at'))
                ->get();
        } else {
            $q = BookingUser::with(['courseDate', 'courseSubgroup'])
                ->whereHas('courseDate', fn($cq) => $cq->whereNull('deleted_at'));

            switch ($scope) {
                case 'single':
                    if ($courseDateId) {
                        $q->where('course_date_id', $courseDateId);
                    } elseif ($bookingId) {
                        $q->where('booking_id', $bookingId);
                    } elseif ($startDate) {
                        $q->whereDate('date', $startDate);
                    }
                    break;

                case 'interval':
                    if ($courseSubgroupId) {
                        // MEJORADO: Usar course_subgroup_dates para obtener las fechas REALES del subgrupo
                        $actualSubgroupDateIds = CourseSubgroupDate::where('course_subgroup_id', $courseSubgroupId)
                            ->pluck('course_date_id');
                        
                        if ($actualSubgroupDateIds->isNotEmpty()) {
                            $q->whereIn('course_date_id', $actualSubgroupDateIds);
                        } else {
                            // Fallback si no hay registros en course_subgroup_dates
                            $q->where('course_subgroup_id', $courseSubgroupId);
                        }
                    }
                    break;

                case 'all':
                    if ($courseId) {
                        $q->whereHas('booking', fn($b) => $b->where('course_id', $courseId));
                    }
                    break;

                case 'from':
                    if ($courseId && $startDate) {
                        $q->whereHas('booking', fn($b) => $b->where('course_id', $courseId))
                            ->whereDate('date', '>=', $startDate);
                    }
                    break;

                case 'range':
                    if ($courseId && $startDate && $endDate) {
                        $q->whereHas('booking', fn($b) => $b->where('course_id', $courseId))
                            ->whereBetween('date', [$startDate, $endDate]);
                    }
                    break;
            }

            if ($validCourseDateIds->isNotEmpty() && in_array($scope, ['all', 'from', 'range'])) {
                $q->whereIn('course_date_id', $validCourseDateIds);
            }

            if ($providedSubgroupIds->isNotEmpty() && $scope !== 'all') {
                $q->whereIn('course_subgroup_id', $providedSubgroupIds);
            }
            $targets = $q->get();
        }

        // 2.1) Determinar degree de contexto
        $degreeIdContext = $degreeIdInput
            ?? ($courseSubgroupId ? (CourseSubgroup::find($courseSubgroupId)->degree_id ?? null) : null)
            ?? optional($targets->first()->courseSubgroup)->degree_id;

        if (!$degreeIdContext) {
            // si no logramos determinar degree, mejor no tocar masivamente
            // (puedes convertir esto en warning si lo prefieres)
            return $this->sendError('Degree not determinable for bulk transfer');
            // fallback: seguimos pero slo tocaremos BUs y subgrupos con degree conocido
        }

        // 3) Resolver Subgrupos objetivo (aunque vacos), con courseDate viva y degree filtrado
        $targetSubgroups = collect();

        $subgroupBase = CourseSubgroup::with('courseDate')
            ->whereHas('courseDate', fn($q) => $q->whereNull('deleted_at'));

        $applyDegreeFilter = function ($query) use ($degreeIdContext) {
            if ($degreeIdContext) $query->where('degree_id', $degreeIdContext);
            return $query;
        };

        if ($providedSubgroupIds->isNotEmpty() && $scope !== 'all') {
            $targetSubgroups = (clone $subgroupBase)
                ->whereIn('id', $providedSubgroupIds)
                ->when($degreeIdContext, fn($q) => $q->where('degree_id', $degreeIdContext))
                ->get();
        } else {
            switch ($scope) {
                case 'single':
                    if ($courseSubgroupId) {
                        $one = (clone $subgroupBase)->where('id', $courseSubgroupId);
                        $one = $applyDegreeFilter($one)->first();
                        if ($one) $targetSubgroups->push($one);
                    } elseif ($courseDateId) {
                        $q = (clone $subgroupBase)->where('course_date_id', $courseDateId);
                        $targetSubgroups = $applyDegreeFilter($q)->get();
                    }
                    break;

                case 'interval':
                    if ($courseSubgroupId) {
                        $one = (clone $subgroupBase)->where('id', $courseSubgroupId);
                        $one = $applyDegreeFilter($one)->first();
                        if ($one) $targetSubgroups->push($one);
                    }
                    if ($courseId && $startDate && $endDate) {
                        $intervalCdIds = CourseDate::where('course_id', $courseId)
                            ->whereNull('deleted_at')
                            ->whereBetween('date', [$startDate, $endDate])
                            ->pluck('id');
                        if ($intervalCdIds->isNotEmpty()) {
                            $intervalSubgroups = $applyDegreeFilter((clone $subgroupBase)->whereIn('course_date_id', $intervalCdIds))->get();
                            $targetSubgroups = $targetSubgroups->merge($intervalSubgroups);
                        }
                    }
                    break;

                case 'all':
                    if ($courseId) {
                        $cdIds = $validCourseDateIds->isNotEmpty()
                            ? $validCourseDateIds
                            : CourseDate::where('course_id', $courseId)->whereNull('deleted_at')->pluck('id');

                        $q = (clone $subgroupBase)->whereIn('course_date_id', $cdIds);
                        $targetSubgroups = $applyDegreeFilter($q)->get();
                    }
                    break;

                case 'from':
                    if ($courseId && $startDate) {
                        $cdIds = CourseDate::where('course_id', $courseId)
                            ->whereNull('deleted_at')
                            ->whereDate('date', '>=', $startDate)
                            ->pluck('id');

                        $q = (clone $subgroupBase)->whereIn('course_date_id', $cdIds);
                        $targetSubgroups = $applyDegreeFilter($q)->get();
                    }
                    break;

                case 'range':
                    if ($courseId && $startDate && $endDate) {
                        $cdIds = CourseDate::where('course_id', $courseId)
                            ->whereNull('deleted_at')
                            ->whereBetween('date', [$startDate, $endDate])
                            ->pluck('id');

                        $q = (clone $subgroupBase)->whereIn('course_date_id', $cdIds);
                        $targetSubgroups = $applyDegreeFilter($q)->get();
                    }
                    break;
            }
        }

        $targetSubgroups = $targetSubgroups->unique('id')->values();

        $targetSubgroups = $targetSubgroups->unique('id')->values();

        $excludeBookingUserIds = $targets
            ->pluck('id')
            ->filter(fn($id) => !is_null($id))
            ->map(fn($id) => (int)$id)
            ->values()
            ->all();

        $bookingRelatedSubgroups = $targets
            ->pluck('course_subgroup_id')
            ->filter(fn($id) => !is_null($id))
            ->map(fn($id) => (int)$id);

        $excludeSubgroupIds = $bookingRelatedSubgroups
            ->merge(
                $targetSubgroups
                    ->pluck('id')
                    ->filter(fn($id) => !is_null($id))
                    ->map(fn($id) => (int)$id)
            )
            ->unique()
            ->values()
            ->all();
        // 3.1) Filtrar BookingUsers por degree tambin (segn esquema)
        /*        if ($degreeIdContext) {
                    // Si BookingUser tiene columna degree_id:
                    if (Schema::hasColumn((new BookingUser)->getTable(), 'degree_id')) {
                        $targets = $targets->where('degree_id', (int)$degreeIdContext)->values();
                    } else {
                        // Si no, filtra por el degree del subgrupo relacionado
                        $targets = $targets->filter(function ($bu) use ($degreeIdContext) {
                            return optional($bu->courseSubgroup)->degree_id === (int)$degreeIdContext;
                        })->values();
                    }
                }*/

        if ($targets->isEmpty() && $targetSubgroups->isEmpty()) {
            return $this->sendError('No booking users or subgroups found for the given scope/degree');
        }

        // 4) Si monitorId === null  desasignar en ambos niveles
        if ($monitorId === null) {
            foreach ($targets as $bu) {
                if ($bu->monitor_id) {
                    $notifications[] = [
                        'monitor_id' => $bu->monitor_id,
                        'type' => ($bu->course_subgroup_id ? 'group' : 'private') . '_removed',
                        'payload' => [
                            'booking_id' => $bu->booking_id,
                            'course_id' => $bu->course_id,
                            'course_date_id' => $bu->course_date_id,
                            'date' => $bu->date,
                            'hour_start' => $bu->hour_start,
                            'hour_end' => $bu->hour_end,
                            'client_id' => $bu->client_id,
                            'course_subgroup_id' => $bu->course_subgroup_id,
                            'course_group_id' => $bu->course_group_id,
                            'group_id' => $bu->group_id,
                            'school_id' => $school->id ?? null,
                        ],
                    ];
                }
            }
            foreach ($targetSubgroups as $sg) {
                if ($sg->monitor_id) {
                    $notifications[] = [
                        'monitor_id' => $sg->monitor_id,
                        'type' => 'group_removed',
                        'payload' => [
                            'course_id' => $sg->course_id,
                            'course_date_id' => $sg->course_date_id,
                            'date' => optional($sg->courseDate)->date,
                            'hour_start' => optional($sg->courseDate)->hour_start,
                            'hour_end' => optional($sg->courseDate)->hour_end,
                            'course_subgroup_id' => $sg->id,
                            'course_group_id' => $sg->course_group_id,
                            'school_id' => $school->id ?? null,
                        ],
                    ];
                }
            }
            DB::transaction(function () use ($targets, $targetSubgroups) {
                foreach ($targets as $bu) {
                    $bu->update(['monitor_id' => null, 'accepted' => true]);
                }
                foreach ($targetSubgroups as $sg) {
                    $sg->update(['monitor_id' => null]);
                }
            });
            foreach ($notifications as $notification) {
                $monitorNotificationService->notifyAssignment(
                    $notification['monitor_id'],
                    $notification['type'],
                    $notification['payload'],
                    $schoolSettings,
                    auth()->id()
                );
            }
            return $this->sendResponse(null, 'Monitor removed successfully.');
        }

        // 5) Comprobar solapes (BookingUsers)
        foreach ($targets as $bu) {
            if (!$bu->date || !$bu->hour_start || !$bu->hour_end) continue;
            if (Monitor::isMonitorBusy($monitorId, $bu->date, $bu->hour_start, $bu->hour_end, null, $excludeBookingUserIds, $excludeSubgroupIds)) {
                return $this->sendError("Overlap detected on {$bu->date}. Monitor cannot be transferred.");
            }
        }

        // 6) Comprobar solapes (Subgrupos)
        foreach ($targetSubgroups as $sg) {
            if (!$sg->courseDate) continue;
            $date = $sg->courseDate->date;
            $hs = $sg->courseDate->hour_start;
            $he = $sg->courseDate->hour_end;
            if (Monitor::isMonitorBusy($monitorId, $date, $hs, $he, null, $excludeBookingUserIds, $excludeSubgroupIds)) {
                return $this->sendError('Overlap detected for subgroup. Monitor cannot be transferred.');
            }
        }

        foreach ($targets as $bu) {
            $typePrefix = $bu->course_subgroup_id ? 'group' : 'private';

            if ($bu->monitor_id && $bu->monitor_id !== $monitorId) {
                $notifications[] = [
                    'monitor_id' => $bu->monitor_id,
                    'type' => "{$typePrefix}_removed",
                    'payload' => [
                        'booking_id' => $bu->booking_id,
                        'course_id' => $bu->course_id,
                        'course_date_id' => $bu->course_date_id,
                        'date' => $bu->date,
                        'hour_start' => $bu->hour_start,
                        'hour_end' => $bu->hour_end,
                        'client_id' => $bu->client_id,
                        'course_subgroup_id' => $bu->course_subgroup_id,
                        'course_group_id' => $bu->course_group_id,
                        'group_id' => $bu->group_id,
                        'school_id' => $school->id ?? null,
                    ],
                ];
            }

            if ($bu->monitor_id !== $monitorId) {
                $notifications[] = [
                    'monitor_id' => $monitorId,
                    'type' => "{$typePrefix}_assigned",
                    'payload' => [
                        'booking_id' => $bu->booking_id,
                        'course_id' => $bu->course_id,
                        'course_date_id' => $bu->course_date_id,
                        'date' => $bu->date,
                        'hour_start' => $bu->hour_start,
                        'hour_end' => $bu->hour_end,
                        'client_id' => $bu->client_id,
                        'course_subgroup_id' => $bu->course_subgroup_id,
                        'course_group_id' => $bu->course_group_id,
                        'group_id' => $bu->group_id,
                        'school_id' => $school->id ?? null,
                    ],
                ];
            }
        }

        foreach ($targetSubgroups as $sg) {
            if ($sg->monitor_id && $sg->monitor_id !== $monitorId) {
                $notifications[] = [
                    'monitor_id' => $sg->monitor_id,
                    'type' => 'group_removed',
                    'payload' => [
                        'course_id' => $sg->course_id,
                        'course_date_id' => $sg->course_date_id,
                        'date' => optional($sg->courseDate)->date,
                        'hour_start' => optional($sg->courseDate)->hour_start,
                        'hour_end' => optional($sg->courseDate)->hour_end,
                        'course_subgroup_id' => $sg->id,
                        'course_group_id' => $sg->course_group_id,
                        'school_id' => $school->id ?? null,
                    ],
                ];
            }

            if ($sg->monitor_id !== $monitorId) {
                $notifications[] = [
                    'monitor_id' => $monitorId,
                    'type' => 'group_assigned',
                    'payload' => [
                        'course_id' => $sg->course_id,
                        'course_date_id' => $sg->course_date_id,
                        'date' => optional($sg->courseDate)->date,
                        'hour_start' => optional($sg->courseDate)->hour_start,
                        'hour_end' => optional($sg->courseDate)->hour_end,
                        'course_subgroup_id' => $sg->id,
                        'course_group_id' => $sg->course_group_id,
                        'school_id' => $school->id ?? null,
                    ],
                ];
            }
        }

        // 7) Aplicar cambios
        DB::transaction(function () use ($monitorId, $scope, $targets, $targetSubgroups) {
            foreach ($targets as $bu) {
                $bu->update(['monitor_id' => $monitorId, 'accepted' => true]);

                if (in_array($scope, ['interval', 'all', 'from', 'range']) && $bu->course_subgroup_id) {
                    CourseSubgroup::where('id', $bu->course_subgroup_id)->update(['monitor_id' => $monitorId]);
                }
            }
            foreach ($targetSubgroups as $sg) {
                $sg->update(['monitor_id' => $monitorId]);
            }
        });

        foreach ($notifications as $notification) {
            $monitorNotificationService->notifyAssignment(
                $notification['monitor_id'],
                $notification['type'],
                $notification['payload'],
                $schoolSettings,
                auth()->id()
            );
        }

        return $this->sendResponse($monitor, 'Monitor updated successfully for scope: ' . $scope);
    }


    public function previewMonitorTransfer(Request $request): JsonResponse
    {
        $scope = $request->input('scope') ?? 'single';
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $courseId = $request->input('course_id');
        $subgroupId = $request->input('subgroup_id');
        $subgroupIds = collect($request->input('subgroup_ids', []))
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int)$id)
            ->values()
            ->all();

        if (!$courseId && !$subgroupId && empty($subgroupIds)) {
            return $this->sendError('Missing scope context to preview transfer.');
        }

        $query = CourseSubgroup::query()
            ->with([
                'courseSubgroupDates.courseDate:id,date,hour_start,hour_end,course_id',
                'courseSubgroupDates.courseDate.course:id,name',
                'courseGroup:id,course_id,degree_id',
                'courseGroup.course:id,name',
                'monitor:id,first_name,last_name'
            ])
            ->whereHas('courseSubgroupDates.courseDate', fn($q) => $q->whereNull('deleted_at'));

        if ($courseId) {
            $query->whereHas('courseGroup.course', fn($q) => $q->where('id', $courseId));
        }

        // MEJORADO: Para scope='all', recuperar TODOS los subgroups con el MISMO subgroup_dates_id
        // Esto es ms eficiente y explcito que buscar por course_group_id
        if ($scope === 'all' && $subgroupId) {
            $selectedSubgroup = CourseSubgroup::find($subgroupId);
            if ($selectedSubgroup && $selectedSubgroup->subgroup_dates_id) {
                $query->where('subgroup_dates_id', $selectedSubgroup->subgroup_dates_id);
            } elseif ($selectedSubgroup) {
                // FALLBACK: Si no tiene subgroup_dates_id, usar posición para encontrar homónimos
                // Esto es para compatibilidad con cursos legacy o mal creados
                $this->applyPositionBasedFallback($query, $selectedSubgroup);
            } else {
                return $this->sendError('Selected subgroup not found.');
            }
        } elseif ($scope === 'all' && !empty($subgroupIds)) {
            $selectedSubgroup = CourseSubgroup::find($subgroupIds[0]);
            if ($selectedSubgroup && $selectedSubgroup->subgroup_dates_id) {
                $query->where('subgroup_dates_id', $selectedSubgroup->subgroup_dates_id);
            } elseif ($selectedSubgroup) {
                // FALLBACK: Si no tiene subgroup_dates_id, usar posición para encontrar homónimos
                // Esto es para compatibilidad con cursos legacy o mal creados
                $this->applyPositionBasedFallback($query, $selectedSubgroup);
            } else {
                return $this->sendError('Selected subgroup not found.');
            }
        } elseif (!empty($subgroupIds)) {
            // Para otros scopes, filtrar por los subgroupIds especficos
            $query->whereIn('id', $subgroupIds);
        } elseif ($subgroupId) {
            // Para scope='single', filtrar por el subgroup especfico
            $query->where('id', $subgroupId);
        }

        // Para scope='all', NO filtrar por rango en la query (queremos TODOS los subgroups)
        // Para otros scopes, filtrar por rango en la query (ms eficiente)
        $_dateFiltered = false;
        if ($startDate && $scope !== 'all') {
            $end = $endDate ?? $startDate;
            // MEJORADO: Para 'from' y 'range', intentar with junction table
            // Si falla o no hay resultados, continuar sin ese filtro
            // (se filtrará en memoria para compatibilidad con legacy courses)
            try {
                $testQuery = clone $query;
                $testResults = $testQuery->whereHas('courseSubgroupDates.courseDate',
                    fn($q) => $q->whereBetween('date', [$startDate, $end])->whereNull('deleted_at')
                )->limit(1)->get();

                if ($testResults->isNotEmpty()) {
                    // Si hay resultados con junction table, aplicar el filtro
                    $query->whereHas('courseSubgroupDates.courseDate',
                        fn($q) => $q->whereBetween('date', [$startDate, $end])->whereNull('deleted_at')
                    );
                    $_dateFiltered = true;
                }
            } catch (\Exception $e) {
                // Si hay error, continuar sin este filtro (legacy courses)
            }
        }

        $rawSubgroups = $query->get();

        // MEJORADO: Para scope='all', agrupar por subgroup_dates_id para evitar duplicados
        // Si tenemos scope='all', todos los subgroups tendrn el mismo subgroup_dates_id
        // Debemos retornar UN resultado con TODAS las fechas, no N resultados (uno por fecha)
        if ($scope === 'all' && $rawSubgroups->isNotEmpty()) {
            $groupedByDatesId = $rawSubgroups->groupBy('subgroup_dates_id');
            $subgroups = $groupedByDatesId->map(function ($subgroupsGroup) use ($startDate, $endDate) {
                // Tomar el primer subgroup como referencia (todos comparten mismo subgroup_dates_id y propiedades)
                $firstSubgroup = $subgroupsGroup->first();
                $course = $firstSubgroup->courseGroup?->course;

                // MEJORADO: Crear mapa de course_date_id => monitor
                // Cada subgrupo est en fechas diferentes y puede tener monitor diferente
                $dateMonitorMap = [];
                foreach ($subgroupsGroup as $sg) {
                    $courseDateIds = $sg->courseSubgroupDates()->pluck('course_date_id');
                    $monitor = $sg->monitor;
                    foreach ($courseDateIds as $cdId) {
                        $dateMonitorMap[$cdId] = $monitor;
                    }
                }

                // Recolectar TODAS las fechas de TODOS los subgroups en este grupo
                $allDates = collect();
                foreach ($subgroupsGroup as $sg) {
                    $dates = $sg->allCourseDates()
                        ->whereNull('course_dates.deleted_at')
                        ->orderBy('course_dates.date', 'asc')
                        ->select('course_dates.id', 'course_dates.date', 'course_dates.hour_start', 'course_dates.hour_end')
                        ->get();
                    $allDates = $allDates->merge($dates);
                }

                // Eliminar duplicados por course_date_id
                $allDates = $allDates->unique('id')->sortBy('date')->values();

                // Si hay rango de fechas, filtrar
                if ($startDate && $endDate) {
                    $allDates = $allDates->filter(function ($date) use ($startDate, $endDate) {
                        $dateStr = is_string($date->date) ? $date->date : $date->date->format('Y-m-d');
                        return $dateStr >= $startDate && $dateStr <= $endDate;
                    });
                } elseif ($startDate) {
                    $allDates = $allDates->filter(function ($date) use ($startDate) {
                        $dateStr = is_string($date->date) ? $date->date : $date->date->format('Y-m-d');
                        return $dateStr === $startDate;
                    });
                }

                $homonymousDateIds = $allDates->pluck('id')->toArray();

                return [
                    'id' => $firstSubgroup->id,
                    'course' => [
                        'id' => $course?->id,
                        'name' => $course?->name
                    ],
                    'level_label' => $firstSubgroup->name
                        ?? $firstSubgroup->courseGroup?->name
                            ?? $firstSubgroup->degree?->name
                            ?? null,
                    'current_monitor' => null, // Para scope='all', usar monitor por fecha
                    'all_dates_in_subgroup' => $allDates->map(function($d) use ($dateMonitorMap) {
                        $monitor = $dateMonitorMap[$d->id] ?? null;
                        return [
                            'course_date_id' => $d->id,
                            'date' => is_string($d->date) ? $d->date : $d->date->format('Y-m-d'),
                            'hour_start' => $d->hour_start,
                            'hour_end' => $d->hour_end,
                            'current_monitor' => $monitor ? [
                                'id' => $monitor->id,
                                'name' => trim(($monitor->first_name ?? '') . ' ' . ($monitor->last_name ?? ''))
                            ] : null
                        ];
                    })->values(),
                    'course_subgroup_dates_ids' => $homonymousDateIds,
                    'total_dates_in_subgroup' => count($homonymousDateIds)
                ];
            })->values();
        } else {
            // Para otros scopes, mapear normalmente (cada subgroup es una instancia nica)
            $subgroups = $rawSubgroups->map(function (CourseSubgroup $subgroup) use ($scope, $startDate, $endDate, $_dateFiltered) {
                $course = $subgroup->courseGroup?->course;

                // MEJORADO: Obtener mapa de course_date_id => monitor para cada fecha
                // Permite mostrar monitor diferente por fecha incluso en otros scopes
                $dateMonitorMap = [];
                $subgroupDates = $subgroup->courseSubgroupDates()
                    ->with('courseDate')
                    ->get();

                foreach ($subgroupDates as $sd) {
                    $dateMonitorMap[$sd->course_date_id] = $subgroup->monitor;
                }

                // Obtener TODAS las fechas del subgrupo desde la tabla junction
                $homonymousDates = $subgroup->allCourseDates()
                    ->whereNull('course_dates.deleted_at')
                    ->orderBy('course_dates.date', 'asc')
                    ->select('course_dates.id', 'course_dates.date', 'course_dates.hour_start', 'course_dates.hour_end')
                    ->get();

                // Si hay rango de fechas, filtrar las que caen dentro del rango
                // Aplicar filtrado en memoria si no fue filtrado en la query (legacy courses)
                if ($scope !== 'all' && !$_dateFiltered && $startDate) {
                    if ($startDate && $endDate && $scope === 'range') {
                        $homonymousDates = $homonymousDates->filter(function ($date) use ($startDate, $endDate) {
                            $dateStr = is_string($date->date) ? $date->date : $date->date->format('Y-m-d');
                            return $dateStr >= $startDate && $dateStr <= $endDate;
                        });
                    } elseif ($startDate && $scope === 'from') {
                        $homonymousDates = $homonymousDates->filter(function ($date) use ($startDate) {
                            $dateStr = is_string($date->date) ? $date->date : $date->date->format('Y-m-d');
                            return $dateStr >= $startDate;
                        });
                    }
                } elseif ($scope === 'all') {
                    // Para scope='all', aplicar filtrado si existe rango
                    if ($startDate && $endDate) {
                        $homonymousDates = $homonymousDates->filter(function ($date) use ($startDate, $endDate) {
                            $dateStr = is_string($date->date) ? $date->date : $date->date->format('Y-m-d');
                            return $dateStr >= $startDate && $dateStr <= $endDate;
                        });
                    } elseif ($startDate) {
                        $homonymousDates = $homonymousDates->filter(function ($date) use ($startDate) {
                            $dateStr = is_string($date->date) ? $date->date : $date->date->format('Y-m-d');
                            return $dateStr === $startDate;
                        });
                    }
                }

                $homonymousDateIds = $homonymousDates->pluck('id')->toArray();

                return [
                    'id' => $subgroup->id,
                    'course' => [
                        'id' => $course?->id,
                        'name' => $course?->name
                    ],
                    'level_label' => $subgroup->name
                        ?? $subgroup->courseGroup?->name
                            ?? $subgroup->degree?->name
                            ?? null,
                    'current_monitor' => null, // Usar monitor por fecha como en scope='all'
                    'all_dates_in_subgroup' => $homonymousDates->map(function($d) use ($dateMonitorMap) {
                        $monitor = $dateMonitorMap[$d->id] ?? null;
                        return [
                            'course_date_id' => $d->id,
                            'date' => is_string($d->date) ? $d->date : $d->date->format('Y-m-d'),
                            'hour_start' => $d->hour_start,
                            'hour_end' => $d->hour_end,
                            'current_monitor' => $monitor ? [
                                'id' => $monitor->id,
                                'name' => trim(($monitor->first_name ?? '') . ' ' . ($monitor->last_name ?? ''))
                            ] : null
                        ];
                    })->values(),
                    'course_subgroup_dates_ids' => $homonymousDateIds,
                    'total_dates_in_subgroup' => count($homonymousDateIds)
                ];
            });
        }

        if ($subgroups->isEmpty()) {
            return $this->sendResponse([], 'No subgroups found for preview.');
        }

        return $this->sendResponse($subgroups, 'Monitor transfer preview ready.');
    }

    /**
     * FALLBACK: Apply position-based filtering for legacy courses without subgroup_dates_id
     *
     * When a subgroup doesn't have subgroup_dates_id, we find all homonymous copies
     * by matching position within the same course_group on the same course_date
     * This ensures legacy/poorly created courses still work with transfer-preview
     */
    private function applyPositionBasedFallback(&$query, CourseSubgroup $selectedSubgroup)
    {
        $courseGroup = $selectedSubgroup->courseGroup;
        $courseDate = $selectedSubgroup->courseDate;

        if (!$courseGroup || !$courseDate) {
            // If we can't determine position context, just return the selected subgroup
            $query->where('id', $selectedSubgroup->id);
            return;
        }

        // Get the position of this subgroup within its course_group (0-indexed)
        $position = $courseGroup->courseSubgroups()
            ->orderBy('id')
            ->pluck('id')
            ->search($selectedSubgroup->id);

        if ($position === false) {
            // Fallback if position can't be determined
            $query->where('id', $selectedSubgroup->id);
            return;
        }

        // Get the course to find all dates with this course_group
        $course = $courseGroup->course;
        if (!$course) {
            $query->where('id', $selectedSubgroup->id);
            return;
        }

        // Find all course_groups with the same structure across all course dates
        // Then find the subgroup at the same position in each group
        $courseDates = $course->courseDates()->whereNull('deleted_at')->pluck('id');

        // Get all subgroup IDs that are at the same position in their respective groups
        $homonymousIds = DB::table('course_groups')
            ->whereIn('course_date_id', $courseDates)
            ->where('degree_id', $courseGroup->degree_id)
            ->get()
            ->flatMap(function($group) use ($position) {
                return CourseSubgroup::where('course_group_id', $group->id)
                    ->orderBy('id')
                    ->get()
                    ->skip($position)
                    ->take(1)
                    ->pluck('id');
            })
            ->unique();

        if ($homonymousIds->isNotEmpty()) {
            $query->whereIn('id', $homonymousIds);
        } else {
            // Fallback: just use the selected subgroup
            $query->where('id', $selectedSubgroup->id);
        }
    }

    private function getSchoolSettings($school): array
    {
        $settings = $school->settings ?? [];

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $settings : [];
    }

}
