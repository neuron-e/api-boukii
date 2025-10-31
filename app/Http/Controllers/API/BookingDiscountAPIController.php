<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\CourseDiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador API para gestión de descuentos en reservas
 */
class BookingDiscountAPIController extends Controller
{
    protected CourseDiscountService $discountService;

    public function __construct(CourseDiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    /**
     * Calcula el mejor descuento aplicable para una reserva
     *
     * POST /api/bookings/calculate-discount
     *
     * Body:
     * {
     *   "course_id": 1,
     *   "interval_id": 2,
     *   "num_days": 5,
     *   "base_price": 500.00,
     *   "promo_code": "SUMMER2024",
     *   "num_participants": 2,
     *   "booking_date": "2024-07-15"
     * }
     */
    public function calculateDiscount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer|exists:courses,id',
            'interval_id' => 'required|integer|exists:course_intervals,id',
            'num_days' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'promo_code' => 'nullable|string',
            'num_participants' => 'nullable|integer|min:1',
            'booking_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $bestDiscount = $this->discountService->getBestDiscount(
                $request->input('course_id'),
                $request->input('interval_id'),
                $request->input('num_days'),
                $request->input('base_price'),
                $request->input('promo_code'),
                $request->input('num_participants'),
                $request->input('booking_date')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'discount_type' => $bestDiscount['type'],
                    'discount_amount' => $bestDiscount['amount'],
                    'final_price' => $bestDiscount['final_price'],
                    'base_price' => $request->input('base_price'),
                    'discount_details' => $bestDiscount['discount'] ? [
                        'id' => $bestDiscount['discount']->id ?? null,
                        'name' => $bestDiscount['discount']->name ?? $bestDiscount['discount']->code ?? 'Descuento',
                        'description' => $bestDiscount['discount']->description ?? null,
                        'discount_value' => $bestDiscount['discount']->discount_value ?? 0,
                        'discount_type' => $bestDiscount['discount']->discount_type ?? 'percentage',
                    ] : null,
                    'alternative_discount' => $bestDiscount['alternative'] ? [
                        'discount_type' => $bestDiscount['alternative']['type'],
                        'discount_amount' => $bestDiscount['alternative']['amount'],
                        'final_price' => $bestDiscount['alternative']['final_price'],
                    ] : null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular descuento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calcula descuentos para múltiples intervalos
     *
     * POST /api/bookings/calculate-multi-interval-discount
     *
     * Body:
     * {
     *   "intervals": [
     *     {
     *       "course_id": 1,
     *       "interval_id": 2,
     *       "num_days": 5,
     *       "base_price": 500.00,
     *       "num_participants": 2
     *     }
     *   ],
     *   "promo_code": "SUMMER2024"
     * }
     */
    public function calculateMultiIntervalDiscount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'intervals' => 'required|array',
            'intervals.*.course_id' => 'required|integer|exists:courses,id',
            'intervals.*.interval_id' => 'required|integer|exists:course_intervals,id',
            'intervals.*.num_days' => 'required|integer|min:1',
            'intervals.*.base_price' => 'required|numeric|min:0',
            'intervals.*.num_participants' => 'nullable|integer|min:1',
            'intervals.*.booking_date' => 'nullable|date',
            'promo_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->discountService->calculateMultiIntervalDiscounts(
                $request->input('intervals'),
                $request->input('promo_code')
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular descuentos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene descuentos disponibles para mostrar en UI
     *
     * GET /api/courses/{courseId}/intervals/{intervalId}/available-discounts
     */
    public function getAvailableDiscounts(int $courseId, int $intervalId): JsonResponse
    {
        try {
            $discounts = $this->discountService->getDiscountsForDisplay($courseId, $intervalId);

            return response()->json([
                'success' => true,
                'data' => $discounts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener descuentos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
