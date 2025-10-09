<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CourseDetailsExport;
use App\Http\Controllers\AppBaseController;
use App\Mail\BookingInfoUpdateMailer;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\Season;
use App\Repositories\CourseRepository;
use App\Traits\Utils;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Response;
use Validator;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */

class CourseController extends AppBaseController
{
    use Utils;
    private $courseRepository;

    public function __construct(CourseRepository $courseRepo)
    {
        $this->courseRepository = $courseRepo;
    }


    /**
     * @OA\Get(
     *      path="/admin/courses",
     *      summary="getCourseList",
     *      tags={"Admin"},
     *      description="Get all Courses",
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
        $school = $this->getSchool($request);
        $courses = $this->courseRepository->all(
            searchArray: $request->except(['skip', 'limit', 'search', 'exclude', 'active', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with', 'include_archived']),
            search: $request->get('search'),
            skip: $request->get('skip'),
            limit: $request->get('limit'),
            pagination: $request->get('perPage'),
            with: $request->get('with',  ['station', 'sport',
                'courseDates.courseGroups.courseSubgroups.bookingUsers',
                'courseExtras']),
            order: $request->get('order', 'desc'),
            orderColumn: $request->get('orderColumn', 'id'),
            additionalConditions: function ($query) use ($request, $school) {
                // Obtén el ID de la escuela y añádelo a los parámetros de búsqueda

                $query->where('school_id', $school->id);

                // Excluir cursos archivados por defecto (a menos que se pida explícitamente incluirlos)
                if (!$request->get('include_archived', false)) {
                    $query->whereNull('archived_at');
                }

                $query->when($request->has('course_types') && is_array($request->course_types), function ($query) use ($request) {
                    $query->whereIn('course_type', $request->course_types);
                });


                $query->when($request->has('sports_id') && is_array($request->sports_id), function ($query) use ($request) {
                    // dd($request->sport_id);
                    $query->whereIn('sport_id', $request->sports_id);
                });

                $query->when($request->has('finished') && $request->finished, function ($query) {
                    $today = now()->format('Y-m-d');
                    $query->whereDoesntHave('courseDatesActive', function ($subquery) use ($today) {
                        $subquery->where('date', '>=', $today);
                    });
                });

                $query->when($request->has('active'), function ($query) {
                    $today = now()->format('Y-m-d');
                    $query->whereHas('courseDatesActive', function ($subquery) use ($today) {
                        $subquery->where('date', '>=', $today);
                    });
                });

                // Agregar condiciones para el rango de fechas
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');

                if ($startDate && $endDate) {
                    $query->whereHas('courseDatesActive', function ($subquery) use ($startDate, $endDate) {
                        $subquery->whereBetween('date', [$startDate, $endDate]);
                    });
                }
            }
        );

        $monitorsBySportAndDegree = $this->getGroupedMonitors($school->id);

        // Calcula reservas y plazas disponibles para cada curso
        foreach ($courses as $course) {
            if($course->course_type == 1) {
                $availability = $this->getCourseAvailability($course, $monitorsBySportAndDegree);

                // dd($availability);

                $course->total_reservations = $availability['total_reservations_places'];
                $course->total_available_places = $availability['total_available_places'];
                $course->total_places = $availability['total_places'];
            } else {
                $course->total_reservations = $course->bookingUsersActive->count();
            }

        }

        return $this->sendResponse($courses, 'Courses retrieved successfully');
    }

    /**
     * @OA\Get(
     *      path="/admin/courses/{id}",
     *      summary="getCourseWithBookings",
     *      tags={"Admin"},
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
        $school = $this->getSchool($request);

        // Comprueba si el cliente principal tiene booking_users asociados con el ID del monitor
        $course = Course::with( 'station','bookingUsersActive.client.sports', 'bookingUsers.client.sports',
            'courseDates.courseSubgroups.bookingUsers.client',
            'courseDates.courseGroups.courseSubgroups.monitor',
            'courseDates.courseGroups.bookingUsers.client',
            'courseDates.bookingUsersActive.client',
            'courseExtras',
            'courseDates.courseGroups.courseSubgroups.bookingUsers.client')
            ->where('school_id', $school->id)->find($id);

        if (empty($course)) {
            return $this->sendError('Course does not exist in this school');
        }

        $monitorsBySportAndDegree = $this->getGroupedMonitors($school->id);

        $availability = $this->getCourseAvailability($course, $monitorsBySportAndDegree);
        $course->total_reservations = $availability['total_reservations_places'];
        $course->total_available_places = $availability['total_available_places'];
        $course->total_places = $availability['total_places'];

        return $this->sendResponse($course, 'Course retrieved successfully');
    }

    public function exportDetails(Request $request, $courseId, $lang = 'fr')
    {
        $school = $this->getSchool($request);

        app()->setLocale($lang);
        // Valida el ID del curso
        $course = Course::with([
            'courseDates.courseGroups.bookingUsers.client'
        ])->where('school_id', $school->id)->findOrFail($courseId);

        if (empty($course)) {
            return $this->sendError('Course does not exist in this school');
        }

        //dd($course->courseDates[0]);
        // Exporta el archivo
        return (new CourseDetailsExport($courseId))->download('course_details.xlsx');
    }

    /**
     * @OA\Post(
     *      path="/admin/courses",
     *      summary="createCourse",
     *      tags={"Admin"},
     *      description="Create Course",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Course")
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
    public function store(Request $request): JsonResponse
    {
        $school = $this->getSchool($request);
        $request->merge(["school_id" => $school->id]);

        try {
            $request->validate([
                'course_type' => 'required',
                'is_flexible' => 'required',
                'sport_id' => 'required|exists:sports,id',
                'school_id' => 'required|exists:schools,id',
                'user_id' => 'nullable|exists:users,id',
                'station_id' => 'nullable|exists:stations,id',
                'name' => 'required|string|max:65535',
                'short_description' => 'required|string|max:65535',
                'description' => 'required|string|max:65535',
                'price' => 'required|numeric|min:0',
                'currency' => 'required|string|max:3',
                'max_participants' => 'required|integer|min:1',
                'duration' => 'nullable',
                'date_start' => 'required|date',
                'date_end' => 'required|date|after_or_equal:date_start',
                'date_start_res' => 'nullable|date',
                'date_end_res' => 'nullable|date|after_or_equal:date_start_res',
                'confirm_attendance' => 'required|boolean',
                'active' => 'required|boolean',
                'online' => 'required|boolean',
                'image' => 'nullable|string',
                'translations' => 'nullable|string',
                'price_range' => 'nullable',
                'discounts' => 'nullable',
                'settings' => 'nullable',
                'extras' => 'nullable|array', // Los extras no son obligatorios
                'extras.*.name' => 'required_with:courseExtras|string|max:255', // Obligatorios si hay extras
                'extras.*.description' => 'nullable|string|max:255',
                'extras.*.group' => 'nullable|string|max:255',
                'extras.*.price' => 'required_with:courseExtras|numeric', // Obligatorios si hay extras
                'course_dates' => 'required|array',
                'course_dates.*.date' => 'required|date',
                'course_dates.*.hour_start' => 'required|string|max:255',
                'course_dates.*.hour_end' => 'nullable|string|max:255',
                'course_dates.*.interval_id' => 'nullable|integer|min:1',
                'course_dates.*.order' => 'nullable|integer|min:1',
                'course_dates.*.duration' => 'nullable|string',
                'course_dates.*.groups' => 'required_if:course_type,1|array',
                'course_dates.*.groups.*.degree_id' => 'required|exists:degrees,id',
                'course_dates.*.groups.*.age_min' => 'nullable|integer|min:0',
                'course_dates.*.groups.*.age_max' => 'nullable|integer|min:0',
                'course_dates.*.groups.*.recommended_age' => 'nullable|integer|min:0',
                'course_dates.*.groups.*.teachers_min' => 'nullable|integer|min:1',
                'course_dates.*.groups.*.teachers_max' => 'nullable|integer|min:1',
                'course_dates.*.groups.*.observations' => 'nullable|string|max:65535',
                'course_dates.*.groups.*.teacher_min_degree' => 'nullable|exists:degrees,id',
                'course_dates.*.groups.*.subgroups' => 'required|array',
                'course_dates.*.groups.*.subgroups.*.degree_id' => 'required|exists:degrees,id',
                'course_dates.*.groups.*.subgroups.*.monitor_id' => 'nullable|exists:monitors,id',
                'course_dates.*.groups.*.subgroups.*.max_participants' => 'nullable|integer|min:0',
            ]);

            $courseData = $request->all();
            $courseData['duration'] = $this->formatDuration($courseData['duration']);

            if (!empty($courseData['image'])) {
                $base64Image = $request->input('image');

                if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                    $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                    $type = strtolower($type[1]);
                    $imageData = base64_decode($imageData);

                    if ($imageData === false) {
                        $this->sendError('base64_decode failed');
                    }
                } else {
                    $this->sendError('did not match data URI with image data');
                }

                $imageName = 'course/image_' . time() . '.' . $type;
                Storage::disk('public')->put($imageName, $imageData);
                $courseData['image'] = url(Storage::url($imageName));
            }

            DB::beginTransaction();

            $schoolSettings = json_decode($school->settings, true) ?? [];
            $existingExtras = $schoolSettings['extras']['forfait'] ?? [];



            if (!empty($courseData['extras'])) {
                foreach ($courseData['extras'] as $extra) {
                    if (!collect($existingExtras)->contains('name', $extra['name'])) {
                        $existingExtras[] = $extra;
                    }
                }
                $schoolSettings['extras']['forfait'] = $existingExtras;
                $school->settings = json_encode($schoolSettings);
                $school->save();
            }

            $course = Course::create($courseData);

            if (!empty($courseData['extras'])) {
                foreach ($courseData['extras'] as $extra) {
                    $course->courseExtras()->create([
                        'name' => $extra['product'],
                        'description' => $extra['name'],
                        'price' => $extra['price']
                    ]);
                }
            }

            if(!isset($courseData['course_dates'])) {
                $this->sendError('Course can not be created without course_dates');
            }
            $settings = $courseData['settings'] ?? null;

            if (is_string($settings)) {
                $settings = json_decode($settings, true);
            }

            if (!is_array($settings)) {
                $settings = []; // Si no es un array válido, inicializamos uno vacío
            }
            // Safely read weekDays; may be absent
            $weekDays = $settings['weekDays'] ?? null;

            if ($course->course_type != 1) {
                $periods = [];
                foreach ($courseData['course_dates'] as $date) {
                    $periods[] = [
                        'date' => $date['date'],
                        'date_end' => $date['date_end'],
                        'hour_start' => $date['hour_start'],
                        'hour_end' => $date['hour_end'],
                    ];
                }
                $settings['periods'] = $periods;

                $courseDates = $this->generateCourseDates($courseData['course_dates'], $weekDays);
                $course->courseDates()->createMany($courseDates);
            }

            // Crear las fechas y grupos
            if ($course->course_type === 1) {
                $hasWeekDays = is_array($weekDays) && count(array_filter($weekDays)) > 0;
                foreach ($courseData['course_dates'] as $dateData) {
                    // Validar o calcular hour_end
                    if (empty($dateData['hour_end']) && !empty($dateData['duration'])) {
                        $dateData['hour_end'] = $this->calculateHourEnd($dateData['hour_start'], $dateData['duration']);
                    } elseif (empty($dateData['hour_end'])) {
                        DB::rollback();
                        return $this->sendError('Either hour_end or duration must be provided.');
                    }

                    if ($hasWeekDays) {
                        $date = new \DateTime($dateData['date']);
                        $dayOfWeek = strtolower($date->format('l')); // Get the day of the week in lowercase

                        if (isset($weekDays[$dayOfWeek]) && $weekDays[$dayOfWeek]) {
                            $date = $course->courseDates()->create($dateData);
                        } else {
                            continue; // Skip this date since it's not in the specified weekdays
                        }
                    } else {
                        $date = $course->courseDates()->create($dateData);
                    }

                    if (isset($dateData['groups'])) {
                        foreach ($dateData['groups'] as $groupData) {
                            $groupData['course_id'] = $course->id;
                            $group = $date->courseGroups()->create($groupData);

                            if (isset($groupData['subgroups'])) {
                                foreach ($groupData['subgroups'] as &$subgroup) {
                                    $subgroup['course_id'] = $course->id;
                                    $subgroup['course_date_id'] = $date->id;
                                }

                                $group->courseSubgroups()->createMany($groupData['subgroups']);
                            }
                        }
                    }
                }
            }

            $allDates = array_column($course->courseDates->toArray(), 'date');
            $allHourStarts = array_column($course->courseDates->toArray(), 'hour_start');
            $allHourEnds = array_column($course->courseDates->toArray(), 'hour_end');

            if (!empty($allDates)) {
                sort($allDates);
                sort($allHourStarts);
                rsort($allHourEnds); // Orden inverso para obtener el mayor

                $course->update([
                    'date_start'  => $allDates[0],   // Primera fecha (mínima)
                    'date_end'    => end($allDates), // Última fecha (máxima)
                    'hour_min'  => $allHourStarts[0],  // Menor hora de inicio
                    'hour_max'    => $allHourEnds[0],    // Mayor hora de fin
                    'settings'    => $settings
                ]);
            }

            DB::commit();
            return $this->sendResponse($course, 'Curso creado con éxito');
        } catch (\Exception $e) {
            DB::rollback();
            \Illuminate\Support\Facades\Log::debug('An error occurred while creating the course: : ' .
                $e->getLine());
            return $this->sendError('An error occurred while creating the course: ' . $e->getMessage());
        }
    }

    private function getMostCommonDuration(array $durations)
    {
        if (empty($durations)) {
            return null;
        }

        $counted = array_count_values($durations);
        arsort($counted); // Ordenar por frecuencia descendente

        $maxFrequency = reset($counted);
        $mostCommon = array_keys($counted, $maxFrequency);

        // Si hay empate, devolver la mayor duración
        usort($mostCommon, function ($a, $b) {
            return strtotime($b) - strtotime($a);
        });

        return $mostCommon[0];
    }

    private function formatDuration($duration)
    {
        preg_match('/(\d+)(h|min)(?:\s*(\d+)?min)?/', $duration, $matches);

        $hours = 0;
        $minutes = 0;

        if ($matches) {
            if ($matches[2] === 'h') {
                $hours = (int) $matches[1];
                if (isset($matches[3])) {
                    $minutes = (int) $matches[3];
                }
            } else {
                $minutes = (int) $matches[1];
            }
        }

        return $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
    }

    private function generateCourseDates(array $courseDates, ?array $weekDays)
    {
        $generatedDates = [];
        $weekDaysMap = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];

        foreach ($courseDates as $dateInfo) {
            $startDate = new Carbon($dateInfo['date']);
            $endDate = new Carbon($dateInfo['date_end']);
            $hourStart = $dateInfo['hour_start'];
            $hourEnd = $dateInfo['hour_end'];
            $applyWeekdayFilter = is_array($weekDays) && count(array_filter($weekDays)) > 0;

            while ($startDate->lte($endDate)) {
                $dayOfWeek = $startDate->dayOfWeekIso; // 1 (Lunes) - 7 (Domingo)

                if ($applyWeekdayFilter) {
                    foreach ($weekDays as $day => $isActive) {
                        if ($isActive && ($weekDaysMap[$day] ?? null) == $dayOfWeek) {
                            $generatedDates[] = [
                                'date' => $startDate->toDateString(),
                                'hour_start' => $hourStart,
                                'hour_end' => $hourEnd,
                                'duration' => array_key_exists('duration', $dateInfo) ? $dateInfo['duration'] : $this->calculateDuration($hourStart, $hourEnd),
                                'date_end' => $startDate->toDateString(), // Ajustar si es necesario
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                } else {
                    // No weekdays specified: include every day in the range
                    $generatedDates[] = [
                        'date' => $startDate->toDateString(),
                        'hour_start' => $hourStart,
                        'hour_end' => $hourEnd,
                        'duration' => array_key_exists('duration', $dateInfo) ? $dateInfo['duration'] : $this->calculateDuration($hourStart, $hourEnd),
                        'date_end' => $startDate->toDateString(), // Ajustar si es necesario
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $startDate->addDay();
            }
        }

        return $generatedDates;
    }

    private function calculateHourEnd(string $hourStart, string $duration): string
    {
        $time = \DateTime::createFromFormat('H:i', $hourStart);
        if (!$time) {
            throw new \Exception('Invalid hour_start format. Expected H:i');
        }

        $matches = [];
        if (preg_match('/(?:(\d+)h)?\s*(\d+)?min/', $duration, $matches)) {
            $hours = isset($matches[1]) ? (int)$matches[1] : 0;
            $minutes = isset($matches[2]) ? (int)$matches[2] : 0;

            $time->modify("+{$hours} hours");
            $time->modify("+{$minutes} minutes");
        } else {
            throw new \Exception('Invalid duration format. Expected formats like "2h 30min" or "15min"');
        }

        return $time->format('H:i');
    }


    /**
     * @OA\Put(
     *      path="/admin/courses/{id}",
     *      summary="updateCourse",
     *      tags={"Admin"},
     *      description="Update Course",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Course",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Course")
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
    public function update($id, Request $request): JsonResponse
    {
        $school = $this->getSchool($request);
        try {
            $emailGroups = [];
            $courseData = $request->all();
            $course = Course::findOrFail($id); // Suponiendo que tienes el ID del curso que deseas editar

            if (!empty($courseData['image'])) {
                $base64Image = $request->input('image');

                if (is_string($base64Image) && preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                    $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                    $type = strtolower($type[1]);
                    $imageData = base64_decode($imageData);

                    if ($imageData !== false) {
                        $imageName = 'course/image_' . time() . '.' . $type;
                        Storage::disk('public')->put($imageName, $imageData);
                        $courseData['image'] = url(Storage::url($imageName));
                    } else {
                        // Si base64_decode falla, simplemente seguimos sin guardar la imagen
                        unset($courseData['image']);
                    }
                } else {
                    // Si no es una imagen en base64, continuar sin procesarla
                    unset($courseData['image']);
                }
            }

            DB::beginTransaction();
            // Actualiza los campos principales del curso

            $schoolSettings = json_decode($school->settings, true) ?? [];
            $existingExtras = $schoolSettings['extras']['forfait'] ?? [];

            if (!empty($courseData['extras'])) {
                foreach ($courseData['extras'] as $extra) {
                    $extraName = $extra['name'] ?? $extra['product']; // Usa 'name' si existe, sino 'product'

                    if ($extraName && !collect($existingExtras)->contains('name', $extraName)) {
                        $existingExtras[] = $extra;
                    }
                }

                $schoolSettings['extras']['forfait'] = $existingExtras;
                $school->settings = json_encode($schoolSettings);
                $school->save();
            }

            $course->update($courseData);

            if (!empty($courseData['extras'])) {
                foreach ($courseData['extras'] as $extra) {
                    $productName = $extra['product'] ?? $extra['name']; // Usar 'product' si existe, sino 'name'

                    $course->courseExtras()->updateOrCreate(
                        ['name' => $productName], // Condición para buscar si ya existe
                        [
                            'description' => $extra['name'],
                            'price' => $extra['price']
                        ]
                    );
                }
            }

            $settings = $courseData['settings'] ?? null;

            if (is_string($settings)) {
                $settings = json_decode($settings, true);
            }

            if (!is_array($settings)) {
                $settings = []; // Si no es un array válido, inicializamos uno vacío
            }
            // Safely read weekDays; may be absent
            $weekDays = $settings['weekDays'] ?? null;
            if ($course->course_type != 1 && isset($courseData['course_dates'])) {
                $existingDates = $course->courseDates()->pluck('date')->toArray();
                $newDates = $this->generateCourseDates($courseData['course_dates'], $weekDays);

                $periods = [];
                foreach ($courseData['course_dates'] as $date) {
                    $periods[] = [
                        'date' => $date['date'],
                        'date_end' => $date['date_end'],
                        'hour_start' => $date['hour_start'],
                        'hour_end' => $date['hour_end'],
                    ];
                }
                $settings['periods'] = $periods;

                // Extraer solo las fechas de los nuevos periodos
                $newDatesList = array_column($newDates, 'date');

                // Fechas candidatas a eliminar (existen en la BD pero no en las nuevas)
                $datesToDelete = array_diff($existingDates, $newDatesList);

                // Filtrar fechas que NO tengan bookingUsersActive antes de eliminarlas
                $datesToDelete = $course->courseDates()
                    ->whereIn('date', $datesToDelete)
                    ->whereDoesntHave('bookingUsersActive') // Solo elimina si no hay reservas activas
                    ->pluck('date')
                    ->toArray();

                if (!empty($datesToDelete)) {
                    $course->courseDates()->whereIn('date', $datesToDelete)->delete();
                }

                // Fechas a insertar (no existen en la BD)
                $datesToInsert = array_filter($newDates, function ($date) use ($existingDates) {
                    return !in_array($date['date'], $existingDates);
                });

                $course->courseDates()->createMany($datesToInsert);
            }

            /* if ($course->course_type !== 1 ) {
                 // Obtener date_start y date_end del curso
                 $courseStartDate = $course->date_start;
                 $courseEndDate = $course->date_end;

                 // Ordenar las fechas de la request por 'date'
                 usort($courseData['course_dates'], function ($a, $b) {
                     return strtotime($a['date']) - strtotime($b['date']);
                 });

                 // Obtener la primera y última fecha de la request
                 $requestFirstDate = $courseData['course_dates'][0]['date'] ?? null;
                 $requestLastDate = end($courseData['course_dates'])['date'] ?? null;

                 // Si las fechas en la request no coinciden con el curso, generar todas las fechas intermedias
                 if ($requestFirstDate !== $courseStartDate || $requestLastDate !== $courseEndDate) {
                     $weekdays = $course->settings['weekdays'] ?? []; // Obtener pattern de weekdays
                     $generatedDates = [];

                     $period = new DatePeriod(
                         new DateTime($courseStartDate),
                         new DateInterval('P1D'),
                         (new DateTime($courseEndDate))->modify('+1 day') // Incluir la última fecha
                     );

                     foreach ($period as $date) {
                         $weekday = $date->format('N'); // 1 = Lunes, 7 = Domingo

                         // Si no hay weekdays definidos, incluir todas las fechas
                         if (empty($weekdays) || in_array($weekday, $weekdays)) {
                             $generatedDates[] = [
                                 'date' => $date->format('Y-m-d'),
                                 'hour_start' => $course->hour_min,
                                 'hour_end' => $course->hour_max,
                                 'course_id' => $course->id
                             ];
                         }
                     }

                     // Reemplazar course_dates con las nuevas fechas generadas
                     $courseData['course_dates'] = $generatedDates;
                 }
             }*/

