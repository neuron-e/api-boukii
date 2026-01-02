<?php

namespace App\Services\Finance\Analyzers;

use App\Models\Booking;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Analizador de Reservas
 *
 * Responsabilidades:
 * - Clasificar reservas (producción vs prueba)
 * - Analizar orígenes de reservas
 * - Analizar distribución por estados
 * - Detectar patrones en reservas
 */
class BookingAnalyzer
{
    // Criterios para detectar reservas de prueba
    const TEST_DETECTION_CRITERIA = [
        'test_clients' => [
            'test', 'prueba', 'demo', 'example', 'admin', 'sistema'
        ],
        'test_emails' => [
            'test@', 'prueba@', 'demo@', 'admin@', 'noreply@'
        ],
        'test_sources' => [
            'admin_test', 'system_test', 'demo'
        ]
    ];

    /**
     * Clasificar reservas en categorías
     */
    public function classifyBookings(Collection $bookings): array
    {
        $classification = [
            'production' => collect(),
            'test' => collect(),
            'cancelled' => collect(),
            'total_count' => $bookings->count()
        ];

        foreach ($bookings as $booking) {
            $category = $this->classifyBooking($booking);
            $classification[$category]->push($booking);
        }

        // Calcular resumen
        $classification['summary'] = [
            'production_count' => $classification['production']->count(),
            'test_count' => $classification['test']->count(),
            'cancelled_count' => $classification['cancelled']->count(),
            'total_count' => $classification['total_count'],
            'production_percentage' => $this->calculatePercentage($classification['production']->count(), $classification['total_count']),
            'test_percentage' => $this->calculatePercentage($classification['test']->count(), $classification['total_count']),
            'cancelled_percentage' => $this->calculatePercentage($classification['cancelled']->count(), $classification['total_count'])
        ];

        Log::channel('finance')->info('Clasificación de reservas completada', $classification['summary']);

        return $classification;
    }



    /**
     * Clasificar una reserva individual
     */
    public function classifyBooking(Booking $booking): string
    {
        // 1. Primero verificar si está cancelada
        if ($booking->status === 'cancelled') {
            return 'cancelled';
        }

        // 2. Verificar si es una reserva de prueba
        if ($this->isTestBooking($booking)) {
            return 'test';
        }

        // 3. Por defecto es producción
        return 'production';
    }

    /**
     * Verificar si una reserva es de prueba
     */
    public function isTestBooking(Booking $booking): bool
    {
        // Verificar cliente de prueba
        if ($this->hasTestClient($booking)) {
            return true;
        }

        // Verificar email de prueba
        if ($this->hasTestEmail($booking)) {
            return true;
        }

        // Verificar origen de prueba
        if ($this->hasTestSource($booking)) {
            return true;
        }

        // Verificar patrones adicionales
        if ($this->hasTestPatterns($booking)) {
            return true;
        }

        return false;
    }

    /**
     * Obtener la razón por la que una reserva se considera de prueba
     */
    public function getTestReason(Booking $booking): string
    {
        if ($this->hasTestClient($booking)) {
            return 'Cliente de prueba detectado';
        }

        if ($this->hasTestEmail($booking)) {
            return 'Email de prueba detectado';
        }

        if ($this->hasTestSource($booking)) {
            return 'Origen de prueba detectado';
        }

        if ($this->hasTestPatterns($booking)) {
            return 'Patrón de prueba detectado';
        }

        return 'No es reserva de prueba';
    }

    /**
     * Analizar orígenes de reservas
     */
    public function analyzeBookingSources(Collection $bookings): array
    {
        $sources = $bookings->groupBy('source')->map(function ($group, $source) use ($bookings) {
            $count = $group->count();
            return [
                'count' => $count,
                'percentage' => $this->calculatePercentage($count, $bookings->count()),
                'total_revenue' => $this->calculateGroupRevenue($group)
            ];
        });

        return $sources->toArray();
    }

