<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\CourseDiscount;
use App\Http\Requests\API\CreateCourseDiscountAPIRequest;
use App\Http\Requests\API\UpdateCourseDiscountAPIRequest;
use App\Http\Resources\CourseDiscountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

        return $this->sendResponse(
            CourseDiscountResource::collection($discounts)->toArray(request()),
            'Descuentos recuperados exitosamente'
        );
    }

    /**
     * Crear un nuevo descuento para el curso
     *
     * @param CreateCourseDiscountAPIRequest $request
     * @param int $courseId
     * @return JsonResponse
     */
    public function store(CreateCourseDiscountAPIRequest $request, int $courseId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $data['course_id'] = $courseId;

            $discount = CourseDiscount::create($data);

            DB::commit();

            return $this->sendResponse(
                (new CourseDiscountResource($discount))->toArray(request()),
                'Descuento creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear descuento: ' . $e->getMessage(), [], 500);
        }
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

        return $this->sendResponse(
            (new CourseDiscountResource($discount))->toArray(request()),
            'Descuento recuperado exitosamente'
        );
    }

    /**
     * Actualizar un descuento existente
     *
     * @param UpdateCourseDiscountAPIRequest $request
     * @param int $courseId
     * @param int $discountId
     * @return JsonResponse
     */
    public function update(UpdateCourseDiscountAPIRequest $request, int $courseId, int $discountId): JsonResponse
    {
        $discount = CourseDiscount::where('course_id', $courseId)
            ->find($discountId);

        if (!$discount) {
            return $this->sendError('Descuento no encontrado', [], 404);
        }

        try {
            DB::beginTransaction();

            $data = $request->validated();
            $discount->update($data);

            DB::commit();

            return $this->sendResponse(
                (new CourseDiscountResource($discount->fresh()))->toArray(request()),
                'Descuento actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar descuento: ' . $e->getMessage(), [], 500);
        }
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

        try {
            DB::beginTransaction();

            $discount->delete();

            DB::commit();

            return $this->sendResponse([], 'Descuento eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar descuento: ' . $e->getMessage(), [], 500);
        }
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
            ->get();

        return $this->sendResponse(
            CourseDiscountResource::collection($discounts)->toArray(request()),
            'Descuentos activos recuperados exitosamente'
        );
    }
}