            // Sincroniza las fechas
            if ($course->course_type == 1 && isset($courseData['course_dates'])) {
                $updatedCourseDates = [];
                foreach ($courseData['course_dates'] as $dateData) {
                    // Verifica si existe 'id' antes de usarlo
                    if (isset($dateData['active']) && $dateData['active'] === false && isset($dateData['id'])) {
                        $date = CourseDate::findOrFail($dateData['id']);
                        $bookingUsersCount = $date->bookingUsers()->count();

                        if ($bookingUsersCount > 0) {
                            DB::rollback();
                            return $this->sendError('Date has bookings and cannot be deactivated');
                        }
                    }
                    $dateId = isset($dateData['id']) ? $dateData['id'] : null;
                    if (empty($dateData['hour_end']) && !empty($dateData['duration'])) {
                        $dateData['hour_end'] = $this->calculateHourEnd($dateData['hour_start'], $dateData['duration']);
                    }
                    $date = $course->courseDates()->updateOrCreate(['id' => $dateId], $dateData);

                    if (isset($dateData['date']) && isset($dateData['id'])) {
                        $date = CourseDate::find($dateData['id']);
                        if ($date) {
                            $providedDate = $dateData['date'];

                            // Verificar si la fecha ya está en el formato 'Y-m-d'
                            if (strpos($providedDate, 'T') !== false) {
                                // Convierte la fecha del formato 'Y-m-d\TH:i:s.u\Z' a 'Y-m-d'
                                $providedDate = date_create_from_format('Y-m-d\TH:i:s.u\Z', $providedDate);

                                if ($providedDate) {
                                    $providedDate = $providedDate->format('Y-m-d');
                                }
                            }

                            $modelDate = $date->date->format('Y-m-d');

                            if ($providedDate && $providedDate !== $modelDate) {
                                $bookingUsers = $date->bookingUsersActive()->get();

                                foreach ($bookingUsers as $bookingUser) {
                                    $clientEmail = $bookingUser->booking->clientMain->email;
                                    $bookingId = $bookingUser->booking_id;

                                    $bookingUser->update([
                                        'date' => $providedDate,
                                        'hour_start' => $date->hour_start, // Si no viene en la request, usa la actual
                                        'hour_end' => $date->hour_end, // Lo mismo para la hora de fin
                                    ]);

                                    if (array_key_exists($clientEmail, $emailGroups)) {
                                        // Verificar si el booking ID ya está en el grupo del correo electrónico
                                        if (!in_array($bookingId, $emailGroups[$clientEmail])) {
                                            // Si no está, agregarlo al grupo del correo electrónico
                                            $emailGroups[$clientEmail][] = $bookingId;
                                        }
                                    } else {
                                        // Si el correo electrónico no está en el array, crear un nuevo grupo
                                        $emailGroups[$clientEmail] = [$bookingId];
                                    }
                                }
                            }
                        }
                    }

                    $updatedCourseDates[] = $date->id;

                    if (isset($dateData['course_groups'])) {
                        $updatedCourseGroups = [];
                        foreach ($dateData['course_groups'] as $groupData) {
                            // Verifica si existe 'id' antes de usarlo
                            $groupId = isset($groupData['id']) ? $groupData['id'] : null;
                            $group = $date->courseGroups()->updateOrCreate(['id' => $groupId], $groupData);
                            $updatedCourseGroups[] = $group->id;

                            if (isset($groupData['course_subgroups'])) {
                                $updatedSubgroups = [];
                                foreach ($groupData['course_subgroups'] as $subgroupData) {
                                    $subgroupData['course_id'] = $course->id;
                                    $subgroupData['course_date_id'] = $date->id;

                                    // Verifica si existe 'id' antes de usarlo
                                    $subgroupId = $subgroupData['id'] ?? null;
                                    if ($subgroupId) {
                                        $subgroup = $group->courseSubgroups()->updateOrCreate(['id' => $subgroupId], $subgroupData);
                                    } else {
                                        $subgroup = $group->courseSubgroups()->create($subgroupData);
                                    }
                                    $updatedSubgroups[] = $subgroup->id;
                                }

                                // Eliminar los subgrupos que ya no están en la request
                                $group->courseSubgroups()->whereNotIn('id', $updatedSubgroups)->delete();
                            }
                        }
                        $date->courseGroups()->whereNotIn('id', $updatedCourseGroups)->delete();
                    }
                }
                $course->courseDates()->whereNotIn('id', $updatedCourseDates)->delete();
            }

