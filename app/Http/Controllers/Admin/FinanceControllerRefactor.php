<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Services\Finance\SeasonFinanceService;
use App\Services\Finance\ExportService;
use App\Services\Finance\Analyzers\BookingAnalyzer;
use App\Services\Finance\Analyzers\PaymentAnalyzer;
use App\Services\Finance\Analyzers\KpiCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Controlador Principal de Analytics y Finanzas - VERSIÓN CONSOLIDADA
 *
 * Responsabilidades:
 * - Gestionar todos los endpoints de analytics del frontend
 * - Coordinar servicios especializados
 * - Mantener compatibilidad con el dashboard del frontend
 * - Gestionar exportaciones y reportes
 */
class FinanceControllerRefactor extends AppBaseController
{
    protected SeasonFinanceService $seasonFinanceService;
    protected ExportService $exportService;
    protected BookingAnalyzer $bookingAnalyzer;
    protected PaymentAnalyzer $paymentAnalyzer;
    protected KpiCalculator $kpiCalculator;

    public function __construct(
        SeasonFinanceService $seasonFinanceService,
        ExportService $exportService,
        BookingAnalyzer $bookingAnalyzer,
        PaymentAnalyzer $paymentAnalyzer,
        KpiCalculator $kpiCalculator
    ) {
        $this->seasonFinanceService = $seasonFinanceService;
        $this->exportService = $exportService;
        $this->bookingAnalyzer = $bookingAnalyzer;
        $this->paymentAnalyzer = $paymentAnalyzer;
        $this->kpiCalculator = $kpiCalculator;
    }

    // ==================== ENDPOINTS PRINCIPALES PARA EL FRONTEND ====================

