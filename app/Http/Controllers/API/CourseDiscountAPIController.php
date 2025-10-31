<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\CourseDiscount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador API para descuentos globales de curso
 *
 * Gestiona los descuentos aplicables a nivel de curso (independiente del intervalo).
 */
class CourseDiscountAPIController extends AppBaseController
{
    /**
     * Listar todos los descuentos de un curso
     *
     * @param int $courseId
     * @return JsonResponse
     */
    public function index(int $courseId): JsonResponse
    {
        $discounts = CourseDiscount::where('course_id', $courseId)
            ->orderBy('priority', 'desc')
            ->orderBy('min_days')
            ->get();

        return $this->sendResponse($discounts->toArray(), 'Descuentos recuperados exitosamente');
    }

    /**
     * Crear un nuevo descuento para el curso
     *
     * @param Request $request
     * @param int $courseId
     * @return JsonResponse
     */
    public function store(Request $request, int $courseId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'min_days' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'priority' => 'nullable|integer',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();
        $data['course_id'] = $courseId;

        // Validación adicional para porcentaje
        if ($data['discount_type'] === 'percentage' && $data['discount_value'] > 100) {
            return $this->sendError('El porcentaje de descuento no puede ser mayor a 100', [], 422);
        }

        $discount = CourseDiscount::create($data);

        return $this->sendResponse(
            $discount->toArray(),
            'Descuento creado exitosamente',
            201
        );
    }

    /**
     * Obtener un descuento específico
     *
     * @param int $courseId
     * @param int $discountId
     * @return JsonResponse
     */
    public function show(int $courseId, int $discountId): JsonResponse
    {
        $discount = CourseDiscount::where('course_id', $courseId)
            ->find($discountId);

        if (!$discount) {
            return $this->sendError('Descuento no encontrado', [], 404);
        }

        return $this->sendResponse($discount->toArray(), 'Descuento recuperado exitosamente');
    }

    /**
     * Actualizar un descuento existente
     *
     * @param Request $request
     * @param int $courseId
     * @param int $discountId
     * @return JsonResponse
     */
    public function update(Request $request, int $courseId, int $discountId): JsonResponse
    {
        $discount = CourseDiscount::where('course_id', $courseId)
            ->find($discountId);

        if (!$discount) {
            return $this->sendError('Descuento no encontrado', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'discount_type' => 'sometimes|required|in:percentage,fixed_amount',
            'discount_value' => 'sometimes|required|numeric|min:0',
            'min_days' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'priority' => 'nullable|integer',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        // Validación adicional para porcentaje
        $discountType = $data['discount_type'] ?? $discount->discount_type;
        $discountValue = $data['discount_value'] ?? $discount->discount_value;

        if ($discountType === 'percentage' && $discountValue > 100) {
            return $this->sendError('El porcentaje de descuento no puede ser mayor a 100', [], 422);
        }

        $discount->update($data);

        return $this->sendResponse($discount->toArray(), 'Descuento actualizado exitosamente');
    }

    /**
     * Eliminar un descuento
     *
     * @param int $courseId
     * @param int $discountId
     * @return JsonResponse
     */
    public function destroy(int $courseId, int $discountId): JsonResponse
    {
        $discount = CourseDiscount::where('course_id', $courseId)
            ->find($discountId);

        if (!$discount) {
            return $this->sendError('Descuento no encontrado', [], 404);
        }

        $discount->delete();

        return $this->sendResponse([], 'Descuento eliminado exitosamente');
    }

    /**
     * Obtener descuentos activos disponibles para mostrar en UI
     *
     * @param int $courseId
     * @return JsonResponse
     */
    public function active(int $courseId): JsonResponse
    {
        $discounts = CourseDiscount::where('course_id', $courseId)
            ->active()
            ->orderBy('min_days')
            ->get()
            ->map(function ($discount) {
                return [
                    'id' => $discount->id,
                    'name' => $discount->name,
                    'description' => $discount->description,
                    'discount_type' => $discount->discount_type,
                    'discount_value' => $discount->discount_value,
                    'min_days' => $discount->min_days,
                    'valid_from' => optional($discount->valid_from)->format('Y-m-d'),
                    'valid_to' => optional($discount->valid_to)->format('Y-m-d'),
                    'display_text' => $this->formatDiscountDisplay($discount),
                ];
            });

        return $this->sendResponse(
            $discounts->toArray(),
            'Descuentos activos recuperados exitosamente'
        );
    }

    /**
     * Formatea un descuento para mostrar en UI
     *
     * @param CourseDiscount $discount
     * @return string
     */
    private function formatDiscountDisplay(CourseDiscount $discount): string
    {
        $valueText = $discount->discount_type === 'percentage'
            ? "{$discount->discount_value}%"
            : "CHF {$discount->discount_value}";

        $conditionsText = [];

        if ($discount->min_days) {
            $conditionsText[] = "al reservar {$discount->min_days}+ días";
        }

        $conditions = !empty($conditionsText) ? ' ' . implode(' y ', $conditionsText) : '';

        return "¡Descuento {$valueText}{$conditions}!";
    }
}
