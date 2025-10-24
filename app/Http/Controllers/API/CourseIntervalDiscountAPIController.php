<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CourseInterval;
use App\Models\CourseIntervalDiscount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CourseIntervalDiscountAPIController extends Controller
{
    /**
     * List discounts for a specific interval.
     */
    public function index(string $intervalId): JsonResponse
    {
        $interval = CourseInterval::with(['discounts' => function ($query) {
            $query->active()->orderBy('min_days');
        }])->find($intervalId);

        if (!$interval) {
            return response()->json([
                'success' => false,
                'message' => 'Intervalo no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $interval->discounts->map(function (CourseIntervalDiscount $discount) {
                return [
                    'id' => $discount->id,
                    'days' => (int) ($discount->min_days ?? 0),
                    'type' => $discount->discount_type === 'fixed_amount' ? 'fixed' : 'percentage',
                    'value' => (float) $discount->discount_value,
                    'name' => $discount->name,
                    'priority' => $discount->priority,
                    'active' => (bool) $discount->active,
                    'valid_from' => optional($discount->valid_from)->format('Y-m-d'),
                    'valid_to' => optional($discount->valid_to)->format('Y-m-d'),
                ];
            }),
        ]);
    }

    /**
     * Replace all discounts for an interval.
     *
     * Expected payload:
     * {
     *   "discounts": [
     *     { "days": 2, "type": "percentage", "value": 10 },
     *     { "days": 4, "type": "fixed", "value": 15 }
     *   ]
     * }
     */
    public function upsert(Request $request, string $intervalId): JsonResponse
    {
        $interval = CourseInterval::find($intervalId);

        if (!$interval) {
            return response()->json([
                'success' => false,
                'message' => 'Intervalo no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'discounts' => 'required|array',
            'discounts.*.days' => 'required|integer|min:1',
            'discounts.*.type' => 'required|string|in:percentage,fixed,fixed_amount',
            'discounts.*.value' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = collect($request->input('discounts', []))
            ->unique('days')
            ->sortBy('days')
            ->values();

        DB::beginTransaction();

        try {
            CourseIntervalDiscount::where('course_interval_id', $interval->id)->delete();

            foreach ($payload as $index => $discountData) {
                CourseIntervalDiscount::create([
                    'course_id' => $interval->course_id,
                    'course_interval_id' => $interval->id,
                    'name' => sprintf('Día %d', $discountData['days']),
                    'discount_type' => ($discountData['type'] === 'fixed' || $discountData['type'] === 'fixed_amount')
                        ? 'fixed_amount'
                        : 'percentage',
                    'discount_value' => $discountData['value'],
                    'min_days' => $discountData['days'],
                    'priority' => $index + 1,
                    'active' => true,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Descuentos actualizados correctamente',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar los descuentos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
