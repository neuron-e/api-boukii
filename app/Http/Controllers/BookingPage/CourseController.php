<?php

namespace App\Http\Controllers\BookingPage;

use App\Http\Controllers\AppBaseController;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseSubgroup;
use App\Models\Degree;
use App\Models\Monitor;
use App\Models\MonitorNwd;
use App\Models\MonitorSportsDegree;
use App\Models\MonitorsSchool;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Response;
use Validator;

;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */
class CourseController extends SlugAuthController
{

    /**
     * @OA\Get(
     *      path="/slug/courses",
     *      summary="getCourseList",
     *      tags={"BookingPage"},
     *      description="Get all Courses available by slug",
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
     *                  @OA\Items(ref="#/components/schemas/Course")
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

        // Validación de las fechas
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));

        if (!$startDate || !$endDate || $startDate->gt($endDate)) {
            return $this->sendError('Invalid date range', 422);
        }

        $startDate = $startDate->format('Y-m-d');
        $endDate = $endDate->format('Y-m-d');

        $type = $request->has('course_type') ? $request->input('course_type') : null;
        $sportId = $request->input('sport_id') ?? null;
        $minAge = $request->input('min_age') ?? null;
        $maxAge = $request->input('max_age') ?? null;
        $clientId = $request->input('client_id');
        $degreeOrder = $request->input('degree_order');
        $highlighted = $request->input('highlighted') ?? null;
        $degreeOrderArray = [];
        if($degreeOrder) {
            $degreeOrderArray = explode(',', $degreeOrder);
        }

        $getLowerDegrees = 1;
        // return $this->sendResponse($this->school->id, 'Courses retrieved successfully');
        $today = now(); // Obtener la fecha actual

        try {
            // Create cache key based on request parameters
            $cacheKey = sprintf(
                'courses_%s_%s_%s_%s_%s_%s_%s_%s_%s_%s_%s',
                $this->school->id,
                $startDate,
                $endDate,
                $type ?? 'null',
                $sportId ?? 'null',
                $clientId ?? 'null',
                $highlighted ?? 'null',
                $minAge ?? 'null',
                $maxAge ?? 'null',
                implode(',', $degreeOrderArray),
                $today->format('Y-m-d')
            );

            // Cache for 5 minutes (300 seconds) for course listings
            $courses = Cache::remember($cacheKey, 300, function() use ($type, $startDate, $endDate, $sportId, $clientId, $getLowerDegrees, $degreeOrderArray, $minAge, $maxAge, $highlighted, $today) {
                $todayDate = now()->format('Y-m-d');
                $requestedStart = $startDate;
                $requestedEnd = $endDate;

                $hasCapacityOnCourseDate = function(int $courseDateId, int $courseType, int $maxParticipants): bool {
                    // Colectivos (tipo 1): disponibilidad por subgrupos
                    if ($courseType === 1) {
                        return \App\Models\CourseSubgroup::where('course_date_id', $courseDateId)
                            ->where(function($q) {
                                $q->whereNull('max_participants')
                                  ->orWhereRaw('course_subgroups.max_participants > (
                                      SELECT COUNT(*) FROM booking_users
                                      INNER JOIN bookings ON bookings.id = booking_users.booking_id
                                      WHERE booking_users.course_subgroup_id = course_subgroups.id
                                        AND booking_users.status = 1
                                        AND bookings.status != 2
                                        AND booking_users.deleted_at IS NULL
                                  )');
                            })
                            ->exists();
                    }
                    // Privados (tipo 2): simplificación por fecha (nº reservas ese día < max)
                    $count = \App\Models\BookingUser::where('course_date_id', $courseDateId)
                        ->where('status', 1)
                        ->whereHas('booking', function($q){ $q->where('status', '!=', 2); })
                        ->count();
                    return $count < $maxParticipants;
                };

                return Course::withAvailableDates($type, $startDate, $endDate, $sportId, $clientId, null, $getLowerDegrees,
                        $degreeOrderArray, $minAge, $maxAge)
                        ->select(['id', 'name', 'description', 'price', 'max_participants', 'course_type', 'sport_id', 'school_id', 'translations',
                            'highlighted', 'is_flexible', 'duration', 'image', 'price_range', 'settings', 'intervals_config_mode'])
                        ->with([
                            'sport:id,name,icon_prive,icon_collective,icon_activity,icon_selected,icon_unselected',
                            'courseDates' => function($query) use ($startDate, $endDate) {
                                $query->select(['id', 'course_id', 'date', 'hour_start', 'hour_end', 'interval_id', 'course_interval_id'])
                                      ->where('date', '>=', $startDate)
                                      ->where('date', '<=', $endDate)
                                      ->orderBy('date');
                            },
                            'courseIntervals' => function ($query) {
                                $query->ordered()->with([
                                    'discounts' => function ($inner) {
                                        $inner->active()->orderBy('min_days');
                                    },
                                ]);
                            }
                        ])
                        ->where('school_id', $this->school->id)
                        ->where('online', 1)
                        ->where('active', 1)
                        ->when(isset($highlighted), function ($query) use ($highlighted) {
                            return $query->where('highlighted', $highlighted);
                        })
                        // FIXED BUG #1: Removed date_start_res/date_end_res filtering from visibility
                        // Courses should be VISIBLE even if booking period hasnt started yet
                        // The booking period validation should happen during actual booking, not during listing
                        ->orderBy('highlighted', 'desc')
                        ->orderBy('name')
                        ->get()
                        ->filter(function($course) use ($requestedStart, $requestedEnd, $hasCapacityOnCourseDate) {
                            // FIXED BUG #2: Improved booking period logic

                            // 1. Check if the requested window overlaps the course's bookable period
                            $isWithinBookingPeriod = true;
                            if ($course->date_start_res && $course->date_end_res) {
                                $isWithinBookingPeriod = !(
                                    ($requestedEnd < $course->date_start_res) ||
                                    ($requestedStart > $course->date_end_res)
                                );
                            }

                            if (!$isWithinBookingPeriod) {
                                return false; // Course not bookable in requested window
                            }

                            // 2. Parse settings
                            $settings = $course->settings ?? [];
                            $mustStartFromFirst = is_array($settings) ? ($settings['mustStartFromFirst'] ?? false) : (json_decode($settings, true)['mustStartFromFirst'] ?? false);

                            // 3. mustStartFromFirst only applies to NON-FLEXIBLE COLLECTIVE courses
                            $isNonFlexibleCollective = ((int)$course->course_type === 1 && !$course->is_flexible);

                            if (!$mustStartFromFirst || !$isNonFlexibleCollective) {
                                return true; // No restriction, course is bookable
                            }

                            // 4. For courses with mustStartFromFirst: verify first date has capacity
                            $hasIntervals = is_array($settings) ? ($settings['multipleIntervals'] ?? false) : (json_decode($settings, true)['multipleIntervals'] ?? false);
                            $intervals = is_array($settings) ? ($settings['intervals'] ?? []) : (json_decode($settings, true)['intervals'] ?? []);

                            if ($hasIntervals && is_array($intervals) && count($intervals) > 0) {
                                // With intervals: at least one interval must have capacity on its first date
                                foreach ($intervals as $interval) {
                                    $intervalId = is_array($interval) ? ($interval['id'] ?? null) : null;
                                    if (!$intervalId) continue;

                                    $firstDate = $course->courseDates()
                                        ->where('active', 1)
                                        ->where('interval_id', $intervalId)
                                        ->orderBy('date', 'asc')
                                        ->first();

                                    // Check if first date has capacity (date can be in the future)
                                    if ($firstDate && $hasCapacityOnCourseDate($firstDate->id, (int)$course->course_type, (int)$course->max_participants)) {
                                        return true;
                                    }
                                }
                                return false;
                            }

                            // Without intervals: check if first date has capacity
                            $firstDate = $course->courseDates()
                                ->where('active', 1)
                                ->orderBy('date', 'asc')
                                ->first();

                            if (!$firstDate) return false;

                            return $hasCapacityOnCourseDate($firstDate->id, (int)$course->course_type, (int)$course->max_participants);
                        })
                        ->values();
            });

            return $this->sendResponse($courses, 'Courses retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    public function getCourseAvailability($course)
    {
        if (!$course) {
            return null; // o manejar como prefieras
        }

        $totalBookings = 0;
        $totalAvailablePlaces = 0;

        if ($course->course_type == 1) {
            // Cursos de tipo 1
            foreach ($course->courseDates as $courseDate) {
                foreach ($courseDate->courseSubgroups as $subgroup) {
                    $bookings = $subgroup->bookingUsers()->count();
                    $totalBookings += $bookings;
                    $totalAvailablePlaces += max(0, $subgroup->max_participants - $bookings);
                }
            }
        } else {
            // Cursos de tipo 2
            foreach ($course->courseDates as $courseDate) {
                $bookings = $courseDate->bookingUsers()->count();
                $totalBookings += $bookings;
            }
            $totalAvailablePlaces = max(0, $course->max_participants - $totalBookings);
        }

        return [
            'total_reservations' => $totalBookings,
            'total_available_places' => $totalAvailablePlaces
        ];
    }


    /**
     * @OA\Get(
     *      path="/slug/courses/{id}",
     *      summary="getCourseWithBookings",
     *      tags={"BookingPage"},
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
        $today = now(); // Obtener la fecha actual
        // Comprueba si el cliente principal tiene booking_users asociados con el ID del monitor
        $course = Course::with([
            'bookingUsers.client.sports',
            'courseExtras',
            'courseDates.courseGroups' => function ($query) {
                $query->with(['courseSubgroups' => function ($subQuery) {
                    $subQuery->withCount('bookingUsers')->with('degree');
                }]);
            },
            'courseIntervals' => function ($query) {
                $query->ordered();
            }
        ])->where('school_id', $this->school->id)
            ->where('online', 1)
            ->where('active', 1)
            ->where(function($query) use ($today) {
                $query->where(function($subquery) use ($today) {
                    $subquery->whereNull('date_start_res')
                        ->whereNull('date_end_res');
                })
                    ->orWhere(function($subquery) use ($today) {
                        $subquery->whereDate('date_start_res', '<=', $today)
                            ->whereDate('date_end_res', '>=', $today);
                    })
                    ->orWhere(function($subquery) use ($today) {
                        $subquery->whereDate('date_start_res', '=', $today)
                            ->whereNotNull('date_end_res');
                    })
                    ->orWhere(function($subquery) use ($today) {
                        $subquery->whereNotNull('date_start_res')
                            ->whereDate('date_end_res', '=', $today);
                    });
            })
        ->find($id);

        if (empty($course)) {
            return $this->sendError('Course does not exist in this school');
        } else {
            $availableDegreeIds = collect();
            $unAvailableDegreeIds = collect();
            foreach ($course->courseDates as $courseDate) {
                foreach ($courseDate->courseGroups as $group) {
                    $group->courseSubgroups = $group->courseSubgroups->filter(function ($subgroup) use ($availableDegreeIds, $unAvailableDegreeIds, $group) {
                        $hasAvailability = $subgroup->booking_users_count < $subgroup->max_participants;

                        // Crear la estructura de datos del degree
                        $availableDegree = [
                            'degree_id' => $group->degree_id,
                            'recommended_age' => $group->recommended_age,
                            'age_max' => $group->age_max,
                            'age_min' => $group->age_min
                        ];

                        // Registrar disponibilidad o no disponibilidad
                        if ($hasAvailability) {
                            if (!$availableDegreeIds->contains($availableDegree)) {
                                $availableDegreeIds->push($availableDegree);
                            }
                        } else {
                            if (!$unAvailableDegreeIds->contains($availableDegree)) {
                                $unAvailableDegreeIds->push($availableDegree);
                            }
                        }

                        return $hasAvailability;
                    });

                    // Verificar si todos los subgrupos han sido rechazados
                    if ($group->courseSubgroups->isEmpty()) {
                        $courseDate->courseGroups = $courseDate->courseGroups->reject(function ($g) use ($group) {
                            return $g->id === $group->id;
                        });
                    }
                }
            }


            $uniqueDegrees = $availableDegreeIds->unique(function ($item) {
                return $item['degree_id'] . '-' . $item['recommended_age'];
            });

            $availableDegrees = $uniqueDegrees->map(function ($item) {
                $degree = Degree::find($item['degree_id']);
                if ($degree) {
                    $degree->load('degreesSchoolSportGoals');
                    $degree->recommended_age = $item['recommended_age'];
                    $degree->age_max = $item['age_max'];
                    $degree->age_min = $item['age_min'];
                }
                return $degree;
            })->filter();

            $course->availableDegrees = $availableDegrees;
        }

        return $this->sendResponse($course,
            'Course retrieved successfully');
    }

    public function getDurationsAvailableByCourseDateAndStart($id, Request $request): JsonResponse
    {
        $courseDate = CourseDate::with('course')->find($id);

        if (!$courseDate) {
            return $this->sendError('Invalid course date ID.');
        }

        $course = $courseDate->course;
        $startTime = $request->hour_start;
        $endTime = $courseDate->hour_end; // Hora máxima del curso

        if (!$startTime || !strtotime($startTime)) {
            return $this->sendError('Invalid start time.');
        }

        // Verificar si es un día festivo
        if ($this->isHoliday($courseDate->school_id, $courseDate->date)) {
            return $this->sendResponse([], 'No availability: holiday.');
        }

        // Obtener monitores activos
        $monitors = $this->getActiveMonitorsForSchool($this->school->id, $courseDate->date);
        if ($monitors->isEmpty()) {
            return $this->sendResponse([], 'No monitors available.');
        }

        foreach ($request->bookingUsers as $bookingUser) {
            if($bookingUser['course']['course_type'] == 2) {
                $clientIds[] = $bookingUser['client']['id'];
            }

            $request['clientIds'] = $clientIds;


/*            if (BookingUser::hasOverlappingBookings($bookingUser, [])) {
                return $this->sendError('Client has booking on that date');
            }*/
        }

        // Procesar duraciones
        $durationsWithMonitors = $this->processDurations($course, $courseDate, $startTime, $endTime, $request, $monitors);

        if (empty($durationsWithMonitors)) {
            return $this->sendResponse([], 'No availability.');
        }

        // Eliminar duplicados basados en la duración
        $uniqueDurationsWithMonitors = $this->removeDuplicateDurations($durationsWithMonitors);

        return $this->sendResponse($uniqueDurationsWithMonitors, 'Available durations and monitors fetched successfully.');
    }

    private function removeDuplicateDurations(array $durationsWithMonitors): array
    {
        $unique = [];
        foreach ($durationsWithMonitors as $item) {
            $unique[$item['duration']] = $item; // Utiliza la duración como clave
        }
        return array_values($unique); // Devuelve los valores únicos
    }

    private function processDurations($course, $courseDate, $startTime, $endTime, Request $request, $monitors): array
    {
        $durationsWithMonitors = [];

        if (!$course->is_flexible) {
            // Procesar duración fija
            $durationsWithMonitors = array_merge($durationsWithMonitors, $this->processFixedDuration($course, $courseDate, $startTime, $endTime, $request, $monitors));
        } else {
            // Procesar duración flexible
            $durationsWithMonitors = array_merge($durationsWithMonitors, $this->processFlexibleDurations($course, $courseDate, $startTime, $endTime, $request, $monitors));
        }

        return $durationsWithMonitors;
    }

    private function processFixedDuration($course, $courseDate, $startTime, $endTime, Request $request, $monitors): array
    {
        $durationInSeconds = $this->convertDurationToSeconds($course->duration);
        $endTimeForFixed = $this->addSecondsToTime($startTime, $durationInSeconds);

        if (strtotime($endTimeForFixed) <= strtotime($endTime)) {
            $monitorAvailabilityRequest = $this->buildMonitorAvailabilityRequest($courseDate, $startTime, $endTimeForFixed, $request);
            $availableMonitors = $this->getAvailableMonitorsForTimeRange($this->getMonitorsAvailable($monitorAvailabilityRequest), $courseDate->date, $startTime, $endTimeForFixed);

            if (!empty($availableMonitors)) {
                return [[
                    'duration' => $this->convertSecondsToHourFormat($durationInSeconds),
                    'monitors' => $availableMonitors,
                ]];
            }
        }

        return [];
    }

    private function processFlexibleDurations($course, $courseDate, $startTime, $endTime, Request $request, $monitors): array
    {
        $durationsWithMonitors = [];

        foreach ($course->price_range as $price) {
            foreach ($price as $participants => $priceValue) {
                if (is_numeric($priceValue)) {
                    $intervalInSeconds = $this->convertDurationRangeToSeconds($price['intervalo']);
                    $endTimeForFlexible = $this->addSecondsToTime($startTime, $intervalInSeconds);

                    if (strtotime($endTimeForFlexible) <= strtotime($endTime)) {
                        $monitorAvailabilityRequest = $this->buildMonitorAvailabilityRequest($courseDate, $startTime, $endTimeForFlexible, $request);
                        $availableMonitors = $this->getAvailableMonitorsForTimeRange($this->getMonitorsAvailable($monitorAvailabilityRequest), $courseDate->date, $startTime, $endTimeForFlexible);

                        if (!empty($availableMonitors)) {
                            $durationsWithMonitors[] = [
                                'duration' => $this->convertSecondsToHourFormat($intervalInSeconds),
                                'monitors' => $availableMonitors,
                            ];
                        }
                    }
                }
            }
        }

        return $durationsWithMonitors;
    }

    private function buildMonitorAvailabilityRequest($courseDate, $startTime, $endTime, Request $request): Request
    {
        return new Request([
            'date' => $courseDate->date,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'clientIds' => $request->clientIds ?? [],
            'sportId' => $request->bookingUsers[0]['course']['sport_id'] ?? null,
            'minimumDegreeId' => $request->bookingUsers[0]['minimumDegreeId'] ?? null,
        ]);
    }


    public function getMonitorsAvailable(Request $request): array
    {
        $school = $this->school;

        $isAnyAdultClient = false;
        $clientLanguages = [];

        if ($request->has('clientIds') && is_array($request->clientIds)) {
            foreach ($request->clientIds as $clientId) {
                $client = Client::find($clientId);
                if ($client) {
                    $clientAge = Carbon::parse($client->birth_date)->age;
                    if ($clientAge >= 18) {
                        $isAnyAdultClient = true;
                    }

                    // Agregar idiomas del cliente al array de idiomas
                    for ($i = 1; $i <= 6; $i++) {
                        $languageField = 'language' . $i . '_id';
                        if (!empty($client->$languageField)) {
                            $clientLanguages[] = $client->$languageField;
                        }
                    }
                }
            }
        }

        $clientLanguages = array_unique($clientLanguages);
        // Paso 1: Obtener todos los monitores que tengan el deporte y grado requerido.
        $eligibleMonitors =
            MonitorSportsDegree::whereHas('monitorSportAuthorizedDegrees', function ($query) use ($school, $request) {
                $query->where('school_id', $school->id);

                // Solo aplicar la condición de degree_order si minimumDegreeId no es null
                if (!is_null($request->minimumDegreeId)) {
                    $query->whereHas('degree', function ($q) use ($request) {
                        $q->where('degree_order', '>=', $request->minimumDegreeId);
                    });
                }
            })
                ->where('sport_id', $request->sportId)
                ->when($isAnyAdultClient, function ($query) {
                    return $query->where('allow_adults', true);
                })
                ->with(['monitor' => function ($query) use ($school, $clientLanguages) {
                    $query->whereHas('monitorsSchools', function ($subQuery) use ($school) {
                        $subQuery->where('school_id', $school->id)
                            ->where('active_school', 1);
                    });

                    // Filtrar monitores por idioma si clientLanguages está presente
                    if (!empty($clientLanguages)) {
                        $query->where(function ($query) use ($clientLanguages) {
                            $query->orWhereIn('language1_id', $clientLanguages)
                                ->orWhereIn('language2_id', $clientLanguages)
                                ->orWhereIn('language3_id', $clientLanguages)
                                ->orWhereIn('language4_id', $clientLanguages)
                                ->orWhereIn('language5_id', $clientLanguages)
                                ->orWhereIn('language6_id', $clientLanguages);
                        });
                    }
                }])
                ->get()
                ->pluck('monitor');

        $busyMonitors = BookingUser::whereDate('date', $request->date)
            ->where(function ($query) use ($request) {
                $query->whereTime('hour_start', '<', Carbon::createFromFormat('H:i', $request->endTime))
                    ->whereTime('hour_end', '>', Carbon::createFromFormat('H:i', $request->startTime))
                    ->where('status', 1);
            })->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->pluck('monitor_id')
            ->merge(MonitorNwd::whereDate('start_date', '<=', $request->date)
                ->whereDate('end_date', '>=', $request->date)
                ->where(function ($query) use ($request) {
                    // Aquí incluimos la lógica para verificar si es un día entero
                    $query->where('full_day', true)
                        ->orWhere(function ($timeQuery) use ($request) {
                            $timeQuery->whereTime('start_time', '<',
                                Carbon::createFromFormat('H:i', $request->endTime))
                                ->whereTime('end_time', '>', Carbon::createFromFormat('H:i', $request->startTime));
                        });
                })
                ->pluck('monitor_id'))
            ->merge(CourseSubgroup::whereHas('courseDate', function ($query) use ($request) {
                $query->whereDate('date', $request->date)
                    ->whereTime('hour_start', '<', Carbon::createFromFormat('H:i', $request->endTime))
                    ->whereTime('hour_end', '>', Carbon::createFromFormat('H:i', $request->startTime));
            })
                ->pluck('monitor_id'))
            ->unique();

        // Paso 3: Filtrar los monitores elegibles excluyendo los ocupados.
        $availableMonitors = $eligibleMonitors->whereNotIn('id', $busyMonitors);

        // Eliminar los elementos nulos
        $availableMonitors = array_filter($availableMonitors->toArray());


        // Reindexar el array para eliminar las claves
        $availableMonitors = array_values($availableMonitors);


        // Paso 4: Devolver los monitores disponibles.
        return $availableMonitors;

    }

