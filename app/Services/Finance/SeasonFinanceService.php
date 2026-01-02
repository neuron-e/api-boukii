<?php

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\Season;
use App\Services\Finance\Analyzers\BookingAnalyzer;
use App\Services\Finance\Analyzers\PaymentAnalyzer;
use App\Services\Finance\Analyzers\KpiCalculator;
use App\Services\Finance\Repositories\BookingFinanceRepository;
use App\Services\Payrexx\PayrexxAnalysisService;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Servicio Principal de Finanzas de Temporada
 *
 * Responsabilidades:
 * - Coordinar análisis financieros de temporada
 * - Gestionar clasificación de reservas
 * - Generar dashboards ejecutivos
 * - Calcular KPIs principales
 */
class SeasonFinanceService
{
    // Cursos a excluir de los cálculos
    const EXCLUDED_COURSES = [260, 243];

    protected BookingFinanceRepository $bookingRepository;
    protected BookingAnalyzer $bookingAnalyzer;
    protected PaymentAnalyzer $paymentAnalyzer;
    protected KpiCalculator $kpiCalculator;
    protected PayrexxAnalysisService $payrexxService;

    public function __construct(
        BookingFinanceRepository $bookingRepository,
        BookingAnalyzer $bookingAnalyzer,
        PaymentAnalyzer $paymentAnalyzer,
        KpiCalculator $kpiCalculator,
        PayrexxAnalysisService $payrexxService
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->bookingAnalyzer = $bookingAnalyzer;
        $this->paymentAnalyzer = $paymentAnalyzer;
        $this->kpiCalculator = $kpiCalculator;
        $this->payrexxService = $payrexxService;
    }

