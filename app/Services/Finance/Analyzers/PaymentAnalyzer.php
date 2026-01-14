<?php

namespace App\Services\Finance\Analyzers;

use App\Models\Booking;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Analizador de Métodos de Pago
 *
 * Responsabilidades:
 * - Analizar distribución de métodos de pago
 * - Calcular métricas por método
 * - Detectar problemas de pago
 * - Analizar pagos de reserva individual
 */
class PaymentAnalyzer
{
    public function analyzePaymentMethods(Collection $bookings): array
    {
        $paymentAnalysis = [];
        $totalAmount = 0;

        foreach ($bookings as $booking) {
            $paymentMethod = $this->getBookingPaymentMethod($booking);
            $bookingAmount = $this->getBookingTotalAmount($booking);

            if (!isset($paymentAnalysis[$paymentMethod])) {
                $paymentAnalysis[$paymentMethod] = [
                    'count' => 0,
                    'total_amount' => 0,
                    'average_amount' => 0,
                    'percentage' => 0
                ];
            }

            $paymentAnalysis[$paymentMethod]['count']++;
            $paymentAnalysis[$paymentMethod]['total_amount'] += $bookingAmount;
            $totalAmount += $bookingAmount;
        }

        // Calcular promedios y porcentajes
        $totalBookings = $bookings->count();
        foreach ($paymentAnalysis as $method => &$data) {
            $data['average_amount'] = $data['count'] > 0 ? round($data['total_amount'] / $data['count'], 2) : 0;
            $data['percentage'] = $totalBookings > 0 ? round(($data['count'] / $totalBookings) * 100, 2) : 0;
        }

        Log::channel('finance')->info('Payment methods analysis completed', [
            'total_bookings' => $totalBookings,
            'payment_methods' => array_keys($paymentAnalysis),
            'total_amount' => $totalAmount
        ]);

        return $paymentAnalysis;
    }

    /**
     * 🔧 MÉTODO MEJORADO: Obtener método de pago de una reserva
     *
     * Usa la lógica sofisticada de determinePaymentMethodImproved
     */
    private function getBookingPaymentMethod(Booking $booking): string
    {
        // Obtener el primer pago completado
        $payment = $booking->payments->where('status', 'completed')->first();

        if (!$payment) {
            // Si no hay pagos completados, verificar pending
            $payment = $booking->payments->where('status', 'pending')->first();

            if (!$payment) {
                return 'no_payment';
            }

            return 'pending';
        }

        return $this->determinePaymentMethodImproved($payment);
    }

    /**
     * 🔧 MÉTODO MEJORADO: Determinar método de pago con distinción link vs pasarela
     *
     * Esta es la lógica completa restaurada de FinanceController
     */
    private function determinePaymentMethodImproved($payment): string
    {
        $notes = strtolower($payment->notes ?? '');

        // Si tiene payrexx_reference, fue procesado online
        if ($payment->payrexx_reference) {
            if ($payment->booking->payment_method_id == Booking::ID_BOUKIIPAY) {
                return 'boukii_direct';  // Pasarela directa en la plataforma
            } else {
                return 'online_link';    // Vía link de email
            }
        }

        // Métodos offline basados en notas
        if (str_contains($notes, 'cash') || str_contains($notes, 'efectivo') || str_contains($notes, 'bar') || str_contains($notes, 'bargeld')) {
            return 'cash';
        }

        if (str_contains($notes, 'card') || str_contains($notes, 'tarjeta') || str_contains($notes, 'karte') || str_contains($notes, 'kredit')) {
            return 'card_offline';
        }

        if (str_contains($notes, 'transfer') || str_contains($notes, 'transferencia')) {
            return 'transfer';
        }

        // Fallback basado en payment_method_id (para pagos sin payrexx_reference)
        switch ($payment->booking->payment_method_id) {
            case Booking::ID_CASH:
                return 'cash';
            case Booking::ID_BOUKIIPAY:
                return 'boukii_offline';  // BoukiiPay sin payrexx = offline
            case Booking::ID_ONLINE:
                return 'online_link';
            default:
                return 'other';
        }
    }

