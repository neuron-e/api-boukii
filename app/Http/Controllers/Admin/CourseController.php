<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CourseDetailsExport;
use App\Http\Controllers\AppBaseController;
use App\Mail\BookingInfoUpdateMailer;
use App\Models\Booking;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\Monitor;
use App\Models\MonitorsSchool;
use App\Repositories\CourseRepository;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Response;
use Validator;
use App\Traits\Utils;

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
            searchArray: $request->except(['skip', 'limit', 'search', 'exclude', 'active', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
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
            $weekDays = $settings ? $settings['weekDays'] : null;

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
                foreach ($courseData['course_dates'] as $dateData) {
                    // Validar o calcular hour_end
                    if (empty($dateData['hour_end']) && !empty($dateData['duration'])) {
                        $dateData['hour_end'] = $this->calculateHourEnd($dateData['hour_start'], $dateData['duration']);
                    } elseif (empty($dateData['hour_end'])) {
                        DB::rollback();
                        return $this->sendError('Either hour_end or duration must be provided.');
                    }

                    if ($weekDays) {
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
            sort($allDates);
            // Obtener las duraciones recorriendo las instancias de los modelos
            $allDurations = $course->courseDates->map(function ($courseDate) {
                return $courseDate->duration; // Accedemos directamente al atributo calculado
            })->toArray();

            if (!empty($allDates)) {
                sort($allDates);
                $course->update([
                    'date_start' => $allDates[0],
                    'date_end' => end($allDates),
                    'settings' => $settings
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

    private function generateCourseDates(array $courseDates, array $weekDays)
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

            while ($startDate->lte($endDate)) {
                $dayOfWeek = $startDate->dayOfWeekIso; // 1 (Lunes) - 7 (Domingo)

                foreach ($weekDays as $day => $isActive) {
                    if ($isActive && $weekDaysMap[$day] == $dayOfWeek) {
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
            $course->update($courseData);

            $settings = $courseData['settings'] ?? null;

            if (is_string($settings)) {
                $settings = json_decode($settings, true);
            }

            if (!is_array($settings)) {
                $settings = []; // Si no es un array válido, inicializamos uno vacío
            }
            $weekDays = $settings ? $settings['weekDays'] : null;
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

                // Fechas a eliminar (existen en la BD pero no en las nuevas)
                $datesToDelete = array_diff($existingDates, $newDatesList);
                $course->courseDates()->whereIn('date', $datesToDelete)->delete();

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
                                $bookingUsers = $date->bookingUsers()->where('status', 1)->whereHas('booking', function ($query) {
                                    $query->where('status', '!=', 2);
                                })->get();

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
                                foreach ($groupData['course_subgroups'] as $subgroupData) {
                                    // Preparar los datos de subgroup
                                    $subgroupData['course_id'] = $course->id;
                                    $subgroupData['course_date_id'] = $date->id;

                                    // Verifica si existe 'id' antes de usarlo
                                    $subgroupId = isset($subgroupData['id']) ? $subgroupData['id'] : null;
                                    if ($subgroupId) {
                                        $group->courseSubgroups()->updateOrCreate(['id' => $subgroupId], $subgroupData);
                                    } else {
                                        $group->courseSubgroups()->create($subgroupData);
                                    }
                                }
                                // Considera si necesitas borrar subgrupos aquí
                            }
                        }
                        $date->courseGroups()->whereNotIn('id', $updatedCourseGroups)->delete();
                    }
                }
                $course->courseDates()->whereNotIn('id', $updatedCourseDates)->delete();
            }

            $allDates = array_column($course->courseDates->toArray(), 'date');
            sort($allDates);
            // Obtener las duraciones recorriendo las instancias de los modelos
            $allDurations = $course->courseDates->map(function ($courseDate) {
                return $courseDate->duration; // Accedemos directamente al atributo calculado
            })->toArray();

            if (!empty($allDates)) {
                sort($allDates);
                $course->update([
                    'date_start' => $allDates[0],
                    'date_end' => end($allDates),
                    'settings' => $settings
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
            return $this->sendError('An error occurred while updating the course: ' . $e->getMessage());
        }

    }

}
