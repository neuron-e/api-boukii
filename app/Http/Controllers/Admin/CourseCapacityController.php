<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\Course;
use App\Models\CourseSubgroup;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MEJORA CRÍTICA: Controller específico para verificación de capacidad en tiempo real
 * Endpoints para validar disponibilidad de cursos sin crear reservas
 */
class CourseCapacityController extends AppBaseController
{
    /**
     * ENDPOINT: Resumen ligero de disponibilidad filtrado por deporte/nivel
     * pensado para precarga y analítica en el panel admin.
     */
    public function previewAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport_id' => 'required|integer|exists:sports,id',
            'degree_id' => 'nullable|integer|exists:degrees,id',
            'school_id' => 'nullable|integer|exists:schools,id',
            'course_type' => 'nullable|integer|in:1,2',
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:200'
        ]);

        $user = $request->user();
        $schoolId = $validated['school_id'] ?? optional($user?->schools()->first())->id;
        $sportId = $validated['sport_id'];
        $degreeId = $validated['degree_id'] ?? null;
        $dateFrom = Carbon::createFromFormat('Y-m-d', $validated['date_from'])->startOfDay();
        $dateTo = Carbon::createFromFormat('Y-m-d', $validated['date_to'])->endOfDay();
        $limit = $validated['limit'] ?? 40;

        $query = Course::query()
            ->select([
                'id',
                'name',
                'course_type',
                'sport_id',
                'school_id',
                'duration',
                'price',
                'currency'
            ])
            ->with(['courseDates' => function ($q) use ($dateFrom, $dateTo, $degreeId) {
                $q->select(['id', 'course_id', 'date', 'hour_start', 'hour_end'])
                    ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                    ->orderBy('date')
                    ->with(['courseGroups' => function ($groupQuery) use ($degreeId) {
                        $groupQuery->select(['id', 'course_date_id', 'degree_id', 'name'])
                            ->when($degreeId, function ($inner) use ($degreeId) {
                                $inner->where('degree_id', $degreeId);
                            });
                    }]);
            }])
            ->where('sport_id', $sportId)
            ->where('active', 1)
            ->whereNull('archived_at');

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if (!empty($validated['course_type'])) {
            $query->where('course_type', $validated['course_type']);
        }

        $query->whereHas('courseDates', function ($datesQuery) use ($dateFrom, $dateTo, $degreeId) {
            $datesQuery->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

            if ($degreeId) {
                $datesQuery->whereHas('courseGroups', function ($groupQuery) use ($degreeId) {
                    $groupQuery->where('degree_id', $degreeId);
                });
            }
        });

        $courses = $query->limit($limit)->get()->map(function (Course $course) use ($degreeId) {
            $dates = $course->courseDates
                ->filter(function ($date) use ($degreeId) {
                    if ($degreeId) {
                        return $date->courseGroups->contains('degree_id', $degreeId);
                    }
                    return true;
                })
                ->map(function ($date) {
                    return [
                        'id' => $date->id,
                        'date' => $date->date ? $date->date->format('Y-m-d') : null,
                        'hour_start' => $date->hour_start,
                        'hour_end' => $date->hour_end,
                    ];
                })
                ->values();

            return [
                'id' => $course->id,
                'name' => $course->name,
                'course_type' => $course->course_type,
                'sport_id' => $course->sport_id,
                'school_id' => $course->school_id,
                'price' => $course->price,
                'currency' => $course->currency,
                'duration' => $course->duration,
                'dates' => $dates,
            ];
        })->values();

        $totalDates = $courses->reduce(function ($carry, $course) {
            return $carry + count($course['dates'] ?? []);
        }, 0);

        $summary = [
            'from' => $dateFrom->toDateString(),
            'to' => $dateTo->toDateString(),
            'sport_id' => $sportId,
            'degree_id' => $degreeId,
            'total_courses' => $courses->count(),
            'total_dates' => $totalDates,
        ];

        return $this->sendResponse([
            'courses' => $courses->toArray(),
            'summary' => $summary,
        ], 'Availability preview generated successfully');
    }

    /**
     * ENDPOINT: Verificar capacidad de múltiples subgrupos en tiempo real
     * ACTUALIZADO: Soporte para intervalos vía parámetro 'date'
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkCapacity(Request $request): JsonResponse
    {
        $request->validate([
            'subgroup_ids' => 'required|array',
            'subgroup_ids.*' => 'integer|exists:course_subgroups,id',
            'needed_participants' => 'required|integer|min:1',
            'date' => 'nullable|date_format:Y-m-d' // NUEVO: parámetro opcional para intervalos
        ]);

        $subgroupIds = $request->input('subgroup_ids');
        $neededParticipants = $request->input('needed_participants');
        $date = $request->input('date'); // NUEVO

        $capacityData = [];

        foreach ($subgroupIds as $subgroupId) {
            $subgroup = CourseSubgroup::find($subgroupId);

            if (!$subgroup) {
                continue;
            }

            // NUEVO: Si se proporciona fecha, usar métodos con soporte de intervalos
            if ($date) {
                $maxParticipants = $subgroup->getMaxParticipantsForDate($date);
                $availableSlots = $subgroup->getAvailableSlotsForDate($date);
                $hasCapacity = $subgroup->hasAvailabilityForDate($date, $neededParticipants);
            } else {
                // Backward compatible: usar métodos antiguos si no hay fecha
                $maxParticipants = $subgroup->max_participants;
                $availableSlots = $subgroup->getAvailableSlotsCount();
                $hasCapacity = $subgroup->hasAvailableSlots() && $availableSlots >= $neededParticipants;
            }

            $capacityData[] = [
                'id' => $subgroup->id,
                'max_participants' => $maxParticipants ?? 999,
                'current_bookings' => $subgroup->bookingUsers()->count(),
                'available_slots' => $availableSlots,
                'has_capacity' => $hasCapacity,
                'is_unlimited' => !$maxParticipants || $maxParticipants > 100,
                'date' => $date, // NUEVO: incluir fecha en respuesta
                'updated_at' => now()->toISOString()
            ];
        }

        return $this->sendResponse($capacityData, 'Capacidad verificada correctamente');
    }

    /**
     * ENDPOINT: Verificar disponibilidad específica para una fecha y nivel (OPTIMIZADO)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAvailabilityByDate(Request $request): JsonResponse
    {
        $request->validate([
            'course_date_id' => 'required|integer|exists:course_dates,id',
            'degree_id' => 'required|integer|exists:degrees,id',
            'needed_participants' => 'required|integer|min:1'
        ]);

        $courseDateId = $request->input('course_date_id');
        $degreeId = $request->input('degree_id');
        $neededParticipants = $request->input('needed_participants');

        // MEJORA CRÍTICA: Usar método optimizado con cache
        $availableSubgroups = CourseSubgroup::getAvailableSubgroupsWithCapacity(
            $courseDateId,
            $degreeId,
            $neededParticipants
        );

        $totalAvailableSlots = $availableSubgroups
            ->where('available_slots', '>=', $neededParticipants)
            ->sum('available_slots');

        return $this->sendResponse([
            'subgroups' => $availableSubgroups->toArray(),
            'total_available_slots' => $totalAvailableSlots,
            'has_availability' => $totalAvailableSlots >= $neededParticipants,
            'course_date_id' => $courseDateId,
            'degree_id' => $degreeId,
            'checked_at' => now()->toISOString(),
            'cached' => true // Indicar que los datos están optimizados
        ], 'Disponibilidad verificada correctamente');
    }

    /**
     * ENDPOINT: Validar si una reserva específica es posible antes de crearla
     * ACTUALIZADO: Soporte para intervalos vía campo 'date' en cada cart item
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateBookingCapacity(Request $request): JsonResponse
    {
        $request->validate([
            'client_main_id' => 'required|integer|exists:clients,id',
            'cart' => 'required|array',
            'cart.*.client_id' => 'required|integer|exists:clients,id',
            'cart.*.course_date_id' => 'required|integer|exists:course_dates,id',
            'cart.*.degree_id' => 'required|integer|exists:degrees,id',
            'cart.*.course_type' => 'required|integer|in:1,2',
            'cart.*.date' => 'nullable|date_format:Y-m-d' // NUEVO: fecha opcional para intervalos
        ]);

        $cart = $request->input('cart');
        $validationResults = [];

        foreach ($cart as $cartItem) {
            // Solo validar cursos colectivos
            if ($cartItem['course_type'] != 1) {
                $validationResults[] = [
                    'cart_item' => $cartItem,
                    'is_valid' => true,
                    'reason' => 'Curso privado - sin límite de capacidad'
                ];
                continue;
            }

            $subgroups = CourseSubgroup::where('course_date_id', $cartItem['course_date_id'])
                ->where('degree_id', $cartItem['degree_id'])
                ->get();

            $hasAvailability = false;
            $availableSubgroups = [];
            $date = $cartItem['date'] ?? null; // NUEVO: obtener fecha del cart item

            foreach ($subgroups as $subgroup) {
                // NUEVO: Si hay fecha, usar métodos con soporte de intervalos
                if ($date) {
                    $hasSlots = $subgroup->hasAvailabilityForDate($date, 1);
                    $availableSlots = $subgroup->getAvailableSlotsForDate($date);
                } else {
                    // Backward compatible
                    $hasSlots = $subgroup->hasAvailableSlots();
                    $availableSlots = $subgroup->getAvailableSlotsCount();
                }

                if ($hasSlots) {
                    $hasAvailability = true;
                    $availableSubgroups[] = [
                        'id' => $subgroup->id,
                        'available_slots' => $availableSlots,
                        'date' => $date // NUEVO
                    ];
                }
            }

            $validationResults[] = [
                'cart_item' => $cartItem,
                'is_valid' => $hasAvailability,
                'reason' => $hasAvailability ?
                    'Plaza disponible' :
                    'No hay plazas disponibles en este nivel y fecha',
                'available_subgroups' => $availableSubgroups
            ];
        }

        $allValid = collect($validationResults)->every('is_valid');

        return $this->sendResponse([
            'is_booking_possible' => $allValid,
            'validation_results' => $validationResults,
            'validated_at' => now()->toISOString()
        ], $allValid ? 'Reserva posible' : 'Reserva no posible');
    }
}