// Método auxiliar para comprobar días festivos
    private function isHoliday($schoolId, $date): bool
    {
        $season = Season::where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();



        if (!$season || empty($season->vacation_days)) {
            return false;
        }

        $vacationDays = json_decode($season->vacation_days, true);

        $formattedDate = $date instanceof Carbon ? $date->format('Y-m-d') : (string)$date;

        return in_array($formattedDate, $vacationDays, true);
    }


// Método auxiliar para encontrar monitores activos

    private function getActiveMonitorsForSchool($schoolId, $date)
    {
        $monitorSchools = MonitorsSchool::with([
            'monitor.sports' => function ($query) use ($schoolId) {
                $query->where('monitor_sports_degrees.school_id', $schoolId);
            },
            'monitor.courseSubgroups' => function ($query) use ($date) {
                $query->whereHas('courseDate', function ($query) use ($date) {
                    $query->whereDate('date', $date);
                });
            }
        ])
            ->where('school_id', $schoolId)
            ->where('active_school', 1)
            ->get();

        return $monitorSchools->pluck('monitor');
    }

// Métodos auxiliares para validaciones

    private function areMonitorsAvailable($monitors, $date, $startTime, $endTime): bool
    {
        foreach ($monitors as $monitor) {
            if (!Monitor::isMonitorBusy($monitor->id, $date, $startTime, $endTime)) {
                return true; // Hay al menos un monitor disponible
            }
        }
        return false; // Ningún monitor está disponible en el rango
    }

    // Método auxiliar para obtener monitores disponibles en un rango de tiempo
    private function getAvailableMonitorsForTimeRange($monitors, $date, $startTime, $endTime): array
    {
        $availableMonitors = [];

        foreach ($monitors as $monitor) {
            if (!Monitor::isMonitorBusy($monitor['id'], $date, $startTime, $endTime)) {
                $availableMonitors[] = [
                    'id' => $monitor['id'],
                    'name' => $monitor['first_name'] . ' ' . $monitor['last_name'],
                ];
            }
        }

        return $availableMonitors;
    }

    private function addSecondsToTime($time, $seconds)
    {
        $time = strtotime($time);
        return date('H:i', $time + $seconds);
    }

    private function convertDurationToSeconds($duration)
    {
        $parts = explode(' ', $duration);
        $hours = 0;
        $minutes = 0;

        foreach ($parts as $part) {
            if (str_contains($part, 'h')) {
                $hours = (int) str_replace('h', '', $part);
            } elseif (str_contains($part, 'min')) {
                $minutes = (int) str_replace('min', '', $part);
            }
        }

        return ($hours * 3600) + ($minutes * 60);
    }

    private function convertDurationRangeToSeconds($duration)
    {
        return $this->convertDurationToSeconds($duration);
    }

    private function convertSecondsToDuration($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = ($seconds % 3600) / 60;

        $result = [];
        if ($hours > 0) {
            $result[] = $hours . 'h';
        }
        if ($minutes > 0) {
            $result[] = $minutes . 'min';
        }

        return implode(' ', $result);
    }

    private function convertSecondsToHourFormat($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = ($seconds % 3600) / 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }



}
