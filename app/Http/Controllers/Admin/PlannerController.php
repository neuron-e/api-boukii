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
                            // withPivot('degree_id') ya está en el modelo, se incluye automáticamente
                        }
                    ]);
            }
        ])
            ->select('id', 'course_group_id', 'course_date_id', 'course_id', 'monitor_id',
                'degree_id', 'max_participants')
            ->whereHas('courseGroup.course', function ($query) use ($schoolId) {
                // Agrega la comprobación de la escuela aquí
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

                    // Busca en el día de hoy para las reservas
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
                // withPivot('degree_id') ya está en el modelo, se incluye automáticamente
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
            // Si no se proporcionan fechas, busca en el día de hoy
            $today = Carbon::today();

            // Busca en el día de hoy para las reservas
            $bookingQuery->whereDate('date', $today);

            // Busca en el día de hoy para los MonitorNwd
            $nwdQuery->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today);
        }


        if ($monitorId) {
            // Filtra solo las reservas y los NWD para el monitor específico
            $bookingQuery->where('monitor_id', $monitorId);
            $nwdQuery->where('monitor_id', $monitorId);
            $subgroupsQuery->where('monitor_id', $monitorId);

            // Obtén solo el monitor específico
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
            // Si no se proporcionó monitor_id, obtén todos los monitores como antes
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

        // Obtén los resultados para las reservas y los MonitorNwd
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
                    // Diferencia la agrupación basada en el course_type
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

                // Agrega el subgrupo al array con la nomenclatura como índice
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
                // Si tiene group_id, agrúpalo por course_id, course_date_id y group_id
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

            // Agrega el subgrupo al array con la nomenclatura como índice
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
        $courseSubgroupId = $request->input('subgroup_id');     // en front lo llamáis subgroup_id
        $courseDateId = $request->input('course_date_id');
        $degreeIdInput = $request->input('degree_id') ?: optional(CourseSubgroup::find($courseSubgroupId))->degree_id;
        $providedSubgroupIds = collect($request->input('subgroup_ids', []) ?? [])
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int)$id)
            ->unique()
            ->values();

        if ($providedSubgroupIds->isNotEmpty() && !$degreeIdInput) {
            $degreeIdInput = CourseSubgroup::whereIn('id', $providedSubgroupIds)->pluck('degree_id')->first();
        }

        // 0) Validaciones base
        $monitor = null;
        if ($monitorId !== null) {
            $monitor = Monitor::find($monitorId);
            if (!$monitor) return $this->sendError('Monitor not found');
        }

        // 1) Preparar conjunto válido de course_dates (no eliminadas)
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
                    // se resolverán por date/subgrupo
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

        // 2) Resolver BookingUsers objetivo (si no llegan explícitos)
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
            // fallback: seguimos pero sólo tocaremos BUs y subgrupos con degree conocido
        }

        // 3) Resolver Subgrupos objetivo (aunque vacíos), con courseDate viva y degree filtrado
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
        // 3.1) Filtrar BookingUsers por degree también (según esquema)
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

        // 4) Si monitorId === null → desasignar en ambos niveles
        if ($monitorId === null) {
            DB::transaction(function () use ($targets, $targetSubgroups) {
                foreach ($targets as $bu) {
                    $bu->update(['monitor_id' => null, 'accepted' => true]);
                }
                foreach ($targetSubgroups as $sg) {
                    $sg->update(['monitor_id' => null]);
                }
            });
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
                'courseDate:id,date,hour_start,hour_end,course_id',
                'courseDate.course:id,name',
                'courseGroup:id,course_id',
                'courseGroup.course:id,name',
                'monitor:id,first_name,last_name'
            ])
            ->whereHas('courseDate', fn($q) => $q->whereNull('deleted_at'));

        if ($courseId) {
            $query->whereHas('courseGroup.course', fn($q) => $q->where('id', $courseId));
        }

        if (!empty($subgroupIds)) {
            $query->whereIn('id', $subgroupIds);
        } elseif ($subgroupId) {
            $query->where('id', $subgroupId);
        }

        if ($startDate) {
            if ($scope === 'single') {
                $query->whereHas('courseDate', fn($q) => $q->whereDate('date', $startDate));
            } else {
                // Para scopes multi-fecha, filtrar por cualquier fecha del subgrupo en el rango
                $end = $endDate ?? $startDate;
                $query->whereHas('courseSubgroupDates.courseDate',
                    fn($q) => $q->whereBetween('date', [$startDate, $end])->whereNull('deleted_at')
                );
            }
        }

        $subgroups = $query->get()->map(function (CourseSubgroup $subgroup) use ($scope, $courseId) {
            $courseDate = $subgroup->courseDate;
            $course = $courseDate?->course ?? $subgroup->courseGroup?->course;
            $monitor = $subgroup->monitor;

            // NUEVO: Obtener TODAS las fechas del subgrupo (o del curso si scope='all')
            if ($scope === 'all' && $courseId) {
                // Para scope='all', obtener TODAS las fechas del curso
                $homonymousDates = \App\Models\CourseDate::where('course_id', $courseId)
                    ->whereNull('deleted_at')
                    ->orderBy('date', 'asc')
                    ->select('id', 'date', 'hour_start', 'hour_end')
                    ->get();
            } else {
                // Para otros scopes, obtener solo las fechas del subgrupo
                $homonymousDates = $subgroup->allCourseDates()
                    ->orderBy('date', 'asc')
                    ->select('course_dates.id', 'date', 'hour_start', 'hour_end')
                    ->get();
            }

            $homonymousDateIds = $homonymousDates->pluck('id')->toArray();

            return [
                'id' => $subgroup->id,
                'date' => optional($courseDate)->date,
                'hour_start' => optional($courseDate)->hour_start,
                'hour_end' => optional($courseDate)->hour_end,
                'course' => [
                    'id' => $course?->id,
                    'name' => $course?->name
                ],
                'level_label' => $subgroup->name
                    ?? $subgroup->courseGroup?->name
                        ?? $subgroup->degree?->name
                        ?? null,
                'current_monitor' => $monitor ? [
                    'id' => $monitor->id,
                    'name' => trim(($monitor->first_name ?? '') . ' ' . ($monitor->last_name ?? ''))
                ] : null,
                // NUEVO: Retornar todas las fechas del subgrupo
                'all_dates_in_subgroup' => $homonymousDates->map(fn($d) => [
                    'course_date_id' => $d->id,
                    'date' => $d->date,
                    'hour_start' => $d->hour_start,
                    'hour_end' => $d->hour_end
                ])->values(),
                'course_subgroup_dates_ids' => $homonymousDateIds,
                'total_dates_in_subgroup' => count($homonymousDateIds)
            ];
        });

        if ($subgroups->isEmpty()) {
            return $this->sendResponse([], 'No subgroups found for preview.');
        }

        return $this->sendResponse($subgroups, 'Monitor transfer preview ready.');
    }
}