    /**
     * Generar dashboard completo de temporada
     */
    public function generateSeasonDashboard(Request $request): array
    {
        $startTime = microtime(true);
        $optimizationLevel = $request->get('optimization_level', 'balanced');

        Log::channel('finance')->debug('=== INICIANDO DASHBOARD EJECUTIVO ===', [
            'school_id' => $request->school_id,
            'optimization_level' => $optimizationLevel,
            'include_test_detection' => $request->boolean('include_test_detection', true),
            'include_payrexx' => $request->boolean('include_payrexx_analysis', false)
        ]);

        try {
            // 1. Determinar período de análisis
            $dateRange = $this->getSeasonDateRange($request);

            // 2. Obtener reservas optimizadas
            $bookings = $this->bookingRepository->getSeasonBookingsOptimized(
                $request,
                $dateRange,
                $optimizationLevel
            );

            // 3. Clasificar reservas (producción vs prueba)
            $classification = $this->bookingAnalyzer->classifyBookings($bookings);

            // 4. Construir dashboard
            $dashboard = [
                'season_info' => $this->buildSeasonInfo($dateRange, $request, $optimizationLevel, $classification),
                'executive_kpis' => $this->kpiCalculator->calculateExecutiveKpis($classification, $request),
                'booking_sources' => $this->bookingAnalyzer->analyzeBookingSources($bookings),
                'payment_methods' => $this->paymentAnalyzer->analyzePaymentMethods($bookings),
                'booking_status_analysis' => $this->bookingAnalyzer->analyzeBookingsByStatus($classification['production']),
                'financial_summary' => $this->calculateFinancialSummary($classification),
                'performance_metrics' => $this->calculatePerformanceMetrics($startTime, $bookings, $classification)
            ];

            // 5. Análisis adicionales opcionales
            if ($request->boolean('include_payrexx_analysis', false)) {
                $dashboard['payrexx_analysis'] = $this->payrexxService->analyzeBookingsWithPayrexx(
                    $classification['production'],
                    $dateRange['start_date'],
                    $dateRange['end_date']
                );
            }

            if ($request->boolean('include_test_detection', true)) {
                $dashboard['test_detection_info'] = $this->buildTestDetectionInfo($classification);
            }

            return $dashboard;
        }  catch (\Exception $e) {
            Log::channel('finance')->error('Error en dashboard ejecutivo con clasificación: ' . $e->getMessage(), [
                'school_id' => $request->school_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [];
        }


    }

    /**
     * Debug de detección de reservas de prueba
     */
    public function debugTestBookingDetection(int $schoolId): array
    {
        $bookings = $this->bookingRepository->getAllSchoolBookings($schoolId);
        $classification = $this->bookingAnalyzer->classifyBookings($bookings);

        return [
            'total_bookings' => $bookings->count(),
            'classification_summary' => $classification['summary'],
            'test_booking_details' => $classification['test']->take(10)->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'client_name' => $booking->clientMain->name ?? 'N/A',
                    'source' => $booking->source,
                    'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
                    'is_test_reason' => $this->bookingAnalyzer->getTestReason($booking)
                ];
            })
        ];
    }

    /**
     * Debug financiero de una reserva específica
     */
    public function debugBookingFinancials(int $bookingId): array
    {
        $booking = Booking::with(['bookingUsers', 'payments', 'vouchersLogs'])->findOrFail($bookingId);

        return [
            'booking_id' => $bookingId,
            'basic_info' => [
                'status' => $booking->status,
                'source' => $booking->source,
                'created_at' => $booking->created_at->format('Y-m-d H:i:s')
            ],
            'financial_calculation' => $this->kpiCalculator->debugBookingCalculation($booking),
            'payment_analysis' => $this->paymentAnalyzer->analyzeBookingPayments($booking),
            'classification' => $this->bookingAnalyzer->classifyBooking($booking)
        ];
    }

    /**
     * Determinar el rango de fechas de la temporada
     */
    private function getSeasonDateRange(Request $request): array
    {
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
        } elseif ($request->season_id) {
            $season = Season::findOrFail($request->season_id);
            $startDate = Carbon::parse($season->start_date);
            $endDate = Carbon::parse($season->end_date);
        } else {
            // Temporada actual por defecto
            $today = Carbon::today();
            $season = Season::where('school_id', $request->school_id)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->first();

            if ($season) {
                $startDate = Carbon::parse($season->start_date);
                $endDate = Carbon::parse($season->end_date);
            } else {
                // Fallback: últimos 6 meses
                $endDate = Carbon::now();
                $startDate = $endDate->copy()->subMonths(6);
            }
        }

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'start_carbon' => $startDate,
            'end_carbon' => $endDate,
            'total_days' => $startDate->diffInDays($endDate),
            'season_name' => $season->name ?? 'Período personalizado'
        ];
    }

    /**
     * Construir información de temporada
     */
    private function buildSeasonInfo(array $dateRange, Request $request, string $optimizationLevel, array $classification): array
    {
        return [
            'season_name' => $dateRange['season_name'],
            'date_range' => [
                'start' => $dateRange['start_date'],
                'end' => $dateRange['end_date'],
                'total_days' => $dateRange['total_days']
            ],
            'school_id' => $request->school_id,
            'optimization_level' => $optimizationLevel,
            'total_bookings' => $classification['total_count'],
            'booking_classification' => $classification['summary']
        ];
    }

    /**
     * Calcular resumen financiero
     */
    private function calculateFinancialSummary(array $classification): array
    {
        $productionBookings = $classification['production'];

        return [
            'total_expected_revenue' => $this->kpiCalculator->calculateTotalExpectedRevenue($productionBookings),
            'total_received_revenue' => $this->kpiCalculator->calculateTotalReceivedRevenue($productionBookings),
            'total_pending_revenue' => $this->kpiCalculator->calculateTotalPendingRevenue($productionBookings),
            'collection_efficiency' => $this->kpiCalculator->calculateCollectionEfficiency($productionBookings),
            'average_booking_value' => $this->kpiCalculator->calculateAverageBookingValue($productionBookings)
        ];
    }

    /**
     * Calcular métricas de rendimiento
     */
    private function calculatePerformanceMetrics(float $startTime, $bookings, array $classification): array
    {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'execution_time_ms' => $executionTime,
            'total_bookings_analyzed' => $bookings->count(),
            'production_bookings_count' => $classification['summary']['production_count'],
            'test_bookings_excluded' => $classification['summary']['test_count'],
            'cancelled_bookings_count' => $classification['summary']['cancelled_count'],
            'bookings_per_second' => $executionTime > 0 ? round(($bookings->count() / $executionTime) * 1000, 2) : 0,
            'optimization_level_used' => 'balanced'
        ];
    }

    /**
     * Construir información de detección de pruebas
     */
    private function buildTestDetectionInfo(array $classification): array
    {
        return [
            'total_test_bookings' => $classification['summary']['test_count'],
            'test_percentage' => $classification['summary']['test_percentage'],
            'detection_criteria' => $this->bookingAnalyzer->getTestDetectionCriteria(),
            'sample_test_bookings' => $classification['test']->take(5)->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'reason' => $this->bookingAnalyzer->getTestReason($booking),
                    'client' => $booking->clientMain->name ?? 'N/A'
                ];
            })
        ];
    }
}