    /**
     * Analizar reservas por estado
     */
    public function analyzeBookingsByStatus(Collection $bookings): array
    {
        $statusAnalysis = $bookings->groupBy('status')->map(function ($group, $status) use ($bookings) {
            $count = $group->count();
            return [
                'count' => $count,
                'percentage' => $this->calculatePercentage($count, $bookings->count()),
                'average_value' => $this->calculateAverageBookingValue($group),
                'total_revenue' => $this->calculateGroupRevenue($group)
            ];
        });

        // Agregar información de estados críticos
        $statusAnalysis['critical_analysis'] = [
            'pending_bookings' => $bookings->where('status', 'pending')->count(),
            'paid_bookings' => $bookings->where('status', 'paid')->count(),
            'confirmed_bookings' => $bookings->where('status', 'confirmed')->count(),
            'pending_revenue_risk' => $this->assessPendingRevenueRisk($bookings)
        ];

        return $statusAnalysis->toArray();
    }

    /**
     * Obtener criterios de detección de pruebas
     */
    public function getTestDetectionCriteria(): array
    {
        return self::TEST_DETECTION_CRITERIA;
    }

    /**
     * Verificar si tiene cliente de prueba
     */
    private function hasTestClient(Booking $booking): bool
    {
        $clientName = strtolower($booking->clientMain->name ?? '');

        foreach (self::TEST_DETECTION_CRITERIA['test_clients'] as $testTerm) {
            if (str_contains($clientName, $testTerm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar si tiene email de prueba
     */
    private function hasTestEmail(Booking $booking): bool
    {
        $email = strtolower($booking->clientMain->email ?? '');

        foreach (self::TEST_DETECTION_CRITERIA['test_emails'] as $testEmail) {
            if (str_contains($email, $testEmail)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar si tiene origen de prueba
     */
    private function hasTestSource(Booking $booking): bool
    {
        $source = strtolower($booking->source ?? '');

        return in_array($source, self::TEST_DETECTION_CRITERIA['test_sources']);
    }

    /**
     * Verificar patrones adicionales de prueba
     */
    private function hasTestPatterns(Booking $booking): bool
    {
        // Verificar si la reserva fue creada muy rápidamente (posible automatización)
        if ($booking->created_at && $booking->updated_at) {
            $timeDiff = $booking->created_at->diffInSeconds($booking->updated_at);
            if ($timeDiff < 5) { // Menos de 5 segundos entre creación y actualización
                return true;
            }
        }

        // Verificar si tiene un ID muy bajo (posiblemente reserva de desarrollo)
        if ($booking->id <= 100) {
            return true;
        }

        return false;
    }

    /**
     * Calcular porcentaje
     */
    private function calculatePercentage(int $count, int $total): float
    {
        return $total > 0 ? round(($count / $total) * 100, 2) : 0;
    }

    /**
     * Calcular ingresos de un grupo de reservas
     */
    private function calculateGroupRevenue(Collection $bookings): float
    {
        return $bookings->sum(function ($booking) {
            // Aquí iría la lógica de cálculo de ingresos por reserva
            // Placeholder - implementar según el servicio de cálculo de precios
            return $booking->total_amount ?? 0;
        });
    }

    /**
     * Calcular valor promedio de reserva
     */
    private function calculateAverageBookingValue(Collection $bookings): float
    {
        if ($bookings->isEmpty()) {
            return 0;
        }

        $totalRevenue = $this->calculateGroupRevenue($bookings);
        return round($totalRevenue / $bookings->count(), 2);
    }

    /**
     * Evaluar riesgo de ingresos pendientes
     */
    private function assessPendingRevenueRisk(Collection $bookings): string
    {
        $pendingBookings = $bookings->where('status', 'pending');
        $totalBookings = $bookings->count();

        if ($totalBookings === 0) {
            return 'none';
        }

        $pendingPercentage = ($pendingBookings->count() / $totalBookings) * 100;

        if ($pendingPercentage > 30) {
            return 'high';
        } elseif ($pendingPercentage > 15) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}