            $allDates = array_column($course->courseDates->toArray(), 'date');
            $allHourStarts = array_column($course->courseDates->toArray(), 'hour_start');
            $allHourEnds = array_column($course->courseDates->toArray(), 'hour_end');

            if (!empty($allDates)) {
                sort($allDates);
                sort($allHourStarts);
                rsort($allHourEnds); // Orden inverso para obtener el mayor

                $course->update([
                    'date_start'  => $allDates[0],   // Primera fecha (mínima)
                    'date_end'    => end($allDates), // Última fecha (máxima)
                    'hour_min'  => $allHourStarts[0],  // Menor hora de inicio
                    'hour_max'    => $allHourEnds[0],    // Mayor hora de fin
                    'settings'    => $settings
                ]);
            }

            DB::commit();

            // Ahora, recorre el array de grupos de correo electrónico y envía correos
            foreach ($emailGroups as $clientEmail => $bookingIds) {
                foreach ($bookingIds as $bookingId) {
                    // Obtener el booking asociado a este correo electrónico y booking ID
                    $booking = Booking::with('clientMain')->find($bookingId);

                    // Envía el correo electrónico aquí usando Laravel Mail


                    dispatch(function () use ($school, $booking, $clientEmail) {
                        // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                        try {
                            Mail::to($clientEmail)->send(new BookingInfoUpdateMailer($school,
                                $booking, $booking->clientMain));
                        } catch (\Exception $ex) {
                            \Illuminate\Support\Facades\Log::debug('Admin/COurseController BookingInfoUpdateMailer: ' .
                                $ex->getMessage());
                        }
                    })->afterResponse();
                }
            }

            return $this->sendResponse($course, 'Course updated successfully');
        }  catch (\Exception $e) {
            DB::rollback();
            \Illuminate\Support\Facades\Log::debug('Admin/COurseController Update: ' .
                $e->getMessage());
            \Illuminate\Support\Facades\Log::debug('TRace: ',
                $e->getTrace());
            return $this->sendError('An error occurred while updating the course: ' . $e->getMessage());
        }

    }

    public function getSellStats($id, Request $request): JsonResponse
    {

        try {
            $schoolId = $this->getSchool($request)->id;
            $today = Carbon::now()->format('Y-m-d');

            // Obtener la temporada actual
            $season = Season::whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->first();

            // Utiliza start_date y end_date de la request si están presentes, sino usa las fechas de la temporada
            $startDate = $request->start_date ?? $season->start_date;
            $endDate = $request->end_date ?? $season->end_date;

            // Verificar que el curso existe
            $course = Course::findOrFail($id); // Suponiendo que tienes el ID del curso que deseas editar
            if (!$course) {
                return $this->sendError('Course not found', [], 404);
            }

            // Obtener reservas para el curso específico
            $bookingusersReserved = BookingUser::whereBetween('date', [$startDate, $endDate])
                ->whereHas('booking', function ($query) {
                    $query->where('status', '!=', 2); // Excluir reservas canceladas
                })
                ->where('status', 1) // Solo reservas confirmadas
                ->where('school_id', $schoolId)
                ->where('course_id', $id)
                ->with('booking')
                ->get();

            // Estructura de respuesta
            $result = [];
            $monitorsGrouped = $this->getGroupedMonitors($schoolId, $request->monitor_id, $request->sport_id);

            // Si el curso es de tipo 1 (colectivo), dividir por grupos
            if ($course->course_type === 1) {
                // Obtener todos los grupos del curso
                $courseGroups = $course->courseGroups ?? [];

                foreach ($courseGroups as $group) {
                    $groupResult = $this->processCourseGroup($course, $group, $bookingusersReserved, $monitorsGrouped, $startDate, $endDate);
                    if ($groupResult) {
                        $result[] = $groupResult;
                    }
                }

                $groupedResults = [];

                foreach ($result as $groupResult) {
                    $degreeId = $groupResult['degree_id'];

                    if (!isset($groupedResults[$degreeId])) {
                        // Si no existe en el array agrupado, lo inicializamos
                        $groupedResults[$degreeId] = $groupResult;
                    } else {
                        if($course->is_flexible) {
                            // Si ya existe, sumamos los valores numéricos
                            $groupedResults[$degreeId]['total_places'] += $groupResult['total_places'];
                            $groupedResults[$degreeId]['booked_places'] += $groupResult['booked_places'];
                            $groupedResults[$degreeId]['available_places'] += $groupResult['available_places'];
                            $groupedResults[$degreeId]['cash'] += $groupResult['cash'];
                            $groupedResults[$degreeId]['other'] += $groupResult['other'];
                            $groupedResults[$degreeId]['boukii'] += $groupResult['boukii'];
                            $groupedResults[$degreeId]['boukii_web'] += $groupResult['boukii_web'];
                            $groupedResults[$degreeId]['online'] += $groupResult['online'];
                            $groupedResults[$degreeId]['extras'] += $groupResult['extras'];
                            $groupedResults[$degreeId]['vouchers'] += $groupResult['vouchers'];
                            $groupedResults[$degreeId]['no_paid'] += $groupResult['no_paid'];
                            $groupedResults[$degreeId]['web'] += $groupResult['web'];
                            $groupedResults[$degreeId]['admin'] += $groupResult['admin'];
                            $groupedResults[$degreeId]['total_cost'] += $groupResult['total_cost'];
                        }
                    }
                }

                // Convertimos el array asociativo en un array indexado
                $result = array_values($groupedResults);


            } else {
                // Para cursos tipo 2 (privados), procesamos todo el curso como una unidad
                $courseResult = $this->processCourse($course, $bookingusersReserved, $monitorsGrouped, $startDate, $endDate);
                if ($courseResult) {
                    $result[] = $courseResult;
                }
            }
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage(), $e->getTrace());
            return $this->sendError('Error retrieving course data', 500);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), $e->getTrace());
            return $this->sendError('Error retrieving course data', 500);
        }

        return $this->sendResponse($result, 'Course details sells retrieved successfully');
    }

    // Método para procesar un grupo específico de un curso
    private function processCourseGroup($course, $group, $bookingusersReserved, $monitorsGrouped, $startDate, $endDate): ?array
    {
        // Filtrar booking users solo para este grupo
        $groupBookingUsers = $bookingusersReserved->filter(function($bookingUser) use ($group) {
            return $bookingUser->course_group_id == $group->id;
        });

        if ($groupBookingUsers->isEmpty()) {
            return null;
        }

        // Inicializar estructura de pagos
        $payments = [
            'cash' => 0,
            'other' => 0,
            'boukii' => 0,
            'boukii_web' => 0,
            'online' => 0,
            'vouchers' => 0,
            'no_paid' => 0,
            'web' => 0,
            'admin' => 0,
        ];

        $extrasByGroup = 0;
        $groupTotal = 0;

        // Calcular la disponibilidad para este grupo
        $availability = $this->getGroupAvailability($group, $monitorsGrouped, $startDate, $endDate);

        // Procesar pagos y totales para el grupo
        foreach ($groupBookingUsers->groupBy('booking_id') as $bookingId => $bookingGroupedUsers) {
            $bookingTotal = 0;
            $booking = $bookingGroupedUsers->first()->booking;
            if ($booking->status == 2) continue;

            // Lógica para cursos colectivos por grupo
            $firstDate = $bookingGroupedUsers->first()->date;
            $firstDayBookingUsers = $bookingGroupedUsers->where('date', $firstDate);

            foreach ($firstDayBookingUsers as $bookingUser) {
                $total = $this->calculateTotalPrice($bookingUser);
                $groupTotal += $total['totalPrice'];
                $bookingTotal += $total['totalPrice'];
                $extrasByGroup += $total['extrasPrice'];
            }

            // Sumar los pagos
            $paymentType = $booking->payment_method_id;
            if (!$booking->paid) {
                $payments['no_paid'] += $bookingTotal;
            } else {
                if ($booking->vouchersLogs()->exists()) {
                    $payments['vouchers'] += $bookingTotal;
                } else {
                    switch ($paymentType) {
                        case Booking::ID_CASH:
                            $payments['cash'] += $bookingTotal;
                            break;
                        case Booking::ID_OTHER:
                            $payments['other'] += $bookingTotal;
                            break;
                        case Booking::ID_BOUKIIPAY:
                            if ($booking->source === 'web') {
                                $payments['boukii_web'] += $bookingTotal;
                            } else {
                                $payments['boukii'] += $bookingTotal;
                            }
                            break;
                        case Booking::ID_ONLINE:
                            $payments['online'] += $bookingTotal;
                            break;
                    }
                }
            }
        }

        // Procesar fuente de reservas (web/admin)
        $bookingUsersGrouped = $groupBookingUsers->groupBy('client_id');
        foreach ($bookingUsersGrouped as $clientBookingUsers) {
            $booking = $clientBookingUsers->first()->booking;
            $source = $booking->source;

            $bookingUsersCount = $clientBookingUsers->count();
            $bookingUsersCount = !$course->is_flexible ? $bookingUsersCount / max(1, $course->courseDates->count()) : $bookingUsersCount;

            if (isset($payments[$source])) {
                $payments[$source] += $bookingUsersCount;
            } else {
                $payments[$source] = $bookingUsersCount;
            }
        }

        // Obtener la configuración de moneda
        $currency = $course && $course->currency ? $course->currency : 'CHF';

        // Retornar resultado para este grupo
        return [
            'course_id' => $course->id,
            'group_id' => $group->id,
            'degree_id' => $group->degree->id,
            'group_name' => $group->degree->name,
            'icon' => $course->icon,
            'name' => $course->name,
            'total_places' => round($availability['total_places']),
            'booked_places' => round($availability['total_reservations_places']),
            'available_places' => round($availability['total_available_places']),
            'cash' => round($payments['cash']),
            'other' => round($payments['other']),
            'boukii' => round($payments['boukii']),
            'boukii_web' => round($payments['boukii_web']),
            'online' => round($payments['online']),
            'extras' => round($extrasByGroup),
            'vouchers' => round($payments['vouchers']),
            'no_paid' => round($payments['no_paid']),
            'web' => round($payments['web']),
            'admin' => round($payments['admin']),
            'currency' => $currency,
            'total_cost' => round($groupTotal),
        ];
    }

    function calculateTotalPrice($bookingUser)
    {
        $courseType = $bookingUser->course->course_type; // 1 = Colectivo, 2 = Privado
        $isFlexible = $bookingUser->course->is_flexible; // Si es flexible o no
        $totalPrice = 0;

        if ($courseType == 1) { // Colectivo
            if ($isFlexible) {
                // Si es colectivo flexible
                $totalPrice = $this->calculateFlexibleCollectivePrice($bookingUser);
            } else {
                // Si es colectivo fijo
                $totalPrice = $this->calculateFixedCollectivePrice($bookingUser);
            }
        } elseif ($courseType == 2) { // Privado
            if ($isFlexible) {
                // Si es privado flexible, calcular precio por `price_range`
                $totalPrice = $this->calculatePrivatePrice($bookingUser, $bookingUser->course->price_range);
            } else {
                // Si es privado no flexible, usar un precio fijo
                $totalPrice = $bookingUser->course->price; // Asumimos que el curso tiene un campo `fixed_price`
            }
        } else {
            Log::debug("Invalid course type: $courseType");
            return $totalPrice;
        }

        // Calcular los extras y sumarlos
        $extrasPrice = $this->calculateExtrasPrice($bookingUser);
        $totalPrice += $extrasPrice;

        return ['priceWithoutExtras' => $totalPrice - $extrasPrice,
            'totalPrice' => $totalPrice,
            'extrasPrice' => $extrasPrice];
    }

    function calculateFixedCollectivePrice($bookingUser)
    {
        $course = $bookingUser->course;

        // Agrupar BookingUsers por participante (course_id, participant_id)
        $participants = BookingUser::select(
            'client_id',
            DB::raw('COUNT(*) as total_bookings'), // Contar cuántos BookingUsers tiene cada participante
            DB::raw('SUM(price) as total_price') // Sumar el precio total por participante
        )
            ->where('course_id', $course->id)
            ->where('client_id', $bookingUser->client_id)
            ->groupBy('client_id')
            ->get();


        // Tomar el precio del curso para cada participante
        return count($participants) ? $course->price : 0;
    }

    function calculateFlexibleCollectivePrice($bookingUser)
    {
        $course = $bookingUser->course;
        $dates = BookingUser::where('course_id', $course->id)
            ->where('client_id', $bookingUser->client_id)
            ->pluck('date');

        $totalPrice = 0;

        foreach ($dates as $index => $date) {
            $price = $course->price;

            // Verificar si $course->discounts ya es un array, si no, decodificarlo
            $discounts = is_array($course->discounts) ? $course->discounts : json_decode($course->discounts, true);

            if (!empty($discounts)) {
                foreach ($discounts as $discount) {
                    if ($index + 1 == $discount['day']) {
                        $price -= ($price * $discount['reduccion'] / 100);
                        break;
                    }
                }
            }

            $totalPrice += $price;
        }

        return $totalPrice;
    }

    function calculatePrivatePrice($bookingUser, $priceRange)
    {
        $course = $bookingUser->course;
        $groupId = $bookingUser->group_id;

        // Agrupar BookingUsers por fecha, hora y monitor
        $groupBookings = BookingUser::where('course_id', $course->id)
            ->where('date', $bookingUser->date)
            ->where('hour_start', $bookingUser->hour_start)
            ->where('hour_end', $bookingUser->hour_end)
            ->where('monitor_id', $bookingUser->monitor_id)
            ->where('group_id', $groupId)
            ->where('booking_id', $bookingUser->booking_id)
            ->where('school_id', $bookingUser->school_id)
            ->where('status', 1)
            ->count();

        $duration = Carbon::parse($bookingUser->hour_end)->diffInMinutes(Carbon::parse($bookingUser->hour_start));
        $interval = $this->getIntervalFromDuration($duration); // Función para mapear duración al intervalo (e.g., "1h 30m").

        // Buscar el precio en el price range
        $priceForInterval = collect($priceRange)->firstWhere('intervalo', $interval);
        $pricePerParticipant = $priceForInterval[$groupBookings] ?? null;

        if (!$pricePerParticipant) {
            Log::debug("Precio no definido curso $course->id para $groupBookings participantes en intervalo $interval");
            return 0;
        }

        // Calcular extras
        $extraPrices = $bookingUser->bookingUserExtras->sum(function ($extra) {
            return $extra->price;
        });

        // Calcular precio total
        $totalPrice = $pricePerParticipant + $extraPrices;

        return $totalPrice;
    }
    function getIntervalFromDuration($duration)
    {
        $mapping = [
            15 => "15m",
            30 => "30m",
            45 => "45m",
            60 => "1h",
            75 => "1h 15m",
            90 => "1h 30m",
            120 => "2h",
            180 => "3h",
            240 => "4h",
        ];

        return $mapping[$duration] ?? null;
    }

    function calculateExtrasPrice($bookingUser)
    {
        $extras = $bookingUser->bookingUserExtras; // Relación con BookingUserExtras

        $totalExtrasPrice = 0;
        foreach ($extras as $extra) {
            //  Log::debug('extra price:'. $extra->courseExtra->price);
            $extraPrice = $extra->courseExtra->price ?? 0;
            $totalExtrasPrice += $extraPrice;
        }

        return $totalExtrasPrice;
    }

