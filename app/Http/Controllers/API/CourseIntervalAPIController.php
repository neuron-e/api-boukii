<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CourseInterval;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CourseIntervalAPIController extends Controller
{
    /**
     * Display a listing of intervals for a specific course.
     */
    public function index(Request $request)
    {
        $courseId = $request->query('course_id');

        if (!$courseId) {
            return response()->json([
                'success' => false,
                'message' => 'course_id is required',
            ], 400);
        }

        $intervals = CourseInterval::where('course_id', $courseId)
            ->with([
                'courseDates' => function ($query) {
                    $query->orderBy('date');
                },
                'discounts' => function ($query) {
                    $query->active()->orderBy('min_days');
                },
            ])
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $intervals,
        ]);
    }

    /**
     * Store a newly created interval in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'display_order' => 'nullable|integer',
            'config_mode' => 'required|in:inherit,custom',
            'date_generation_method' => 'nullable|in:consecutive,weekly,manual,first_day',
            'consecutive_days_count' => 'nullable|integer|min:1',
            'weekly_pattern' => 'nullable|array',
            'booking_mode' => 'required|in:flexible,package',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Si no se especifica display_order, poner al final
            $data = $request->all();
            if (!isset($data['display_order'])) {
                $maxOrder = CourseInterval::where('course_id', $data['course_id'])->max('display_order');
                $data['display_order'] = ($maxOrder ?? -1) + 1;
            }

            $interval = CourseInterval::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Intervalo creado exitosamente',
                'data' => $interval,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear intervalo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified interval.
     */
    public function show(string $id)
    {
        $interval = CourseInterval::with([
            'course',
            'courseDates',
            'discounts' => function ($query) {
                $query->active()->orderBy('min_days');
            },
        ])->find($id);

        if (!$interval) {
            return response()->json([
                'success' => false,
                'message' => 'Intervalo no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $interval,
        ]);
    }

    /**
     * Update the specified interval in storage.
     */
    public function update(Request $request, string $id)
    {
        $interval = CourseInterval::find($id);

        if (!$interval) {
            return response()->json([
                'success' => false,
                'message' => 'Intervalo no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'display_order' => 'nullable|integer',
            'config_mode' => 'sometimes|required|in:inherit,custom',
            'date_generation_method' => 'nullable|in:consecutive,weekly,manual,first_day',
            'consecutive_days_count' => 'nullable|integer|min:1',
            'weekly_pattern' => 'nullable|array',
            'booking_mode' => 'sometimes|required|in:flexible,package',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $interval->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Intervalo actualizado exitosamente',
                'data' => $interval->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar intervalo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified interval from storage.
     */
    public function destroy(string $id)
    {
        $interval = CourseInterval::find($id);

        if (!$interval) {
            return response()->json([
                'success' => false,
                'message' => 'Intervalo no encontrado',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Desvincular las fechas de este intervalo (opcional, depende de la estrategia)
            $interval->courseDates()->update(['course_interval_id' => null]);

            $interval->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Intervalo eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar intervalo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder intervals for a course.
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'intervals' => 'required|array',
            'intervals.*.id' => 'required|exists:course_intervals,id',
            'intervals.*.display_order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($request->intervals as $intervalData) {
                CourseInterval::where('id', $intervalData['id'])
                    ->where('course_id', $request->course_id)
                    ->update(['display_order' => $intervalData['display_order']]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden de intervalos actualizado exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al reordenar intervalos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate course dates for an interval based on its configuration.
     */
    public function generateDates(Request $request, string $id)
    {
        $interval = CourseInterval::find($id);

        if (!$interval) {
            return response()->json([
                'success' => false,
                'message' => 'Intervalo no encontrado',
            ], 404);
        }

        // Only generate dates for custom config intervals
        if ($interval->config_mode !== 'custom' || !$interval->date_generation_method) {
            return response()->json([
                'success' => false,
                'message' => 'El intervalo debe tener configuración personalizada y un método de generación',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Get course to retrieve default hour and duration
            $course = $interval->course;

            // Delete existing dates for this interval
            \App\Models\CourseDate::where('course_interval_id', $interval->id)->delete();

            $generatedDates = [];

            switch ($interval->date_generation_method) {
                case 'first_day':
                    $generatedDates = $this->generateFirstDayDates($interval, $course);
                    break;

                case 'consecutive':
                    $generatedDates = $this->generateConsecutiveDates($interval, $course);
                    break;

                case 'weekly':
                    $generatedDates = $this->generateWeeklyDates($interval, $course);
                    break;

                case 'manual':
                    // No auto-generation for manual mode
                    break;
            }

            // Create the course dates
            foreach ($generatedDates as $dateData) {
                \App\Models\CourseDate::create($dateData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Fechas generadas exitosamente',
                'data' => [
                    'interval_id' => $interval->id,
                    'dates_generated' => count($generatedDates),
                    'dates' => $generatedDates
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al generar fechas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate only the first day of the interval.
     */
    private function generateFirstDayDates($interval, $course)
    {
        return [[
            'course_id' => $interval->course_id,
            'course_interval_id' => $interval->id,
            'date' => $interval->start_date,
            'hour_start' => $course->hour_min ?? '09:00',
            'hour_end' => $this->calculateEndTime($course->hour_min ?? '09:00', $course->duration ?? '1h'),
            'interval_id' => $interval->id, // Legacy field
            'order' => 1,
            'active' => 1
        ]];
    }

    /**
     * Generate consecutive dates.
     */
    private function generateConsecutiveDates($interval, $course)
    {
        $dates = [];
        $count = $interval->consecutive_days_count ?? 5;
        $startDate = new \DateTime($interval->start_date);
        $endDate = new \DateTime($interval->end_date);

        for ($i = 0; $i < $count; $i++) {
            $currentDate = clone $startDate;
            $currentDate->modify("+{$i} days");

            if ($currentDate > $endDate) {
                break;
            }

            $dates[] = [
                'course_id' => $interval->course_id,
                'course_interval_id' => $interval->id,
                'date' => $currentDate->format('Y-m-d'),
                'hour_start' => $course->hour_min ?? '09:00',
                'hour_end' => $this->calculateEndTime($course->hour_min ?? '09:00', $course->duration ?? '1h'),
                'interval_id' => $interval->id, // Legacy field
                'order' => $i + 1,
                'active' => 1
            ];
        }

        return $dates;
    }

    /**
     * Generate weekly pattern dates.
     */
    private function generateWeeklyDates($interval, $course)
    {
        $dates = [];
        $pattern = $interval->weekly_pattern ?? [];

        if (empty($pattern) || !is_array($pattern)) {
            return [];
        }

        // Convert pattern to day numbers (0=Sunday, 1=Monday, etc.)
        $selectedDays = [];
        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6
        ];

        foreach ($pattern as $day => $selected) {
            if ($selected && isset($dayMap[$day])) {
                $selectedDays[] = $dayMap[$day];
            }
        }

        if (empty($selectedDays)) {
            return [];
        }

        $currentDate = new \DateTime($interval->start_date);
        $endDate = new \DateTime($interval->end_date);
        $order = 1;

        while ($currentDate <= $endDate) {
            $dayOfWeek = (int) $currentDate->format('w'); // 0=Sunday

            if (in_array($dayOfWeek, $selectedDays)) {
                $dates[] = [
                    'course_id' => $interval->course_id,
                    'course_interval_id' => $interval->id,
                    'date' => $currentDate->format('Y-m-d'),
                    'hour_start' => $course->hour_min ?? '09:00',
                    'hour_end' => $this->calculateEndTime($course->hour_min ?? '09:00', $course->duration ?? '1h'),
                    'interval_id' => $interval->id, // Legacy field
                    'order' => $order++,
                    'active' => 1
                ];
            }

            $currentDate->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Calculate end time based on start time and duration.
     */
    private function calculateEndTime($startTime, $duration)
    {
        // Parse duration (format: "1h 30min" or "1h" or "30min")
        preg_match('/(\d+)h/', $duration, $hours);
        preg_match('/(\d+)min/', $duration, $minutes);

        $totalMinutes = (isset($hours[1]) ? intval($hours[1]) * 60 : 0) + (isset($minutes[1]) ? intval($minutes[1]) : 0);

        if ($totalMinutes === 0) {
            $totalMinutes = 60; // Default 1 hour
        }

        $start = new \DateTime($startTime);
        $start->modify("+{$totalMinutes} minutes");

        return $start->format('H:i');
    }
}
