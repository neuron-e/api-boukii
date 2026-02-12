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
use App\Services\CourseAvailabilityService;
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
            $cacheVersion = Cache::get('courses_cache_version_' . $this->school->id, 1);
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
                $today->format('Y-m-d') . '_' . $cacheVersion
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
            'courseExtras',
            'courseDates' => function ($query) use ($today) {
                $query->whereDate('date', '>=', $today->format('Y-m-d'))
                    ->with(['courseGroups' => function ($groupQuery) {
                        $groupQuery->with(['courseSubgroups' => function ($subQuery) {
                            $subQuery->withCount('bookingUsers')->with('degree');
                        }]);
                    }]);
            },
            'courseIntervals' => function ($query) {
                $query->ordered();
            }
        ])->where('school_id', $this->school->id)
            ->where('online', 1)
            ->where('active', 1)
        ->find($id);

        if (empty($course)) {
            return $this->sendError('Course does not exist in this school');
        } else {
            $availableDegreeIds = collect();
            $unAvailableDegreeIds = collect();
            $availabilityService = app(CourseAvailabilityService::class);
            $dateAvailabilityMap = [];
            foreach ($course->courseDates as $courseDate) {
                $dateAvailabilityMap[$courseDate->id] = false;
                $dateString = null;
                if ($courseDate->date instanceof Carbon) {
                    $dateString = $courseDate->date->format('Y-m-d');
                } elseif (!empty($courseDate->date)) {
                    $dateString = Carbon::parse($courseDate->date)->format('Y-m-d');
                }

                // Build degree-level totals to cap availability if total bookings exceed total capacity
                $degreeTotals = [];
                $subgroupCapacityMap = [];
                foreach ($courseDate->courseGroups as $group) {
                    foreach ($group->courseSubgroups as $subgroup) {
                        $capacity = $this->buildSubgroupCapacitySnapshot($course, $subgroup, $dateString, $availabilityService);
                        $subgroupCapacityMap[$subgroup->id] = $capacity;

                        $degreeId = $group->degree_id;
                        if (!isset($degreeTotals[$degreeId])) {
                            $degreeTotals[$degreeId] = [
                                'max_participants' => 0,
                                'current_bookings' => 0,
                                'has_unlimited' => false,
                            ];
                        }

                        if ($capacity['max_participants'] === null) {
                            $degreeTotals[$degreeId]['has_unlimited'] = true;
                        } else {
                            $degreeTotals[$degreeId]['max_participants'] += (int) $capacity['max_participants'];
                        }

                        $degreeTotals[$degreeId]['current_bookings'] += (int) ($capacity['current_bookings'] ?? 0);
                    }
                }

                foreach ($courseDate->courseGroups as $group) {
                    $degreeInfo = $degreeTotals[$group->degree_id] ?? null;
                    $degreeHasCapacity = true;
                    if ($degreeInfo) {
                        $degreeHasCapacity = $degreeInfo['has_unlimited']
                            ? true
                            : ($degreeInfo['current_bookings'] < $degreeInfo['max_participants']);
                    }

                    $group->courseSubgroups = $group->courseSubgroups->filter(function ($subgroup) use ($availableDegreeIds, $unAvailableDegreeIds, $group, $courseDate, &$dateAvailabilityMap, $subgroupCapacityMap, $degreeHasCapacity) {
                        $capacity = $subgroupCapacityMap[$subgroup->id] ?? null;
                        if (!$capacity) {
                            return false;
                        }

                        $hasAvailability = $capacity['has_availability'] && $degreeHasCapacity;

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

                        if ($hasAvailability) {
                            $dateAvailabilityMap[$courseDate->id] = true;
                        }

                        $subgroup->capacity_info = [
                            'max_participants' => $capacity['max_participants'],
                            'current_bookings' => $capacity['current_bookings'],
                            'available_slots' => $capacity['available_slots']
                        ];

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

            if ($course->course_type === 2) {
                foreach ($course->courseDates as $courseDate) {
                    if (!empty($dateAvailabilityMap[$courseDate->id])) {
                        continue;
                    }

                    $dateAvailabilityMap[$courseDate->id] = $this->hasPrivateAvailabilityForDate($course, $courseDate);
                }
            }

            $filteredCourseDates = collect();
            $intervalsWithCapacity = [];
            foreach ($course->courseDates as $courseDate) {
                $hasAvailability = $dateAvailabilityMap[$courseDate->id] ?? false;
                $courseDate->active = $hasAvailability ? 1 : 0;

                if (!$hasAvailability) {
                    $courseDate->setRelation('courseGroups', collect());
                    $courseDate->setRelation('courseSubgroups', collect());
                } else {
                    $courseDate->setRelation('courseGroups', $courseDate->courseGroups->filter(function ($group) {
                        return $group->courseSubgroups->isNotEmpty();
                    })->values());

                    $filteredCourseDates->push($courseDate);
                    $intervalKey = $courseDate->course_interval_id ?? $courseDate->interval_id;
                    if ($intervalKey !== null) {
                        $intervalsWithCapacity[(string)$intervalKey] = true;
                    }
                }
            }

            $course->setRelation('courseDates', $filteredCourseDates);
            if ($course->relationLoaded('courseIntervals')) {
                $course->setRelation('courseIntervals', $course->courseIntervals->filter(function ($interval) use ($intervalsWithCapacity) {
                    $intervalKey = $interval->id ?? $interval->interval_id;
                    if (!$intervalKey) {
                        return false;
                    }
                    return !empty($intervalsWithCapacity[(string)$intervalKey]);
                })->values());
            }

            $this->filterIntervalSettings($course, $dateAvailabilityMap, $intervalsWithCapacity);


            $uniqueDegrees = $availableDegreeIds->unique(function ($item) {
                return $item['degree_id'] . '-' . $item['recommended_age'];
            });

            $degreeIds = $uniqueDegrees->pluck('degree_id')->unique()->filter()->values();
            $degreesById = Degree::with('degreesSchoolSportGoals')
                ->whereIn('id', $degreeIds)
                ->get()
                ->keyBy('id');

            $availableDegrees = $uniqueDegrees->map(function ($item) use ($degreesById) {
                $degree = $degreesById->get($item['degree_id']);
                if ($degree) {
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
        $endTime = $courseDate->hour_end; // Hora m?xima del curso

        if (!$startTime || !strtotime($startTime)) {
            return $this->sendError('Invalid start time.');
        }

        if ((int) $course->course_type === 2 && !$this->isPrivateDateAllowedByPeriods($course, $courseDate->date)) {
            return $this->sendResponse([], 'No availability: date not allowed.');
        }

        if ((int) $course->course_type === 2 && $this->isPrivateLeadTimeViolated($courseDate->date, $startTime)) {
            return $this->sendResponse([], 'No availability: private lead time.');
        }

        // Verificar si es un d?a festivo
        if ($this->isHoliday($courseDate->school_id, $courseDate->date)) {
            return $this->sendResponse([], 'No availability: holiday.');
        }

        // Obtener monitores activos
        $monitors = $this->getActiveMonitorsForSchool($this->school->id, $courseDate->date);
        if ($monitors->isEmpty()) {
            return $this->sendResponse([], 'No monitors available.');
        }

        $clientIds = [];
        $bookingUsers = $request->bookingUsers ?? [];
        if (is_array($bookingUsers)) {
            foreach ($bookingUsers as $bookingUser) {
                if (($bookingUser['course']['course_type'] ?? null) == 2 && !empty($bookingUser['client']['id'])) {
                    $clientIds[] = $bookingUser['client']['id'];
                }
            }
        }
        $request['clientIds'] = $clientIds;

/*        if (BookingUser::hasOverlappingBookings($bookingUser, [])) {
            return $this->sendError('Client has booking on that date');
        }*/

        // Procesar duraciones
        $monitorAvailabilityCache = [];
        $durationsWithMonitors = $this->processDurations($course, $courseDate, $startTime, $endTime, $request, $monitors, $monitorAvailabilityCache);

        if (empty($durationsWithMonitors)) {
            return $this->sendResponse([], 'No availability.');
        }

        // Eliminar duplicados basados en la duraci?n
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

        private function processDurations($course, $courseDate, $startTime, $endTime, Request $request, $monitors, array &$monitorAvailabilityCache): array
    {
        $durationsWithMonitors = [];

        if (!$course->is_flexible) {
            // Procesar duraci�n fija
            $durationsWithMonitors = array_merge($durationsWithMonitors, $this->processFixedDuration($course, $courseDate, $startTime, $endTime, $request, $monitors, $monitorAvailabilityCache));
        } else {
            // Procesar duraci�n flexible
            $durationsWithMonitors = array_merge($durationsWithMonitors, $this->processFlexibleDurations($course, $courseDate, $startTime, $endTime, $request, $monitors, $monitorAvailabilityCache));
        }

        return $durationsWithMonitors;
    }

        private function processFixedDuration($course, $courseDate, $startTime, $endTime, Request $request, $monitors, array &$monitorAvailabilityCache): array
    {
        $durationInSeconds = $this->convertDurationToSeconds($course->duration);
        $endTimeForFixed = $this->addSecondsToTime($startTime, $durationInSeconds);

        if (strtotime($endTimeForFixed) <= strtotime($endTime)) {
            $cacheKey = $startTime . '|' . $endTimeForFixed;
            if (!array_key_exists($cacheKey, $monitorAvailabilityCache)) {
                $monitorAvailabilityRequest = $this->buildMonitorAvailabilityRequest($courseDate, $startTime, $endTimeForFixed, $request);
                $monitorAvailabilityCache[$cacheKey] = $this->getAvailableMonitorsForTimeRange(
                    $this->getMonitorsAvailable($monitorAvailabilityRequest),
                    $courseDate->date,
                    $startTime,
                    $endTimeForFixed
                );
            }
            $availableMonitors = $monitorAvailabilityCache[$cacheKey];
            $availableCount = is_array($availableMonitors) ? count($availableMonitors) : 0;
            $overbookingAllowed = $this->hasPrivateOverbookingCapacity($courseDate->date, $startTime, $endTimeForFixed, $availableCount);

            if ($availableCount > 0 || $overbookingAllowed) {
                return [[
                    'duration' => $this->convertSecondsToHourFormat($durationInSeconds),
                    'monitors' => $availableMonitors,
                ]];
            }
        }

        return [];
    }

        private function processFlexibleDurations($course, $courseDate, $startTime, $endTime, Request $request, $monitors, array &$monitorAvailabilityCache): array
    {
        $durationsWithMonitors = [];

        $intervals = [];
        foreach ($course->price_range as $price) {
            if (isset($price['intervalo'])) {
                $intervalInSeconds = $this->convertDurationRangeToSeconds($price['intervalo']);
                if ($intervalInSeconds > 0) {
                    $intervals[$intervalInSeconds] = true;
                }
            }
        }

        foreach (array_keys($intervals) as $intervalInSeconds) {
            $endTimeForFlexible = $this->addSecondsToTime($startTime, $intervalInSeconds);

            if (strtotime($endTimeForFlexible) <= strtotime($endTime)) {
                $cacheKey = $startTime . '|' . $endTimeForFlexible;
                if (!array_key_exists($cacheKey, $monitorAvailabilityCache)) {
                    $monitorAvailabilityRequest = $this->buildMonitorAvailabilityRequest($courseDate, $startTime, $endTimeForFlexible, $request);
                    $monitorAvailabilityCache[$cacheKey] = $this->getAvailableMonitorsForTimeRange(
                        $this->getMonitorsAvailable($monitorAvailabilityRequest),
                        $courseDate->date,
                        $startTime,
                        $endTimeForFlexible
                    );
                }
                $availableMonitors = $monitorAvailabilityCache[$cacheKey];
                $availableCount = is_array($availableMonitors) ? count($availableMonitors) : 0;
                $overbookingAllowed = $this->hasPrivateOverbookingCapacity($courseDate->date, $startTime, $endTimeForFlexible, $availableCount);

                if ($availableCount > 0 || $overbookingAllowed) {
                    $durationsWithMonitors[] = [
                        'duration' => $this->convertSecondsToHourFormat($intervalInSeconds),
                        'monitors' => $availableMonitors,
                    ];
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
        $cacheKey = sprintf(
            'booking_monitors_avail_%s_%s_%s_%s_%s_%s_%s',
            $this->school->id,
            $request->date,
            $request->startTime,
            $request->endTime,
            $request->sportId ?? 'na',
            $request->minimumDegreeId ?? 'na',
            md5(json_encode($request->clientIds ?? []))
        );

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($request) {
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
            $availableMonitors = array_filter($availableMonitors->toArray());
            $availableMonitors = array_values($availableMonitors);

            return $availableMonitors;
        });
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

    private function getSchoolSettings(): array
    {
        $settings = $this->school->settings ?? [];

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $settings : [];
    }

    private function getPrivateLeadMinutes(): int
    {
        $settings = $this->getSchoolSettings();
        $value = $settings['booking']['private_min_lead_minutes'] ?? null;

        if (is_numeric($value) && (int) $value >= 0) {
            return (int) $value;
        }

        return 30;
    }

    private function getPrivateOverbookingLimit(): int
    {
        $settings = $this->getSchoolSettings();
        $value = $settings['booking']['private_overbooking_limit'] ?? null;

        if (is_numeric($value) && (int) $value >= 0) {
            return (int) $value;
        }

        return 0;
    }

    private function getConcurrentPrivateBookings(string $date, string $startTime, string $endTime): int
    {
        return BookingUser::whereDate('date', $date)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereTime('hour_start', '<', Carbon::createFromFormat('H:i', $endTime))
                    ->whereTime('hour_end', '>', Carbon::createFromFormat('H:i', $startTime));
            })
            ->where('status', 1)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2);
            })
            ->count();
    }

    private function hasPrivateOverbookingCapacity(string $date, string $startTime, string $endTime, int $availableCount): bool
    {
        $overbookingLimit = $this->getPrivateOverbookingLimit();
        if ($overbookingLimit <= 0) {
            return false;
        }

        $concurrentBookings = $this->getConcurrentPrivateBookings($date, $startTime, $endTime);
        return ($availableCount + $overbookingLimit) > $concurrentBookings;
    }

    private function hasPrivateAvailabilityForDate($course, $courseDate): bool
    {
        if (!$course || !$courseDate) {
            return false;
        }

        $dateString = $courseDate->date instanceof Carbon
            ? $courseDate->date->format('Y-m-d')
            : (string) $courseDate->date;

        $cacheKey = sprintf('private_availability_%s_%s_%s', $this->school->id, $course->id, $courseDate->id);

        return Cache::remember($cacheKey, 60, function () use ($course, $courseDate, $dateString) {
            if ((int) $course->course_type === 2 && !$this->isPrivateDateAllowedByPeriods($course, $dateString)) {
                return false;
            }

            if ($this->isHoliday($courseDate->school_id, $dateString)) {
                return false;
            }

            $startSource = $courseDate->hour_start ?: ($course->hour_min ?? null);
            $endSource = $courseDate->hour_end ?: ($course->hour_max ?? null);
            if (!$startSource || !$endSource) {
                return false;
            }

            $minDurationSeconds = 0;
            if ($course->is_flexible) {
                $durations = [];
                $priceRange = $course->price_range;
                if (is_string($priceRange)) {
                    $decoded = json_decode($priceRange, true);
                    $priceRange = is_array($decoded) ? $decoded : [];
                }
                foreach ($priceRange ?? [] as $price) {
                    $interval = $price['intervalo'] ?? null;
                    if ($interval) {
                        $seconds = $this->convertDurationRangeToSeconds($interval);
                        if ($seconds > 0) {
                            $durations[] = $seconds;
                        }
                    }
                }
                if (!empty($durations)) {
                    $minDurationSeconds = min($durations);
                }
            } else {
                $minDurationSeconds = $this->convertDurationToSeconds($course->duration);
            }

            if ($minDurationSeconds <= 0) {
                return false;
            }

            $startMinutes = $this->timeToMinutes($startSource);
            $endMinutes = $this->timeToMinutes($endSource);
            $durationMinutes = (int) ceil($minDurationSeconds / 60);
            if ($endMinutes <= $startMinutes || $durationMinutes <= 0) {
                return false;
            }

            $stepMinutes = $course->is_flexible ? 5 : 15;
            $minStartMinutes = 0;
            if ($dateString === now()->format('Y-m-d')) {
                $minStart = Carbon::now()->addMinutes($this->getPrivateLeadMinutes());
                $minStartMinutes = $minStart->hour * 60 + $minStart->minute;
            }

            $eligibleMonitorIds = $this->getEligibleMonitorIdsForPrivateCourse($course);
            $overbookingLimit = $this->getPrivateOverbookingLimit();

            if (empty($eligibleMonitorIds) && $overbookingLimit <= 0) {
                return false;
            }

            $busyData = $this->getBusyIntervalsForDate($dateString, $this->school->id);
            $busyByMonitor = $busyData['by_monitor'] ?? [];
            $bookingIntervals = $busyData['booking_intervals'] ?? [];

            foreach ($eligibleMonitorIds as $monitorId) {
                $busyIntervals = $busyByMonitor[$monitorId] ?? [];
                $freeIntervals = $this->invertIntervals($busyIntervals, $startMinutes, $endMinutes);

                foreach ($freeIntervals as $free) {
                    $freeStart = max($free[0], $minStartMinutes);
                    $alignedStart = $this->alignToStep($freeStart, $stepMinutes);
                    if ($alignedStart + $durationMinutes <= $free[1]) {
                        return true;
                    }
                }
            }

            if ($overbookingLimit <= 0) {
                return false;
            }

            $startCandidate = max($startMinutes, $minStartMinutes);
            $startCandidate = $this->alignToStep($startCandidate, $stepMinutes);
            for ($minute = $startCandidate; $minute <= $endMinutes - $durationMinutes; $minute += $stepMinutes) {
                $overlaps = $this->countOverlappingIntervals($bookingIntervals, $minute, $minute + $durationMinutes);
                if ($overlaps < $overbookingLimit) {
                    return true;
                }
            }

            return false;
        });
    }

    private function isPrivateDateAllowedByPeriods(Course $course, string $dateString): bool
    {
        if ((int) $course->course_type !== 2) {
            return true;
        }

        $settings = $course->settings ?? [];
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        $periods = $settings['periods'] ?? [];
        if (!is_array($periods) || empty($periods)) {
            return true;
        }

        $date = Carbon::parse($dateString);
        $dayKey = strtolower($date->format('l'));

        foreach ($periods as $period) {
            $start = $period['date'] ?? null;
            $end = $period['date_end'] ?? null;
            if (!$start || !$end) {
                continue;
            }

            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);

            if ($date->lt($startDate) || $date->gt($endDate)) {
                continue;
            }

            $weekDays = $period['weekDays'] ?? $period['week_days'] ?? null;
            if (is_string($weekDays)) {
                $decoded = json_decode($weekDays, true);
                $weekDays = is_array($decoded) ? $decoded : null;
            }

            if (!is_array($weekDays) || count(array_filter($weekDays)) === 0) {
                return true;
            }

            if (!empty($weekDays[$dayKey])) {
                return true;
            }
        }

        return false;
    }

    private function getEligibleMonitorIdsForPrivateCourse(Course $course): array
    {
        $schoolId = $this->school->id;
        $sportId = $course->sport_id ?? null;
        if (!$sportId) {
            return [];
        }

        $cacheKey = sprintf('eligible_monitors_%s_%s', $schoolId, $sportId);

        return Cache::remember($cacheKey, 300, function () use ($schoolId, $sportId) {
            return MonitorSportsDegree::whereHas('monitorSportAuthorizedDegrees', function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
                ->where('sport_id', $sportId)
                ->whereHas('monitor.monitorsSchools', function ($query) use ($schoolId) {
                    $query->where('school_id', $schoolId)
                        ->where('active_school', 1);
                })
                ->distinct()
                ->pluck('monitor_id')
                ->filter()
                ->values()
                ->toArray();
        });
    }

    private function getBusyIntervalsForDate(string $date, int $schoolId): array
    {
        $byMonitor = [];
        $bookingIntervals = [];

        $bookingUsers = BookingUser::query()
            ->select(['monitor_id', 'hour_start', 'hour_end'])
            ->where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->where('status', 1)
            ->whereNotNull('monitor_id')
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2);
            })
            ->get();

        foreach ($bookingUsers as $booking) {
            $start = $this->timeToMinutes($booking->hour_start);
            $end = $this->timeToMinutes($booking->hour_end);
            if ($start === null || $end === null) {
                continue;
            }
            $bookingIntervals[] = [$start, $end];
            $byMonitor[$booking->monitor_id][] = [$start, $end];
        }

        $nwds = MonitorNwd::query()
            ->select(['monitor_id', 'start_time', 'end_time', 'full_day'])
            ->where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();

        foreach ($nwds as $nwd) {
            $start = $nwd->full_day ? 0 : $this->timeToMinutes($nwd->start_time);
            $end = $nwd->full_day ? 1440 : $this->timeToMinutes($nwd->end_time);
            if ($start === null || $end === null) {
                continue;
            }
            $byMonitor[$nwd->monitor_id][] = [$start, $end];
        }

        $subgroups = CourseSubgroup::query()
            ->select(['monitor_id', 'course_date_id'])
            ->whereNotNull('monitor_id')
            ->whereHas('courseDate', function ($query) use ($date, $schoolId) {
                $query->whereDate('date', $date)
                    ->whereHas('course', function ($inner) use ($schoolId) {
                        $inner->where('school_id', $schoolId);
                    });
            })
            ->with(['courseDate:id,hour_start,hour_end'])
            ->get();

        foreach ($subgroups as $subgroup) {
            if (!$subgroup->courseDate) {
                continue;
            }
            $start = $this->timeToMinutes($subgroup->courseDate->hour_start);
            $end = $this->timeToMinutes($subgroup->courseDate->hour_end);
            if ($start === null || $end === null) {
                continue;
            }
            $byMonitor[$subgroup->monitor_id][] = [$start, $end];
        }

        return [
            'by_monitor' => $byMonitor,
            'booking_intervals' => $bookingIntervals,
        ];
    }

    private function timeToMinutes(?string $time): ?int
    {
        if (!$time) {
            return null;
        }
        [$hour, $minute] = array_pad(explode(':', $time), 2, 0);
        return ((int) $hour) * 60 + (int) $minute;
    }

    private function alignToStep(int $minute, int $step): int
    {
        if ($step <= 1) {
            return $minute;
        }
        $remainder = $minute % $step;
        if ($remainder === 0) {
            return $minute;
        }
        return $minute + ($step - $remainder);
    }

    private function invertIntervals(array $intervals, int $rangeStart, int $rangeEnd): array
    {
        if (empty($intervals)) {
            return [[$rangeStart, $rangeEnd]];
        }

        $merged = $this->mergeIntervals($intervals);
        $free = [];
        $cursor = $rangeStart;

        foreach ($merged as $interval) {
            $start = max($rangeStart, $interval[0]);
            $end = min($rangeEnd, $interval[1]);
            if ($start > $cursor) {
                $free[] = [$cursor, $start];
            }
            $cursor = max($cursor, $end);
            if ($cursor >= $rangeEnd) {
                break;
            }
        }

        if ($cursor < $rangeEnd) {
            $free[] = [$cursor, $rangeEnd];
        }

        return $free;
    }

    private function mergeIntervals(array $intervals): array
    {
        usort($intervals, function ($a, $b) {
            return $a[0] <=> $b[0];
        });

        $merged = [];
        foreach ($intervals as $interval) {
            if (empty($merged)) {
                $merged[] = $interval;
                continue;
            }
            $lastIndex = count($merged) - 1;
            if ($interval[0] <= $merged[$lastIndex][1]) {
                $merged[$lastIndex][1] = max($merged[$lastIndex][1], $interval[1]);
            } else {
                $merged[] = $interval;
            }
        }

        return $merged;
    }

    private function countOverlappingIntervals(array $intervals, int $start, int $end): int
    {
        $count = 0;
        foreach ($intervals as $interval) {
            if ($interval[0] < $end && $interval[1] > $start) {
                $count++;
            }
        }
        return $count;
    }

    private function isPrivateLeadTimeViolated($date, ?string $startTime): bool
    {
        if (!$date || !$startTime) {
            return false;
        }

        $dateString = $date instanceof Carbon ? $date->format('Y-m-d') : (string) $date;
        $start = Carbon::parse(sprintf('%s %s', $dateString, $startTime));
        $minStart = Carbon::now()->addMinutes($this->getPrivateLeadMinutes());

        return $start->lt($minStart);
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






    private function resolveEffectiveMaxParticipants(Course $course, CourseSubgroup $subgroup, ?string $dateString, CourseAvailabilityService $service): ?int
    {
        $dateForCalc = $dateString;
        if (!$dateForCalc) {
            $dateForCalc = optional($subgroup->courseDate)->date
                ? Carbon::parse($subgroup->courseDate->date)->format('Y-m-d')
                : null;
        }

        if ($course->intervals_config_mode === 'independent' && $dateForCalc) {
            return $service->getMaxParticipants($subgroup, $dateForCalc);
        }

        return $subgroup->max_participants ?? $course->max_participants;
    }

    /**
     * Construir un snapshot de capacidad real del subgrupo considerando intervalos y reservas activas.
     */
    private function buildSubgroupCapacitySnapshot(
        Course $course,
        CourseSubgroup $subgroup,
        ?string $dateString,
        CourseAvailabilityService $availabilityService
    ): array {
        $effectiveMax = $this->resolveEffectiveMaxParticipants($course, $subgroup, $dateString, $availabilityService);

        // Valores por defecto
        $relationBookings = $subgroup->booking_users_count ?? null;
        if ($relationBookings === null) {
            $relationBookings = $subgroup->bookingUsers()->count();
        }
        $relationBookings = $relationBookings ?? 0;
        $availableSlots = $effectiveMax === null ? null : max(0, $effectiveMax - $relationBookings);
        $hasAvailability = $effectiveMax === null ? true : $availableSlots > 0;
        $serviceBookings = null;

        // Para cursos colectivos y con fecha concreta calculamos usando el servicio central
        if ((int) $course->course_type === 1 && $dateString) {
            $availableSlots = $availabilityService->getAvailableSlots($subgroup, $dateString);

            if ($effectiveMax === null) {
                $serviceBookings = null;
                $hasAvailability = $availableSlots > 0;
            } else {
                $serviceBookings = max(0, $effectiveMax - $availableSlots);
                $hasAvailability = $availableSlots > 0;
            }
        }

        $currentBookings = $serviceBookings !== null
            ? max($serviceBookings, $relationBookings)
            : $relationBookings;

        if ($effectiveMax !== null) {
            $availableSlots = max(0, $effectiveMax - $currentBookings);
            $hasAvailability = $availableSlots > 0;
        }

        return [
            'has_availability' => $hasAvailability,
            'max_participants' => $effectiveMax,
            'current_bookings' => $currentBookings,
            'available_slots' => $effectiveMax === null ? null : $availableSlots,
        ];
    }

        /**
     * Ajusta la metadata de intervalos/fechas del curso seg?n la disponibilidad calculada.
     */
    private function filterIntervalSettings(Course $course, array $dateAvailabilityMap, array $intervalsWithCapacity): void
    {
        $settings = $course->settings;
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : $settings;
        }

        if (!is_array($settings)) {
            $course->settings = $settings;
            return;
        }

        $keptIndexes = [];
        $keptIds = [];

        if (!empty($settings['intervals']) && is_array($settings['intervals'])) {
            [$settings['intervals'], $keptIndexes, $keptIds] = $this->filterIntervalEntries($settings['intervals'], $dateAvailabilityMap, $intervalsWithCapacity);
        }

        if (!empty($settings['intervalConfiguration']['intervals']) && is_array($settings['intervalConfiguration']['intervals'])) {
            $validIds = !empty($keptIds) ? $keptIds : $intervalsWithCapacity;
            $settings['intervalConfiguration']['intervals'] = $this->filterIntervalConfigurationEntries(
                $settings['intervalConfiguration']['intervals'],
                $dateAvailabilityMap,
                $validIds
            );
        }

        if (!empty($settings['intervalGroups']) && is_array($settings['intervalGroups'])) {
            if (!empty($keptIndexes)) {
                $settings['intervalGroups'] = array_values(array_filter(
                    $settings['intervalGroups'],
                    function ($value, $index) use ($keptIndexes) {
                        return in_array($index, $keptIndexes, true);
                    },
                    ARRAY_FILTER_USE_BOTH
                ));
            } else {
                $settings['intervalGroups'] = [];
            }
        }

        if (!empty($settings['intervalGroupsById']) && is_array($settings['intervalGroupsById'])) {
            if (!empty($keptIds)) {
                $settings['intervalGroupsById'] = array_intersect_key(
                    $settings['intervalGroupsById'],
                    $keptIds
                );
            } else {
                $settings['intervalGroupsById'] = [];
            }
        }

        $course->settings = $settings;
    }

    private function filterIntervalEntries(array $intervals, array $dateAvailabilityMap, array $intervalsWithCapacity): array
    {
        $filtered = [];
        $keptIndexes = [];
        $keptIds = [];

        foreach ($intervals as $index => $intervalData) {
            $intervalId = $this->normalizeIntervalIdentifier($intervalData['id'] ?? $intervalData['intervalId'] ?? null);
            if (!$intervalId || empty($intervalsWithCapacity[$intervalId])) {
                continue;
            }

            $intervalData['dates'] = $this->filterIntervalDates($intervalData['dates'] ?? [], $dateAvailabilityMap);
            if (empty($intervalData['dates'])) {
                continue;
            }

            $filtered[] = $intervalData;
            $keptIndexes[] = $index;
            $keptIds[$intervalId] = true;
        }

        return [array_values($filtered), $keptIndexes, $keptIds];
    }

    private function filterIntervalConfigurationEntries(array $intervals, array $dateAvailabilityMap, array $validIds): array
    {
        $filtered = [];

        foreach ($intervals as $intervalData) {
            $intervalId = $this->normalizeIntervalIdentifier($intervalData['id'] ?? $intervalData['intervalId'] ?? null);
            if (!empty($validIds) && (!$intervalId || empty($validIds[$intervalId]))) {
                continue;
            }

            $intervalData['dates'] = $this->filterIntervalDates($intervalData['dates'] ?? [], $dateAvailabilityMap);
            if (empty($intervalData['dates'])) {
                continue;
            }

            $filtered[] = $intervalData;
        }

        return array_values($filtered);
    }

    private function filterIntervalDates(array $dates, array $dateAvailabilityMap): array
    {
        $filtered = [];

        foreach ($dates as $dateEntry) {
            $courseDateId = $dateEntry['course_date_id'] ?? $dateEntry['id'] ?? null;
            if (!$courseDateId) {
                continue;
            }

            $hasAvailability = $dateAvailabilityMap[$courseDateId] ?? $dateAvailabilityMap[(string)$courseDateId] ?? false;
            $dateEntry['active'] = $hasAvailability ? 1 : 0;
            $dateEntry['is_available'] = $hasAvailability;

            if ($hasAvailability) {
                $filtered[] = $dateEntry;
            }
        }

        return array_values($filtered);
    }

    private function normalizeIntervalIdentifier($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (string)$value;
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }

}
