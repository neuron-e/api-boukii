<?php

namespace App\Services;

use App\Models\CourseIntervalDiscount;
use App\Models\CourseDiscount;
use App\Models\DiscountCode;
use Illuminate\Support\Facades\Log;

/**
 * Servicio centralizado para gestionar descuentos de curso
 *
 * Proporciona lógica para:
 * - Obtener descuentos globales aplicables a un curso
 * - Obtener descuentos aplicables para un intervalo específico
 * - Calcular el mejor descuento (global vs intervalo vs código promocional)
 * - Calcular precios finales con descuentos
 */
class CourseDiscountService
{
    /**
     * Obtiene los descuentos GLOBALES aplicables para un curso
     *
     * @param int $courseId ID del curso
     * @param int $numDays Número de días de la reserva
     * @param string|null $bookingDate Fecha de la reserva (opcional)
     * @return \Illuminate\Support\Collection Colección de descuentos aplicables
     */
    public function getApplicableCourseDiscounts(
        int $courseId,
        int $numDays,
        ?string $bookingDate = null
    ) {
        $discounts = CourseDiscount::where('course_id', $courseId)
            ->active()
            ->byPriority()
            ->get();

        return $discounts->filter(function ($discount) use ($numDays, $bookingDate) {
            return $discount->isApplicable(
                $numDays,
                $bookingDate ?? now()->format('Y-m-d')
            );
        });
    }

    /**
     * Obtiene los descuentos aplicables para un intervalo específico
     *
     * @param int $courseId ID del curso
     * @param int $intervalId ID del intervalo
     * @param int $numDays Número de días de la reserva
     * @param int|null $numParticipants Número de participantes (opcional)
     * @param string|null $bookingDate Fecha de la reserva (opcional)
     * @return \Illuminate\Support\Collection Colección de descuentos aplicables
     */
    public function getApplicableIntervalDiscounts(
        int $courseId,
        int $intervalId,
        int $numDays,
        ?int $numParticipants = null,
        ?string $bookingDate = null
    ) {
        $discounts = CourseIntervalDiscount::where('course_id', $courseId)
            ->where('course_interval_id', $intervalId)
            ->active()
            ->byPriority()
            ->get();

        return $discounts->filter(function ($discount) use ($numDays, $numParticipants, $bookingDate) {
            return $discount->isApplicable(
                $numParticipants ?? 1,
                $numDays,
                $bookingDate ?? now()->format('Y-m-d')
            );
        });
    }

