<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\BookingUser;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseSubgroup;
use App\Models\CourseSubgroupDate;
use App\Models\Monitor;
use App\Models\MonitorNwd;
use App\Models\MonitorSportAuthorizedDegree;
use App\Models\MonitorsSchool;
use App\Models\Station;
use App\Services\MonitorNotificationService;
use App\Services\CourseRepairDispatcher;
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
    private CourseRepairDispatcher $repairDispatcher;

    public function __construct(CourseRepairDispatcher $repairDispatcher)
    {
        $this->repairDispatcher = $repairDispatcher;
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
            'course' => function ($query) use ($dateStart, $dateEnd) {
                $query->select('id', 'name', 'sport_id', 'course_type', 'max_participants', 'date_start', 'date_end')
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
            'courseDate:id,course_id,date,hour_start,hour_end,active,deleted_at',
            'courseGroup:id,course_id',
            'courseGroup.course:id,name,sport_id,course_type,max_participants,date_start,date_end',
            'bookingUsers' => function ($query) {
                $query->select('id', 'booking_id', 'client_id', 'course_id', 'course_date_id', 'course_subgroup_id',
                    'monitor_id', 'group_id', 'date', 'hour_start', 'hour_end', 'status', 'accepted',
                    'degree_id', 'color', 'school_id', 'attended')
                    ->where('status', 1)
                    ->whereHas('booking')
                    ->with([
                        'booking:id,user_id,paid',
                        'booking.user:id,first_name,last_name',
                        'course:id,name,sport_id,course_type,max_participants,date_start,date_end',
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
            'course' => function ($query) use ($dateStart, $dateEnd) {
                $query->select('id', 'name', 'sport_id', 'course_type', 'max_participants', 'date_start', 'date_end')
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
            'client:id,first_name,last_name,birth_date,language1_id',
            'client.sports' => function ($query) {
                $query->select('sports.id', 'sports.name');
                // withPivot('degree_id') ya est en el modelo, se incluye automticamente
            }
        ])
            ->select('id', 'booking_id', 'client_id', 'course_id', 'course_date_id', 'course_subgroup_id',
                'monitor_id', 'group_id', 'date', 'hour_start', 'hour_end', 'status', 'accepted',
                'degree_id', 'color', 'school_id', 'attended')
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
                'monitor:id,first_name,last_name,birth_date',
                'monitor.sports' => function ($query) use ($schoolId) {
                    $query->select('sports.id', 'sports.name', 'sports.icon_selected')
                        ->where('monitor_sports_degrees.school_id', $schoolId);
                }
            ])
                ->where('school_id', $schoolId)
                ->whereHas('monitor', function ($query) use ($monitorId, $languageIds) {
                    $query->where('id', $monitorId);
                    if (!empty($languageIds)) {
                        $placeholders = implode(',', array_fill(0, count($languageIds), '?'));
                        $query->whereRaw(
                            '(monitors.language1_id IN (' . $placeholders . ')' .
                            ' OR monitors.language2_id IN (' . $placeholders . ')' .
                            ' OR monitors.language3_id IN (' . $placeholders . ')' .
                            ' OR monitors.language4_id IN (' . $placeholders . ')' .
                            ' OR monitors.language5_id IN (' . $placeholders . ')' .
                            ' OR monitors.language6_id IN (' . $placeholders . '))',
                            array_merge($languageIds, $languageIds, $languageIds, $languageIds, $languageIds, $languageIds)
                        );
                    }
                })
                ->get()
                ->pluck('monitor');
        } else {
            // Si no se proporcion monitor_id, obtn todos los monitores como antes
            // OPTIMIZACION: Cargar solo campos necesarios del monitor
            $monitorSchools = MonitorsSchool::with([
                'monitor:id,first_name,last_name,birth_date',
                'monitor.sports' => function ($query) use ($schoolId) {
                    $query->select('sports.id', 'sports.name', 'sports.icon_selected')
                        ->where('monitor_sports_degrees.school_id', $schoolId);
                }
            ])
                ->where('school_id', $schoolId)
                ->where('active_school', 1)
                ->when(!empty($languageIds), function ($query) use ($languageIds) {
                    $query->whereHas('monitor', function ($q) use ($languageIds) {
                        $placeholders = implode(',', array_fill(0, count($languageIds), '?'));
                        $q->whereRaw(
                            '(monitors.language1_id IN (' . $placeholders . ')' .
                            ' OR monitors.language2_id IN (' . $placeholders . ')' .
                            ' OR monitors.language3_id IN (' . $placeholders . ')' .
                            ' OR monitors.language4_id IN (' . $placeholders . ')' .
                            ' OR monitors.language5_id IN (' . $placeholders . ')' .
                            ' OR monitors.language6_id IN (' . $placeholders . '))',
                            array_merge($languageIds, $languageIds, $languageIds, $languageIds, $languageIds, $languageIds)
                        );
                    });
                })
                ->get();
            $monitors = $monitorSchools->pluck('monitor');
        }

        // OPTIMIZACION: Cargar authorized degrees para todos los monitores en una sola query
        $monitorIds = $monitors->pluck('id')->toArray();
        if (!empty($monitorIds)) {
            $authorizedDegreesByMonitorSport = MonitorSportAuthorizedDegree::with([
                'degree:id,name,degree_order',
                'monitorSport' => function ($query) use ($schoolId, $monitorIds) {
                    $query->select('id', 'monitor_id', 'sport_id', 'school_id')
                        ->where('school_id', $schoolId)
                        ->whereIn('monitor_id', $monitorIds);
                }
            ])
                ->whereHas('monitorSport', function ($q) use ($schoolId, $monitorIds) {
                    $q->where('school_id', $schoolId)
                        ->whereIn('monitor_id', $monitorIds);
                })
                ->select('id', 'monitor_sport_id', 'degree_id')
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

        // Normalize missing monitor_id for private/activity groups so planner can group them together.
        $monitorByGroupKey = [];
        $monitorByGroupKeyConflict = [];
        foreach ($bookings as $booking) {
            $courseType = $booking->course?->course_type;
            if (!in_array($courseType, [2, 3], true)) {
                continue;
            }
            if (empty($booking->group_id) || empty($booking->monitor_id)) {
                continue;
            }
            $key = implode('|', [
                $booking->booking_id ?? 'no-booking',
                $booking->group_id,
                $booking->course_id ?? 'no-course',
                $booking->course_date_id ?? 'no-date',
                $booking->date ?? 'no-day',
                $booking->hour_start ?? 'no-start',
                $booking->hour_end ?? 'no-end',
            ]);
            if (!isset($monitorByGroupKey[$key])) {
                $monitorByGroupKey[$key] = $booking->monitor_id;
                continue;
            }
            if ($monitorByGroupKey[$key] !== $booking->monitor_id) {
                $monitorByGroupKeyConflict[$key] = true;
            }
        }

        foreach ($bookings as $booking) {
            $courseType = $booking->course?->course_type;
            if (!in_array($courseType, [2, 3], true)) {
                continue;
            }
            if (!empty($booking->monitor_id) || empty($booking->group_id)) {
                continue;
            }
            $key = implode('|', [
                $booking->booking_id ?? 'no-booking',
                $booking->group_id,
                $booking->course_id ?? 'no-course',
                $booking->course_date_id ?? 'no-date',
                $booking->date ?? 'no-day',
                $booking->hour_start ?? 'no-start',
                $booking->hour_end ?? 'no-end',
            ]);
            if (!empty($monitorByGroupKeyConflict[$key])) {
                continue;
            }
            if (!empty($monitorByGroupKey[$key])) {
                $booking->monitor_id = $monitorByGroupKey[$key];
            }
        }

        $courseIds = collect()
            ->merge($bookings->pluck('course_id'))
            ->merge($subgroups->pluck('course_id'))
            ->merge($subgroups->pluck('courseGroup.course_id'))
            ->merge($subgroups->flatMap(function ($subgroup) {
                return $subgroup->relationLoaded('bookingUsers')
                    ? $subgroup->bookingUsers->pluck('course_id')
                    : collect();
            }))
            ->filter()
            ->unique()
            ->values();

        if ($courseIds->isNotEmpty()) {
            $courses = Course::select('id', 'name', 'sport_id', 'course_type', 'max_participants', 'date_start', 'date_end')
                ->withCount(['courseDates as course_dates_total'])
                ->with(['courseDates' => function ($dateQuery) use ($dateStart, $dateEnd) {
                    $dateQuery->select('id', 'course_id', 'date', 'hour_start', 'hour_end')
                        ->orderBy('date');
                    if ($dateStart && $dateEnd) {
                        $dateQuery->whereBetween('date', [$dateStart, $dateEnd]);
                    } else {
                        $dateQuery->whereDate('date', Carbon::today());
                    }
                }])
                ->whereIn('id', $courseIds)
                ->get()
                ->keyBy('id');

            $attachCourse = function ($model) use ($courses): void {
                if (!$model) {
                    return;
                }

                if (!$model->relationLoaded('course') || !$model->course) {
                    $course = $courses->get($model->course_id);
                    if ($course) {
                        $model->setRelation('course', $course);
                    }
                }
            };

            $bookings->each($attachCourse);

            $subgroups->each(function ($subgroup) use ($attachCourse, $courses) {
                $attachCourse($subgroup);
                if ($subgroup->relationLoaded('courseGroup') && $subgroup->courseGroup) {
                    if (!$subgroup->courseGroup->relationLoaded('course') || !$subgroup->courseGroup->course) {
                        $course = $courses->get($subgroup->courseGroup->course_id);
                        if ($course) {
                            $subgroup->courseGroup->setRelation('course', $course);
                        }
                    }
                }
                if ($subgroup->relationLoaded('bookingUsers')) {
                    $subgroup->bookingUsers->each($attachCourse);
                }
            });
        }

        $stripCourseAppends = function ($course): void {
            if ($course) {
                $course->setAppends([]);
            }
        };
        $stripBookingAppends = function ($booking): void {
            if ($booking) {
                $booking->setAppends([]);
                if (method_exists($booking, 'disableSportLazyLoad')) {
                    $booking->disableSportLazyLoad();
                }
            }
        };

        $bookings->each(function ($booking) use ($stripCourseAppends, $stripBookingAppends) {
            $stripCourseAppends($booking->course ?? null);
            $stripBookingAppends($booking->booking ?? null);
        });

        $subgroups->each(function ($subgroup) use ($stripCourseAppends, $stripBookingAppends) {
            $stripCourseAppends($subgroup->course ?? null);
            $stripCourseAppends($subgroup->courseGroup?->course ?? null);
            if ($subgroup->relationLoaded('bookingUsers')) {
                $subgroup->bookingUsers->each(function ($bookingUser) use ($stripCourseAppends, $stripBookingAppends) {
                    $stripCourseAppends($bookingUser->course ?? null);
                    $stripBookingAppends($bookingUser->booking ?? null);
                });
            }
        });

        $subgroupPositions = collect();
        $subgroupTotals = collect();
        $subgroupKey = static function ($subgroup): string {
            return ($subgroup->course_id ?? 0) . '-' . ($subgroup->course_date_id ?? 0) . '-' . ($subgroup->degree_id ?? 0);
        };
        if ($subgroups->isNotEmpty()) {
            $groupedByKey = $subgroups->groupBy(fn($subgroup) => $subgroupKey($subgroup));
            foreach ($groupedByKey as $key => $items) {
                $sorted = $items->sortBy('id')->values();
                $subgroupTotals[$key] = $sorted->count();
                foreach ($sorted as $index => $subgroup) {
                    $subgroupPositions[$subgroup->id] = $index + 1;
                }
            }
        }

        $bookingsByMonitor = $bookings->groupBy('monitor_id');
        $subgroupsByMonitor = $subgroups->groupBy('monitor_id');
        $nwdByMonitor = $nwd->groupBy('monitor_id');

        // Attach booking user id to each booking user for planner consumers
        $bookings->each(function ($bookingUser) {
            if ($bookingUser->relationLoaded('booking') && $bookingUser->booking) {
                $bookingUser->user_id = $bookingUser->booking->user_id;
            }
        });
        $groupedData = collect([]);

        // OPTIMIZACION: Calcular full_day NWDs para todos los monitores en una sola query
        $monitorFullDayNwds = collect();
        if ($dateStart && $dateEnd && !empty($monitorIds)) {
            // Obtener todos los NWDs de full_day para los monitores en el rango de fechas
            $fullDayNwds = MonitorNwd::where('school_id', $schoolId)
                ->whereIn('monitor_id', $monitorIds)
                ->where('full_day', true)
                ->where('user_nwd_subtype_id', 1)
                ->where('start_date', '<=', $dateEnd)
                ->where('end_date', '>=', $dateStart)
                ->get();

            $fullDayNwdsByMonitor = $fullDayNwds->groupBy('monitor_id');
            foreach ($monitorIds as $mId) {
                $monitorFullDayEntries = $fullDayNwdsByMonitor->get($mId, collect());
                if ($monitorFullDayEntries->isEmpty()) {
                    $monitorFullDayNwds[$mId] = false;
                    continue;
                }
                $intervals = $monitorFullDayEntries
                    ->map(function ($item) {
                        return [
                            'start' => Carbon::parse($item->start_date),
                            'end' => Carbon::parse($item->end_date)
                        ];
                    })
                    ->sortBy('start')
                    ->values();

                $rangeStart = Carbon::parse($dateStart);
                $rangeEnd = Carbon::parse($dateEnd);
                $currentEnd = $intervals[0]['end'];
                if ($intervals[0]['start']->gt($rangeStart)) {
                    $monitorFullDayNwds[$mId] = false;
                    continue;
                }

                foreach ($intervals as $interval) {
                    if ($interval['start']->gt($currentEnd->copy()->addDay())) {
                        break;
                    }
                    if ($interval['end']->gt($currentEnd)) {
                        $currentEnd = $interval['end'];
                    }
                    if ($currentEnd->gte($rangeEnd)) {
                        break;
                    }
                }

                $monitorFullDayNwds[$mId] = $currentEnd->gte($rangeEnd);
            }
        }

        foreach ($monitors as $monitor) {
            $monitorBookings = $bookingsByMonitor->get($monitor->id, collect())
                ->groupBy(function ($booking) {
                    // Diferencia la agrupacin basada en el course_type
                    if ($booking->course->course_type == 2 || $booking->course->course_type == 3) {
                        // Agrupa por booking.course_id y booking.course_date_id para el tipo 2
                        return $booking->course_id . '-' . $booking->course_date_id;
                    }
                });

            $monitor->hasFullDayNwd = $monitorFullDayNwds->get($monitor->id, false);

            $subgroupsWithMonitor = $subgroupsByMonitor->get($monitor->id, collect());

            $subgroupsArray = [];

            $subgroupsWithMonitor->each(function ($subgroup) use (&$subgroupsArray, $subgroupTotals, $subgroupPositions, $subgroupKey) {
                $subgroupId = $subgroup->id;
                $courseDateId = $subgroup->course_date_id;
                $courseId = $subgroup->course_id;

                $totalSubgroups = $subgroupTotals[$subgroupKey($subgroup)] ?? 1;
                $subgroupPosition = $subgroupPositions[$subgroupId] ?? 1;

                $subgroup->subgroup_number = $subgroupPosition;
                $subgroup->total_subgroups = $totalSubgroups;

                // Define la misma nomenclatura que en los bookings
                $nomenclature = $courseId . '-' . $courseDateId . '-' . $subgroupId;

                // Agrega el subgrupo al array con la nomenclatura como ndice
                $subgroupsArray[$nomenclature] = $subgroup;
            });

            $allBookings = $monitorBookings->concat($subgroupsArray);


            $monitorNwd = $nwdByMonitor->get($monitor->id, collect());

            $groupedData[$monitor->id] = [
                'monitor' => $monitor,
                'bookings' => $allBookings,
                'nwds' => $monitorNwd,
                /*'subgroups' => $availableSubgroups,*/
            ];
        }
        $bookingsWithoutMonitor = $bookings->whereNull('monitor_id')->groupBy(function ($booking) {
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

        $subgroupsWithoutMonitor->each(function ($subgroup) use (&$subgroupsArray, $subgroupTotals, $subgroupPositions, $subgroupKey) {
            $subgroupId = $subgroup->id;
            $courseDateId = $subgroup->course_date_id;
            $courseId = $subgroup->course_id;

            $totalSubgroups = $subgroupTotals[$subgroupKey($subgroup)] ?? 1;
            $subgroupPosition = $subgroupPositions[$subgroupId] ?? 1;

            $subgroup->subgroup_number = $subgroupPosition;
            $subgroup->total_subgroups = $totalSubgroups;

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

        $intervalSubgroupIds = collect();
        if ($scope === 'interval' && $courseSubgroupId) {
            $selectedSubgroup = CourseSubgroup::find($courseSubgroupId);
            if ($selectedSubgroup) {
                $intervalCourseDateIds = collect();
                if ($courseId) {
                    $cdQuery = CourseDate::where('course_id', $courseId)->whereNull('deleted_at');
                    if ($startDate && $endDate) {
                        $cdQuery->whereBetween('date', [$startDate, $endDate]);
                    } elseif ($startDate) {
                        $cdQuery->whereDate('date', $startDate);
                    }
                    $intervalCourseDateIds = $cdQuery->pluck('id');
                }

                if ($selectedSubgroup->subgroup_dates_id && $intervalCourseDateIds->isNotEmpty()) {
                    $intervalSubgroupIds = CourseSubgroup::where('course_id', $selectedSubgroup->course_id)
                        ->where('subgroup_dates_id', $selectedSubgroup->subgroup_dates_id)
                        ->whereIn('course_date_id', $intervalCourseDateIds)
                        ->pluck('id');
                } elseif ($intervalCourseDateIds->isNotEmpty()) {
                    $intervalQuery = CourseSubgroup::query()->whereIn('course_date_id', $intervalCourseDateIds);
                    $this->applyPositionBasedFallback($intervalQuery, $selectedSubgroup);
                    $intervalSubgroupIds = $intervalQuery->pluck('id');
                }
            }
        }

        $resolvedSubgroupIds = collect();
        if (in_array($scope, ['single', 'from', 'range'], true) && $courseSubgroupId) {
            if ($scope === 'single') {
                $resolvedSubgroupIds = collect([(int)$courseSubgroupId]);
            } else {
                $selectedSubgroup = CourseSubgroup::find($courseSubgroupId);
                if ($selectedSubgroup && $selectedSubgroup->subgroup_dates_id) {
                    $dateQuery = CourseDate::where('course_id', $selectedSubgroup->course_id)
                        ->whereNull('deleted_at');
                    if ($scope === 'from' && $startDate) {
                        $dateQuery->whereDate('date', '>=', $startDate);
                    } elseif ($scope === 'range' && $startDate && $endDate) {
                        $dateQuery->whereBetween('date', [$startDate, $endDate]);
                    }
                    $dateIds = $dateQuery->pluck('id');
                    if ($dateIds->isNotEmpty()) {
                        $resolvedSubgroupIds = CourseSubgroup::where('course_id', $selectedSubgroup->course_id)
                            ->where('subgroup_dates_id', $selectedSubgroup->subgroup_dates_id)
                            ->whereIn('course_date_id', $dateIds)
                            ->pluck('id');
                    }
                }
            }
        }

        $explicitSubgroupIds = $resolvedSubgroupIds->isNotEmpty()
            ? $resolvedSubgroupIds
            : $providedSubgroupIds;

        // 2) Resolver BookingUsers objetivo (si no llegan explcitos)
        $targets = collect();

        if (!empty($bookingUserIds)) {
            $targets = BookingUser::with(['courseDate', 'courseSubgroup', 'course'])
                ->whereIn('id', $bookingUserIds)
                ->get();
        } else {
            $q = BookingUser::with(['courseDate', 'courseSubgroup'])
                ->whereHas('courseDate', fn($cq) => $cq->whereNull('deleted_at'));

            switch ($scope) {
                case 'single':
                    if ($explicitSubgroupIds->isNotEmpty()) {
                        $q->whereIn('course_subgroup_id', $explicitSubgroupIds);
                    } elseif ($courseDateId) {
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

            if ($scope === 'interval' && $intervalSubgroupIds->isNotEmpty()) {
                $q->whereIn('course_subgroup_id', $intervalSubgroupIds);
            } elseif ($explicitSubgroupIds->isNotEmpty() && $scope !== 'all' && $scope !== 'interval') {
                $q->whereIn('course_subgroup_id', $explicitSubgroupIds);
            }
            $targets = $q->get();
        }

        $targets->loadMissing(['course', 'courseSubgroup']);
        $isPrivateCourseTransfer = $targets->isNotEmpty() && $targets->every(function ($bu) {
            $type = optional($bu->course)->course_type;
            return in_array($type, [2, 3], true);
        });

        $targetSubgroups = collect();

        if (!$isPrivateCourseTransfer) {
            // 2.1) Determinar degree de contexto
            $degreeIdContext = $degreeIdInput
                ?? ($courseSubgroupId ? (CourseSubgroup::find($courseSubgroupId)->degree_id ?? null) : null)
                ?? optional($targets->first())->degree_id
                ?? optional(optional($targets->first())->courseSubgroup)->degree_id;

            if (!$degreeIdContext) {
                // si no logramos determinar degree, mejor no tocar masivamente
                // (puedes convertir esto en warning si lo prefieres)
                return $this->sendError('Degree not determinable for bulk transfer');
                // fallback: seguimos pero slo tocaremos BUs y subgrupos con degree conocido
            }

            // 3) Resolver Subgrupos objetivo (aunque vacos), con courseDate viva y degree filtrado
            $subgroupBase = CourseSubgroup::with('courseDate')
                ->whereHas('courseDate', fn($q) => $q->whereNull('deleted_at'));

            $applyDegreeFilter = function ($query) use ($degreeIdContext) {
                if ($degreeIdContext) $query->where('degree_id', $degreeIdContext);
                return $query;
            };

            if ($scope === 'interval' && $intervalSubgroupIds->isNotEmpty()) {
                $targetSubgroups = (clone $subgroupBase)
                    ->whereIn('id', $intervalSubgroupIds)
                    ->when($degreeIdContext, fn($q) => $q->where('degree_id', $degreeIdContext))
                    ->get();
            } elseif ($explicitSubgroupIds->isNotEmpty() && $scope !== 'all' && $scope !== 'interval') {
                $targetSubgroups = (clone $subgroupBase)
                    ->whereIn('id', $explicitSubgroupIds)
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
                        $selectedSubgroup = null;
                        if ($courseSubgroupId) {
                            $one = (clone $subgroupBase)->where('id', $courseSubgroupId);
                            $selectedSubgroup = $applyDegreeFilter($one)->first();
                            if ($selectedSubgroup) {
                                $targetSubgroups->push($selectedSubgroup);
                            }
                        }
                        if ($courseId && $startDate && $endDate && $selectedSubgroup) {
                            $intervalCdIds = CourseDate::where('course_id', $courseId)
                                ->whereNull('deleted_at')
                                ->whereBetween('date', [$startDate, $endDate])
                                ->pluck('id');
                            if ($intervalCdIds->isNotEmpty()) {
                                if ($selectedSubgroup->subgroup_dates_id) {
                                    $intervalSubgroups = (clone $subgroupBase)
                                        ->whereIn('course_date_id', $intervalCdIds)
                                        ->where('subgroup_dates_id', $selectedSubgroup->subgroup_dates_id);
                                    $targetSubgroups = $targetSubgroups->merge($applyDegreeFilter($intervalSubgroups)->get());
                                } else {
                                    $intervalQuery = (clone $subgroupBase)->whereIn('course_date_id', $intervalCdIds);
                                    $this->applyPositionBasedFallback($intervalQuery, $selectedSubgroup);
                                    $targetSubgroups = $targetSubgroups->merge($applyDegreeFilter($intervalQuery)->get());
                                }
                            }
                        }
                        break;

                    case 'all':
                        if ($courseId) {
                            // Si se proporciona un courseSubgroupId, buscar TODOS los subgrupos homónimos
                            // con el mismo subgroup_dates_id (en todas las fechas)
                            if ($courseSubgroupId) {
                                $selectedSubgroup = CourseSubgroup::find($courseSubgroupId);
                                if ($selectedSubgroup && $selectedSubgroup->subgroup_dates_id) {
                                    $targetSubgroups = (clone $subgroupBase)
                                        ->where('course_id', $courseId)
                                        ->where('subgroup_dates_id', $selectedSubgroup->subgroup_dates_id)
                                        ->when($degreeIdContext, fn($q) => $q->where('degree_id', $degreeIdContext))
                                        ->get();
                                } else {
                                    // Fallback si no tiene subgroup_dates_id: usar todas las fechas del course
                                    $cdIds = $validCourseDateIds->isNotEmpty()
                                        ? $validCourseDateIds
                                        : CourseDate::where('course_id', $courseId)->whereNull('deleted_at')->pluck('id');
                                    $q = (clone $subgroupBase)->whereIn('course_date_id', $cdIds);
                                    $targetSubgroups = $applyDegreeFilter($q)->get();
                                }
                            } else {
                                // Sin courseSubgroupId especificado: obtener todos los subgrupos en todas las fechas
                                $cdIds = $validCourseDateIds->isNotEmpty()
                                    ? $validCourseDateIds
                                    : CourseDate::where('course_id', $courseId)->whereNull('deleted_at')->pluck('id');

                                $q = (clone $subgroupBase)->whereIn('course_date_id', $cdIds);
                                $targetSubgroups = $applyDegreeFilter($q)->get();
                            }
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

        }

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

        $excludeSubgroupIds = $bookingRelatedSubgroups;
        if (!$isPrivateCourseTransfer) {
            $excludeSubgroupIds = $excludeSubgroupIds->merge(
                $targetSubgroups
                    ->pluck('id')
                    ->filter(fn($id) => !is_null($id))
                    ->map(fn($id) => (int)$id)
            );
        }
        $excludeSubgroupIds = $excludeSubgroupIds
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
                $now = now();
                if ($targets->isNotEmpty()) {
                    BookingUser::whereIn('id', $targets->pluck('id'))
                        ->update(['monitor_id' => null, 'accepted' => true, 'updated_at' => $now]);
                }
                if ($targetSubgroups->isNotEmpty()) {
                    CourseSubgroup::whereIn('id', $targetSubgroups->pluck('id'))
                        ->update(['monitor_id' => null, 'updated_at' => $now]);
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
            $now = now();
            if ($targets->isNotEmpty()) {
                BookingUser::whereIn('id', $targets->pluck('id'))
                    ->update(['monitor_id' => $monitorId, 'accepted' => true, 'updated_at' => $now]);
            }

            $subgroupIdsFromTargets = collect();
            if (in_array($scope, ['interval', 'all', 'from', 'range'])) {
                $subgroupIdsFromTargets = $targets->pluck('course_subgroup_id')
                    ->filter()
                    ->map(fn($id) => (int) $id)
                    ->unique();
            }

            $subgroupIds = $subgroupIdsFromTargets
                ->merge($targetSubgroups->pluck('id'))
                ->filter()
                ->unique();

            if ($subgroupIds->isNotEmpty()) {
                CourseSubgroup::whereIn('id', $subgroupIds)
                    ->update(['monitor_id' => $monitorId, 'updated_at' => $now]);
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

        $this->repairDispatcher->dispatchForSchool($school->id ?? null);
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
        } elseif ($scope !== 'all' && $subgroupId) {
            // Para scopes no "all", priorizar subgroup_id y evitar colisiones por subgroup_ids
            $query->where('id', $subgroupId);
        } elseif (!empty($subgroupIds)) {
            // Fallback legacy si no hay subgroup_id disponible
            $query->whereIn('id', $subgroupIds);
        } elseif ($subgroupId) {
            // Para scope='all' sin subgroupIds
            $query->where('id', $subgroupId);
        }

        // Para scope='all', NO filtrar por rango en la query (queremos TODOS los subgroups)
        // Para otros scopes, filtrar por rango en la query (ms eficiente)
        $_dateFiltered = false;
        if ($startDate && $scope === 'single') {
            $end = $endDate ?? $startDate;
            // Para scope='single', intentar filtrar por fecha con junction table
            try {
                $query->whereHas('courseSubgroupDates.courseDate',
                    fn($q) => $q->whereBetween('date', [$startDate, $end])->whereNull('deleted_at')
                );
                $_dateFiltered = true;
            } catch (\Exception $e) {
                // Si falla, continuar sin este filtro
            }
        }
        // Para scope='from' y 'range': NO filtrar en la query, solo en memoria

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
            $subgroups = $rawSubgroups->map(function (CourseSubgroup $subgroup) use ($scope, $startDate, $endDate, $_dateFiltered, $courseId) {
                $course = $subgroup->courseGroup?->course;

                // MEJORADO: Obtener mapa de course_date_id => monitor para cada fecha
                // Para scope='from' y 'range', incluir monitors de todos los subgrupos homónimos
                $dateMonitorMap = [];

                if (in_array($scope, ['from', 'range', 'interval']) && $subgroup->subgroup_dates_id) {
                    // Obtener monitors de todos los subgrupos homónimos
                    $homonymousSubgroups = CourseSubgroup::where('course_id', $courseId)
                        ->where('subgroup_dates_id', $subgroup->subgroup_dates_id)
                        ->get();

                    foreach ($homonymousSubgroups as $homSg) {
                        $subgroupDates = $homSg->courseSubgroupDates()
                            ->with('courseDate')
                            ->get();

                        foreach ($subgroupDates as $sd) {
                            $dateMonitorMap[$sd->course_date_id] = $homSg->monitor;
                        }
                    }
                } else {
                    // Para scope='single': solo el monitor del subgroup actual
                    $subgroupDates = $subgroup->courseSubgroupDates()
                        ->with('courseDate')
                        ->get();

                    foreach ($subgroupDates as $sd) {
                        $dateMonitorMap[$sd->course_date_id] = $subgroup->monitor;
                    }
                }

                // Para scope='from' y 'range', obtener fechas de TODOS los subgrupos homónimos
                // Para scope='single', usar solo las fechas del subgroup actual
                if (in_array($scope, ['from', 'range', 'interval']) && $subgroup->subgroup_dates_id) {
                    // Buscar todos los subgrupos homónimos con el mismo subgroup_dates_id
                    $homonymousSubgroups = CourseSubgroup::where('course_id', $courseId)
                        ->where('subgroup_dates_id', $subgroup->subgroup_dates_id)
                        ->get();

                    // Consolidar fechas de todos los subgrupos homónimos
                    $dateIds = [];
                    $homonymousDates = collect([]);
                    foreach ($homonymousSubgroups as $homSg) {
                        $dates = $homSg->allCourseDates()
                            ->whereNull('course_dates.deleted_at')
                            ->select('course_dates.id', 'course_dates.date', 'course_dates.hour_start', 'course_dates.hour_end')
                            ->get();

                        foreach ($dates as $date) {
                            if (!in_array($date->id, $dateIds)) {
                                $dateIds[] = $date->id;
                                $homonymousDates->push($date);
                            }
                        }
                    }
                    $homonymousDates = $homonymousDates->sortBy('date')->values();
                } else {
                    // Para scope='single': usar solo las fechas del subgroup actual
                    $homonymousDates = $subgroup->allCourseDates()
                        ->whereNull('course_dates.deleted_at')
                        ->orderBy('course_dates.date', 'asc')
                        ->select('course_dates.id', 'course_dates.date', 'course_dates.hour_start', 'course_dates.hour_end')
                        ->get();
                }

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
                    } elseif ($startDate && $scope === 'interval') {
                        $end = $endDate ?? $startDate;
                        $homonymousDates = $homonymousDates->filter(function ($date) use ($startDate, $end) {
                            $dateStr = is_string($date->date) ? $date->date : $date->date->format('Y-m-d');
                            return $dateStr >= $startDate && $dateStr <= $end;
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
