<?php

namespace App\Http\Services;

use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\VouchersLog;
use App\Support\IntervalDiscountHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BookingPriceCalculatorService
{
    /**
     * Calcula el precio total de una reserva incluyendo todos los conceptos
     */
    public function calculateBookingTotal(Booking $booking, array $options = []): array
    {
        $excludeCourses = $options['exclude_courses'] ?? [];
        $includeInsurance = $options['include_insurance'] ?? true;

        // Obtener usuarios de reserva activos y no excluidos
        $activeBookingUsers = $this->getActiveBookingUsers($booking, $excludeCourses);

        // Calcular precios base por actividades
        $activitiesPrice = $this->calculateActivitiesPrice($activeBookingUsers);

        // Calcular conceptos adicionales
        $additionalConcepts = $this->calculateAdditionalConcepts($booking, $activitiesPrice, $includeInsurance);

        // Calcular descuentos (SIN vouchers - los vouchers son balance, no descuentos)
        $discounts = $this->calculateDiscounts($booking, $activitiesPrice);

        $totalBeforeVouchers = $activitiesPrice + array_sum($additionalConcepts) - array_sum($discounts);

        return [
            'activities_price' => $activitiesPrice,
            'additional_concepts' => $additionalConcepts,
            'discounts' => $discounts,
            'total_before_vouchers' => $totalBeforeVouchers,
            'total_final' => $totalBeforeVouchers, // Los vouchers no reducen el precio, son forma de pago
            'vouchers_info' => $this->analyzeVouchersForBalance($booking),
            'breakdown' => $this->getDetailedBreakdown($booking, $activeBookingUsers, $additionalConcepts, $discounts)
        ];
    }

    public function analyzeVouchersForBalance(Booking $booking): array
    {
        $voucherData = [
            'total_used' => 0,
            'total_refunded' => 0,
            'net_voucher_payment' => 0,
            'details' => []
        ];

        foreach ($booking->vouchersLogs as $voucherLog) {
            $voucher = $voucherLog->voucher;
            if (!$voucher) continue;

            // Determinar si es uso o refund del voucher
            $voucherAnalysis = $this->determineVoucherLogType($voucherLog, $voucher, $booking);

            if ($voucherAnalysis['type'] === 'payment') {
                $voucherData['total_used'] += $voucherAnalysis['amount'];
            } else {
                $voucherData['total_refunded'] += $voucherAnalysis['amount'];
            }

            $voucherData['details'][] = [
                'voucher_log_id' => $voucherLog->id,
                'voucher_code' => $voucher->code,
                'original_amount' => $voucherLog->amount,
                'interpreted_amount' => $voucherAnalysis['amount'],
                'interpreted_type' => $voucherAnalysis['type'],
                'reason' => $voucherAnalysis['reason']
            ];
        }

        $voucherData['net_voucher_payment'] = $voucherData['total_used'] - $voucherData['total_refunded'];

        return $voucherData;
    }

    /**
     * NUEVO: Analizar la realidad financiera vs el precio calculado
     */
    public function analyzeFinancialReality(Booking $booking, array $options = []): array
    {
        // 1. Calcular lo que DEBERÍA costar
        $calculatedTotal = $this->calculateBookingTotal($booking, $options);

        // 2. Analizar lo que REALMENTE se movió financieramente
        $financialReality = $this->getFinancialReality($booking);

        // 3. Comparar realidad vs expectativa
        $realityCheck = $this->compareRealityVsCalculated($calculatedTotal, $financialReality, $booking);

        return [
            'calculated_total' => $calculatedTotal['total_final'],
            'stored_total' => $booking->price_total, // Solo informativo
            'financial_reality' => $financialReality,
            'reality_check' => $realityCheck,
            'calculation_details' => $calculatedTotal,
            'recommendation' => $this->getRecommendation($realityCheck, $booking)
        ];
    }

    /**
     * NUEVO: Obtener la realidad financiera real
     */
    private function getFinancialReality(Booking $booking): array
    {
        // Pagos reales
        $totalPaid = $booking->payments->whereIn('status', ['paid'])->sum('amount');
        $totalRefunded = $booking->payments->whereIn('status', ['refund', 'partial_refund'])->sum('amount');
        $totalNoRefund = $booking->payments->whereIn('status', ['no_refund'])->sum('amount');

        // Vouchers reales
        $voucherAnalysis = $this->analyzeVouchersForBalance($booking);
        $totalVouchersUsed = $voucherAnalysis['total_used'];
        $totalVouchersRefunded = $voucherAnalysis['total_refunded'];

        // Realidad financiera neta
        $totalReceived = $totalPaid + $totalVouchersUsed;
        $totalProcessed = $totalRefunded + $totalVouchersRefunded + $totalNoRefund;
        // No-refund should not reduce the net balance kept by the school.
        $netBalance = $totalReceived - ($totalRefunded + $totalVouchersRefunded);

        return [
            'total_paid' => $totalPaid,
            'total_vouchers_used' => $totalVouchersUsed,
            'total_received' => $totalReceived,
            'total_refunded' => $totalRefunded,
            'total_vouchers_refunded' => $totalVouchersRefunded,
            'total_no_refund' => $totalNoRefund,
            'total_processed' => $totalProcessed,
            'net_balance' => $netBalance,
          //  'payment_details' => $this->getPaymentDetails($booking),
            'voucher_details' => $voucherAnalysis['details']
        ];
    }

    /**
     * NUEVO: Comparar realidad financiera vs precio calculado
     */
    private function compareRealityVsCalculated(array $calculated, array $reality, Booking $booking): array
    {
        $calculatedPrice = $calculated['total_final'];
        $receivedAmount = $reality['total_received'];
        $netBalance = $reality['net_balance'];

        $comparison = [
            'calculated_price' => $calculatedPrice,
            'received_amount' => $receivedAmount,
            'net_balance' => $netBalance,
            'price_vs_received' => $calculatedPrice - $receivedAmount,
            'price_vs_balance' => $calculatedPrice - $netBalance,
            'is_consistent' => false,
            'consistency_type' => '',
            'issues' => []
        ];

        $tolerance = 0.50;

        switch ($booking->status) {
            case 1: // ACTIVA
                // Para activas: el balance neto debería igualar el precio calculado
                $discrepancy = abs($calculatedPrice - $netBalance);
                $comparison['is_consistent'] = $discrepancy <= $tolerance;
                $comparison['consistency_type'] = 'active_booking';
                $comparison['main_discrepancy'] = $calculatedPrice - $netBalance;

                if (!$comparison['is_consistent']) {
                    if ($netBalance < $calculatedPrice) {
                        $comparison['issues'][] = "Falta dinero: se necesita " . round($calculatedPrice - $netBalance, 2) . "€ más";
                    } else {
                        $comparison['issues'][] = "Exceso de dinero: se recibió " . round($netBalance - $calculatedPrice, 2) . "€ de más";
                    }
                }
                break;

            case 2: // CANCELADA
                // Para canceladas: el balance neto debería ser 0 (todo refundado/procesado)
                $comparison['is_consistent'] = abs($netBalance) <= $tolerance;
                $comparison['consistency_type'] = 'cancelled_booking';
                $comparison['main_discrepancy'] = $netBalance;

                if (!$comparison['is_consistent']) {
                    if ($netBalance > 0) {
                        $comparison['issues'][] = "Dinero sin procesar: quedan " . round($netBalance, 2) . "€ por refundar";
                    } else {
                        $comparison['issues'][] = "Se procesó más dinero del recibido: " . round(abs($netBalance), 2) . "€";
                    }
                }
                break;

            case 3: // PARCIALMENTE CANCELADA
                // Para parciales: el balance neto debería igualar el precio de usuarios activos
                $activeUsersPrice = $this->calculateActivitiesPrice(
                    $booking->bookingUsers->where('status', 1)
                );
                $comparison['active_users_price'] = $activeUsersPrice;
                $discrepancy = abs($activeUsersPrice - $netBalance);
                $comparison['is_consistent'] = $discrepancy <= $tolerance;
                $comparison['consistency_type'] = 'partially_cancelled_booking';
                $comparison['main_discrepancy'] = $activeUsersPrice - $netBalance;

                if (!$comparison['is_consistent']) {
                    $comparison['issues'][] = "Discrepancia en cancelación parcial: " . round($discrepancy, 2) . "€";
                }
                break;
        }

        return $comparison;
    }

    /**
     * NUEVO: Obtener recomendación basada en realidad financiera
     */
    private function getRecommendation(array $realityCheck, Booking $booking): string
    {
        if ($realityCheck['is_consistent']) {
            return "✅ Consistente: La realidad financiera coincide con el precio calculado";
        }

        $mainDiscrepancy = abs($realityCheck['main_discrepancy']);
        $issues = $realityCheck['issues'];

        if ($mainDiscrepancy > 10) {
            return "🚨 CRÍTICO: " . implode(". ", $issues) . ". Revisar inmediatamente.";
        } elseif ($mainDiscrepancy > 1) {
            return "⚠️ ATENCIÓN: " . implode(". ", $issues) . ". Revisar cuando sea posible.";
        } else {
            return "ℹ️ MENOR: " . implode(". ", $issues) . ". Diferencia menor, posiblemente redondeo.";
        }
    }

    /**
     * NUEVO: Determinar si un voucherLog es payment o refund (copia del método del controller)
     */
    public function determineVoucherLogType($voucherLog, $voucher, $booking)
    {
        $logAmount = $voucherLog->amount;
        $voucherQuantity = $voucher->quantity ?? 0;
        $voucherRemainingBalance = $voucher->remaining_balance ?? 0;
        $voucherPayed = $voucher->payed ?? false;

        // ✅ NUEVA LÓGICA: Analizar el contexto completo
        $voucherUsedAmount = $voucherQuantity - $voucherRemainingBalance;

        Log::channel('finance')->debug("Analizando voucher log", [
            'booking_id' => $booking->id,
            'log_amount' => $logAmount,
            'voucher_quantity' => $voucherQuantity,
            'voucher_remaining_balance' => $voucherRemainingBalance,
            'voucher_used_amount' => $voucherUsedAmount,
            'voucher_payed' => $voucherPayed
        ]);

        // REGLA PRINCIPAL: Si el voucher se ha usado completamente (remaining_balance = 0)
        // y coincide con el amount del log, es USO del voucher
        if ($voucherRemainingBalance == 0 && abs($logAmount) == $voucherUsedAmount) {
            return [
                'type' => 'payment',
                'amount' => abs($logAmount), // Siempre positivo para payments
                'reason' => 'Voucher completamente usado - coincide con cantidad'
            ];
        }

        // REGLA SECUNDARIA: Si el voucher está pagado y se ha usado
        if ($voucherPayed && $voucherUsedAmount > 0) {
            // Si el log amount coincide con lo usado, es payment
            if (abs($logAmount) == $voucherUsedAmount) {
                return [
                    'type' => 'payment',
                    'amount' => abs($logAmount),
                    'reason' => 'Voucher pagado - cantidad coincide con uso'
                ];
            }

            // Si es menor, podría ser un uso parcial
            if (abs($logAmount) < $voucherUsedAmount) {
                return [
                    'type' => 'payment',
                    'amount' => abs($logAmount),
                    'reason' => 'Voucher pagado - uso parcial'
                ];
            }

            // Si es mayor, podría ser refund
            return [
                'type' => 'refund',
                'amount' => abs($logAmount),
                'reason' => 'Voucher pagado - cantidad excede uso, probable refund'
            ];
        }

        // REGLA FALLBACK: Analizar por signo pero con más contexto
        if ($logAmount > 0) {
            return [
                'type' => 'payment',
                'amount' => $logAmount,
                'reason' => 'Cantidad positiva = uso de voucher'
            ];
        } else {
            // Para negativos, usar contexto adicional
            $absAmount = abs($logAmount);

            // Si el booking está activo y el voucher se ha usado, probable payment
            if ($booking->status == 1 && $voucherUsedAmount > 0) {
                return [
                    'type' => 'payment',
                    'amount' => $absAmount,
                    'reason' => 'Booking activo + voucher usado = probable payment (pese a signo negativo)'
                ];
            }

            // Si el booking está cancelado, más probable refund
            if ($booking->status == 2) {
                return [
                    'type' => 'refund',
                    'amount' => $absAmount,
                    'reason' => 'Booking cancelado + cantidad negativa = probable refund'
                ];
            }

            // Fallback: considerar como payment si no hay evidencia de refund
            return [
                'type' => 'payment',
                'amount' => $absAmount,
                'reason' => 'Sin evidencia clara de refund - asumido como payment'
            ];
        }
    }

    /**
     * Calcula el precio de actividades agrupadas por curso
     */
    public function calculateActivitiesPrice(Collection $bookingUsers): float
    {
        $totalPrice = 0;

        foreach ($bookingUsers->groupBy('course_id') as $courseId => $courseBookingUsers) {
            $course = $courseBookingUsers->first()->course;
            if (!$course) continue;

            if ($course->course_type === 1) {
                // Cursos colectivos
                $totalPrice += $this->calculateCollectivePrice($courseBookingUsers, $course);
            } elseif ($course->course_type === 2) {
                // Cursos privados
                $totalPrice += $this->calculatePrivatePrice($courseBookingUsers, $course);
            } else {
                // Actividades u otros tipos
                $totalPrice += $this->calculateActivityPrice($courseBookingUsers, $course);
            }
        }

        return round($totalPrice, 2);
    }

    /**
     * Calcula precio para cursos colectivos
     */
    public function calculateCollectivePrice(Collection $courseBookingUsers, $course): float
    {
        $totalPrice = 0;

        if ($course->is_flexible) {
            // Colectivo flexible: precio por cliente por fecha
            foreach ($courseBookingUsers->groupBy('client_id') as $clientBookingUsers) {
                $totalPrice += $this->calculateFlexibleCollectiveForClient($clientBookingUsers, $course);
            }
        } else {
            // Colectivo fijo: precio base por cliente único
            $uniqueClients = $courseBookingUsers->groupBy('client_id')->count();
            $totalPrice += $course->price * $uniqueClients;
        }

        // Añadir extras
        $totalPrice += $this->calculateExtrasPrice($courseBookingUsers);

        return $totalPrice;
    }

    /**
     * Calcula precio para un cliente en curso colectivo flexible
     */
    private function calculateFlexibleCollectiveForClient(Collection $clientBookingUsers, $course): float
    {
        return IntervalDiscountHelper::calculateFlexibleCollectivePrice($course, $clientBookingUsers);
    }

    /**
     * Calcula precio para cursos privados
     */
    public function calculatePrivatePrice(Collection $courseBookingUsers, $course): float
    {
        $totalPrice = 0;

        if (!empty($course->is_flexible)) {
            // CURSO PRIVADO FLEXIBLE → agrupar por sesión y calcular
            $sessions = $courseBookingUsers->groupBy(function ($bookingUser) {
                return $bookingUser->date . '|' . $bookingUser->hour_start . '|' .
                    $bookingUser->hour_end . '|' . $bookingUser->monitor_id . '|' .
                    $bookingUser->group_id;
            });

            foreach ($sessions as $sessionBookingUsers) {
                $sessionPrice = $this->calculateSessionPrice($sessionBookingUsers, $course);
                $totalPrice += $sessionPrice;
            }
        } else {
            // ✅ CURSO PRIVADO FIJO → precio directo por pax
            foreach ($courseBookingUsers as $bookingUser) {
                $totalPrice += $bookingUser->price ?? $course->price;
            }
        }

        return round($totalPrice, 2);
    }

    /**
     * Calcula precio de una sesión privada
     */
    private function calculateSessionPrice(Collection $sessionBookingUsers, $course): float
    {
        $bookingUser = $sessionBookingUsers->first();
        $participantsCount = $sessionBookingUsers->count();

        // Calcular duración
        $duration = $this->calculateDurationMinutes($bookingUser->hour_start, $bookingUser->hour_end);
        $interval = $this->getDurationInterval($duration);

        // Obtener precio de price_range
        $priceRange = $this->parsePriceRange($course->price_range);
        $sessionPrice = $this->getPriceFromRange($priceRange, $interval, $participantsCount);

        // Añadir extras de la sesión
        $extrasPrice = $sessionBookingUsers->sum(function ($bu) {
            return $bu->bookingUserExtras->sum('courseExtra.price');
        });

        return $sessionPrice + $extrasPrice;
    }

    /**
     * Calcula precio para actividades
     */
    public function calculateActivityPrice(Collection $courseBookingUsers, $course): float
    {
        // Lógica similar a privados o específica para actividades
        return $this->calculatePrivatePrice($courseBookingUsers, $course);
    }

    /**
     * Calcula precio de extras
     */
    private function calculateExtrasPrice(Collection $bookingUsers): float
    {
        return $bookingUsers->sum(function ($bookingUser) {
            return $bookingUser->bookingUserExtras->sum('courseExtra.price');
        });
    }

    /**
     * Calcula conceptos adicionales (seguro, TVA, etc.)
     */
    private function calculateAdditionalConcepts(Booking $booking, float $basePrice, bool $includeInsurance): array
    {
        $concepts = [];
        // Seguro de cancelacion
        if ($includeInsurance && $booking->has_cancellation_insurance && $basePrice > 0) {
            $storedInsurance = (float) ($booking->price_cancellation_insurance ?? 0);
            if ($storedInsurance > 0) {
                $concepts['cancellation_insurance'] = $storedInsurance;
            } else {
                $school = $booking->school;
                $settings = json_decode($school->settings, true);
                $insurancePercent = $settings['taxes']['cancellation_insurance_percent'] ?? 0.10;
                $concepts['cancellation_insurance'] = round($basePrice * $insurancePercent, 2);
            }
        }

        // Boukii Care
        if ($booking->has_boukii_care && $booking->price_boukii_care > 0) {
            $concepts['boukii_care'] = $booking->price_boukii_care;
        }

        // TVA
        if ($booking->has_tva && $booking->price_tva > 0) {
            $concepts['tva'] = $booking->price_tva;
        }

        return $concepts;
    }

    /**
     * Calcula descuentos y vouchers
     */
    /**
     * CORREGIDO: Calcular descuentos SIN incluir vouchers
     */
    private function calculateDiscounts(Booking $booking, float $activitiesPrice = 0): array
    {
        $discounts = [];
        // Solo reduccion manual y descuento de codigo - NO vouchers
        if ($booking->has_reduction && $booking->price_reduction > 0) {
            $shouldApplyReduction = true;
            $storedTotal = (float) ($booking->price_total ?? 0);
            if ($activitiesPrice > 0 && $storedTotal > 0 && abs($activitiesPrice - $storedTotal) <= 0.01) {
                // Activities already reflect the reduction, avoid double discount.
                $shouldApplyReduction = false;
            }

            $basket = $this->decodeBasket($booking->basket);
            if (is_array($basket)) {
                $basketTotal = $this->parseFloatValue($basket['price_total'] ?? null);
                $basketBase = $this->parseFloatValue($basket['price_base']['price'] ?? null);
                if ($basketBase > 0 && abs($activitiesPrice - $basketBase) <= 0.01) {
                    $shouldApplyReduction = true;
                } elseif ($basketTotal > 0 && abs($activitiesPrice - $basketTotal) <= 0.01) {
                    $shouldApplyReduction = false;
                }
            }

            if ($shouldApplyReduction) {
                $discounts['manual_reduction'] = $booking->price_reduction;
            }
        }
        if (!empty($booking->discount_code_value)) {
            $discounts['discount_code'] = (float) $booking->discount_code_value;
        }

        return $discounts;
    }

    private function decodeBasket(?string $basket): ?array
    {
        if (!$basket) {
            return null;
        }
        $decoded = json_decode($basket, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function parseFloatValue($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return (float) str_replace(',', '.', (string) $value);
    }

    public function calculateIntervalDiscounts(Booking $booking): array
    {
        $booking->loadMissing([
            'bookingUsers.course',
            'bookingUsers.courseDate',
            'bookingUsers.course.courseIntervals.discounts',
        ]);

        $bookingUsers = $this->getActiveBookingUsers($booking, []);
        if ($bookingUsers->isEmpty()) {
            return ['total' => 0.0, 'discounts' => []];
        }

        $discounts = [];
        $total = 0.0;

        foreach ($bookingUsers->groupBy('course_id') as $courseId => $courseBookingUsers) {
            $course = $courseBookingUsers->first()->course;
            if (!$course || (int) $course->course_type !== 1 || !$course->is_flexible) {
                continue;
            }

            $courseBase = 0.0;
            $courseDiscounted = 0.0;

            foreach ($courseBookingUsers->groupBy('client_id') as $clientBookingUsers) {
                $uniqueDates = $clientBookingUsers->pluck('date')->unique()->count();
                $courseBase += (float) $course->price * $uniqueDates;
                $courseDiscounted += IntervalDiscountHelper::calculateFlexibleCollectivePrice(
                    $course,
                    $clientBookingUsers
                );
            }

            $discount = round($courseBase - $courseDiscounted, 2);
            if ($discount > 0.01) {
                $discounts[] = [
                    'course_id' => $courseId,
                    'name' => 'Interval discount - ' . $course->name,
                    'quantity' => 1,
                    'price' => -$discount,
                ];
                $total += $discount;
            }
        }

        return [
            'total' => round($total, 2),
            'discounts' => $discounts,
        ];
    }

    /**
     * Calcula descuento por vouchers
     */
    private function calculateVoucherDiscount(Booking $booking, float $totalPrice): float
    {
        $totalVoucherAmount = 0;
        $remainingPrice = $totalPrice;

        foreach ($booking->vouchersLogs as $voucherLog) {
            if ($voucherLog->amount > 0 && $remainingPrice > 0) {
                $voucherAmount = min($voucherLog->amount, $remainingPrice);
                $totalVoucherAmount += $voucherAmount;
                $remainingPrice -= $voucherAmount;
            }
        }

        return $totalVoucherAmount;
    }

    /**
     * Recalcula y ajusta vouchers según el nuevo precio total
     */
    public function recalculateVouchers(Booking $booking, float $newTotalPrice): array
    {
        $adjustments = [];
        $remainingPrice = $newTotalPrice;

        foreach ($booking->vouchersLogs as $voucherLog) {
            $voucher = $voucherLog->voucher;
            if (!$voucher || $voucherLog->is_old) continue;

            $currentUsed = $voucherLog->amount;
            $availableBalance = $voucher->remaining_balance + $currentUsed;

            if ($remainingPrice > 0) {
                $newUsedAmount = min($availableBalance, $remainingPrice);
                $adjustment = $newUsedAmount - $currentUsed;

                if (abs($adjustment) > 0.01) {
                    $adjustments[] = [
                        'voucher_log_id' => $voucherLog->id,
                        'voucher_id' => $voucher->id,
                        'old_amount' => $currentUsed,
                        'new_amount' => $newUsedAmount,
                        'adjustment' => $adjustment
                    ];
                }

                $remainingPrice -= $newUsedAmount;
            } else {
                // Si no queda precio, liberar todo el voucher
                if ($currentUsed > 0) {
                    $adjustments[] = [
                        'voucher_log_id' => $voucherLog->id,
                        'voucher_id' => $voucher->id,
                        'old_amount' => $currentUsed,
                        'new_amount' => 0,
                        'adjustment' => -$currentUsed
                    ];
                }
            }
        }

        return $adjustments;
    }

    /**
     * Obtiene usuarios de reserva activos no excluidos
     */
    private function getActiveBookingUsers(Booking $booking, array $excludeCourses = []): Collection
    {
        return $booking->bookingUsers
            ->where('status', '!=', 2)
            ->filter(function ($bookingUser) use ($excludeCourses) {
                return !in_array((int) $bookingUser->course_id, $excludeCourses);
            });
    }

    /**
     * Obtiene desglose detallado
     */
    private function getDetailedBreakdown(Booking $booking, Collection $bookingUsers, array $additionalConcepts, array $discounts): array
    {
        $breakdown = [];

        // Desglose por curso
        foreach ($bookingUsers->groupBy('course_id') as $courseId => $courseBookingUsers) {
            $course = $courseBookingUsers->first()->course;
            if (!$course) continue;

            $coursePrice = 0;
            if ($course->course_type === 1) {
                $coursePrice = $this->calculateCollectivePrice($courseBookingUsers, $course);
            } elseif ($course->course_type === 2) {
                $coursePrice = $this->calculatePrivatePrice($courseBookingUsers, $course);
            } else {
                $coursePrice = $this->calculateActivityPrice($courseBookingUsers, $course);
            }

            $breakdown['courses'][] = [
                'course_id' => $courseId,
                'course_name' => $course->name,
                'course_type' => $course->course_type,
                'participants' => $courseBookingUsers->groupBy('client_id')->count(),
                'price' => round($coursePrice, 2)
            ];
        }

        // Conceptos adicionales
        $breakdown['additional_concepts'] = $additionalConcepts;

        // Descuentos
        $breakdown['discounts'] = $discounts;

        return $breakdown;
    }

    // Métodos auxiliares
    private function parseDiscounts($discounts)
    {
        if (is_array($discounts)) return $discounts;
        if (is_string($discounts)) return json_decode($discounts, true) ?? [];
        return [];
    }

    private function parsePriceRange($priceRange)
    {
        if (is_array($priceRange)) return $priceRange;
        if (is_string($priceRange)) return json_decode($priceRange, true) ?? [];
        return [];
    }

    private function calculateDurationMinutes($startTime, $endTime): int
    {
        try {
            // Asegurarse de que tienen formato completo
            $startTime = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
            $endTime = strlen($endTime) === 5 ? $endTime . ':00' : $endTime;

            $start = Carbon::createFromFormat('H:i:s', $startTime);
            $end = Carbon::createFromFormat('H:i:s', $endTime);

            if (!$start || !$end) {
                throw new \Exception("Tiempos inválidos: $startTime - $endTime");
            }

            if ($end->lt($start)) {
                $end->addDay();
            }

            return $start->diffInMinutes($end);
        } catch (\Exception $e) {
            Log::channel('finance')->warning("Error al calcular duración: " . $e->getMessage());
            return 0; // Valor por defecto si hay error
        }
    }

    private function getDurationInterval($minutes): string
    {
        $minutes = (int) $minutes;
        if ($minutes <= 0) {
            return "0m";
        }

        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        if ($remaining === 0) {
            return "{$hours}h 0min";
        }

        return "{$hours}h {$remaining}m";
    }

    private function getPriceFromRange($priceRange, $interval, $participants): float
    {
        foreach ($priceRange as $range) {
            if (!isset($range['intervalo'])) {
                continue;
            }

            if ($range['intervalo'] === $interval) {
                return $range[$participants] ?? 0;
            }
        }

        $normalizedInterval = $this->normalizeIntervalLabel($interval);

        foreach ($priceRange as $range) {
            if (!isset($range['intervalo'])) {
                continue;
            }

            if ($this->normalizeIntervalLabel($range['intervalo']) === $normalizedInterval) {
                return $range[$participants] ?? 0;
            }
        }
        return 0;
    }

    private function normalizeIntervalLabel(?string $label): string
    {
        $label = strtolower(trim((string) $label));
        $label = str_replace(' ', '', $label);
        $label = str_replace('min', 'm', $label);
        return $label;
    }

    /**
     * MÉTODO PRINCIPAL: Análisis completo de realidad financiera
     * Unifica todo el análisis en un solo método comprehensivo
     */
    public function getCompleteFinancialReality(Booking $booking, array $options = []): array
    {
        $excludeCourses = $options['exclude_courses'] ?? [260, 243];

        Log::channel('finance')->info("=== INICIANDO ANÁLISIS COMPLETO REALIDAD FINANCIERA ===", [
            'booking_id' => $booking->id,
            'booking_status' => $booking->status,
            'exclude_courses' => $excludeCourses
        ]);

        try {
            // 1. CALCULAR LO QUE DEBERÍA COSTAR
            $calculatedData = $this->calculateBookingTotal($booking, $options);

            // 2. OBTENER REALIDAD FINANCIERA DETALLADA
            $financialReality = $this->getDetailedFinancialReality($booking);

            // 3. ANALIZAR PAGOS CRONOLÓGICAMENTE
            $paymentAnalysis = $this->analyzePaymentTimeline($booking);

            // 4. ANALIZAR VOUCHERS INTELIGENTEMENTE
            $voucherAnalysis = $this->analyzeVouchersIntelligently($booking);

            // 5. DETECTAR DISCREPANCIAS Y PROBLEMAS
            $discrepancyAnalysis = $this->detectFinancialDiscrepancies($booking, $calculatedData, $financialReality);

            // 6. GENERAR RECOMENDACIONES ESPECÍFICAS
            $recommendations = $this->generateSpecificRecommendations($booking, $discrepancyAnalysis);

            // 7. CALCULAR MÉTRICAS DE CONSISTENCIA
            $consistencyMetrics = $this->calculateConsistencyMetrics($booking, $calculatedData, $financialReality);

            $result = [
                'booking_id' => $booking->id,
                'analysis_timestamp' => now()->toDateTimeString(),
                'booking_info' => $this->getBookingBasicInfo($booking),

                // PRECIOS Y CÁLCULOS
                'calculated_data' => $calculatedData,
                'stored_price_info' => [
                    'price_total' => $booking->price_total,
                    'note' => 'Solo informativo - no usado para análisis de consistencia'
                ],

                // REALIDAD FINANCIERA
                'financial_reality' => $financialReality,
                'payment_analysis' => $paymentAnalysis,
                'voucher_analysis' => $voucherAnalysis,

                // ANÁLISIS Y PROBLEMAS
                'discrepancy_analysis' => $discrepancyAnalysis,
                'consistency_metrics' => $consistencyMetrics,
                'detected_issues' => $this->detectAllIssues($booking, $financialReality, $calculatedData),

                // RECOMENDACIONES
                'recommendations' => $recommendations,
                'action_required' => $this->determineActionRequired($discrepancyAnalysis),

                // METADATOS
                'analysis_method' => 'complete_financial_reality_v2',
                'confidence_score' => $this->calculateConfidenceScore($financialReality, $paymentAnalysis),
                'reliability_flags' => $this->getReliabilityFlags($booking, $financialReality)
            ];

            Log::channel('finance')->info("=== ANÁLISIS COMPLETO FINALIZADO ===", [
                'booking_id' => $booking->id,
                'is_consistent' => $discrepancyAnalysis['is_financially_consistent'],
                'main_discrepancy' => $discrepancyAnalysis['main_discrepancy_amount'],
                'confidence_score' => $result['confidence_score']
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::channel('finance')->error("Error en análisis financiero completo: " . $e->getMessage(), [
                'booking_id' => $booking->id,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->getErrorFallback($booking, $e);
        }
    }

    /**
     * MÉTODO MEJORADO: Realidad financiera detallada con clasificación inteligente
     */
    private function getDetailedFinancialReality(Booking $booking): array
    {
        $payments = $booking->payments;
        $voucherLogs = $booking->vouchersLogs;

        // ANÁLISIS DE PAGOS POR CATEGORÍA
        $paidPayments = $payments->where('status', 'paid');
        $refundPayments = $payments->whereIn('status', ['refund', 'partial_refund']);
        $noRefundPayments = $payments->where('status', 'no_refund');

        // CLASIFICAR NO_REFUNDS EN PRE/POST PAGO
        $noRefundClassification = $this->classifyNoRefundPayments($noRefundPayments, $paidPayments);

        // ANALIZAR VOUCHERS CON CONTEXTO TEMPORAL
        $voucherAnalysis = $this->analyzeVouchersWithTemporalContext($voucherLogs, $booking);

        // CALCULAR FLUJOS NETOS
        $totalReceived = $paidPayments->sum('amount') + $voucherAnalysis['total_used'];
        $totalProcessedPostPayment = $refundPayments->sum('amount') +
            $voucherAnalysis['total_refunded'] +
            $noRefundClassification['post_payment_total'];

        $netBalance = $totalReceived - $totalProcessedPostPayment;

        return [
            // INGRESOS
            'total_paid' => round($paidPayments->sum('amount'), 2),
            'total_vouchers_used' => round($voucherAnalysis['total_used'], 2),
            'total_received' => round($totalReceived, 2),

            // EGRESOS/PROCESAMIENTOS
            'total_refunded' => round($refundPayments->sum('amount'), 2),
            'total_vouchers_refunded' => round($voucherAnalysis['total_refunded'], 2),
            'total_no_refund_post_payment' => round($noRefundClassification['post_payment_total'], 2),
            'total_no_refund_pre_payment' => round($noRefundClassification['pre_payment_total'], 2),
            'total_processed' => round($totalProcessedPostPayment, 2),

            // BALANCE NETO
            'net_balance' => round($netBalance, 2),

            // DETALLES CLASIFICADOS
            'payment_details' => $this->getClassifiedPaymentDetails($booking),
            'voucher_details' => $voucherAnalysis['details'],
            'no_refund_classification' => $noRefundClassification,

            // MÉTRICAS ADICIONALES
            'payment_methods_breakdown' => $this->getPaymentMethodsBreakdown($paidPayments),
            'temporal_analysis' => $this->getTemporalAnalysis($booking),
            'cash_flow_summary' => [
                'inflow' => $totalReceived,
                'outflow' => $totalProcessedPostPayment,
                'net_position' => $netBalance
            ]
        ];
    }

    /**
     * NUEVO: Análisis temporal de pagos para detectar patrones
     */
    private function analyzePaymentTimeline(Booking $booking): array
    {
        $allPayments = $booking->payments->sortBy('created_at');
        $voucherLogs = $booking->vouchersLogs->sortBy('created_at');

        $timeline = [];
        $runningBalance = 0;
        $milestones = [];

        // CREAR TIMELINE UNIFICADO
        foreach ($allPayments as $payment) {
            $timeline[] = [
                'type' => 'payment',
                'timestamp' => $payment->created_at,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'cumulative_effect' => $payment->status === 'paid' ? $payment->amount :
                    (in_array($payment->status, ['refund', 'partial_refund', 'no_refund']) ? -$payment->amount : 0),
                'notes' => $payment->notes,
                'payment_id' => $payment->id
            ];
        }

        foreach ($voucherLogs as $voucherLog) {
            $voucherAnalysis = $this->determineVoucherLogType($voucherLog, $voucherLog->voucher, $booking);

            $timeline[] = [
                'type' => 'voucher',
                'timestamp' => $voucherLog->created_at,
                'amount' => $voucherLog->amount,
                'interpreted_type' => $voucherAnalysis['type'],
                'cumulative_effect' => $voucherAnalysis['type'] === 'payment' ?
                    $voucherAnalysis['amount'] : -$voucherAnalysis['amount'],
                'voucher_code' => $voucherLog->voucher->code ?? 'N/A',
                'voucher_log_id' => $voucherLog->id
            ];
        }

        // ORDENAR CRONOLÓGICAMENTE
        usort($timeline, function($a, $b) {
            return $a['timestamp']->timestamp <=> $b['timestamp']->timestamp;
        });

        // CALCULAR BALANCE ACUMULATIVO Y DETECTAR HITOS
        foreach ($timeline as &$event) {
            $runningBalance += $event['cumulative_effect'];
            $event['running_balance'] = round($runningBalance, 2);

            // DETECTAR HITOS IMPORTANTES
            if ($event['type'] === 'payment' && $event['status'] === 'paid' && count($milestones) === 0) {
                $milestones[] = [
                    'type' => 'first_payment',
                    'timestamp' => $event['timestamp'],
                    'amount' => $event['amount'],
                    'running_balance' => $event['running_balance']
                ];
            }

            if (abs($event['running_balance']) < 0.01 && $runningBalance !== 0) {
                $milestones[] = [
                    'type' => 'balance_zero',
                    'timestamp' => $event['timestamp'],
                    'description' => 'Balance llegó a cero'
                ];
            }
        }

        return [
            'timeline' => $timeline,
            'milestones' => $milestones,
            'final_balance' => round($runningBalance, 2),
            'total_events' => count($timeline),
            'payment_events' => count(array_filter($timeline, fn($e) => $e['type'] === 'payment')),
            'voucher_events' => count(array_filter($timeline, fn($e) => $e['type'] === 'voucher')),
            'analysis_summary' => $this->summarizePaymentTimeline($timeline, $milestones)
        ];
    }

    /**
     * NUEVO: Análisis inteligente de vouchers con contexto completo
     */
    private function analyzeVouchersIntelligently(Booking $booking): array
    {
        $voucherLogs = $booking->vouchersLogs;

        if ($voucherLogs->isEmpty()) {
            return [
                'has_vouchers' => false,
                'total_used' => 0,
                'total_refunded' => 0,
                'net_voucher_contribution' => 0,
                'voucher_details' => [],
                'analysis_notes' => ['No vouchers found']
            ];
        }

        $voucherDetails = [];
        $totalUsed = 0;
        $totalRefunded = 0;
        $analysisNotes = [];

        // AGRUPAR POR VOUCHER PARA ANÁLISIS COMPLETO
        $voucherGroups = $voucherLogs->groupBy('voucher_id');

        foreach ($voucherGroups as $voucherId => $logs) {
            $voucher = $logs->first()->voucher;
            if (!$voucher) continue;

            $voucherAnalysis = $this->analyzeIndividualVoucher($voucher, $logs, $booking);

            $totalUsed += $voucherAnalysis['total_used'];
            $totalRefunded += $voucherAnalysis['total_refunded'];

            $voucherDetails[] = $voucherAnalysis;

            if (!empty($voucherAnalysis['warnings'])) {
                $analysisNotes = array_merge($analysisNotes, $voucherAnalysis['warnings']);
            }
        }

        return [
            'has_vouchers' => true,
            'total_vouchers' => $voucherGroups->count(),
            'total_logs' => $voucherLogs->count(),
            'total_used' => round($totalUsed, 2),
            'total_refunded' => round($totalRefunded, 2),
            'net_voucher_contribution' => round($totalUsed - $totalRefunded, 2),
            'voucher_details' => $voucherDetails,
            'analysis_notes' => $analysisNotes,
            'voucher_consistency_score' => $this->calculateVoucherConsistencyScore($voucherDetails)
        ];
    }

    /**
     * NUEVO: Detectar discrepancias financieras con análisis profundo
     */
    private function detectFinancialDiscrepancies(Booking $booking, array $calculatedData, array $financialReality): array
    {
        $calculatedTotal = $calculatedData['total_final'];
        $netBalance = $financialReality['net_balance'];
        $bookingStatus = $booking->status;

        $discrepancies = [];
        $isConsistent = true;
        $mainDiscrepancy = 0;
        $tolerance = 0.50;

        // ANÁLISIS ESPECÍFICO POR ESTADO DE RESERVA
        switch ($bookingStatus) {
            case 1: // ACTIVA
                $expectedBalance = $calculatedTotal;
                $mainDiscrepancy = $expectedBalance - $netBalance;
                $isConsistent = abs($mainDiscrepancy) <= $tolerance;

                if (!$isConsistent) {
                    if ($netBalance < $expectedBalance) {
                        $discrepancies[] = [
                            'type' => 'underpayment',
                            'severity' => $mainDiscrepancy > 10 ? 'high' : 'medium',
                            'amount' => round($mainDiscrepancy, 2),
                            'description' => "Falta pago: se necesita " . round($mainDiscrepancy, 2) . "€ más"
                        ];
                    } else {
                        $discrepancies[] = [
                            'type' => 'overpayment',
                            'severity' => abs($mainDiscrepancy) > 10 ? 'high' : 'medium',
                            'amount' => round(abs($mainDiscrepancy), 2),
                            'description' => "Exceso de pago: se recibió " . round(abs($mainDiscrepancy), 2) . "€ de más"
                        ];
                    }
                }
                break;

            case 2: // CANCELADA
                $expectedBalance = 0; // Todo debería estar procesado
                $mainDiscrepancy = $netBalance;
                $isConsistent = abs($mainDiscrepancy) <= $tolerance;

                if (!$isConsistent) {
                    if ($netBalance > 0) {
                        $discrepancies[] = [
                            'type' => 'unprocessed_cancellation',
                            'severity' => 'high',
                            'amount' => round($netBalance, 2),
                            'description' => "Reserva cancelada con " . round($netBalance, 2) . "€ sin procesar"
                        ];
                    } else {
                        $discrepancies[] = [
                            'type' => 'overprocessed_cancellation',
                            'severity' => 'medium',
                            'amount' => round(abs($netBalance), 2),
                            'description' => "Se procesó más dinero del recibido en cancelación"
                        ];
                    }
                }
                break;

            case 3: // PARCIALMENTE CANCELADA
                // Calcular precio de usuarios activos
                $activeUsersPrice = $this->calculateActivePriceForPartialCancellation($booking, $calculatedData);
                $expectedBalance = $activeUsersPrice;
                $mainDiscrepancy = $expectedBalance - $netBalance;
                $isConsistent = abs($mainDiscrepancy) <= $tolerance;

                if (!$isConsistent) {
                    $discrepancies[] = [
                        'type' => 'partial_cancellation_discrepancy',
                        'severity' => abs($mainDiscrepancy) > 10 ? 'high' : 'medium',
                        'amount' => round(abs($mainDiscrepancy), 2),
                        'description' => "Cancelación parcial con discrepancia de " . round($mainDiscrepancy, 2) . "€",
                        'active_users_price' => $activeUsersPrice
                    ];
                }
                break;
        }

        // DETECTAR OTROS PROBLEMAS ESPECÍFICOS
        $otherIssues = $this->detectAdditionalFinancialIssues($booking, $financialReality, $calculatedData);
        $discrepancies = array_merge($discrepancies, $otherIssues);

        return [
            'is_financially_consistent' => $isConsistent,
            'main_discrepancy_amount' => round(abs($mainDiscrepancy), 2),
            'main_discrepancy_direction' => $mainDiscrepancy > 0 ? 'shortfall' : 'excess',
            'consistency_type' => $this->getConsistencyType($bookingStatus),
            'expected_balance' => $expectedBalance ?? $calculatedTotal,
            'actual_balance' => $netBalance,
            'tolerance_used' => $tolerance,
            'discrepancies' => $discrepancies,
            'total_issues' => count($discrepancies),
            'severity_breakdown' => $this->getDiscrepancySeverityBreakdown($discrepancies),
            'requires_immediate_attention' => $this->requiresImmediateAttention($discrepancies),
            'analysis_confidence' => $this->calculateDiscrepancyConfidence($booking, $financialReality)
        ];
    }

    /**
     * NUEVO: Generar recomendaciones específicas y accionables
     */
    private function generateSpecificRecommendations(Booking $booking, array $discrepancyAnalysis): array
    {
        $recommendations = [];

        if ($discrepancyAnalysis['is_financially_consistent']) {
            $recommendations[] = [
                'type' => 'success',
                'priority' => 'info',
                'title' => 'Estado Financiero Consistente',
                'description' => 'La realidad financiera coincide con el precio calculado',
                'action' => 'No se requiere acción',
                'icon' => '✅'
            ];
            return $recommendations;
        }

        // RECOMENDACIONES BASADAS EN DISCREPANCIAS
        foreach ($discrepancyAnalysis['discrepancies'] as $discrepancy) {
            switch ($discrepancy['type']) {
                case 'underpayment':
                    $recommendations[] = [
                        'type' => 'payment_required',
                        'priority' => $discrepancy['severity'],
                        'title' => 'Pago Pendiente',
                        'description' => $discrepancy['description'],
                        'action' => 'Contactar al cliente para completar el pago',
                        'amount' => $discrepancy['amount'],
                        'suggested_steps' => [
                            'Verificar la información de contacto del cliente',
                            'Enviar recordatorio de pago pendiente',
                            'Ofrecer métodos de pago alternativos si es necesario'
                        ],
                        'icon' => '💳'
                    ];
                    break;

                case 'overpayment':
                    $recommendations[] = [
                        'type' => 'refund_required',
                        'priority' => $discrepancy['severity'],
                        'title' => 'Reembolso Requerido',
                        'description' => $discrepancy['description'],
                        'action' => 'Procesar reembolso al cliente',
                        'amount' => $discrepancy['amount'],
                        'suggested_steps' => [
                            'Verificar los datos bancarios del cliente',
                            'Iniciar proceso de reembolso',
                            'Notificar al cliente sobre el reembolso'
                        ],
                        'icon' => '💰'
                    ];
                    break;

                case 'unprocessed_cancellation':
                    $recommendations[] = [
                        'type' => 'cancellation_processing',
                        'priority' => 'high',
                        'title' => 'Cancelación Sin Procesar',
                        'description' => $discrepancy['description'],
                        'action' => 'Decidir entre reembolso o no-reembolso según política',
                        'amount' => $discrepancy['amount'],
                        'suggested_steps' => [
                            'Revisar política de cancelación aplicable',
                            'Verificar fecha de cancelación vs fecha del curso',
                            'Procesar reembolso o aplicar no-reembolso según corresponda'
                        ],
                        'icon' => '❌'
                    ];
                    break;

                case 'partial_cancellation_discrepancy':
                    $recommendations[] = [
                        'type' => 'partial_review',
                        'priority' => $discrepancy['severity'],
                        'title' => 'Revisar Cancelación Parcial',
                        'description' => $discrepancy['description'],
                        'action' => 'Verificar cálculo de usuarios activos vs cancelados',
                        'suggested_steps' => [
                            'Confirmar qué usuarios están activos vs cancelados',
                            'Recalcular precio basado en usuarios activos',
                            'Ajustar balance según corresponda'
                        ],
                        'icon' => '⚖️'
                    ];
                    break;
            }
        }

        // RECOMENDACIONES ADICIONALES BASADAS EN CONTEXTO
        $this->addContextualRecommendations($booking, $recommendations);

        return $recommendations;
    }

    /**
     * NUEVO: Calcular métricas de consistencia financiera
     */
    private function calculateConsistencyMetrics(Booking $booking, array $calculatedData, array $financialReality): array
    {
        $metrics = [];

        // MÉTRICAS BÁSICAS
        $expectedTotal = $calculatedData['total_final'];
        $actualBalance = $financialReality['net_balance'];
        $totalReceived = $financialReality['total_received'];

        $metrics['price_accuracy'] = $expectedTotal > 0 ?
            round((1 - abs($expectedTotal - $actualBalance) / $expectedTotal) * 100, 2) : 100;

        $metrics['collection_rate'] = $expectedTotal > 0 ?
            round(($totalReceived / $expectedTotal) * 100, 2) : 0;

        $metrics['processing_completeness'] = $booking->status == 2 ?
            round((1 - abs($actualBalance) / max($totalReceived, 1)) * 100, 2) : null;

        // MÉTRICAS DE COMPLEJIDAD
        $paymentCount = $booking->payments->count();
        $voucherCount = $booking->vouchersLogs->count();
        $metrics['transaction_complexity'] = min(($paymentCount + $voucherCount) * 10, 100);

        // SCORE GENERAL
        $weights = [
            'price_accuracy' => 0.4,
            'collection_rate' => 0.3,
            'processing_completeness' => 0.2,
            'transaction_complexity' => 0.1
        ];

        $totalScore = 0;
        $weightSum = 0;

        foreach ($weights as $metric => $weight) {
            if (isset($metrics[$metric]) && $metrics[$metric] !== null) {
                $score = $metric === 'transaction_complexity' ?
                    (100 - $metrics[$metric]) : $metrics[$metric];
                $totalScore += $score * $weight;
                $weightSum += $weight;
            }
        }

        $metrics['overall_consistency_score'] = $weightSum > 0 ?
            round($totalScore / $weightSum, 2) : 0;

        return $metrics;
    }

    // ... MÉTODOS AUXILIARES ...

    private function getBookingBasicInfo(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'paid' => $booking->paid,
            'status_text' => $this->getStatusText($booking->status),
            'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,
            'client_email' => $booking->clientMain->email,
            'created_at' => $booking->created_at->toDateTimeString(),
            'source' => $booking->source,
            'currency' => $booking->currency,
            'school_id' => $booking->school_id
        ];
    }

    private function classifyNoRefundPayments(Collection $noRefundPayments, Collection $paidPayments): array
    {
        $prePaymentTotal = 0;
        $postPaymentTotal = 0;
        $details = [];

        if ($paidPayments->isEmpty()) {
            // Si no hay pagos, todos los no_refund son pre-payment
            $prePaymentTotal = $noRefundPayments->sum('amount');
            foreach ($noRefundPayments as $payment) {
                $details[] = [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'classification' => 'pre_payment',
                    'reason' => 'No hay pagos exitosos registrados'
                ];
            }
        } else {
            $firstPaymentDate = $paidPayments->min('created_at');

            foreach ($noRefundPayments as $payment) {
                if ($payment->created_at < $firstPaymentDate) {
                    $prePaymentTotal += $payment->amount;
                    $classification = 'pre_payment';
                    $reason = 'Aplicado antes del primer pago';
                } else {
                    $postPaymentTotal += $payment->amount;
                    $classification = 'post_payment';
                    $reason = 'Aplicado después del pago';
                }

                $details[] = [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'classification' => $classification,
                    'reason' => $reason,
                    'payment_date' => $payment->created_at,
                    'first_payment_date' => $firstPaymentDate
                ];
            }
        }

        return [
            'pre_payment_total' => round($prePaymentTotal, 2),
            'post_payment_total' => round($postPaymentTotal, 2),
            'total_no_refund' => round($prePaymentTotal + $postPaymentTotal, 2),
            'classification_details' => $details,
            'has_problematic_post_payment' => $postPaymentTotal > 0.50
        ];
    }

    private function analyzeVouchersWithTemporalContext(Collection $voucherLogs, Booking $booking): array
    {
        $totalUsed = 0;
        $totalRefunded = 0;
        $details = [];

        foreach ($voucherLogs as $voucherLog) {
            $voucher = $voucherLog->voucher;
            if (!$voucher) continue;

            $analysis = $this->determineVoucherLogType($voucherLog, $voucher, $booking);

            if ($analysis['type'] === 'payment') {
                $totalUsed += $analysis['amount'];
            } else {
                $totalRefunded += $analysis['amount'];
            }

            $details[] = [
                'voucher_log_id' => $voucherLog->id,
                'voucher_code' => $voucher->code,
                'original_amount' => $voucherLog->amount,
                'interpreted_amount' => $analysis['amount'],
                'interpreted_type' => $analysis['type'],
                'reason' => $analysis['reason'],
                'voucher_status' => [
                    'quantity' => $voucher->quantity,
                    'remaining_balance' => $voucher->remaining_balance,
                    'payed' => $voucher->payed
                ],
                'timestamp' => $voucherLog->created_at
            ];
        }

        return [
            'total_used' => $totalUsed,
            'total_refunded' => $totalRefunded,
            'details' => $details,
            'voucher_logs_count' => $voucherLogs->count()
        ];
    }

    private function getClassifiedPaymentDetails(Booking $booking): array
    {
        return $booking->payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'method' => $this->determinePaymentMethod($payment),
                'notes' => $payment->notes,
                'created_at' => $payment->created_at,
                'has_payrexx' => !empty($payment->payrexx_reference),
                'payrexx_reference' => $payment->payrexx_reference
            ];
        })->toArray();
    }

    private function getStatusText(int $status): string
    {
        $statusMap = [
            1 => 'Activa',
            2 => 'Cancelada',
            3 => 'Parcialmente Cancelada'
        ];

        return $statusMap[$status] ?? 'Desconocido';
    }

    private function getErrorFallback(Booking $booking, \Exception $e): array
    {
        return [
            'booking_id' => $booking->id,
            'error' => true,
            'error_message' => 'Error en análisis financiero: ' . $e->getMessage(),
            'fallback_data' => [
                'stored_price_total' => $booking->price_total,
                'basic_payment_sum' => $booking->payments->where('status', 'paid')->sum('amount'),
                'basic_voucher_sum' => $booking->vouchersLogs->sum('amount')
            ],
            'analysis_method' => 'error_fallback',
            'timestamp' => now()->toDateTimeString()
        ];
    }

    private function analyzeIndividualVoucher($voucher, Collection $logs, Booking $booking): array
    {
        $totalUsed = 0;
        $totalRefunded = 0;
        $logDetails = [];
        $warnings = [];

        foreach ($logs as $log) {
            $analysis = $this->determineVoucherLogType($log, $voucher, $booking);

            if ($analysis['type'] === 'payment') {
                $totalUsed += $analysis['amount'];
            } else {
                $totalRefunded += $analysis['amount'];
            }

            $logDetails[] = [
                'log_id' => $log->id,
                'amount' => $log->amount,
                'interpreted_amount' => $analysis['amount'],
                'interpreted_type' => $analysis['type'],
                'reason' => $analysis['reason'],
                'timestamp' => $log->created_at
            ];
        }

        // DETECTAR INCONSISTENCIAS EN EL VOUCHER
        $voucherUsedAmount = $voucher->quantity - $voucher->remaining_balance;

        if (abs($totalUsed - $voucherUsedAmount) > 0.01) {
            $warnings[] = "Inconsistencia: logs indican uso de {$totalUsed}€ pero voucher muestra uso de {$voucherUsedAmount}€";
        }

        if ($totalUsed > $voucher->quantity) {
            $warnings[] = "Uso excede cantidad original del voucher";
        }

        return [
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'voucher_quantity' => $voucher->quantity,
            'voucher_remaining_balance' => $voucher->remaining_balance,
            'voucher_payed' => $voucher->payed,
            'total_used' => round($totalUsed, 2),
            'total_refunded' => round($totalRefunded, 2),
            'net_contribution' => round($totalUsed - $totalRefunded, 2),
            'log_details' => $logDetails,
            'warnings' => $warnings,
            'logs_count' => $logs->count(),
            'consistency_score' => $this->calculateVoucherLogConsistency($voucher, $totalUsed)
        ];
    }

    private function calculateVoucherConsistencyScore(array $voucherDetails): float
    {
        if (empty($voucherDetails)) return 100;

        $totalScore = 0;
        $voucherCount = 0;

        foreach ($voucherDetails as $voucher) {
            $score = $voucher['consistency_score'];
            $totalScore += $score;
            $voucherCount++;
        }

        return $voucherCount > 0 ? round($totalScore / $voucherCount, 2) : 100;
    }

    private function calculateVoucherLogConsistency($voucher, float $totalUsedFromLogs): float
    {
        $voucherUsedAmount = $voucher->quantity - $voucher->remaining_balance;
        $difference = abs($totalUsedFromLogs - $voucherUsedAmount);

        if ($voucherUsedAmount == 0) return 100;

        $consistencyPercentage = max(0, (1 - ($difference / $voucherUsedAmount)) * 100);
        return round($consistencyPercentage, 2);
    }

    private function summarizePaymentTimeline(array $timeline, array $milestones): array
    {
        $summary = [
            'first_transaction_date' => $timeline[0]['timestamp'] ?? null,
            'last_transaction_date' => end($timeline)['timestamp'] ?? null,
            'timeline_span_days' => 0,
            'payment_pattern' => 'unknown',
            'key_observations' => []
        ];

        if (!empty($timeline)) {
            $firstDate = $timeline[0]['timestamp'];
            $lastDate = end($timeline)['timestamp'];
            $summary['timeline_span_days'] = $firstDate->diffInDays($lastDate);
        }

        // DETERMINAR PATRÓN DE PAGO
        $paymentEvents = array_filter($timeline, fn($e) => $e['type'] === 'payment' && $e['status'] === 'paid');
        $paymentCount = count($paymentEvents);

        if ($paymentCount === 0) {
            $summary['payment_pattern'] = 'no_payments';
        } elseif ($paymentCount === 1) {
            $summary['payment_pattern'] = 'single_payment';
        } else {
            $summary['payment_pattern'] = 'multiple_payments';
        }

        // OBSERVACIONES CLAVE
        if (!empty($milestones)) {
            $summary['key_observations'][] = "Se detectaron " . count($milestones) . " hitos importantes";
        }

        $voucherEvents = array_filter($timeline, fn($e) => $e['type'] === 'voucher');
        if (!empty($voucherEvents)) {
            $summary['key_observations'][] = "Se usaron vouchers en " . count($voucherEvents) . " ocasiones";
        }

        return $summary;
    }

    private function calculateActivePriceForPartialCancellation(Booking $booking, array $calculatedData): float
    {
        // Para cancelaciones parciales, necesitamos calcular solo el precio de usuarios activos
        $excludedCourses = [260, 243];

        $activeBookingUsers = $booking->bookingUsers
            ->where('status', 1) // Solo activos
            ->filter(function ($bookingUser) use ($excludedCourses) {
                return !in_array((int)$bookingUser->course_id, $excludedCourses);
            });

        $activitiesPrice = $this->calculateActivitiesPrice($activeBookingUsers);

        // Añadir conceptos adicionales proporcionalmente
        $totalUsers = $booking->bookingUsers->where('status', '!=', 2)->count();
        $activeUsers = $activeBookingUsers->count();

        if ($totalUsers > 0) {
            $proportion = $activeUsers / $totalUsers;

            $additionalConcepts = $this->calculateAdditionalConcepts($booking, $activitiesPrice, true);
            $totalAdditional = array_sum($additionalConcepts);

            return $activitiesPrice + ($totalAdditional * $proportion);
        }

        return $activitiesPrice;
    }

    private function detectAdditionalFinancialIssues(Booking $booking, array $financialReality, array $calculatedData): array
    {
        $issues = [];

        // PROBLEMA: Vouchers exceden precio total
        if ($financialReality['total_vouchers_used'] > $calculatedData['total_final']) {
            $excess = $financialReality['total_vouchers_used'] - $calculatedData['total_final'];
            $issues[] = [
                'type' => 'voucher_excess',
                'severity' => 'medium',
                'amount' => round($excess, 2),
                'description' => "Vouchers usados ({$financialReality['total_vouchers_used']}€) exceden precio total ({$calculatedData['total_final']}€)"
            ];
        }

        // PROBLEMA: Pagos sin vouchers pero marcado como no pagado
        if (!$booking->paid && $financialReality['total_paid'] > $calculatedData['total_final'] * 0.8 && $financialReality['total_vouchers_used'] == 0) {
            $issues[] = [
                'type' => 'payment_status_inconsistency',
                'severity' => 'medium',
                'amount' => $financialReality['total_paid'],
                'description' => "Reserva marcada como no pagada pero se recibió pago significativo sin vouchers"
            ];
        }

        // PROBLEMA: Múltiples refunds sin lógica clara
        $refundCount = $booking->payments->whereIn('status', ['refund', 'partial_refund'])->count();
        if ($refundCount > 2) {
            $issues[] = [
                'type' => 'multiple_refunds',
                'severity' => 'low',
                'amount' => $financialReality['total_refunded'],
                'description' => "Múltiples refunds detectados ({$refundCount}) - revisar lógica de procesamiento"
            ];
        }

        return $issues;
    }

    private function getDiscrepancySeverityBreakdown(array $discrepancies): array
    {
        $breakdown = ['high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($discrepancies as $discrepancy) {
            $severity = $discrepancy['severity'] ?? 'low';
            $breakdown[$severity]++;
        }

        return $breakdown;
    }

    private function requiresImmediateAttention(array $discrepancies): bool
    {
        foreach ($discrepancies as $discrepancy) {
            if ($discrepancy['severity'] === 'high') {
                return true;
            }
            if ($discrepancy['type'] === 'unprocessed_cancellation') {
                return true;
            }
        }
        return false;
    }

    private function calculateDiscrepancyConfidence(Booking $booking, array $financialReality): float
    {
        $confidenceFactors = [];

        // Factor 1: Claridad de los datos de pago
        $paymentClarity = $booking->payments->every(function($payment) {
            return !empty($payment->status) && !empty($payment->amount);
        });
        $confidenceFactors[] = $paymentClarity ? 25 : 10;

        // Factor 2: Consistencia de vouchers
        $voucherConsistency = true;
        foreach ($booking->vouchersLogs as $log) {
            if (!$log->voucher) {
                $voucherConsistency = false;
                break;
            }
        }
        $confidenceFactors[] = $voucherConsistency ? 25 : 15;

        // Factor 3: Completitud de la información
        $hasCompleteInfo = !empty($booking->price_total) && $booking->bookingUsers->isNotEmpty();
        $confidenceFactors[] = $hasCompleteInfo ? 25 : 10;

        // Factor 4: Simplicidad de la transacción
        $transactionComplexity = $booking->payments->count() + $booking->vouchersLogs->count();
        $simplicityScore = max(0, 25 - ($transactionComplexity * 2));
        $confidenceFactors[] = $simplicityScore;

        return round(array_sum($confidenceFactors), 2);
    }

    private function getConsistencyType(int $bookingStatus): string
    {
        $types = [
            1 => 'active_booking_balance',
            2 => 'cancelled_booking_processing',
            3 => 'partial_cancellation_balance'
        ];

        return $types[$bookingStatus] ?? 'unknown_status';
    }

    private function addContextualRecommendations(Booking $booking, array &$recommendations): void
    {
        // RECOMENDACIÓN: Actualizar flag de pagado
        if (!$booking->paid && $this->shouldBeMarkedAsPaid($booking)) {
            $recommendations[] = [
                'type' => 'status_update',
                'priority' => 'medium',
                'title' => 'Actualizar Estado de Pago',
                'description' => 'La reserva debería marcarse como pagada basado en el balance actual',
                'action' => 'Marcar reserva como pagada',
                'suggested_steps' => [
                    'Verificar que el balance sea suficiente',
                    'Actualizar campo "paid" a true',
                    'Notificar al cliente si es necesario'
                ],
                'icon' => '🔄'
            ];
        }

        // RECOMENDACIÓN: Revisar vouchers problemáticos
        $problematicVouchers = $this->getProblematicVouchers($booking);
        if (!empty($problematicVouchers)) {
            $recommendations[] = [
                'type' => 'voucher_review',
                'priority' => 'medium',
                'title' => 'Revisar Vouchers',
                'description' => 'Se detectaron inconsistencias en ' . count($problematicVouchers) . ' voucher(s)',
                'action' => 'Revisar y corregir datos de vouchers',
                'voucher_details' => $problematicVouchers,
                'icon' => '🎟️'
            ];
        }

        // RECOMENDACIÓN: Seguimiento post-resolución
        if ($this->hasHistoricalIssues($booking)) {
            $recommendations[] = [
                'type' => 'follow_up',
                'priority' => 'low',
                'title' => 'Seguimiento Recomendado',
                'description' => 'Esta reserva tuvo problemas anteriores, considerar seguimiento adicional',
                'action' => 'Programar revisión en 7 días',
                'icon' => '📅'
            ];
        }
    }

    private function detectAllIssues(Booking $booking, array $financialReality, array $calculatedData): array
    {
        $allIssues = [];

        // ISSUES DE DATOS
        if (empty($booking->clientMain)) {
            $allIssues[] = [
                'category' => 'data_integrity',
                'type' => 'missing_client',
                'severity' => 'high',
                'description' => 'Cliente principal no encontrado'
            ];
        }

        // ISSUES DE CÁLCULO
        if ($calculatedData['total_final'] <= 0) {
            $allIssues[] = [
                'category' => 'calculation',
                'type' => 'zero_price',
                'severity' => 'high',
                'description' => 'Precio calculado es cero o negativo'
            ];
        }

        // ISSUES DE BALANCE
        if ($booking->status == 1 && $financialReality['net_balance'] < 0) {
            $allIssues[] = [
                'category' => 'balance',
                'type' => 'negative_balance',
                'severity' => 'high',
                'description' => 'Balance negativo en reserva activa'
            ];
        }

        // ISSUES DE VOUCHERS
        foreach ($booking->vouchersLogs as $log) {
            if (!$log->voucher) {
                $allIssues[] = [
                    'category' => 'voucher',
                    'type' => 'orphaned_voucher_log',
                    'severity' => 'medium',
                    'description' => "VoucherLog {$log->id} sin voucher asociado"
                ];
            }
        }

        return $allIssues;
    }

    private function determineActionRequired(array $discrepancyAnalysis): string
    {
        if ($discrepancyAnalysis['is_financially_consistent']) {
            return 'none';
        }

        if ($discrepancyAnalysis['requires_immediate_attention']) {
            return 'immediate';
        }

        if ($discrepancyAnalysis['main_discrepancy_amount'] > 10) {
            return 'urgent';
        }

        if ($discrepancyAnalysis['main_discrepancy_amount'] > 1) {
            return 'routine';
        }

        return 'monitoring';
    }

    private function calculateConfidenceScore(array $financialReality, array $paymentAnalysis): float
    {
        $factors = [];

        // Factor de completitud de datos
        $hasCompleteData = !empty($financialReality['payment_details']) &&
            isset($financialReality['net_balance']);
        $factors[] = $hasCompleteData ? 30 : 10;

        // Factor de consistencia temporal
        $timelineConsistent = isset($paymentAnalysis['timeline']) &&
            count($paymentAnalysis['timeline']) > 0;
        $factors[] = $timelineConsistent ? 25 : 10;

        // Factor de claridad de transacciones
        $simpleTransactions = ($financialReality['total_received'] ?? 0) > 0 &&
            count($financialReality['payment_details'] ?? []) <= 5;
        $factors[] = $simpleTransactions ? 25 : 15;

        // Factor de coherencia de vouchers
        $voucherCoherence = ($financialReality['total_vouchers_used'] ?? 0) <=
            ($financialReality['total_received'] ?? 0);
        $factors[] = $voucherCoherence ? 20 : 5;

        return round(array_sum($factors), 2);
    }

    private function getReliabilityFlags(Booking $booking, array $financialReality): array
    {
        $flags = [];

        // FLAG: Datos incompletos
        if (empty($booking->clientMain)) {
            $flags[] = 'missing_client_data';
        }

        // FLAG: Transacciones complejas
        $totalTransactions = $booking->payments->count() + $booking->vouchersLogs->count();
        if ($totalTransactions > 10) {
            $flags[] = 'complex_transaction_history';
        }

        // FLAG: Vouchers sin datos completos
        foreach ($booking->vouchersLogs as $log) {
            if (!$log->voucher) {
                $flags[] = 'incomplete_voucher_data';
                break;
            }
        }

        // FLAG: Discrepancias históricas
        if ($this->hasHistoricalIssues($booking)) {
            $flags[] = 'historical_issues';
        }

        return $flags;
    }

    // Métodos auxiliares simples

    private function getPaymentMethodsBreakdown(Collection $paidPayments): array
    {
        $breakdown = [];

        foreach ($paidPayments as $payment) {
            $method = $this->determinePaymentMethod($payment);
            $breakdown[$method] = ($breakdown[$method] ?? 0) + $payment->amount;
        }

        return $breakdown;
    }

    private function getTemporalAnalysis(Booking $booking): array
    {
        $now = now();
        $bookingDate = $booking->created_at;

        return [
            'booking_age_days' => $bookingDate->diffInDays($now),
            'first_payment_delay' => $this->getFirstPaymentDelay($booking),
            'last_activity' => $this->getLastActivityDate($booking),
            'is_recent' => $bookingDate->diffInDays($now) <= 7
        ];
    }

    private function determinePaymentMethod($payment): string
    {
        if ($payment->payrexx_reference) {
            return 'online';
        }

        $notes = strtolower($payment->notes ?? '');

        if (str_contains($notes, 'cash') || str_contains($notes, 'efectivo')) {
            return 'cash';
        }

        if (str_contains($notes, 'transfer') || str_contains($notes, 'transferencia')) {
            return 'transfer';
        }

        return 'other';
    }

    private function shouldBeMarkedAsPaid(Booking $booking): bool
    {
        if ($booking->paid) return false;

        $calculation = $this->calculateBookingTotal($booking);
        $balance = $booking->getCurrentBalance();

        return $balance['current_balance'] >= $calculation['total_final'] - 0.50;
    }

    private function getProblematicVouchers(Booking $booking): array
    {
        $problematic = [];

        foreach ($booking->vouchersLogs as $log) {
            if (!$log->voucher) {
                $problematic[] = [
                    'log_id' => $log->id,
                    'issue' => 'missing_voucher',
                    'amount' => $log->amount
                ];
            }
        }

        return $problematic;
    }

    private function hasHistoricalIssues(Booking $booking): bool
    {
        // Verificar si hay múltiples refunds o patrones sospechosos
        $refundCount = $booking->payments->whereIn('status', ['refund', 'partial_refund'])->count();
        return $refundCount > 1;
    }

    private function getFirstPaymentDelay(Booking $booking): ?int
    {
        $firstPayment = $booking->payments->where('status', 'paid')->sortBy('created_at')->first();

        if (!$firstPayment) return null;

        return $booking->created_at->diffInHours($firstPayment->created_at);
    }

    private function getLastActivityDate(Booking $booking): ?Carbon
    {
        $lastPayment = $booking->payments->sortByDesc('created_at')->first();
        $lastVoucher = $booking->vouchersLogs->sortByDesc('created_at')->first();

        $dates = array_filter([
            $lastPayment?->created_at,
            $lastVoucher?->created_at,
            $booking->updated_at
        ]);

        return empty($dates) ? null : max($dates);
    }

}



