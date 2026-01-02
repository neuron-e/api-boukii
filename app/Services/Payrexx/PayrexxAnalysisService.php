<?php
namespace App\Services\Payrexx;

use App\Models\Booking;
use App\Services\Payrexx\PayrexxService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Análisis de Payrexx
 *
 * Responsabilidades:
 * - Analizar consistencia entre sistema y Payrexx
 * - Detectar discrepancias de pagos
 * - Generar reportes de transacciones
 */
class PayrexxAnalysisService
{
    protected PayrexxService $payrexxService;

    public function __construct(PayrexxService $payrexxService)
    {
        $this->payrexxService = $payrexxService;
    }

    /**
     * Analizar reservas con Payrexx
     */
    public function analyzeBookingsWithPayrexx(Collection $bookings, ?string $startDate = null, ?string $endDate = null): array
    {
        Log::channel('payrexx')->info('Iniciando análisis de Payrexx', [
            'total_bookings' => $bookings->count(),
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $analysis = $this->initializeAnalysis();

        foreach ($bookings as $booking) {
            $this->analyzeBookingWithPayrexx($booking, $analysis);
        }

        $this->finalizeAnalysis($analysis);

        Log::channel('payrexx')->info('Análisis de Payrexx completado', [
            'total_bookings' => $analysis['total_bookings'],
            'total_system_amount' => $analysis['total_system_amount'],
            'total_payrexx_amount' => $analysis['total_payrexx_amount'],
            'total_discrepancies' => $analysis['total_discrepancies']
        ]);

        return $analysis;
    }

    /**
     * Inicializar estructura de análisis
     */
    private function initializeAnalysis(): array
    {
        return [
            'total_bookings' => 0,
            'bookings_by_status' => [],
            'amounts_by_status' => [],
            'total_system_amount' => 0,
            'total_payrexx_amount' => 0,
            'discrepancies_amount' => 0,
            'total_discrepancies' => 0,
            'payrexx_transactions' => [],
            'discrepancies_details' => [],
            'consistency_rate' => 0,
            'payment_methods' => []
        ];
    }

    /**
     * Analizar una reserva individual con Payrexx
     */
    private function analyzeBookingWithPayrexx(Booking $booking, array &$analysis): void
    {
        $analysis['total_bookings']++;

        // Incrementar contadores por estado
        $status = $booking->status;
        $analysis['bookings_by_status'][$status] = ($analysis['bookings_by_status'][$status] ?? 0) + 1;

        // Calcular amounts del sistema
        $systemAmount = $this->calculateSystemAmount($booking);
        $analysis['amounts_by_status'][$status] = ($analysis['amounts_by_status'][$status] ?? 0) + $systemAmount;
        $analysis['total_system_amount'] += $systemAmount;

        // Buscar transacciones de Payrexx relacionadas
        $payrexxTransactions = $this->findPayrexxTransactions($booking);

        if (!empty($payrexxTransactions)) {
            foreach ($payrexxTransactions as $transaction) {
                $payrexxAmount = $this->extractPayrexxAmount($transaction);
                $analysis['total_payrexx_amount'] += $payrexxAmount;

                $analysis['payrexx_transactions'][] = [
                    'booking_id' => $booking->id,
                    'transaction_id' => $transaction->getId(),
                    'payrexx_amount' => $payrexxAmount,
                    'system_amount' => $systemAmount,
                    'status' => $transaction->getStatus(),
                    'payment_method' => $this->getPaymentMethodFromTransaction($transaction)
                ];

                // Detectar discrepancias
                if (abs($systemAmount - $payrexxAmount) > 0.01) {
                    $analysis['total_discrepancies']++;
                    $analysis['discrepancies_amount'] += abs($systemAmount - $payrexxAmount);

                    $analysis['discrepancies_details'][] = [
                        'booking_id' => $booking->id,
                        'system_amount' => $systemAmount,
                        'payrexx_amount' => $payrexxAmount,
                        'difference' => $systemAmount - $payrexxAmount,
                        'status_match' => $this->checkStatusMatch($booking->status, $transaction->getStatus())
                    ];
                }
            }
        }
    }

    /**
     * Finalizar análisis con cálculos finales
     */
    private function finalizeAnalysis(array &$analysis): void
    {
        // Redondear amounts
        $analysis['total_system_amount'] = round($analysis['total_system_amount'], 2);
        $analysis['total_payrexx_amount'] = round($analysis['total_payrexx_amount'], 2);
        $analysis['discrepancies_amount'] = round($analysis['discrepancies_amount'], 2);
        $analysis['total_difference'] = round($analysis['total_system_amount'] - $analysis['total_payrexx_amount'], 2);

        // Calcular tasa de consistencia
        $analysis['consistency_rate'] = $analysis['total_bookings'] > 0
            ? round((($analysis['total_bookings'] - $analysis['total_discrepancies']) / $analysis['total_bookings']) * 100, 2)
            : 100;

        // Redondear amounts por estado
        foreach ($analysis['amounts_by_status'] as $key => $amount) {
            $analysis['amounts_by_status'][$key] = round($amount, 2);
        }
    }

    /**
     * Calcular amount del sistema para una reserva
     */
    private function calculateSystemAmount(Booking $booking): float
    {
        // Placeholder - implementar según el servicio de cálculo de precios
        return $booking->total_amount ?? 0;
    }

    /**
     * Buscar transacciones de Payrexx para una reserva
     */
    private function findPayrexxTransactions(Booking $booking): array
    {
        // Placeholder - implementar búsqueda real en Payrexx
        // Esto requeriría configuración específica de la escuela y llamadas a la API
        return [];
    }

    /**
     * Extraer amount de una transacción de Payrexx
     */
    private function extractPayrexxAmount($transaction): float
    {
        if (method_exists($transaction, 'getAmount')) {
            return $transaction->getAmount() / 100; // Convertir centavos a euros
        }
        return 0;
    }

    /**
     * Obtener método de pago de una transacción
     */
    private function getPaymentMethodFromTransaction($transaction): string
    {
        try {
            if (method_exists($transaction, 'getPsp') && $transaction->getPsp()) {
                $psp = $transaction->getPsp();
                if (is_array($psp) && isset($psp[0]['name'])) {
                    return $psp[0]['name'];
                }
            }

            if (method_exists($transaction, 'getPaymentMethod')) {
                return $transaction->getPaymentMethod();
            }

            return 'unknown';

        } catch (\Exception $e) {
            Log::channel('payrexx')->warning('Error obteniendo método de pago de transacción Payrexx', [
                'error' => $e->getMessage()
            ]);
            return 'unknown';
        }
    }

    /**
     * Verificar si los estados coinciden
     */
    private function checkStatusMatch(string $systemStatus, string $payrexxStatus): bool
    {
        $statusMap = [
            'paid' => ['confirmed', 'authorized', 'captured', 'paid', 'settled'],
            'refund' => ['refunded'],
            'partial_refund' => ['partially_refunded'],
            'pending' => ['waiting', 'processing', 'pending'],
            'failed' => ['failed', 'declined', 'error'],
            'cancelled' => ['cancelled', 'canceled']
        ];

        $validPayrexxStatuses = $statusMap[$systemStatus] ?? [];
        return in_array(strtolower($payrexxStatus), array_map('strtolower', $validPayrexxStatuses));
    }
}