    /**
     * Calcula el mejor descuento entre TRES tipos:
     * 1. Descuentos GLOBALES del curso (course_discounts)
     * 2. Descuentos por intervalo (course_interval_discounts)
     * 3. Códigos promocionales (discount_codes)
     *
     * Regla de negocio: descuentos EXCLUSIVOS - el usuario obtiene el mejor entre los tres.
     *
     * @param int $courseId ID del curso
     * @param int $intervalId ID del intervalo
     * @param int $numDays Número de días
     * @param float $basePrice Precio base sin descuentos
     * @param string|null $promoCode Código promocional (opcional)
     * @param int|null $numParticipants Número de participantes (opcional)
     * @param string|null $bookingDate Fecha de la reserva (opcional)
     * @return array Resultado con el mejor descuento y detalles
     */
    public function getBestDiscount(
        int $courseId,
        int $intervalId,
        int $numDays,
        float $basePrice,
        ?string $promoCode = null,
        ?int $numParticipants = null,
        ?string $bookingDate = null
    ): array {
        // 1. Calcular mejor descuento GLOBAL del curso
        $courseDiscounts = $this->getApplicableCourseDiscounts(
            $courseId,
            $numDays,
            $bookingDate
        );

        $bestCourseDiscount = null;
        $bestCourseAmount = 0;

        foreach ($courseDiscounts as $discount) {
            $amount = $discount->calculateDiscount($basePrice);
            if ($amount > $bestCourseAmount) {
                $bestCourseAmount = $amount;
                $bestCourseDiscount = $discount;
            }
        }

        // 2. Calcular mejor descuento de intervalo
        $intervalDiscounts = $this->getApplicableIntervalDiscounts(
            $courseId,
            $intervalId,
            $numDays,
            $numParticipants,
            $bookingDate
        );

        $bestIntervalDiscount = null;
        $bestIntervalAmount = 0;

        foreach ($intervalDiscounts as $discount) {
            $amount = $discount->calculateDiscount($basePrice);
            if ($amount > $bestIntervalAmount) {
                $bestIntervalAmount = $amount;
                $bestIntervalDiscount = $discount;
            }
        }

        // 3. Calcular descuento de código promocional si se proporciona
        $promoDiscount = null;
        $promoAmount = 0;

        if ($promoCode) {
            $discountCode = DiscountCode::where('code', $promoCode)
                ->where('active', true)
                ->first();

            if ($discountCode) {
                // Validar código promocional
                $validation = $this->validateDiscountCode($discountCode, $courseId, $bookingDate);

                if ($validation['valid']) {
                    if ($discountCode->discount_type === 'percentage') {
                        $promoAmount = $basePrice * ($discountCode->discount_value / 100);
                    } else {
                        $promoAmount = min($discountCode->discount_value, $basePrice);
                    }
                    $promoDiscount = $discountCode;
                }
            }
        }

        // Determinar el mejor descuento entre los TRES tipos
        $alternatives = [];

        // Crear array con todos los descuentos disponibles
        $allDiscounts = [
            [
                'type' => 'course_global',
                'discount' => $bestCourseDiscount,
                'amount' => $bestCourseAmount,
            ],
            [
                'type' => 'interval',
                'discount' => $bestIntervalDiscount,
                'amount' => $bestIntervalAmount,
            ],
            [
                'type' => 'promo_code',
                'discount' => $promoDiscount,
                'amount' => $promoAmount,
            ],
        ];

        // Ordenar por monto de descuento (mayor primero)
        usort($allDiscounts, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        // El mejor es el primero
        $best = $allDiscounts[0];

        // Los demás son alternativas (si tienen monto > 0)
        for ($i = 1; $i < count($allDiscounts); $i++) {
            if ($allDiscounts[$i]['amount'] > 0 && $allDiscounts[$i]['discount']) {
                $alternatives[] = [
                    'type' => $allDiscounts[$i]['type'],
                    'discount' => $allDiscounts[$i]['discount'],
                    'amount' => $allDiscounts[$i]['amount'],
                    'final_price' => max(0, $basePrice - $allDiscounts[$i]['amount'])
                ];
            }
        }

        return [
            'type' => $best['type'],
            'discount' => $best['discount'],
            'amount' => $best['amount'],
            'final_price' => max(0, $basePrice - $best['amount']),
            'alternatives' => $alternatives,
            'base_price' => $basePrice
        ];
    }

    /**
     * Calcula el precio final después de aplicar un descuento
     *
     * @param float $basePrice Precio base
     * @param CourseIntervalDiscount|CourseDiscount|null $discount Descuento a aplicar
     * @return array Resultado con precio final y desglose
     */
    public function calculateFinalPrice(float $basePrice, $discount = null): array
    {
        if (!$discount) {
            return [
                'base_price' => $basePrice,
                'discount_amount' => 0,
                'final_price' => $basePrice,
                'has_discount' => false
            ];
        }

        $discountAmount = $discount->calculateDiscount($basePrice);
        $finalPrice = $discount->applyDiscount($basePrice);

        return [
            'base_price' => $basePrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'has_discount' => true,
            'discount_type' => $discount->discount_type,
            'discount_value' => $discount->discount_value,
            'discount_name' => $discount->name
        ];
    }

    /**
     * Valida un código de descuento
     *
     * @param DiscountCode $discountCode Código de descuento
     * @param int|null $courseId ID del curso (opcional)
     * @param string|null $bookingDate Fecha de la reserva (opcional)
     * @return array Resultado de validación
     */
    private function validateDiscountCode(
        DiscountCode $discountCode,
        ?int $courseId = null,
        ?string $bookingDate = null
    ): array {
        $errors = [];

        // Verificar si está activo
        if (!$discountCode->active) {
            $errors[] = 'El código promocional no está activo';
        }

        // Verificar fechas de validez
        $currentDate = $bookingDate ?? now()->format('Y-m-d');

        if ($discountCode->valid_from && $currentDate < $discountCode->valid_from->format('Y-m-d')) {
            $errors[] = 'El código promocional aún no es válido';
        }

        if ($discountCode->valid_to && $currentDate > $discountCode->valid_to->format('Y-m-d')) {
            $errors[] = 'El código promocional ha expirado';
        }

        // Verificar límite de uso
        if ($discountCode->max_uses && $discountCode->times_used >= $discountCode->max_uses) {
            $errors[] = 'El código promocional ha alcanzado su límite de uso';
        }

        // Verificar restricción de curso
        if ($courseId && $discountCode->course_id && $discountCode->course_id != $courseId) {
            $errors[] = 'El código promocional no es válido para este curso';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'discount_code' => $discountCode
        ];
    }

    /**
     * Calcula descuentos para múltiples intervalos en una reserva
     *
     * @param array $intervals Array de intervalos con formato ['interval_id' => X, 'num_days' => Y, 'base_price' => Z]
     * @param string|null $promoCode Código promocional (opcional)
     * @return array Resultado con descuentos por intervalo
     */
    public function calculateMultiIntervalDiscounts(array $intervals, ?string $promoCode = null): array
    {
        $results = [];
        $totalBasePrice = 0;
        $totalDiscountAmount = 0;
        $hasPromoCode = !empty($promoCode);

        foreach ($intervals as $intervalData) {
            $courseId = $intervalData['course_id'];
            $intervalId = $intervalData['interval_id'];
            $numDays = $intervalData['num_days'];
            $basePrice = $intervalData['base_price'];
            $numParticipants = $intervalData['num_participants'] ?? null;
            $bookingDate = $intervalData['booking_date'] ?? null;

            $bestDiscount = $this->getBestDiscount(
                $courseId,
                $intervalId,
                $numDays,
                $basePrice,
                $hasPromoCode ? $promoCode : null,
                $numParticipants,
                $bookingDate
            );

            $results[] = [
                'interval_id' => $intervalId,
                'course_id' => $courseId,
                'base_price' => $basePrice,
                'discount_type' => $bestDiscount['type'],
                'discount_amount' => $bestDiscount['amount'],
                'final_price' => $bestDiscount['final_price'],
                'discount_details' => $bestDiscount['discount']
            ];

            $totalBasePrice += $basePrice;
            $totalDiscountAmount += $bestDiscount['amount'];
        }

        return [
            'intervals' => $results,
            'total_base_price' => $totalBasePrice,
            'total_discount_amount' => $totalDiscountAmount,
            'total_final_price' => $totalBasePrice - $totalDiscountAmount,
            'has_promo_code' => $hasPromoCode,
            'promo_code' => $promoCode
        ];
    }

    /**
     * Obtiene información de descuentos disponibles para mostrar en UI (GLOBALES del curso)
     *
     * @param int $courseId ID del curso
     * @return array Descuentos disponibles formateados para UI
     */
    public function getCourseDiscountsForDisplay(int $courseId): array
    {
        $discounts = CourseDiscount::where('course_id', $courseId)
            ->active()
            ->orderBy('min_days')
            ->get();

        return $discounts->map(function ($discount) {
            return [
                'id' => $discount->id,
                'name' => $discount->name,
                'description' => $discount->description,
                'discount_type' => $discount->discount_type,
                'discount_value' => $discount->discount_value,
                'min_days' => $discount->min_days,
                'valid_from' => optional($discount->valid_from)->format('Y-m-d'),
                'valid_to' => optional($discount->valid_to)->format('Y-m-d'),
                'display_text' => $this->formatCourseDiscountDisplay($discount)
            ];
        })->toArray();
    }

    /**
     * Obtiene información de descuentos disponibles para mostrar en UI (por intervalo)
     *
     * @param int $courseId ID del curso
     * @param int $intervalId ID del intervalo
     * @return array Descuentos disponibles formateados para UI
     */
    public function getIntervalDiscountsForDisplay(int $courseId, int $intervalId): array
    {
        $discounts = CourseIntervalDiscount::where('course_id', $courseId)
            ->where('course_interval_id', $intervalId)
            ->active()
            ->orderBy('min_days')
            ->get();

        return $discounts->map(function ($discount) {
            return [
                'id' => $discount->id,
                'name' => $discount->name,
                'description' => $discount->description,
                'discount_type' => $discount->discount_type,
                'discount_value' => $discount->discount_value,
                'min_days' => $discount->min_days,
                'min_participants' => $discount->min_participants,
                'valid_from' => optional($discount->valid_from)->format('Y-m-d'),
                'valid_to' => optional($discount->valid_to)->format('Y-m-d'),
                'display_text' => $this->formatIntervalDiscountDisplay($discount)
            ];
        })->toArray();
    }

    /**
     * Formatea un descuento global de curso para mostrar en UI
     *
     * @param CourseDiscount $discount Descuento
     * @return string Texto formateado
     */
    private function formatCourseDiscountDisplay(CourseDiscount $discount): string
    {
        $valueText = $discount->discount_type === 'percentage'
            ? "{$discount->discount_value}%"
            : "CHF {$discount->discount_value}";

        $conditionsText = [];

        if ($discount->min_days) {
            $conditionsText[] = "al reservar {$discount->min_days}+ días";
        }

        $conditions = !empty($conditionsText) ? ' ' . implode(' y ', $conditionsText) : '';

        return "Descuento {$valueText}{$conditions}";
    }

    /**
     * Formatea un descuento de intervalo para mostrar en UI
     *
     * @param CourseIntervalDiscount $discount Descuento
     * @return string Texto formateado
     */
    private function formatIntervalDiscountDisplay(CourseIntervalDiscount $discount): string
    {
        $valueText = $discount->discount_type === 'percentage'
            ? "{$discount->discount_value}%"
            : "CHF {$discount->discount_value}";

        $conditionsText = [];

        if ($discount->min_days) {
            $conditionsText[] = "al reservar {$discount->min_days}+ días";
        }

        if ($discount->min_participants) {
            $conditionsText[] = "con {$discount->min_participants}+ participantes";
        }

        $conditions = !empty($conditionsText) ? ' ' . implode(' y ', $conditionsText) : '';

        return "Descuento {$valueText}{$conditions}";
    }

    /**
     * Registra la aplicación de un descuento para auditoría
     *
     * @param int $bookingId ID de la reserva
     * @param string $discountType Tipo de descuento ('course_global', 'interval' o 'promo_code')
     * @param mixed $discount Objeto de descuento
     * @param float $amount Monto del descuento
     * @return void
     */
    public function logDiscountApplication(
        int $bookingId,
        string $discountType,
        $discount,
        float $amount
    ): void {
        $logData = [
            'booking_id' => $bookingId,
            'discount_type' => $discountType,
            'discount_amount' => $amount,
            'timestamp' => now()->toDateTimeString()
        ];

        if ($discountType === 'course_global' && $discount instanceof CourseDiscount) {
            $logData['course_discount_id'] = $discount->id;
            $logData['course_discount_name'] = $discount->name;
            $logData['course_id'] = $discount->course_id;
        } elseif ($discountType === 'interval' && $discount instanceof CourseIntervalDiscount) {
            $logData['interval_discount_id'] = $discount->id;
            $logData['interval_discount_name'] = $discount->name;
            $logData['course_id'] = $discount->course_id;
            $logData['course_interval_id'] = $discount->course_interval_id;
        } elseif ($discountType === 'promo_code' && $discount instanceof DiscountCode) {
            $logData['discount_code_id'] = $discount->id;
            $logData['discount_code'] = $discount->code;
        }

        Log::channel('daily')->info('Discount applied', $logData);
    }
}