// Método para procesar un curso completo (usado para cursos privados)
    private function processCourse($course, $bookingusersReserved, $monitorsGrouped, $startDate, $endDate)
    {
        // Inicializar estructura de pagos
        $payments = [
            'cash' => 0,
            'other' => 0,
            'boukii' => 0,
            'boukii_web' => 0,
            'online' => 0,
            'vouchers' => 0,
            'no_paid' => 0,
            'web' => 0,
            'admin' => 0,
        ];

        $extrasByCourse = 0;
        $courseTotal = 0;

        // Calcular la disponibilidad
        $availability = $this->getCourseAvailability($course, $monitorsGrouped, $startDate, $endDate);

        // Procesar pagos y totales
        foreach ($bookingusersReserved->groupBy('booking_id') as $bookingId => $bookingGroupedUsers) {
            $bookingTotal = 0;
            $booking = $bookingGroupedUsers->first()->booking;
            if ($booking->status == 2) continue;

            // Calcular totales para cursos privados
            $groupedBookingUsers = $bookingGroupedUsers
                ->whereBetween('date', [$startDate, $endDate])
                ->groupBy(function ($bookingUser) {
                    return $bookingUser->date . '|' . $bookingUser->hour_start . '|' . $bookingUser->hour_end . '|' .
                        $bookingUser->monitor_id . '|' . $bookingUser->group_id . '|' . $bookingUser->booking_id;
                });

            foreach ($groupedBookingUsers as $groupKey => $bookingUsers) {
                $groupTotal = 0;
                foreach ($bookingUsers as $bookingUser) {
                    $total = $this->calculateTotalPrice($bookingUser);
                    $groupTotal += $total['totalPrice'];
                    $extrasByCourse += $total['extrasPrice'];
                }
                $courseTotal += $groupTotal;
                $bookingTotal += $groupTotal;
            }

            // Sumar los pagos
            $paymentType = $booking->payment_method_id;
            if (!$booking->paid) {
                $payments['no_paid'] += $bookingTotal;
            } else {
                if ($booking->vouchersLogs()->exists()) {
                    $payments['vouchers'] += $bookingTotal;
                } else {
                    switch ($paymentType) {
                        case Booking::ID_CASH:
                            $payments['cash'] += $bookingTotal;
                            break;
                        case Booking::ID_OTHER:
                            $payments['other'] += $bookingTotal;
                            break;
                        case Booking::ID_BOUKIIPAY:
                            if ($booking->source === 'web') {
                                $payments['boukii_web'] += $bookingTotal;
                            } else {
                                $payments['boukii'] += $bookingTotal;
                            }
                            break;
                        case Booking::ID_ONLINE:
                            $payments['online'] += $bookingTotal;
                            break;
                    }
                }
            }
        }

        // Procesar fuente de reservas (web/admin)
        $bookingUsersGrouped = $course->bookingUsersActive->groupBy('client_id');
        foreach ($bookingUsersGrouped as $clientBookingUsers) {
            $booking = $clientBookingUsers->first()->booking;
            $source = $booking->source;

            $bookingUsersCount = $clientBookingUsers->count();
            $bookingUsersCount = !$course->is_flexible ? $bookingUsersCount / max(1, $course->courseDates->count()) : $bookingUsersCount;

            if (isset($payments[$source])) {
                $payments[$source] += $bookingUsersCount;
            } else {
                $payments[$source] = $bookingUsersCount;
            }
        }

        // Obtener la configuración de moneda
        $currency = $course && $course->currency ? $course->currency : 'CHF';

        // Retornar resultado para este curso
        return [
            'course_id' => $course->id,
            'icon' => $course->icon,
            'name' => $course->name,
            'total_places' => $course->course_type == 1 ? round($availability['total_places']) : 'NDF',
            'booked_places' => $course->course_type == 1 ?
                round($availability['total_reservations_places']) : round($payments['web']) + round($payments['admin']),
            'available_places' => $course->course_type == 1 ?
                round($availability['total_available_places']) : 'NDF',
            'cash' => round($payments['cash']),
            'other' => round($payments['other']),
            'boukii' => round($payments['boukii']),
            'boukii_web' => round($payments['boukii_web']),
            'online' => round($payments['online']),
            'extras' => round($extrasByCourse),
            'vouchers' => round($payments['vouchers']),
            'no_paid' => round($payments['no_paid']),
            'web' => round($payments['web']),
            'admin' => round($payments['admin']),
            'currency' => $currency,
            'total_cost' => round($courseTotal),
        ];
    }

// Método para calcular la disponibilidad de un grupo específico
    private function getGroupAvailability($group, $monitorsGrouped, $startDate, $endDate)
    {
        // Implementar lógica para calcular la disponibilidad específica del grupo
        // Similar a getCourseAvailability pero enfocado en un solo grupo
        // Puedes adaptar esta función según tus necesidades específicas

        $totalPlaces = $group->max_students ?? 0;
        $totalReservationsPlaces = $group->bookingUsers()
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        $totalAvailablePlaces = max(0, $totalPlaces - $totalReservationsPlaces);

        return [
            'total_places' => $totalPlaces,
            'total_reservations_places' => $totalReservationsPlaces,
            'total_available_places' => $totalAvailablePlaces
        ];
    }

}