    /**
     * Obtener importe total de una reserva
     */
    private function getBookingTotalAmount(Booking $booking): float
    {
        // Usar el método de cálculo que ya existe en el modelo
        if (method_exists($booking, 'getShouldCostAttribute')) {
            return $booking->getShouldCostAttribute();
        }

        // Fallback: sumar pagos completados
        return $booking->payments->where('status', 'completed')->sum('amount');
    }

    /**
     * Analizar pagos de una reserva específica
     */
    public function analyzeBookingPayments(Booking $booking): array
    {
        $payments = $booking->payments;

        return [
            'total_payments' => $payments->count(),
            'completed_payments' => $payments->where('status', 'completed')->count(),
            'pending_payments' => $payments->where('status', 'pending')->count(),
            'failed_payments' => $payments->where('status', 'failed')->count(),
            'total_paid_amount' => $payments->where('status', 'completed')->sum('amount'),
            'payment_methods_used' => $payments->map(function($payment) {
                return $this->determinePaymentMethodImproved($payment);
            })->unique()->values(),
            'payment_timeline' => $payments->map(function ($payment) {
                return [
                    'date' => $payment->created_at->format('Y-m-d H:i:s'),
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'method' => $this->determinePaymentMethodImproved($payment),
                    'payrexx_reference' => $payment->payrexx_reference
                ];
            })
        ];
    }

    /**
     * 🆕 MÉTODO AUXILIAR: Nombres display para métodos de pago
     */
    public function getPaymentMethodDisplayName($method): string
    {
        $names = [
            'boukii_direct' => 'BoukiiPay (Pasarela Directa)',
            'online_link' => 'Online (Vía Link)',
            'cash' => 'Efectivo',
            'card_offline' => 'Tarjeta (Offline)',
            'transfer' => 'Transferencia',
            'boukii_offline' => 'BoukiiPay (Offline)',
            'online_manual' => 'Online (Manual)',
            'pending' => 'Pago Pendiente',
            'no_payment' => 'Sin Pago',
            'other' => 'Otros'
        ];

        return $names[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    /**
     * 🆕 Analizar distribución online vs offline
     */
    public function analyzeOnlineVsOffline(Collection $bookings): array
    {
        $onlineRevenue = 0;
        $offlineRevenue = 0;
        $onlineCount = 0;
        $offlineCount = 0;

        foreach ($bookings as $booking) {
            $paymentMethod = $this->getBookingPaymentMethod($booking);
            $amount = $this->getBookingTotalAmount($booking);

            $isOnline = in_array($paymentMethod, ['boukii_direct', 'online_link']);

            if ($isOnline) {
                $onlineRevenue += $amount;
                $onlineCount++;
            } else {
                $offlineRevenue += $amount;
                $offlineCount++;
            }
        }

        $totalRevenue = $onlineRevenue + $offlineRevenue;
        $totalCount = $onlineCount + $offlineCount;

        return [
            'online' => [
                'revenue' => round($onlineRevenue, 2),
                'count' => $onlineCount,
                'revenue_percentage' => $totalRevenue > 0 ? round(($onlineRevenue / $totalRevenue) * 100, 2) : 0,
                'count_percentage' => $totalCount > 0 ? round(($onlineCount / $totalCount) * 100, 2) : 0
            ],
            'offline' => [
                'revenue' => round($offlineRevenue, 2),
                'count' => $offlineCount,
                'revenue_percentage' => $totalRevenue > 0 ? round(($offlineRevenue / $totalRevenue) * 100, 2) : 0,
                'count_percentage' => $totalCount > 0 ? round(($offlineCount / $totalCount) * 100, 2) : 0
            ]
        ];
    }
}

