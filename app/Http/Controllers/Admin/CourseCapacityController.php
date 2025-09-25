<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\CourseSubgroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MEJORA CRÍTICA: Controller específico para verificación de capacidad en tiempo real
 * Endpoints para validar disponibilidad de cursos sin crear reservas
 */
class CourseCapacityController extends AppBaseController
{
    /**
     * ENDPOINT: Verificar capacidad de múltiples subgrupos en tiempo real
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkCapacity(Request $request): JsonResponse
    {
        $request->validate([
            'subgroup_ids' => 'required|array',
            'subgroup_ids.*' => 'integer|exists:course_subgroups,id',
            'needed_participants' => 'required|integer|min:1'
        ]);

        $subgroupIds = $request->input('subgroup_ids');
        $neededParticipants = $request->input('needed_participants');

        $capacityData = [];

        foreach ($subgroupIds as $subgroupId) {
            $subgroup = CourseSubgroup::find($subgroupId);

            if (!$subgroup) {
                continue;
            }

            $capacityData[] = [
                'id' => $subgroup->id,
                'max_participants' => $subgroup->max_participants ?? 999,
                'current_bookings' => $subgroup->bookingUsers()->count(),
                'available_slots' => $subgroup->getAvailableSlotsCount(),
                'has_capacity' => $subgroup->hasAvailableSlots() &&
                                $subgroup->getAvailableSlotsCount() >= $neededParticipants,
                'is_unlimited' => !$subgroup->max_participants || $subgroup->max_participants > 100,
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
            'cart.*.course_type' => 'required|integer|in:1,2'
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

            foreach ($subgroups as $subgroup) {
                if ($subgroup->hasAvailableSlots()) {
                    $hasAvailability = true;
                    $availableSubgroups[] = [
                        'id' => $subgroup->id,
                        'available_slots' => $subgroup->getAvailableSlotsCount()
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