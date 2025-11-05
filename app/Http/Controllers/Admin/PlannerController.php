<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\BookingUser;
use App\Models\CourseSubgroup;
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

        // OPTIMIZACION: Join en lugar de whereHas para mejor performance
        $subgroupsQuery = CourseSubgroup::with(['courseGroup.course', 'bookingUsers.client.sports', 'bookingUsers.booking.user',
            'bookingUsers.client.evaluations.degree', 'bookingUsers.client.evaluations.evaluationFulfilledGoals'])
            ->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
            ->join('courses', 'course_groups.course_id', '=', 'courses.id')
            ->join('course_dates', 'course_subgroups.course_date_id', '=', 'course_dates.id')
            ->where('courses.school_id', $schoolId)
            ->where('courses.active', 1)
            ->where('course_dates.active', 1)
            ->select('course_subgroups.*'); // Importante: seleccionar solo las columnas de course_subgroups

        // Aplicar filtros de fecha
        if ($dateStart && $dateEnd) {
            $subgroupsQuery->whereBetween('course_dates.date', [$dateStart, $dateEnd]);
        } else {
            $today = Carbon::today();
            $subgroupsQuery->whereDate('course_dates.date', $today);
        }

        // Filtrar booking_users con status = 1
        $subgroupsQuery->with('bookingUsers', function ($query) {
            $query->where('status', 1)->whereHas('booking');
        });

        // OPTIMIZACION: Join en lugar de whereHas para bookings
        $bookingQuery = BookingUser::with(['booking.user', 'course.courseDates', 'client.sports',
            'client.evaluations.degree', 'client.evaluations.evaluationFulfilledGoals'])
            ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
            ->where('bookings.status', '!=', 2)
            ->where('booking_users.school_id', $schoolId)
            ->whereNull('booking_users.course_subgroup_id')
            ->where('booking_users.status', 1)
            ->select('booking_users.*')
            ->orderBy('booking_users.hour_start');

        // Consulta para los MonitorNwd
        $nwdQuery = MonitorNwd::where('school_id', $schoolId)
            ->orderBy('start_time');

        // Aplicar filtros de fecha
        if ($dateStart && $dateEnd) {
            $bookingQuery->whereBetween('booking_users.date', [$dateStart, $dateEnd]);
            $nwdQuery->whereBetween('start_date', [$dateStart, $dateEnd])
                ->whereBetween('end_date', [$dateStart, $dateEnd]);
        } else {
            $today = Carbon::today();
            $bookingQuery->whereDate('booking_users.date', $today);
            $nwdQuery->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today);
        }

        // Aplicar filtro de monitor si existe
        if ($monitorId) {
            $bookingQuery->where('booking_users.monitor_id', $monitorId);
            $nwdQuery->where('monitor_id', $monitorId);
            $subgroupsQuery->where('course_subgroups.monitor_id', $monitorId);

            // Obtén solo el monitor específico
            $monitors = MonitorsSchool::with(['monitor.sports' => function ($query) use ($schoolId) {
                $query->where('monitor_sports_degrees.school_id', $schoolId);
            }])
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
            // Obtener todos los monitores
            $monitorSchools = MonitorsSchool::with(['monitor.sports' => function ($query) use ($schoolId) {
                $query->where('monitor_sports_degrees.school_id', $schoolId);
            }])
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

        // Obtener los resultados
        $nwd = $nwdQuery->get();
        $subgroups = $subgroupsQuery->get();
        $bookings = $bookingQuery->get();

        // Attach booking user id to each booking user for planner consumers
        $bookings->each(function ($bookingUser) {
            if ($bookingUser->relationLoaded('booking') && $bookingUser->booking) {
                $bookingUser->user_id = $bookingUser->booking->user_id;
            }
        });

        // OPTIMIZACION: Filtrar subgroups por school_id y fechas antes de contar
        $subgroupsPerGroupQuery = CourseSubgroup::select('course_group_id', DB::raw('COUNT(*) as total'))
            ->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
            ->join('courses', 'course_groups.course_id', '=', 'courses.id')
            ->where('courses.school_id', $schoolId);

        if ($dateStart && $dateEnd) {
            $subgroupsPerGroupQuery->join('course_dates', 'course_subgroups.course_date_id', '=', 'course_dates.id')
                ->whereBetween('course_dates.date', [$dateStart, $dateEnd]);
        }

        $subgroupsPerGroup = $subgroupsPerGroupQuery->groupBy('course_group_id')
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
            // FIX: Mejorar la lógica de agrupación para incluir TODOS los tipos de curso
            $monitorBookings = $bookings->where('monitor_id', $monitor->id)
                ->groupBy(function ($booking) {
                    // Agrupa cursos privados (type 2) y actividades (type 3) por course + date
                    if ($booking->course->course_type == 2 || $booking->course->course_type == 3) {
                        return $booking->course_id . '-' . $booking->course_date_id;
                    }
                    // Para cursos colectivos (type 1), agrupa por booking individual
                    // Esto evita que se agrupen todos juntos bajo la clave null
                    return $booking->course_id . '-' . $booking->course_date_id . '-' . $booking->id;
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

                $subgroup->loadMissing(['course.courseDates', 'courseGroup']);

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
            ];
        }

        // FIX: Mantener consistencia en la agrupación de bookings sin monitor
        $bookingsWithoutMonitor = $bookings->whereNull('monitor_id')->groupBy(function ($booking) {
            // Agrupa cursos privados (type 2) y actividades (type 3) por course + date
            if ($booking->course->course_type == 2 || $booking->course->course_type == 3) {
                return $booking->course_id . '-' . $booking->course_date_id;
            }
            // Para cursos colectivos (type 1), agrupa por booking individual
            return $booking->course_id . '-' . $booking->course_date_id . '-' . $booking->id;
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

            $subgroup->loadMissing(['course.courseDates', 'courseGroup']);

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
        $bookingUserIds  = $request->input('booking_users');
        $courseSubgroupId  = $request->input('subgroup_id');
        if ($courseSubgroupId) {
            $courseSubgroup = CourseSubgroup::find($courseSubgroupId);

            if ($courseSubgroup) {
                // Comprobación de superposición usando la información de courseDate
                $date = $courseSubgroup->courseDate->date;
                $hourStart = $courseSubgroup->courseDate->hour_start;
                $hourEnd = $courseSubgroup->courseDate->hour_end;
                if($monitorId !== null) {
                    if (Monitor::isMonitorBusy($monitorId, $date, $hourStart, $hourEnd)) {
                        return $this->sendError('Overlap detected for subgroup.
                        Monitor cannot be transferred.');
                    }
                }

                // Actualizar el monitor_id del subgrupo
                $courseSubgroup->update(['monitor_id' => $monitorId]);
                $bookingUsers = BookingUser::where('course_subgroup_id', $courseSubgroup->id)->get();

                foreach ($bookingUsers as $bookingUser) {
                    // Actualizar el monitor_id de cada BookingUser
                    $bookingUser->monitor_id = $courseSubgroup->monitor_id;
                    $bookingUser->save();
                }

            } else {
                return $this->sendError('Subgroup cannot be found.');
            }

            // Actualizar el monitor_id del subgrupo

        }
        $overlapDetected = false;

        if ($monitorId !== null) {
            // Check if the monitor exists (only if monitor_id is provided)
            $monitor = Monitor::find($monitorId);

            if (!$monitor) {
                return $this->sendError('Monitor not found');
            }
        }

        // If monitor_id is null, set all monitors to null
        if ($monitorId === null) {
            foreach ($bookingUserIds as $bookingUserId) {
                $bookingUserModel = BookingUser::find($bookingUserId);

                if ($bookingUserModel) {
                    $bookingUserModel->update(['monitor_id' => null, 'accepted' => true]);
                }

                $courseSubgroupId = $bookingUserModel['course_subgroup_id'];

                // If the bookingUser has a course_subgroup_id, update the monitor_id of the subgroup
                if ($courseSubgroupId) {
                    $courseSubgroup = CourseSubgroup::find($courseSubgroupId);

                    if ($courseSubgroup) {
                        $courseSubgroup->update(['monitor_id' => null]);
                    }
                }
            }

            return $this->sendResponse(null, 'Monitor set to null for all bookingUsers successfully');
        }

        // Iterar sobre los bookingUsers
        foreach ($bookingUserIds as $bookingUserId) {
            // Obtener la información del bookingUser
            $bookingUser = BookingUser::find($bookingUserId);

            if (!$bookingUser) {
                return $this->sendError("BookingUser with ID $bookingUserId not found");
            }

            // If monitor_id is not null, check for monitor availability using isMonitorBusy
            if (Monitor::isMonitorBusy($monitorId, $bookingUser['date'], $bookingUser['hour_start'], $bookingUser['hour_end'])) {
                $overlapDetected = true;
                break; // Se detectó superposición, sal del bucle
            }
        }

        if ($overlapDetected) {
            return $this->sendError('Overlap detected. Monitor cannot be transferred.');
        }

        // Si no hay superposición y monitor_id is not null, update the monitor_id of all bookingUsers and subgroups if necessary
        foreach ($bookingUserIds as $bookingUserId) {
            // Actualizar el monitor_id del bookingUser
            $bookingUserModel = BookingUser::find($bookingUserId);

            $courseSubgroupId = $bookingUserModel['course_subgroup_id'];

            if ($bookingUserModel) {
                $bookingUserModel->update(['monitor_id' => $monitorId, 'accepted' => true]);
            }

            // Si el bookingUser tiene un course_subgroup_id, actualizar el monitor_id del subgrupo
            if ($courseSubgroupId) {
                $courseSubgroup = CourseSubgroup::find($courseSubgroupId);

                if ($courseSubgroup) {
                    $courseSubgroup->update(['monitor_id' => $monitorId]);
                }
            }
        }

        return $this->sendResponse($monitor, 'Monitor updated for bookingUsers successfully');
    }



}