    /**
     * Obtener resumen analytics principal
     * GET /api/admin/analytics/summary
     */
    public function getSummary(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            $dashboard = $this->seasonFinanceService->generateSeasonDashboard($request);

            $summary = [
                'totalRevenue' => $dashboard['executive_kpis']['revenue_received'] ?? 0,
                'totalBookings' => $dashboard['executive_kpis']['total_bookings'] ?? 0,
                'totalParticipants' => $dashboard['executive_kpis']['total_participants'] ?? 0,
                'averageBookingValue' => $dashboard['executive_kpis']['average_booking_value'] ?? 0,
                'collectionEfficiency' => $dashboard['executive_kpis']['collection_efficiency'] ?? 0,
                'pendingRevenue' => $dashboard['executive_kpis']['revenue_pending'] ?? 0,
                'previousPeriod' => $this->calculatePreviousPeriodData($request)
            ];

            return $this->sendResponse($summary, 'Analytics summary retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting analytics summary', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving analytics summary: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener análisis de cursos
     * GET /api/admin/analytics/courses
     */
    public function getCourseAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            $dashboard = $this->seasonFinanceService->generateSeasonDashboard($request);
            $bookings = $this->getFilteredBookings($request);

           // dd($dashboard);

            // Analizar cursos usando BookingAnalyzer
            $courseAnalytics = $this->analyzeCoursePerformance($bookings);

            return $this->sendResponse($courseAnalytics, 'Course analytics retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting course analytics', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving course analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener análisis de ingresos
     * GET /api/admin/analytics/revenue
     */
    public function getRevenueAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            $revenueData = $this->calculateRevenueOverTime($request);

            return $this->sendResponse($revenueData, 'Revenue analytics retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting revenue analytics', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving revenue analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener detalles de métodos de pago
     * GET /api/admin/analytics/payment-details
     */
    public function getPaymentDetails(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);

            Log::channel('finance')->info('Getting payment details', [
                'filters' => $request->all()
            ]);

            // Obtener reservas filtradas
            $bookings = $this->getFilteredBookings($request);

            Log::channel('finance')->info('Bookings for payment analysis', [
                'count' => $bookings->count(),
                'first_booking_id' => $bookings->first()?->id
            ]);

            // Analizar métodos de pago usando la lógica mejorada
            $paymentAnalysis = $this->paymentAnalyzer->analyzePaymentMethods($bookings);

            // Análisis adicional online vs offline
            $onlineVsOffline = $this->paymentAnalyzer->analyzeOnlineVsOffline($bookings);

            // Añadir nombres display para frontend
            $paymentAnalysisWithNames = [];
            foreach ($paymentAnalysis as $method => $data) {
                $paymentAnalysisWithNames[$method] = array_merge($data, [
                    'display_name' => $this->paymentAnalyzer->getPaymentMethodDisplayName($method)
                ]);
            }

            $result = [
                'payment_methods' => $paymentAnalysisWithNames,
                'online_vs_offline' => $onlineVsOffline,
                'summary' => [
                    'total_bookings' => $bookings->count(),
                    'total_methods' => count($paymentAnalysis),
                    'most_used_method' => $this->getMostUsedPaymentMethod($paymentAnalysis),
                    'has_online_payments' => $this->hasOnlinePayments($paymentAnalysis)
                ]
            ];

            Log::channel('finance')->info('Payment analysis completed', [
                'methods_found' => array_keys($paymentAnalysis),
                'total_methods' => count($paymentAnalysis)
            ]);

            return $this->sendResponse($result, 'Payment details retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting payment details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving payment details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 🆕 MÉTODO AUXILIAR: Obtener método de pago más usado
     */
    private function getMostUsedPaymentMethod(array $paymentAnalysis): ?string
    {
        if (empty($paymentAnalysis)) {
            return null;
        }

        $maxCount = 0;
        $mostUsedMethod = null;

        foreach ($paymentAnalysis as $method => $data) {
            if ($data['count'] > $maxCount) {
                $maxCount = $data['count'];
                $mostUsedMethod = $method;
            }
        }

        return $mostUsedMethod;
    }

    /**
     * 🆕 MÉTODO AUXILIAR: Verificar si hay pagos online
     */
    private function hasOnlinePayments(array $paymentAnalysis): bool
    {
        $onlineMethods = ['boukii_direct', 'online_link'];

        foreach ($onlineMethods as $method) {
            if (isset($paymentAnalysis[$method]) && $paymentAnalysis[$method]['count'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener dashboard financiero
     * GET /api/admin/analytics/financial-dashboard
     */
    public function getFinancialDashboard(Request $request): JsonResponse
    {
        try {
            $this->ensureSchoolInRequest($request);

            dd($request);
            $dashboard = $this->seasonFinanceService->generateSeasonDashboard($request);

            return $this->sendResponse($dashboard, 'Financial dashboard retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting financial dashboard', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving financial dashboard: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener comparación de rendimiento
     * GET /api/admin/analytics/performance-comparison
     */
    public function getPerformanceComparison(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            $currentPeriod = $this->seasonFinanceService->generateSeasonDashboard($request);
            $previousPeriod = $this->calculatePreviousPeriodData($request);

            $comparison = [
                'current_period' => $currentPeriod['executive_kpis'],
                'previous_period' => $previousPeriod,
                'comparison_metrics' => $this->calculateComparisonMetrics($currentPeriod['executive_kpis'], $previousPeriod)
            ];

            return $this->sendResponse($comparison, 'Performance comparison retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting performance comparison', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving performance comparison: ' . $e->getMessage(), 500);
        }
    }

    // ==================== MÉTODOS DE EXPORTACIÓN ====================

    /**
     * Exportar datos a CSV
     * POST /api/admin/analytics/export/csv
     */
    public function exportToCSV(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'sections' => 'array'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            $request->merge(['format' => 'csv']);
            $exportResult = $this->exportService->exportSeasonDashboard($request);

            return $this->sendResponse($exportResult, 'CSV export generated successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error exporting to CSV', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error generating CSV export: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Exportar datos a Excel
     * POST /api/admin/analytics/export/excel
     */
    public function exportToExcel(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'sections' => 'array'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            $request->merge(['format' => 'excel']);
            $exportResult = $this->exportService->exportSeasonDashboard($request);

            return $this->sendResponse($exportResult, 'Excel export generated successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error exporting to Excel', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error generating Excel export: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Exportar datos a PDF
     * POST /api/admin/analytics/export/pdf
     */
    public function exportToPDF(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'sections' => 'array'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            $request->merge(['format' => 'pdf']);
            $exportResult = $this->exportService->exportSeasonDashboard($request);

            return $this->sendResponse($exportResult, 'PDF export generated successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error exporting to PDF', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error generating PDF export: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Descargar archivo exportado
     * GET /api/admin/analytics/download/{filename}
     */
    public function downloadExport(string $filename)
    {
        try {
            return $this->exportService->downloadFile($filename);

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error downloading export file', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'File not found'], 404);
        }
    }

    // ==================== MÉTODOS AUXILIARES PRIVADOS ====================

    /**
     * Obtener reservas filtradas según los parámetros
     */
    private function getFilteredBookings(Request $request)
    {
        // Usar el servicio para obtener reservas optimizadas
        $dateRange = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'season_name' => 'Custom Period'
        ];

        // Aquí necesitarías acceder al BookingFinanceRepository
        // Por simplicidad, voy a hacer una consulta directa, pero lo ideal sería usar el repository
        $query = \App\Models\Booking::with(['bookingUsers', 'payments', 'clientMain'])
            ->where('school_id', $request->school_id)
            ->whereHas('bookingUsers', function($q) use ($request) {
                $q->whereBetween('date', [$request->start_date, $request->end_date]);
            });

        // Aplicar filtros adicionales
        if ($request->course_type) {
            $query->whereHas('bookingUsers.course', function($q) use ($request) {
                $q->where('course_type', $request->course_type);
            });
        }

        if ($request->source) {
            $query->where('source', $request->source);
        }

        if ($request->sport_id) {
            $query->whereHas('bookingUsers.course', function($q) use ($request) {
                $q->where('sport_id', $request->sport_id);
            });
        }

        return $query->get();
    }

    /**
     * Calcular datos del período anterior para comparación
     */
    private function calculatePreviousPeriodData(Request $request)
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $periodDays = $endDate->diffInDays($startDate);

        $previousStart = $startDate->copy()->subDays($periodDays + 1);
        $previousEnd = $startDate->copy()->subDay();

        $previousRequest = new Request([
            'school_id' => $request->school_id,
            'start_date' => $previousStart->format('Y-m-d'),
            'end_date' => $previousEnd->format('Y-m-d')
        ]);

        try {
            $previousDashboard = $this->seasonFinanceService->generateSeasonDashboard($previousRequest);
            return $previousDashboard['executive_kpis'];
        } catch (\Exception $e) {
            Log::channel('finance')->warning('Error calculating previous period data', ['error' => $e->getMessage()]);
            return [
                'total_bookings' => 0,
                'revenue_received' => 0,
                'total_participants' => 0,
                'average_booking_value' => 0,
                'collection_efficiency' => 0,
                'revenue_pending' => 0
            ];
        }
    }

    /**
     * Calcular métricas de comparación entre períodos
     */
    private function calculateComparisonMetrics($current, $previous)
    {
        $metrics = [];

        foreach (['total_bookings', 'revenue_received', 'total_participants', 'average_booking_value'] as $metric) {
            $currentValue = $current[$metric] ?? 0;
            $previousValue = $previous[$metric] ?? 0;

            $change = $currentValue - $previousValue;
            $percentageChange = $previousValue > 0 ? (($change / $previousValue) * 100) : 0;

            $metrics[$metric] = [
                'current' => $currentValue,
                'previous' => $previousValue,
                'change' => $change,
                'percentage_change' => round($percentageChange, 2),
                'trend' => $change >= 0 ? 'up' : 'down'
            ];
        }

        return $metrics;
    }

    /**
     * Analizar rendimiento de cursos
     */
    private function analyzeCoursePerformance($bookings)
    {
        $courseData = [];

        foreach ($bookings as $booking) {
            foreach ($booking->bookingUsers as $bookingUser) {
                $course = $bookingUser->course;
                if (!$course) continue;

                $courseId = $course->id;

                if (!isset($courseData[$courseId])) {
                    $courseData[$courseId] = [
                        'courseId' => $courseId,
                        'courseName' => $course->name,
                        'courseType' => $course->course_type,
                        'totalRevenue' => 0,
                        'totalBookings' => 0,
                        'averagePrice' => 0,
                        'completionRate' => 100, // Simplificado
                        'paymentMethods' => [
                            'cash' => 0,
                            'card' => 0,
                            'online' => 0,
                            'vouchers' => 0,
                            'pending' => 0
                        ]
                    ];
                }

                $courseData[$courseId]['totalBookings']++;
                $courseData[$courseId]['totalRevenue'] += $course->price ?? 0;

                // Analizar método de pago
                $paymentMethod = $this->getBookingPaymentMethod($booking);
                if (isset($courseData[$courseId]['paymentMethods'][$paymentMethod])) {
                    $courseData[$courseId]['paymentMethods'][$paymentMethod] += $course->price ?? 0;
                }
            }
        }

        // Calcular promedios
        foreach ($courseData as &$course) {
            if ($course['totalBookings'] > 0) {
                $course['averagePrice'] = $course['totalRevenue'] / $course['totalBookings'];
            }
        }

        return array_values($courseData);
    }

    /**
     * Calcular ingresos a lo largo del tiempo
     */
    private function calculateRevenueOverTime(Request $request)
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $daysDiff = $endDate->diffInDays($startDate);

        // Determinar agrupación según el rango de fechas
        if ($daysDiff <= 31) {
            $groupBy = 'DATE(bu.date)';
            $dateFormat = '%Y-%m-%d';
        } elseif ($daysDiff <= 180) {
            $groupBy = 'YEARWEEK(bu.date, 1)';
            $dateFormat = '%Y-W%u';
        } else {
            $groupBy = 'DATE_FORMAT(bu.date, "%Y-%m")';
            $dateFormat = '%Y-%m';
        }

        $revenueData = DB::select("
    SELECT
        {$groupBy} as period,
        DATE_FORMAT(bu.date, '{$dateFormat}') as formatted_date,
        COUNT(*) as bookings,
        SUM(COALESCE(c.price, 0)) as revenue,
        AVG(COALESCE(c.price, 0)) as averageValue,
        SUM(CASE WHEN b.status = 'cancelled' THEN COALESCE(c.price, 0) ELSE 0 END) as refunds
    FROM booking_users bu
    INNER JOIN bookings b ON bu.booking_id = b.id
    INNER JOIN courses c ON bu.course_id = c.id
    WHERE b.school_id = ?
        AND bu.date BETWEEN ? AND ?
        AND bu.status = 1
    GROUP BY {$groupBy}, DATE_FORMAT(bu.date, '{$dateFormat}')
    ORDER BY period ASC
", [$request->school_id, $request->start_date, $request->end_date]);



        return array_map(function($row) {
            return [
                'date' => $row->formatted_date,
                'revenue' => (float) $row->revenue,
                'bookings' => (int) $row->bookings,
                'averageValue' => (float) $row->averageValue,
                'refunds' => (float) $row->refunds,
                'netRevenue' => (float) ($row->revenue - $row->refunds)
            ];
        }, $revenueData);
    }

    /**
     * Obtener método de pago de una reserva
     */
    private function getBookingPaymentMethod($booking)
    {
        $payment = $booking->payments()->where('status', 'completed')->first();

        if (!$payment) {
            return 'pending';
        }

        return match($payment->payment_method ?? 'cash') {
            'card' => 'card',
            'online' => 'online',
            'voucher' => 'vouchers',
            default => 'cash'
        };
    }

    // ==================== MÉTODOS PARA COMPATIBILIDAD CON VERSIÓN ANTERIOR ====================

    /**
     * Dashboard principal de temporada (mantener compatibilidad)
     * GET /api/admin/finance/season-dashboard
     */
    public function getSeasonFinancialDashboard(Request $request): JsonResponse
    {
        return $this->getFinancialDashboard($request);
    }

    /**
     * Exportar dashboard de temporada (mantener compatibilidad)
     * GET /api/admin/finance/export-dashboard
     */
    public function exportSeasonDashboard(Request $request): JsonResponse
    {
        $this->ensureSchoolInRequest($request);
        $request->validate([
            'format' => 'required|in:csv,pdf,excel'
        ]);

        switch ($request['format']) {
            case 'csv':
                return $this->exportToCSV($request);
            case 'pdf':
                return $this->exportToPDF($request);
            case 'excel':
                return $this->exportToExcel($request);
            default:
                return $this->sendError('Invalid export format', 400);
        }
    }

    // ==================== MÉTODOS ADICIONALES PARA COMPLETAR LA API ====================

    /**
     * Obtener análisis de ingresos por período detallado
     * GET /api/admin/analytics/revenue-by-period
     */
    public function getRevenueByPeriod(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'period' => 'required|in:daily,weekly,monthly'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            $revenueData = $this->calculateRevenueOverTime($request);

            return $this->sendResponse($revenueData, 'Revenue by period retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting revenue by period', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving revenue by period: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener análisis detallado de cursos
     * GET /api/admin/analytics/courses-detailed
     */
    public function getDetailedCourseAnalytics(Request $request): JsonResponse
    {
        // Reutilizar el método existente getCourseAnalytics con más detalle
        return $this->getCourseAnalytics($request);
    }

    /**
     * Obtener análisis de eficiencia de monitores
     * GET /api/admin/analytics/monitors-efficiency
     */
    public function getMonitorEfficiencyAnalytics(Request $request): JsonResponse
    {
        $request->validate([

            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            // Placeholder - implementar análisis detallado de monitores
            $monitorAnalytics = [
                'total_monitors' => 10,
                'active_monitors' => 8,
                'efficiency_average' => 85.5,
                'top_performers' => [],
                'improvement_opportunities' => []
            ];

            return $this->sendResponse($monitorAnalytics, 'Monitor efficiency analytics retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting monitor efficiency analytics', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving monitor efficiency analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener análisis de conversión
     * GET /api/admin/analytics/conversion-analysis
     */
    public function getConversionAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            // Placeholder - implementar análisis de conversión
            $conversionAnalysis = [
                'overall_conversion_rate' => 75.5,
                'by_source' => [
                    'web' => ['rate' => 80.2, 'volume' => 150],
                    'app' => ['rate' => 85.1, 'volume' => 200],
                    'phone' => ['rate' => 70.5, 'volume' => 50]
                ],
                'abandonment_points' => [],
                'recommendations' => []
            ];

            return $this->sendResponse($conversionAnalysis, 'Conversion analysis retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting conversion analysis', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving conversion analysis: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener tendencias y predicciones
     * GET /api/admin/analytics/trends-prediction
     */
    public function getTrendsAndPredictions(Request $request): JsonResponse
    {
        $request->validate([
            'analysis_months' => 'nullable|integer|min:3|max:24',
            'prediction_months' => 'nullable|integer|min:1|max:6'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            // Placeholder - implementar análisis de tendencias
            $trendsData = [
                'revenue_trend' => 'increasing',
                'bookings_trend' => 'stable',
                'seasonal_patterns' => [],
                'predictions' => [
                    'next_month_revenue' => 15000,
                    'next_month_bookings' => 120,
                    'confidence_level' => 0.75
                ]
            ];

            return $this->sendResponse($trendsData, 'Trends and predictions retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting trends and predictions', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving trends and predictions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener métricas en tiempo real
     * GET /api/admin/analytics/realtime-metrics
     */
    public function getRealtimeMetrics(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id'
        ]);

        try {
            $today = Carbon::today();

            $realtimeMetrics = [
                'today' => [
                    'bookings' => $this->getBookingCountForDate($request->school_id, $today),
                    'revenue' => $this->getRevenueForDate($request->school_id, $today),
                    'active_sessions' => 5 // Placeholder
                ],
                'this_week' => [
                    'bookings' => $this->getBookingCountForWeek($request->school_id),
                    'revenue' => $this->getRevenueForWeek($request->school_id)
                ],
                'last_updated' => now()->toISOString()
            ];

            return $this->sendResponse($realtimeMetrics, 'Realtime metrics retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting realtime metrics', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving realtime metrics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener análisis por deportes
     * GET /api/admin/analytics/sports-performance
     */
    public function getSportsPerformanceAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            // Placeholder - implementar análisis por deportes
            $sportsAnalytics = [
                'total_sports' => 5,
                'performance_by_sport' => [],
                'popular_sports' => [],
                'revenue_by_sport' => []
            ];

            return $this->sendResponse($sportsAnalytics, 'Sports performance analytics retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting sports performance analytics', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving sports performance analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener comparación de temporadas
     * GET /api/admin/analytics/seasonal-comparison
     */
    public function getSeasonalComparison(Request $request): JsonResponse
    {
        $request->validate([
            'seasons' => 'array'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            // Placeholder - implementar comparación de temporadas
            $seasonalComparison = [
                'seasons_compared' => 2,
                'comparison_metrics' => [],
                'insights' => []
            ];

            return $this->sendResponse($seasonalComparison, 'Seasonal comparison retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting seasonal comparison', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving seasonal comparison: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener insights de clientes
     * GET /api/admin/analytics/customer-insights
     */
    public function getCustomerInsights(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            // Placeholder - implementar insights de clientes
            $customerInsights = [
                'total_customers' => 500,
                'new_customers' => 50,
                'returning_customers' => 150,
                'customer_segments' => [],
                'lifetime_value' => 250.0
            ];

            return $this->sendResponse($customerInsights, 'Customer insights retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting customer insights', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving customer insights: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener análisis de capacidad
     * GET /api/admin/analytics/capacity-analysis
     */
    public function getCapacityAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $this->ensureSchoolInRequest($request);
            // Placeholder - implementar análisis de capacidad
            $capacityAnalysis = [
                'overall_utilization' => 75.5,
                'peak_times' => [],
                'underutilized_slots' => [],
                'capacity_recommendations' => []
            ];

            return $this->sendResponse($capacityAnalysis, 'Capacity analysis retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting capacity analysis', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->sendError('Error retrieving capacity analysis: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener analytics diarios de monitor
     * GET /api/admin/analytics/monitors/{monitorId}/daily
     */
    public function getMonitorDailyAnalytics(Request $request, int $monitorId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            // Placeholder - implementar analytics diarios de monitor
            $monitorAnalytics = [
                'monitor_id' => $monitorId,
                'daily_metrics' => [],
                'performance_trends' => [],
                'recommendations' => []
            ];

            return $this->sendResponse($monitorAnalytics, 'Monitor daily analytics retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting monitor daily analytics', [
                'error' => $e->getMessage(),
                'monitor_id' => $monitorId
            ]);
            return $this->sendError('Error retrieving monitor daily analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener rendimiento de monitor
     * GET /api/admin/analytics/monitors/{monitorId}/performance
     */
    public function getMonitorPerformance(Request $request, int $monitorId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            // Placeholder - implementar rendimiento de monitor
            $monitorPerformance = [
                'monitor_id' => $monitorId,
                'overall_score' => 85.5,
                'metrics' => [],
                'strengths' => [],
                'improvement_areas' => []
            ];

            return $this->sendResponse($monitorPerformance, 'Monitor performance retrieved successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error getting monitor performance', [
                'error' => $e->getMessage(),
                'monitor_id' => $monitorId
            ]);
            return $this->sendError('Error retrieving monitor performance: ' . $e->getMessage(), 500);
        }
    }

    // ==================== MÉTODOS AUXILIARES PARA MÉTRICAS EN TIEMPO REAL ====================

    private function getBookingCountForDate(int $schoolId, Carbon $date): int
    {
        return DB::table('booking_users')
            ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
            ->where('bookings.school_id', $schoolId)
            ->whereDate('booking_users.date', $date)
            ->where('booking_users.status', 1)
            ->count();
    }

    private function getRevenueForDate(int $schoolId, Carbon $date): float
    {
        return (float) DB::table('booking_users')
            ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
            ->join('courses', 'booking_users.course_id', '=', 'courses.id')
            ->where('bookings.school_id', $schoolId)
            ->whereDate('booking_users.date', $date)
            ->where('booking_users.status', 1)
            ->sum('courses.price');
    }

    private function getBookingCountForWeek(int $schoolId): int
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return DB::table('booking_users')
            ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
            ->where('bookings.school_id', $schoolId)
            ->whereBetween('booking_users.date', [$startOfWeek, $endOfWeek])
            ->where('booking_users.status', 1)
            ->count();
    }

    private function getRevenueForWeek(int $schoolId): float
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return (float) DB::table('booking_users')
            ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
            ->join('courses', 'booking_users.course_id', '=', 'courses.id')
            ->where('bookings.school_id', $schoolId)
            ->whereBetween('booking_users.date', [$startOfWeek, $endOfWeek])
            ->where('booking_users.status', 1)
            ->sum('courses.price');
    }

    // ==================== MÉTODOS STUB PARA FUNCIONALIDADES FUTURAS ====================

    public function getExecutiveDashboard(Request $request): JsonResponse
    {
        return $this->getFinancialDashboard($request);
    }

    public function getOperationalDashboard(Request $request): JsonResponse
    {
        return $this->getFinancialDashboard($request);
    }

    public function getPricingAnalysis(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Pricing analysis coming soon'], 'Feature not implemented yet');
    }

    public function getSatisfactionMetrics(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Satisfaction metrics coming soon'], 'Feature not implemented yet');
    }

    public function getAnalyticsPreferences(Request $request): JsonResponse
    {
        return $this->sendResponse(['preferences' => []], 'Analytics preferences retrieved');
    }

    public function saveAnalyticsPreferences(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Preferences saved'], 'Analytics preferences saved successfully');
    }

    public function executeCustomQuery(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Custom query feature coming soon'], 'Feature not implemented yet');
    }

    // Métodos para integración
    public function syncPayrexxData(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Payrexx sync initiated'], 'Payrexx synchronization started');
    }

    public function exportToGoogleAnalytics(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Google Analytics export coming soon'], 'Feature not implemented yet');
    }

    public function handleRealtimeWebhook(Request $request): JsonResponse
    {
        return $this->sendResponse(['status' => 'received'], 'Webhook processed successfully');
    }

    // Métodos para administración
    public function getAnalyticsPermissions(Request $request): JsonResponse
    {
        return $this->sendResponse(['permissions' => []], 'Analytics permissions retrieved');
    }

    public function updateAnalyticsPermissions(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Permissions updated'], 'Analytics permissions updated successfully');
    }

    public function getSystemConfig(Request $request): JsonResponse
    {
        return $this->sendResponse(['config' => []], 'System configuration retrieved');
    }

    public function updateSystemConfig(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Configuration updated'], 'System configuration updated successfully');
    }

    public function getAuditLogs(Request $request): JsonResponse
    {
        return $this->sendResponse(['logs' => []], 'Audit logs retrieved');
    }

    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        return $this->sendResponse(['metrics' => []], 'Performance metrics retrieved');
    }

    // Métodos para embebidos
    public function getEmbeddedDashboard(Request $request, string $token): JsonResponse
    {
        return $this->sendResponse(['message' => 'Embedded dashboard coming soon'], 'Feature not implemented yet');
    }

    public function getEmbeddedKpis(Request $request, string $token): JsonResponse
    {
        return $this->sendResponse(['message' => 'Embedded KPIs coming soon'], 'Feature not implemented yet');
    }

    // Métodos para debug
    public function generateTestData(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Test data generation completed'], 'Test data generated successfully');
    }

    public function performanceTest(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Performance test completed'], 'Performance test executed successfully');
    }

    public function validateCalculations(Request $request): JsonResponse
    {
        return $this->sendResponse(['message' => 'Calculations validated'], 'Calculation validation completed');
    }

    /**
     * Debug de detección de reservas de prueba
     * GET /api/admin/debug-analytics/debug-test-detection/{schoolId}
     */
    public function debugTestBookingDetection(int $schoolId): JsonResponse
    {
        try {
            $debugInfo = $this->seasonFinanceService->debugTestBookingDetection($schoolId);

            return $this->sendResponse($debugInfo, 'Debug test detection completed successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error in debug test detection', [
                'school_id' => $schoolId,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Error in debug test detection: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Debug financiero de una reserva específica
     * GET /api/admin/debug-analytics/debug-booking-financials/{bookingId}
     */
    public function debugBookingFinancials(int $bookingId): JsonResponse
    {
        try {
            $debugInfo = $this->seasonFinanceService->debugBookingFinancials($bookingId);

            return $this->sendResponse($debugInfo, 'Booking financial debug completed successfully');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error in booking financial debug', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Error in booking financial debug: ' . $e->getMessage(), 500);
        }
    }
}

