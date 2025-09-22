<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\BookingPriceCalculatorService;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Course;
use App\Models\CourseSubgroup;
use App\Models\Monitor;
use App\Models\MonitorNwd;
use App\Models\Season;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Response;
use Validator;
use App\Traits\Utils;

/**
 * Class StatisticsController
 * @package App\Http\Controllers\Admin
 */

class StatisticsController extends AppBaseController
{
    use Utils;

    // ✅ CURSOS A EXCLUIR DE LOS CÁLCULOS
    const EXCLUDED_COURSES = [260, 243];

    protected $priceCalculator;

    public function __construct(BookingPriceCalculatorService $priceCalculator)
    {
        $this->priceCalculator = $priceCalculator;
    }

    /**
     * ENDPOINT PRINCIPAL: Análisis completo de realidad financiera para múltiples reservas
     * REEMPLAZAR getFinancialRealityStatistics en StatisticsController.php
     */
    public function getCompleteFinancialRealityAnalysis(Request $request)
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'booking_ids' => 'nullable|array',
            'booking_ids.*' => 'integer|exists:bookings,id',
            'include_consistent' => 'boolean',
            'min_discrepancy' => 'nullable|numeric|min:0',
            'max_results' => 'nullable|integer|min:1|max:1000'
        ]);

        $startTime = microtime(true);

        Log::debug('=== INICIANDO ANÁLISIS FINANCIERO COMPLETO ===', [
            'school_id' => $request->school_id,
            'date_range' => [$request->start_date, $request->end_date],
            'specific_bookings' => $request->booking_ids ? count($request->booking_ids) : null
        ]);

        try {
            // OBTENER RESERVAS SEGÚN CRITERIOS
            $bookings = $this->getBookingsForAnalysis($request);

            // FILTRAR RESERVAS QUE SOLO TIENEN CURSOS EXCLUIDOS
            $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);
            $filteredBookings = $this->filterBookingsWithExcludedCourses($bookings, $excludedCourses);

            Log::info('Reservas filtradas para análisis', [
                'total_bookings_before_filter' => $bookings->count(),
                'total_bookings_after_filter' => $filteredBookings->count(),
                'excluded_courses' => $excludedCourses
            ]);

            // INICIALIZAR ESTADÍSTICAS GLOBALES
            $globalStats = $this->initializeGlobalStats();

            // PROCESAR CADA RESERVA CON EL NUEVO SERVICIO
            $detailedResults = [];
            $processedCount = 0;
            $maxResults = $request->get('max_results', 500);

            foreach ($filteredBookings as $booking) {
                if ($processedCount >= $maxResults) {
                    Log::info("Límite de resultados alcanzado: {$maxResults}");
                    break;
                }

                $analysis = $this->priceCalculator->getCompleteFinancialReality($booking, [
                    'exclude_courses' => $excludedCourses
                ]);

                // FILTRAR POR CRITERIOS SI SE ESPECIFICAN
                if (!$this->meetsCriteria($analysis, $request)) {
                    continue;
                }

                // ACUMULAR ESTADÍSTICAS GLOBALES
                $this->accumulateGlobalStats($globalStats, $analysis);

                // AGREGAR A RESULTADOS DETALLADOS
                $detailedResults[] = $this->formatAnalysisForResponse($analysis);
                $processedCount++;

                // LOG PROGRESS CADA 100 RESERVAS
                if ($processedCount % 100 === 0) {
                    Log::debug("Progreso del análisis: {$processedCount}/{$filteredBookings->count()}");
                }
            }

            // CALCULAR MÉTRICAS FINALES
            $this->calculateFinalMetrics($globalStats, $processedCount);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $response = [
                'analysis_metadata' => [
                    'analysis_method' => 'complete_financial_reality_v2',
                    'execution_time_ms' => $executionTime,
                    'timestamp' => now()->toDateTimeString(),
                    'school_id' => $request->school_id,
                    'date_range' => [
                        'start' => $request->start_date,
                        'end' => $request->end_date
                    ],
                    'excluded_courses' => $excludedCourses,
                 //   'filters_applied' => $this->getAppliedFilters($request)
                ],

                'global_statistics' => $globalStats,

                'performance_metrics' => [
                    'total_bookings_analyzed' => $processedCount,
                    'bookings_per_second' => $executionTime > 0 ? round($processedCount / ($executionTime / 1000), 2) : 0,
                    'average_analysis_time_ms' => $processedCount > 0 ? round($executionTime / $processedCount, 2) : 0,
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
                ],

                'detailed_results' => $detailedResults,
/*
                'summary_insights' => $this->generateSummaryInsights($globalStats, $processedCount),

                'recommendations' => $this->generateGlobalRecommendations($globalStats)*/
            ];

            Log::info('=== ANÁLISIS FINANCIERO COMPLETO FINALIZADO ===', [
                'processed_bookings' => $processedCount,
                'execution_time_ms' => $executionTime,
                'inconsistent_bookings' => $globalStats['issues']['total_with_financial_issues'],
                'critical_issues' => $globalStats['issues']['critical_issues_count']
            ]);

            return $this->sendResponse($response, 'Análisis completo de realidad financiera completado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error en análisis financiero completo: ' . $e->getMessage(), [
                'school_id' => $request->school_id,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->sendError('Error en análisis financiero: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ENDPOINT ESPECÍFICO: Análisis de una reserva individual
     */
    public function getBookingFinancialAnalysis(Request $request, $bookingId)
    {
        $request->validate([
            'include_timeline' => 'boolean',
            'include_recommendations' => 'boolean'
        ]);

        try {
            $booking = Booking::with([
                'bookingUsers.course.sport',
                'bookingUsers.client',
                'bookingUsers.bookingUserExtras.courseExtra',
                'payments',
                'vouchersLogs.voucher',
                'clientMain',
                'school'
            ])->findOrFail($bookingId);

            $analysis = $this->priceCalculator->getCompleteFinancialReality($booking, [
                'exclude_courses' => self::EXCLUDED_COURSES
            ]);

            // AGREGAR INFORMACIÓN ADICIONAL SI SE SOLICITA
            if ($request->boolean('include_timeline', false)) {
                $analysis['detailed_timeline'] = $this->getDetailedTimeline($booking);
            }

            if ($request->boolean('include_recommendations', true)) {
                $analysis['actionable_recommendations'] = $this->getActionableRecommendations($analysis);
            }

            // COMPARACIÓN CON MÉTODO ANTERIOR PARA DEBUGGING
            $analysis['legacy_comparison'] = $this->compareLegacyMethod($booking);

            return $this->sendResponse($analysis, 'Análisis financiero individual completado');

        } catch (\Exception $e) {
            Log::error("Error en análisis individual booking {$bookingId}: " . $e->getMessage());
            return $this->sendError('Error en análisis de reserva: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ENDPOINT DASHBOARD: Resumen ejecutivo financiero
     */
    public function getFinancialDashboard(Request $request)
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'period' => 'nullable|in:week,month,quarter,year',
            'quick_analysis' => 'boolean'
        ]);

        $period = $request->get('period', 'month');
        $isQuickAnalysis = $request->boolean('quick_analysis', false);

        // DETERMINAR RANGO DE FECHAS
        $dateRange = $this->getDateRangeForPeriod($period);

        $tempRequest = new Request([
            'school_id' => $request->school_id,
            'start_date' => $dateRange['start'],
            'end_date' => $dateRange['end'],
            'include_consistent' => false, // Solo problemas para el dashboard
            'max_results' => $isQuickAnalysis ? 100 : 300
        ]);

        // OBTENER ANÁLISIS COMPLETO
        $analysisResponse = $this->getCompleteFinancialRealityAnalysis($tempRequest);
        $analysisData = $analysisResponse->getData()->data;

        // CREAR DASHBOARD EJECUTIVO
        $dashboard = [
            'period_info' => [
                'period' => $period,
                'date_range' => $dateRange,
                'is_quick_analysis' => $isQuickAnalysis
            ],

            'kpis' => [
                'financial_health_score' => $this->calculateFinancialHealthScore($analysisData['global_statistics']),
                'total_revenue_at_risk' => $this->calculateRevenueAtRisk($analysisData['global_statistics']),
                'collection_efficiency' => $this->calculateCollectionEfficiency($analysisData['global_statistics']),
                'processing_accuracy' => $this->calculateProcessingAccuracy($analysisData['global_statistics'])
            ],

            'alerts' => $this->generateDashboardAlerts($analysisData['global_statistics']),

            'trends' => $this->analyzeTrends($analysisData['detailed_results']),

            'priority_actions' => $this->getPriorityActions($analysisData['detailed_results']),

            'summary_stats' => $analysisData['global_statistics'],

            'generated_at' => now()->toDateTimeString()
        ];

        return $this->sendResponse($dashboard, 'Dashboard financiero generado exitosamente');
    }

    // === MÉTODOS AUXILIARES ===

    private function getBookingsForAnalysis(Request $request)
    {
        $query = Booking::query()
            ->with([
                'bookingUsers.course.sport',
                'bookingUsers.client',
                'bookingUsers.bookingUserExtras.courseExtra',
                'payments',
                'vouchersLogs.voucher',
                'clientMain',
                'school'
            ])
            ->whereIn('status', [1, 2, 3])
            ->where('school_id', $request->school_id);

        // FILTROS ESPECÍFICOS
        if ($request->booking_ids) {
            $query->whereIn('id', $request->booking_ids);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereHas('bookingUsers', function($q) use ($request) {
                $q->whereBetween('date', [$request->start_date, $request->end_date]);
            });
        }

        return $query->get();
    }

    private function filterBookingsWithExcludedCourses($bookings, array $excludedCourses)
    {
        return $bookings->filter(function($booking) use ($excludedCourses) {
            $activeNonExcludedCourses = $booking->bookingUsers
                ->where('status', '!=', 2)
                ->filter(function($bu) use ($excludedCourses) {
                    return !in_array((int) $bu->course_id, $excludedCourses);
                });

            return $activeNonExcludedCourses->isNotEmpty();
        });
    }

    private function initializeGlobalStats(): array
    {
        return [
            'totals' => [
                'total_bookings_analyzed' => 0,
                'total_calculated_revenue' => 0,
                'total_received_amount' => 0,
                'total_pending_amount' => 0,
                'total_processed_amount' => 0,
                'net_financial_position' => 0
            ],

            'consistency' => [
                'consistent_bookings' => 0,
                'inconsistent_bookings' => 0,
                'consistency_rate' => 0,
                'average_discrepancy' => 0,
                'total_discrepancy_amount' => 0
            ],

            'by_status' => [
                'active' => ['count' => 0, 'revenue' => 0, 'issues' => 0],
                'cancelled' => ['count' => 0, 'revenue' => 0, 'issues' => 0],
                'partial_cancelled' => ['count' => 0, 'revenue' => 0, 'issues' => 0]
            ],

            'payment_analysis' => [
                'total_paid' => 0,
                'total_vouchers_used' => 0,
                'total_refunded' => 0,
                'total_no_refund' => 0,
                'payment_methods_breakdown' => []
            ],

            'issues' => [
                'total_with_financial_issues' => 0,
                'critical_issues_count' => 0,
                'high_priority_count' => 0,
                'medium_priority_count' => 0,
                'low_priority_count' => 0,
                'issues_by_type' => []
            ],

            'confidence_metrics' => [
                'average_confidence_score' => 0,
                'high_confidence_count' => 0,
                'low_confidence_count' => 0
            ]
        ];
    }

    private function meetsCriteria(array $analysis, Request $request): bool
    {
        // FILTRO: Solo inconsistentes
        if (!$request->boolean('include_consistent', true)) {
            if ($analysis['discrepancy_analysis']['is_financially_consistent']) {
                return false;
            }
        }

        // FILTRO: Discrepancia mínima
        if ($request->has('min_discrepancy')) {
            $minDiscrepancy = $request->get('min_discrepancy');
            if ($analysis['discrepancy_analysis']['main_discrepancy_amount'] < $minDiscrepancy) {
                return false;
            }
        }

        return true;
    }

    private function accumulateGlobalStats(array &$globalStats, array $analysis): void
    {
        $globalStats['totals']['total_bookings_analyzed']++;

        // ✅ Comprobar existencia de 'calculated_data'
        $totalFinal = $analysis['calculated_data']['total_final'] ?? 0;
        $globalStats['totals']['total_calculated_revenue'] += $totalFinal;

        $globalStats['totals']['total_received_amount'] += $analysis['financial_reality']['total_received'] ?? 0;
        $globalStats['totals']['net_financial_position'] += $analysis['financial_reality']['net_balance'] ?? 0;

        if (!isset($analysis['calculated_data'])) {
            Log::error('⚠️ Falta calculated_data en análisis', [
                'booking_id' => $analysis['booking_info']['id'] ?? 'UNKNOWN',
                'analysis_keys' => array_keys($analysis)
            ]);
        }


        // ✅ Consistencia
        if (!empty($analysis['discrepancy_analysis']['is_financially_consistent'])) {
            $globalStats['consistency']['consistent_bookings']++;
        } else {
            $globalStats['consistency']['inconsistent_bookings']++;
            $globalStats['consistency']['total_discrepancy_amount'] += $analysis['discrepancy_analysis']['main_discrepancy_amount'] ?? 0;
        }

        // ✅ Por estado
        $status = $analysis['booking_info']['status'] ?? null;
        $statusKey = $this->getStatusKey($status);
        $globalStats['by_status'][$statusKey]['count']++;
        $globalStats['by_status'][$statusKey]['revenue'] += $totalFinal;

        if (!empty($analysis['discrepancy_analysis']) && !$analysis['discrepancy_analysis']['is_financially_consistent']) {
            $globalStats['by_status'][$statusKey]['issues']++;
        }

        // ✅ Pagos
        $globalStats['payment_analysis']['total_paid'] += $analysis['financial_reality']['total_paid'] ?? 0;
        $globalStats['payment_analysis']['total_vouchers_used'] += $analysis['financial_reality']['total_vouchers_used'] ?? 0;
        $globalStats['payment_analysis']['total_refunded'] += $analysis['financial_reality']['total_refunded'] ?? 0;

        // ✅ Problemas detectados
        if (!empty($analysis['detected_issues'])) {
            $globalStats['issues']['total_with_financial_issues']++;
        }

        // ✅ Confianza
        $confidence = $analysis['confidence_score'] ?? 0;
        $globalStats['confidence_metrics']['average_confidence_score'] += $confidence;
        if ($confidence >= 80) {
            $globalStats['confidence_metrics']['high_confidence_count']++;
        } else {
            $globalStats['confidence_metrics']['low_confidence_count']++;
        }
    }


    private function calculateFinalMetrics(array &$globalStats, int $processedCount): void
    {
        if ($processedCount > 0) {
            $globalStats['consistency']['consistency_rate'] = round(
                ($globalStats['consistency']['consistent_bookings'] / $processedCount) * 100, 2
            );

            $globalStats['consistency']['average_discrepancy'] = round(
                $globalStats['consistency']['total_discrepancy_amount'] / max(1, $globalStats['consistency']['inconsistent_bookings']), 2
            );

            $globalStats['confidence_metrics']['average_confidence_score'] = round(
                $globalStats['confidence_metrics']['average_confidence_score'] / $processedCount, 2
            );
        }

        // REDONDEAR TOTALES
        foreach ($globalStats['totals'] as $key => $value) {
            $globalStats['totals'][$key] = round($value, 2);
        }
    }

    private function formatAnalysisForResponse(array $analysis): array
    {
        return [
            'booking_id' => $analysis['booking_id'] ?? null,
            'client_name' => $analysis['booking_info']['client_name'] ?? 'Desconocido',
            'client_email' => $analysis['booking_info']['client_email'] ?? 'N/A',
            'status' => $analysis['booking_info']['status'] ?? null,
            'paid' => $analysis['booking_info']['paid'] ?? null,
            'calculated_price' => $analysis['calculated_data']['total_final'] ?? 0,
            'net_balance' => $analysis['financial_reality']['net_balance'] ?? 0,
            'is_consistent' => $analysis['discrepancy_analysis']['is_financially_consistent'] ?? false,
            'discrepancy_amount' => $analysis['discrepancy_analysis']['main_discrepancy_amount'] ?? 0,
            'discrepancy_amount' => $analysis['discrepancy_analysis']['main_discrepancy_direction'] ?? 0,
            'action_required' => $analysis['action_required'] ?? false,
            'confidence_score' => $analysis['confidence_score'] ?? 0,
            // 'priority_issues' => $this->extractPriorityIssues($analysis),
            // 'key_recommendations' => $this->extractKeyRecommendations($analysis)
        ];
    }

    private function getStatusKey(int $status): string
    {
        $statusMap = [1 => 'active', 2 => 'cancelled', 3 => 'partial_cancelled'];
        return $statusMap[$status] ?? 'unknown';
    }

    /**
     * NUEVO MÉTODO PRINCIPAL: Estadísticas basadas en realidad financiera
     */
    public function getFinancialRealityStatistics(Request $request)
    {
        $query = Booking::query()
            ->with([
                'bookingUsers.course.sport',
                'bookingUsers.client',
                'bookingUsers.bookingUserExtras.courseExtra',
                'payments',
                'vouchersLogs.voucher',
                'clientMain',
                'school'
            ])
            ->whereIn('status', [1, 2, 3]);

        if ($request->school_id) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->booking_id) {
            $query->where('id', $request->booking_id);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereHas('bookingUsers', function($q) use ($request) {
                $q->whereBetween('date', [$request->start_date, $request->end_date]);
            });
        }

        $bookings = $query->get();

        // Filtrar reservas que solo tienen cursos excluidos
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);
        $filteredBookings = $bookings->filter(function($booking) use ($excludedCourses) {
            $activeNonExcludedCourses = $booking->bookingUsers
                ->where('status', '!=', 2)
                ->filter(function($bu) use ($excludedCourses) {
                    return !in_array((int) $bu->course_id, $excludedCourses);
                });

            return $activeNonExcludedCourses->isNotEmpty();
        });

        $statistics = [
            'analysis_method' => 'financial_reality',
            'explanation' => 'Estadísticas basadas en movimientos reales de dinero, ignorando price_total almacenado',
            'excluded_courses' => self::EXCLUDED_COURSES,
            'total_bookings' => 0,

            // 💰 REALIDAD FINANCIERA
            'financial_totals' => [
                'total_should_cost' => 0,        // Lo que DEBERÍA costar
                'total_received' => 0,           // Lo que REALMENTE se recibió
                'total_processed' => 0,          // Lo que se procesó (refunds/no_refunds)
                'net_balance' => 0,              // Balance neto real
                'pending_amount' => 0,           // Lo que realmente falta
            ],

            // 📊 DESGLOSE POR TIPO DE MOVIMIENTO
            'money_movements' => [
                'payments' => 0,
                'vouchers_used' => 0,
                'refunds' => 0,
                'vouchers_refunded' => 0,
                'no_refunds' => 0
            ],

            // 📈 ANÁLISIS POR ESTADO DE RESERVA
            'by_status' => [
                'active' => [
                    'count' => 0,
                    'should_cost' => 0,
                    'net_balance' => 0,
                    'consistency_rate' => 0
                ],
                'cancelled' => [
                    'count' => 0,
                    'was_received' => 0,
                    'processed' => 0,
                    'unprocessed' => 0
                ],
                'partial_cancelled' => [
                    'count' => 0,
                    'should_cost_active' => 0,
                    'net_balance' => 0,
                    'refunds_needed' => 0
                ]
            ],

            // 🚨 PROBLEMAS REALES
            'financial_issues' => [
                'total_with_issues' => 0,
                'missing_money' => [],      // Reservas activas con dinero faltante
                'excess_money' => [],       // Reservas con dinero de más
                'unprocessed_cancelled' => [], // Canceladas sin procesar refunds
                'partial_issues' => []      // Parciales con problemas
            ],

            // 📋 DETALLES POR RESERVA
            'booking_details' => []
        ];

        foreach ($filteredBookings as $booking) {
            $analysis = $this->analyzeBookingFinancialReality($booking);

            $statistics['total_bookings']++;

            // Acumular totales financieros
            $statistics['financial_totals']['total_should_cost'] += $analysis['calculated_total'];
            $statistics['financial_totals']['total_received'] += $analysis['financial_reality']['total_received'];
            $statistics['financial_totals']['total_processed'] += $analysis['financial_reality']['total_processed'];
            $statistics['financial_totals']['net_balance'] += $analysis['financial_reality']['net_balance'];
            $statistics['financial_totals']['pending_amount'] += max(0, $analysis['calculated_total'] - $analysis['financial_reality']['net_balance']);

            // Acumular movimientos de dinero
            $statistics['money_movements']['payments'] += $analysis['financial_reality']['total_paid'];
            $statistics['money_movements']['vouchers_used'] += $analysis['financial_reality']['total_vouchers_used'];
            $statistics['money_movements']['refunds'] += $analysis['financial_reality']['total_refunded'];
            $statistics['money_movements']['vouchers_refunded'] += $analysis['financial_reality']['total_vouchers_refunded'];
            $statistics['money_movements']['no_refunds'] += $analysis['financial_reality']['total_no_refund'];

            // Análisis por estado
            $this->analyzeByStatus($booking, $analysis, $statistics);

            // Detectar problemas financieros reales
            $this->detectFinancialIssues($booking, $analysis, $statistics);

            $statistics['booking_details'][] = $analysis;
        }

        // Calcular tasas de consistencia
        $this->calculateConsistencyRates($statistics);

        // Redondear totales
        $this->roundFinancialTotals($statistics);

        return $this->sendResponse($statistics, 'Financial reality statistics completed - based on actual money movements');
    }

    /**
     * NUEVO: Analizar una reserva basándose solo en realidad financiera
     */
    private function analyzeBookingFinancialReality(Booking $booking): array
    {
        try {
            $realityAnalysis = $booking->checkFinancialReality();

            return [
                'booking_id' => $booking->id,
                'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,
                'client_email' => $booking->clientMain->email,
                'status' => $booking->status,
                'source' => $booking->source ?? 'unknown',
                'booking_date' => $booking->created_at->format('Y-m-d H:i:s'),

                // 💰 LO QUE IMPORTA: DINERO REAL
                'calculated_total' => $realityAnalysis['calculated_total'],
                'financial_reality' => $realityAnalysis['financial_reality'],
                'reality_check' => $realityAnalysis['reality_check'],

                // 📊 ESTADO FINANCIERO
                'is_financially_consistent' => $realityAnalysis['reality_check']['is_consistent'],
                'financial_discrepancy' => abs($realityAnalysis['reality_check']['main_discrepancy'] ?? 0),
                'discrepancy_type' => $realityAnalysis['reality_check']['consistency_type'],

                // ℹ️ SOLO INFORMATIVO (ignorado para cálculos)
                'stored_total_info' => [
                    'amount' => $booking->price_total,
                    'note' => 'Solo informativo - no usado para cálculos'
                ],

                'recommendation' => $realityAnalysis['recommendation'],
                'issues' => $realityAnalysis['reality_check']['issues'] ?? [],
                'analysis_method' => 'financial_reality'
            ];

        } catch (\Exception $e) {
            Log::error('Error analyzing booking financial reality: ' . $e->getMessage(), [
                'booking_id' => $booking->id
            ]);

            return [
                'booking_id' => $booking->id,
                'error' => 'Analysis failed',
                'calculated_total' => 0,
                'financial_reality' => ['net_balance' => 0],
                'is_financially_consistent' => false,
                'financial_discrepancy' => 0
            ];
        }
    }

    /**
     * NUEVO: Analizar por estado de reserva
     */
    private function analyzeByStatus(Booking $booking, array $analysis, array &$statistics): void
    {
        $calculatedTotal = $analysis['calculated_total'];
        $netBalance = $analysis['financial_reality']['net_balance'];
        $isConsistent = $analysis['is_financially_consistent'];

        switch ($booking->status) {
            case 1: // ACTIVA
                $statistics['by_status']['active']['count']++;
                $statistics['by_status']['active']['should_cost'] += $calculatedTotal;
                $statistics['by_status']['active']['net_balance'] += $netBalance;
                if ($isConsistent) {
                    $statistics['by_status']['active']['consistent_count'] =
                        ($statistics['by_status']['active']['consistent_count'] ?? 0) + 1;
                }
                break;

            case 2: // CANCELADA
                $statistics['by_status']['cancelled']['count']++;
                $statistics['by_status']['cancelled']['was_received'] += $analysis['financial_reality']['total_received'];
                $statistics['by_status']['cancelled']['processed'] += $analysis['financial_reality']['total_processed'];
                $statistics['by_status']['cancelled']['unprocessed'] += max(0, $netBalance);
                break;

            case 3: // PARCIALMENTE CANCELADA
                $statistics['by_status']['partial_cancelled']['count']++;
                $statistics['by_status']['partial_cancelled']['should_cost_active'] += $calculatedTotal;
                $statistics['by_status']['partial_cancelled']['net_balance'] += $netBalance;

                if ($netBalance > $calculatedTotal) {
                    $statistics['by_status']['partial_cancelled']['refunds_needed'] +=
                        ($netBalance - $calculatedTotal);
                }
                break;
        }
    }

    /**
     * NUEVO: Detectar problemas financieros reales
     */
    private function detectFinancialIssues(Booking $booking, array $analysis, array &$statistics): void
    {
        if ($analysis['is_financially_consistent']) {
            return; // Sin problemas reales
        }

        $calculatedTotal = $analysis['calculated_total'];
        $netBalance = $analysis['financial_reality']['net_balance'];
        $discrepancy = $analysis['financial_discrepancy'];

        $issueInfo = [
            'booking_id' => $booking->id,
            'client_name' => $analysis['client_name'],
            'status' => $booking->status,
            'calculated_total' => $calculatedTotal,
            'net_balance' => $netBalance,
            'discrepancy' => $discrepancy,
            'issues' => $analysis['issues']
        ];

        $statistics['financial_issues']['total_with_issues']++;

        switch ($booking->status) {
            case 1: // ACTIVA
                if ($netBalance < $calculatedTotal) {
                    $statistics['financial_issues']['missing_money'][] = array_merge($issueInfo, [
                        'missing_amount' => round($calculatedTotal - $netBalance, 2)
                    ]);
                } else {
                    $statistics['financial_issues']['excess_money'][] = array_merge($issueInfo, [
                        'excess_amount' => round($netBalance - $calculatedTotal, 2)
                    ]);
                }
                break;

            case 2: // CANCELADA
                if ($netBalance > 0.50) {
                    $statistics['financial_issues']['unprocessed_cancelled'][] = array_merge($issueInfo, [
                        'unprocessed_amount' => round($netBalance, 2)
                    ]);
                }
                break;

            case 3: // PARCIALMENTE CANCELADA
                $statistics['financial_issues']['partial_issues'][] = $issueInfo;
                break;
        }
    }

    /**
     * NUEVO: Calcular tasas de consistencia
     */
    private function calculateConsistencyRates(array &$statistics): void
    {
        // Tasa de consistencia para activas
        $activeCount = $statistics['by_status']['active']['count'];
        $activeConsistent = $statistics['by_status']['active']['consistent_count'] ?? 0;
        $statistics['by_status']['active']['consistency_rate'] = $activeCount > 0
            ? round(($activeConsistent / $activeCount) * 100, 2)
            : 100;

        // Porcentaje de reservas con problemas
        $statistics['financial_issues']['issues_percentage'] = $statistics['total_bookings'] > 0
            ? round(($statistics['financial_issues']['total_with_issues'] / $statistics['total_bookings']) * 100, 2)
            : 0;
    }

    /**
     * NUEVO: Redondear totales financieros
     */
    private function roundFinancialTotals(array &$statistics): void
    {
        foreach ($statistics['financial_totals'] as $key => $value) {
            $statistics['financial_totals'][$key] = round($value, 2);
        }

        foreach ($statistics['money_movements'] as $key => $value) {
            $statistics['money_movements'][$key] = round($value, 2);
        }

        foreach (['should_cost', 'net_balance'] as $field) {
            if (isset($statistics['by_status']['active'][$field])) {
                $statistics['by_status']['active'][$field] = round($statistics['by_status']['active'][$field], 2);
            }
        }

        foreach (['was_received', 'processed', 'unprocessed'] as $field) {
            if (isset($statistics['by_status']['cancelled'][$field])) {
                $statistics['by_status']['cancelled'][$field] = round($statistics['by_status']['cancelled'][$field], 2);
            }
        }

        foreach (['should_cost_active', 'net_balance', 'refunds_needed'] as $field) {
            if (isset($statistics['by_status']['partial_cancelled'][$field])) {
                $statistics['by_status']['partial_cancelled'][$field] = round($statistics['by_status']['partial_cancelled'][$field], 2);
            }
        }
    }

    /**
     * NUEVO: Endpoint para comparación directa con método anterior
     */
    public function compareStatisticsMethods(Request $request)
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id'
        ]);

        // Estadísticas con método anterior (price_total)
        $oldMethodRequest = new Request($request->all());
        $oldStats = $this->checkBookingsImproved($oldMethodRequest)->getData()->data;

        // Estadísticas con realidad financiera
        $newStats = $this->getFinancialRealityStatistics($request)->getData()->data;

        $comparison = [
            'school_id' => $request->school_id,
            'comparison_date' => now()->format('Y-m-d H:i:s'),

            'old_method' => [
                'name' => 'Price_total vs Calculated',
                'total_bookings' => $oldStats->total_bookings,
                'total_stored' => $oldStats->total_stored,
                'total_calculated' => $oldStats->total_calculated,
                'discrepancies_count' => $oldStats->discrepancies_count,
                'discrepancies_amount' => $oldStats->discrepancies_amount
            ],

            'new_method' => [
                'name' => 'Financial Reality vs Calculated',
                'total_bookings' => $newStats->total_bookings,
                'total_should_cost' => $newStats->financial_totals->total_should_cost,
                'total_received' => $newStats->financial_totals->total_received,
                'issues_count' => $newStats->financial_issues->total_with_issues,
                'pending_amount' => $newStats->financial_totals->pending_amount
            ],

            'differences' => [
                'calculation_method' => 'Old: stored vs calculated | New: financial reality vs calculated',
                'discrepancies_difference' => $oldStats->discrepancies_count - $newStats->financial_issues->total_with_issues,
                'amount_difference' => round($oldStats->total_stored - $newStats->financial_totals->total_received, 2),
                'reliability' => 'New method more reliable - based on actual money movements'
            ],

            'recommendation' => $newStats->financial_issues->total_with_issues < $oldStats->discrepancies_count
                ? '✅ New method shows fewer real issues - old discrepancies were data inconsistencies'
                : '⚠️ New method reveals real financial problems that need attention'
        ];

        return $this->sendResponse($comparison, 'Statistics methods comparison completed');
    }

    /**
     * NUEVO: Dashboard financiero ejecutivo
     */
    public function getExecutiveFinancialDashboard(Request $request)
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'period' => 'nullable|in:week,month,quarter,year'
        ]);

        $period = $request->get('period', 'month');
        $endDate = now();

        switch ($period) {
            case 'week':
                $startDate = $endDate->copy()->subWeek();
                break;
            case 'quarter':
                $startDate = $endDate->copy()->subMonths(3);
                break;
            case 'year':
                $startDate = $endDate->copy()->subYear();
                break;
            default: // month
                $startDate = $endDate->copy()->subMonth();
                break;
        }

        $tempRequest = new Request([
            'school_id' => $request->school_id,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ]);

        $stats = $this->getFinancialRealityStatistics($tempRequest)->getData()->data;

        $dashboard = [
            'period' => $period,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],

            // 📊 KPIs PRINCIPALES
            'kpis' => [
                'total_revenue_expected' => $stats->financial_totals->total_should_cost,
                'total_revenue_received' => $stats->financial_totals->total_received,
                'collection_rate' => $stats->financial_totals->total_should_cost > 0
                    ? round(($stats->financial_totals->total_received / $stats->financial_totals->total_should_cost) * 100, 2)
                    : 100,
                'pending_collections' => $stats->financial_totals->pending_amount,
                'financial_health_score' => 100 - $stats->financial_issues->issues_percentage
            ],

            // 💰 FLUJO DE DINERO
            'cash_flow' => [
                'inflows' => [
                    'payments' => $stats->money_movements->payments,
                    'vouchers' => $stats->money_movements->vouchers_used,
                    'total' => $stats->money_movements->payments + $stats->money_movements->vouchers_used
                ],
                'outflows' => [
                    'refunds' => $stats->money_movements->refunds,
                    'voucher_refunds' => $stats->money_movements->vouchers_refunded,
                    'total' => $stats->money_movements->refunds + $stats->money_movements->vouchers_refunded
                ],
                'net_flow' => $stats->financial_totals->net_balance
            ],

            // 🚨 ALERTAS
            'alerts' => [
                'critical' => [],
                'warning' => [],
                'info' => []
            ],

            'analysis_method' => 'financial_reality',
            'generated_at' => now()->format('Y-m-d H:i:s')
        ];

        // Generar alertas basadas en los datos
        $this->generateFinancialAlerts($stats, $dashboard);

        return $this->sendResponse($dashboard, 'Executive financial dashboard generated');
    }

    /**
     * NUEVO: Generar alertas financieras
     */
    private function generateFinancialAlerts(object $stats, array &$dashboard): void
    {
        // Alerta crítica: Mucho dinero sin cobrar
        if ($stats->financial_totals->pending_amount > 1000) {
            $dashboard['alerts']['critical'][] = [
                'type' => 'high_pending_amount',
                'message' => "Alto importe pendiente de cobro: " . number_format($stats->financial_totals->pending_amount, 2) . "€",
                'action' => 'Revisar reservas activas con pagos pendientes'
            ];
        }

        // Alerta crítica: Muchas canceladas sin procesar
        $unprocessedCancelled = count($stats->financial_issues->unprocessed_cancelled ?? []);
        if ($unprocessedCancelled > 5) {
            $dashboard['alerts']['critical'][] = [
                'type' => 'unprocessed_cancellations',
                'message' => "{$unprocessedCancelled} reservas canceladas con dinero sin procesar",
                'action' => 'Procesar refunds pendientes'
            ];
        }

        // Alerta warning: Tasa de cobro baja
        $collectionRate = $stats->financial_totals->total_should_cost > 0
            ? ($stats->financial_totals->total_received / $stats->financial_totals->total_should_cost) * 100
            : 100;

        if ($collectionRate < 90) {
            $dashboard['alerts']['warning'][] = [
                'type' => 'low_collection_rate',
                'message' => "Tasa de cobro baja: " . round($collectionRate, 1) . "%",
                'action' => 'Revisar proceso de cobros'
            ];
        }

        // Info: Resumen de problemas
        if ($stats->financial_issues->total_with_issues > 0) {
            $dashboard['alerts']['info'][] = [
                'type' => 'financial_issues_summary',
                'message' => "{$stats->financial_issues->total_with_issues} reservas con problemas financieros detectados",
                'action' => 'Ver detalle en estadísticas completas'
            ];
        }
    }



// Agregar estos métodos al StatisticsController.php

    /**
     * NUEVO: Análisis mejorado completo usando el servicio centralizado
     */
    public function checkBookingsImproved(Request $request)
    {
        $query = Booking::query()
            ->with([
                'bookingUsers.course.sport',
                'bookingUsers.client',
                'bookingUsers.bookingUserExtras.courseExtra',
                'payments',
                'vouchersLogs.voucher',
                'clientMain',
                'bookingLogs' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                },
                'school'
            ])
            ->whereIn('status', [1, 2, 3]);

        if ($request->school_id) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->booking_id) {
            $query->where('id', $request->booking_id);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereHas('bookingUsers', function ($q) use ($request) {
                $q->whereBetween('date', [$request->start_date, $request->end_date]);
            });
        }

        $bookings = $query->get();

        // ✅ FILTRAR RESERVAS QUE SOLO TIENEN CURSOS EXCLUIDOS
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);
        $filteredBookings = $bookings->filter(function ($booking) use ($excludedCourses) {
            $activeNonExcludedCourses = $booking->bookingUsers
                ->where('status', '!=', 2)
                ->filter(function ($bu) use ($excludedCourses) {
                    return !in_array((int)$bu->course_id, $excludedCourses);
                });

            return $activeNonExcludedCourses->isNotEmpty();
        });

        $summary = [
            'total_bookings' => 0,
            'total_calculated' => 0,
            'total_stored' => 0,
            'total_paid_real' => 0,
            'total_vouchers_used' => 0,
            'total_refunded' => 0,
            'total_pending' => 0,
            'discrepancies_count' => 0,
            'discrepancies_amount' => 0,
            'active_bookings' => 0,
            'cancelled_bookings' => 0,
            'partial_cancelled_bookings' => 0,
            'payment_methods' => [
                'cash' => 0, 'card' => 0, 'transfer' => 0,
                'boukii' => 0, 'online' => 0, 'vouchers' => 0, 'other' => 0
            ],
            'refund_analysis' => [
                'total_refunds' => 0, 'refund_full' => 0, 'refund_partial' => 0,
                'no_refund' => 0, 'refund_pending' => 0
            ],
            'bookings_to_review' => [
                'high_priority' => [], 'medium_priority' => [],
                'cancelled_with_money' => [], 'unpaid_but_attended' => []
            ],
            'calculation_method' => 'new_centralized_service',
            'excluded_courses' => self::EXCLUDED_COURSES,
            'booking_details' => []
        ];


        foreach ($filteredBookings as $booking) {
            $analysis = $this->analyzeBookingWithNewService($booking);

            $summary['total_bookings']++;

            // Contar por estado
            switch ($booking->status) {
                case 1: $summary['active_bookings']++; break;
                case 2: $summary['cancelled_bookings']++; break;
                case 3: $summary['partial_cancelled_bookings']++; break;
            }

            // ✅ CORREGIDO: Acumular totales incluyendo vouchers
            $summary['total_calculated'] += $analysis['calculated_total'];
            $summary['total_stored'] += $analysis['stored_total'];
            $summary['total_paid_real'] += $analysis['payments']['total_paid'];
            $summary['total_vouchers_used'] += $analysis['vouchers']['total_used']; // ✅ Como dinero recibido
            $summary['total_refunded'] += $analysis['refunds']['total_refunded'];
            $summary['total_pending'] += $analysis['pending_amount'];

            // Contar discrepancias
            if ($analysis['has_discrepancy']) {
                $summary['discrepancies_count']++;
                $summary['discrepancies_amount'] += abs($analysis['discrepancy_amount']);
            }

            // ✅ AÑADIR: Total dinero recibido (pagos + vouchers)
            $summary['total_received'] = ($summary['total_received'] ?? 0) +
                $analysis['payments']['total_paid'] + $analysis['vouchers']['total_used'];

            // Categorizar para revisión
            $this->categorizeBookingForReviewImproved($booking, $analysis, $summary);

            $summary['booking_details'][] = $analysis;
        }

        // ✅ AÑADIR: Información sobre vouchers en el resumen
        $summary['vouchers_info'] = [
            'total_vouchers_used' => $summary['total_vouchers_used'],
            'note' => 'Los vouchers se cuentan como dinero recibido, igual que los pagos'
        ];

        // Redondear totales (añadir total_received)
        foreach (['total_calculated', 'total_stored', 'total_paid_real', 'total_vouchers_used', 'total_received', 'total_refunded', 'total_pending', 'discrepancies_amount'] as $field) {
            $summary[$field] = round($summary[$field] ?? 0, 2);
        }

        return $this->sendResponse($summary, 'Improved booking analysis completed - vouchers included in financial balance');
    }


    /**
     * NUEVO: Análisis mejorado de pagos
     */
    private function analyzePaymentsImproved(Booking $booking): array
    {
        $payments = $booking->payments;

        return [
            'total_paid' => $payments->whereIn('status', ['paid'])->sum('amount'),
            'total_refunded' => $payments->whereIn('status', ['refund', 'partial_refund'])->sum('amount'),
            'total_no_refund' => $payments->whereIn('status', ['no_refund'])->sum('amount'),
            'by_method' => $this->groupPaymentsByMethodImproved($payments),
            'details' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'method' => $this->determinePaymentMethodImproved($payment),
                    'notes' => $payment->notes,
                    'date' => $payment->created_at->format('Y-m-d H:i:s'),
                    'has_payrexx' => !empty($payment->payrexx_reference)
                ];
            })->toArray()
        ];
    }

    /**
     * NUEVO: Análisis mejorado de vouchers
     */
    /**
     * CORREGIDO: Análisis mejorado de vouchers que diferencia payment/refund
     */
    private function analyzeVouchersImproved(Booking $booking): array
    {
        // Usar el análisis del servicio que ya diferencia payment/refund
        $voucherAnalysis = $this->priceCalculator->analyzeVouchersForBalance($booking);

        return [
            'total_used' => $voucherAnalysis['total_used'], // Vouchers usados (dinero "recibido")
            'total_refunded' => $voucherAnalysis['total_refunded'], // Vouchers refundados
            'net_payment' => $voucherAnalysis['net_voucher_payment'], // Neto de vouchers
            'count' => $booking->vouchersLogs->count(),
            'details' => $voucherAnalysis['details']
        ];
    }
    /**
     * ACTUALIZADO: Análisis basado en realidad financiera
     */
    private function analyzeBookingWithNewService(Booking $booking): array
    {
        try {
            // ✅ USAR ANÁLISIS DE REALIDAD FINANCIERA
            $realityAnalysis = $booking->checkFinancialReality();
            $financialSummary = $booking->getFinancialSummary();

            return [
                'booking_id' => $booking->id,
                'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,
                'client_email' => $booking->clientMain->email,
                'booking_date' => $booking->created_at->format('Y-m-d H:i:s'),
                'source' => $booking->source ?? 'unknown',
                'status' => $booking->status,

                // ✅ COMPARACIÓN HONESTA: realidad vs cálculo
                'stored_total' => $booking->price_total, // Solo informativo
                'calculated_total' => $realityAnalysis['calculated_total'],
                'financial_reality' => $realityAnalysis['financial_reality'],
                'reality_vs_calculated' => $realityAnalysis['reality_check'],

                'calculation_breakdown' => $realityAnalysis['calculation_details']['breakdown'],
                'activities_price' => $realityAnalysis['calculation_details']['activities_price'],
                'additional_concepts' => $realityAnalysis['calculation_details']['additional_concepts'],
                'discounts' => $realityAnalysis['calculation_details']['discounts'],

                'payments' => $this->analyzePaymentsImproved($booking),
                'vouchers' => $this->analyzeVouchersImproved($booking),
                'refunds' => $this->analyzeRefundsImproved($booking),

                // ✅ USAR REALIDAD FINANCIERA PARA DISCREPANCIAS
                'has_discrepancy' => !$realityAnalysis['reality_check']['is_consistent'],
                'discrepancy_amount' => abs($realityAnalysis['reality_check']['main_discrepancy'] ?? 0),
                'discrepancy_type' => $realityAnalysis['reality_check']['consistency_type'],

                'pending_amount' => $financialSummary['pending_amount'],
                'is_fully_paid' => $financialSummary['is_fully_paid'],

                'issues' => $this->detectIssuesFromReality($booking, $realityAnalysis),
                'recommendation' => $realityAnalysis['recommendation'],
                'validation' => $booking->validateAllPrices(),

                'analysis_method' => 'financial_reality',
                'explanation' => 'Análisis basado en movimientos reales de dinero, no en price_total almacenado'
            ];

        } catch (\Exception $e) {
            Log::error('Error analyzing booking financial reality: ' . $e->getMessage(), [
                'booking_id' => $booking->id,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->analyzeBookingLegacyFallback($booking);
        }
    }

    /**
     * NUEVO: Detectar problemas basados en realidad financiera
     */
    private function detectIssuesFromReality(Booking $booking, array $realityAnalysis): array
    {
        $issues = [];
        $realityCheck = $realityAnalysis['reality_check'];

        // Añadir issues del reality check
        foreach ($realityCheck['issues'] as $issue) {
            $issues[] = $issue;
        }

        // Issue adicional: price_total muy diferente de la realidad
        $storedTotal = $booking->price_total;
        $calculatedTotal = $realityAnalysis['calculated_total'];
        $receivedAmount = $realityAnalysis['financial_reality']['total_received'];

        if (abs($storedTotal - $calculatedTotal) > 10) {
            $issues[] = "Price_total almacenado ({$storedTotal}€) muy diferente del calculado ({$calculatedTotal}€)";
        }

        if (abs($storedTotal - $receivedAmount) > 10) {
            $issues[] = "Price_total almacenado ({$storedTotal}€) muy diferente de lo recibido ({$receivedAmount}€)";
        }

        return $issues;
    }

    /**
     * NUEVO: Endpoint para comparar enfoques
     */
    public function compareAnalysisApproaches(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id'
        ]);

        $booking = Booking::with([
            'bookingUsers.course.sport',
            'bookingUsers.bookingUserExtras.courseExtra',
            'payments',
            'vouchersLogs.voucher',
            'clientMain'
        ])->find($request->booking_id);

        $comparison = [
            'booking_id' => $booking->id,
            'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,

            // Enfoque tradicional (price_total vs calculated)
            'traditional_approach' => [
                'stored_total' => $booking->price_total,
                'calculated_total' => $booking->calculateCurrentTotal()['total_final'],
                'discrepancy' => abs($booking->price_total - $booking->calculateCurrentTotal()['total_final']),
                'method' => 'Comparar price_total almacenado vs calculado'
            ],

            // Nuevo enfoque (realidad financiera vs calculated)
            'financial_reality_approach' => [
                'calculated_total' => null,
                'financial_reality' => null,
                'reality_check' => null,
                'method' => 'Comparar movimientos reales de dinero vs calculado'
            ],

            'recommendation' => '',
            'why_different' => []
        ];

        // Analizar con nuevo enfoque
        $realityAnalysis = $booking->checkFinancialReality();
        $comparison['financial_reality_approach'] = [
            'calculated_total' => $realityAnalysis['calculated_total'],
            'received_amount' => $realityAnalysis['financial_reality']['total_received'],
            'net_balance' => $realityAnalysis['financial_reality']['net_balance'],
            'discrepancy' => abs($realityAnalysis['reality_check']['main_discrepancy'] ?? 0),
            'is_consistent' => $realityAnalysis['reality_check']['is_consistent'],
            'method' => 'Comparar movimientos reales de dinero vs calculado'
        ];

        // Comparar ambos enfoques
        $traditionalConsistent = $comparison['traditional_approach']['discrepancy'] <= 0.50;
        $realityConsistent = $comparison['financial_reality_approach']['is_consistent'];

        if ($traditionalConsistent && $realityConsistent) {
            $comparison['recommendation'] = "✅ Ambos enfoques coinciden - reserva consistente";
        } elseif (!$traditionalConsistent && $realityConsistent) {
            $comparison['recommendation'] = "🔄 Price_total almacenado incorrecto, pero realidad financiera OK";
            $comparison['why_different'][] = "El price_total en DB necesita actualización";
        } elseif ($traditionalConsistent && !$realityConsistent) {
            $comparison['recommendation'] = "⚠️ Price_total coincide pero hay problema en movimientos de dinero";
            $comparison['why_different'][] = "Revisar pagos, vouchers o refunds";
        } else {
            $comparison['recommendation'] = "🚨 Problemas en ambos enfoques - revisar completamente";
            $comparison['why_different'][] = "Múltiples inconsistencias detectadas";
        }

        return $this->sendResponse($comparison, 'Comparison between analysis approaches completed');
    }

    /**
     * CORREGIDO: Detectar problemas considerando vouchers en price_total
     */
    private function detectIssuesWithNewServiceFixed(Booking $booking, array $calculation, array $financialSummary, array $consistency): array
    {
        $issues = [];

        // ✅ NUEVA LÓGICA: Verificar discrepancias considerando vouchers
        if (!$consistency['is_consistent']) {
            $discrepancy = $consistency['discrepancy'];

            if ($consistency['vouchers_in_stored_price'] ?? false) {
                // Si vouchers están en price_total, la discrepancia es diferente
                if ($discrepancy > 10) {
                    $issues[] = "Discrepancia significativa en precio neto (considerando vouchers): " . round($discrepancy, 2) . "€";
                } elseif ($discrepancy > 1) {
                    $issues[] = "Discrepancia menor en precio neto (considerando vouchers): " . round($discrepancy, 2) . "€";
                }
            } else {
                // Lógica original
                if ($discrepancy > 10) {
                    $issues[] = "Discrepancia significativa de precio: " . round($discrepancy, 2) . "€";
                } elseif ($discrepancy > 1) {
                    $issues[] = "Discrepancia menor de precio: " . round($discrepancy, 2) . "€";
                }
            }
        }

        // Verificar problemas de balance (considerando vouchers)
        $balance = $financialSummary['balance'];
        if ($booking->status == 1 && $booking->paid && $balance['current_balance'] < $calculation['total_final'] - 0.01) {
            $shortfall = $calculation['total_final'] - $balance['current_balance'];
            $issues[] = "Marcada como pagada pero falta: " . round($shortfall, 2) . "€ (incluyendo vouchers)";
        }

        // Verificar vouchers problemáticos
        $totalVouchersUsed = $balance['total_vouchers_used'] ?? 0;
        if ($totalVouchersUsed > $calculation['total_final']) {
            $excess = $totalVouchersUsed - $calculation['total_final'];
            $issues[] = "Vouchers exceden el precio total por: " . round($excess, 2) . "€";
        }

        // ✅ NUEVO: Verificar si los vouchers están mal interpretados
        if ($consistency['vouchers_in_stored_price'] ?? false) {
            $voucherDiscount = $consistency['voucher_discount'] ?? 0;
            if ($voucherDiscount != $totalVouchersUsed && abs($voucherDiscount - $totalVouchersUsed) > 0.01) {
                $issues[] = "Inconsistencia en interpretación de vouchers: descuento calculado {$voucherDiscount}€ vs usado {$totalVouchersUsed}€";
            }
        }

        return $issues;
    }


    /**
     * NUEVO: Análisis mejorado de refunds
     */
    private function analyzeRefundsImproved(Booking $booking): array
    {
        $refunds = $booking->payments->whereIn('status', ['refund', 'partial_refund', 'no_refund']);

        return [
            'total_refunded' => $refunds->whereIn('status', ['refund', 'partial_refund'])->sum('amount'),
            'total_no_refund' => $refunds->where('status', 'no_refund')->sum('amount'),
            'breakdown' => [
                'refund_full' => $refunds->where('status', 'refund')->sum('amount'),
                'refund_partial' => $refunds->where('status', 'partial_refund')->sum('amount'),
                'no_refund' => $refunds->where('status', 'no_refund')->sum('amount')
            ],
            'details' => $refunds->map(function ($refund) {
                return [
                    'id' => $refund->id,
                    'amount' => $refund->amount,
                    'status' => $refund->status,
                    'reason' => $this->determineRefundReasonImproved($refund),
                    'notes' => $refund->notes,
                    'date' => $refund->created_at->format('Y-m-d H:i:s')
                ];
            })->toArray()
        ];
    }

    /**
     * NUEVO: Detectar problemas usando el nuevo servicio
     */
    private function detectIssuesWithNewService(Booking $booking, array $calculation, array $financialSummary): array
    {
        $issues = [];

        // Verificar discrepancias de precio
        if (!$financialSummary['consistency']['is_consistent']) {
            $discrepancy = $financialSummary['consistency']['discrepancy'];
            if ($discrepancy > 10) {
                $issues[] = "Discrepancia significativa de precio: " . round($discrepancy, 2) . "€";
            } elseif ($discrepancy > 1) {
                $issues[] = "Discrepancia menor de precio: " . round($discrepancy, 2) . "€";
            }
        }

        // Verificar problemas de balance (considerando vouchers)
        $balance = $financialSummary['balance'];
        if ($booking->status == 1 && $booking->paid && $balance['current_balance'] < $calculation['total_final'] - 0.01) {
            $shortfall = $calculation['total_final'] - $balance['current_balance'];
            $issues[] = "Marcada como pagada pero falta: " . round($shortfall, 2) . "€ (incluyendo vouchers)";
        }

        // Verificar vouchers problemáticos
        $totalVouchersUsed = $balance['total_vouchers_used'] ?? 0;
        if ($totalVouchersUsed > $calculation['total_final']) {
            $excess = $totalVouchersUsed - $calculation['total_final'];
            $issues[] = "Vouchers exceden el precio total por: " . round($excess, 2) . "€";
        }

        // Verificar cancelaciones problemáticas
        if ($booking->status == 2) {
            $totalReceived = $balance['received']; // Ya incluye vouchers
            if ($totalReceived > 0 && $balance['current_balance'] > 0.01) {
                $issues[] = "Reserva cancelada con dinero sin procesar: " . round($balance['current_balance'], 2) . "€ (incluye vouchers)";
            }
        }

        return $issues;
    }

    /**
     * NUEVO: Categorización mejorada para revisión
     */
    private function categorizeBookingForReviewImproved(Booking $booking, array $analysis, array &$summary): void
    {
        $bookingId = $booking->id;
        $discrepancyAmount = $analysis['discrepancy_amount'] ?? 0;
        $hasDiscrepancy = $analysis['has_discrepancy'] ?? false;

        if (!$hasDiscrepancy) {
            return; // No categorizar si no hay problemas reales
        }

        $bookingInfo = [
            'id' => $bookingId,
            'status' => $booking->status,
            'client_name' => $analysis['client_name'],
            'discrepancy_amount' => $discrepancyAmount,
            'stored_total' => $analysis['stored_total'],
            'calculated_total' => $analysis['calculated_total'],
            'issues' => $analysis['issues']
        ];

        // Categorización por prioridad
        if ($discrepancyAmount > 10) {
            $summary['bookings_to_review']['high_priority'][] = $bookingInfo;
        } elseif ($discrepancyAmount > 1) {
            $summary['bookings_to_review']['medium_priority'][] = $bookingInfo;
        }

        // Categorías especiales
        if ($booking->status == 2 && in_array('Reserva cancelada con dinero sin procesar', $analysis['issues'])) {
            $summary['bookings_to_review']['cancelled_with_money'][] = $bookingInfo;
        }

        if (!$booking->paid && $booking->attendance) {
            $summary['bookings_to_review']['unpaid_but_attended'][] = $bookingInfo;
        }
    }

    /**
     * MÉTODO AUXILIAR: Agrupar pagos por método mejorado
     */
    private function groupPaymentsByMethodImproved($payments): array
    {
        $byMethod = [
            'cash' => 0, 'card' => 0, 'transfer' => 0,
            'boukii' => 0, 'online' => 0, 'other' => 0
        ];

        foreach ($payments->whereIn('status', ['paid']) as $payment) {
            $method = $this->determinePaymentMethodImproved($payment);
            $byMethod[$method] = ($byMethod[$method] ?? 0) + $payment->amount;
        }

        return $byMethod;
    }

    /**
     * MÉTODO AUXILIAR: Determinar método de pago mejorado
     */
    private function determinePaymentMethodImproved($payment): string
    {
        $notes = strtolower($payment->notes ?? '');

        if ($payment->payrexx_reference) {
            if ($payment->booking->payment_method_id == Booking::ID_BOUKIIPAY) {
                return 'boukii';
            } else {
                return 'online';
            }
        }

        if (str_contains($notes, 'cash') || str_contains($notes, 'efectivo')) {
            return 'cash';
        }

        if (str_contains($notes, 'card') || str_contains($notes, 'tarjeta')) {
            return 'card';
        }

        if (str_contains($notes, 'transfer') || str_contains($notes, 'transferencia')) {
            return 'transfer';
        }

        // Fallback basado en payment_method_id
        switch ($payment->booking->payment_method_id) {
            case Booking::ID_CASH:
                return 'cash';
            case Booking::ID_BOUKIIPAY:
                return 'boukii';
            case Booking::ID_ONLINE:
                return 'online';
            default:
                return 'other';
        }
    }

    /**
     * MÉTODO AUXILIAR: Determinar razón de refund mejorado
     */
    private function determineRefundReasonImproved($refund): string
    {
        $notes = strtolower($refund->notes ?? '');
        $status = $refund->status;

        if ($status === 'no_refund') {
            if (str_contains($notes, 'policy') || str_contains($notes, 'politica')) {
                return 'policy_no_refund';
            }
            if (str_contains($notes, 'late') || str_contains($notes, 'tardio')) {
                return 'late_cancellation_no_refund';
            }
            return 'no_refund_other';
        }

        if (str_contains($notes, 'weather') || str_contains($notes, 'clima')) {
            return 'weather_cancellation';
        }

        if (str_contains($notes, 'illness') || str_contains($notes, 'enfermedad')) {
            return 'illness';
        }

        if (str_contains($notes, 'voluntary') || str_contains($notes, 'voluntaria')) {
            return 'voluntary_cancellation';
        }

        return $status === 'refund' ? 'full_refund_other' : 'partial_refund_other';
    }

    /**
     * MÉTODO AUXILIAR: Fallback legacy si falla el nuevo servicio
     */
    private function analyzeBookingLegacyFallback(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,
            'client_email' => $booking->clientMain->email,
            'booking_date' => $booking->created_at->format('Y-m-d H:i:s'),
            'source' => $booking->source ?? 'unknown',
            'status' => $booking->status,
            'stored_total' => $booking->price_total,
            'calculated_total' => $booking->price_total, // Usar stored como fallback
            'calculation_breakdown' => [],
            'activities_price' => 0,
            'additional_concepts' => [],
            'discounts' => [],
            'payments' => ['total_paid' => 0],
            'vouchers' => ['total_used' => 0],
            'refunds' => ['total_refunded' => 0],
            'balance' => [],
            'pending_amount' => 0,
            'is_fully_paid' => false,
            'has_discrepancy' => false,
            'discrepancy_amount' => 0,
            'issues' => ['Error en cálculo - usando fallback legacy'],
            'validation' => ['is_valid' => false, 'errors' => ['Service error']]
        ];
    }

    /**
     * NUEVO: Endpoint para recalcular precios masivamente
     */
    public function recalculateBookingPrices(Request $request)
    {
        $request->validate([
            'booking_ids' => 'array',
            'booking_ids.*' => 'integer|exists:bookings,id',
            'school_id' => 'integer|exists:schools,id',
            'force_update' => 'boolean',
            'recalculate_vouchers' => 'boolean'
        ]);

        $bookingIds = $request->get('booking_ids', []);
        $schoolId = $request->get('school_id');
        $forceUpdate = $request->boolean('force_update', false);
        $recalculateVouchers = $request->boolean('recalculate_vouchers', false);

        if (empty($bookingIds) && $schoolId) {
            // Si no se especifican IDs, recalcular todas las reservas de la escuela
            $bookingIds = Booking::where('school_id', $schoolId)
                ->whereIn('status', [1, 3]) // Solo activas y parcialmente canceladas
                ->pluck('id')
                ->toArray();
        }

        $results = [
            'total_processed' => 0,
            'updated_bookings' => 0,
            'errors' => 0,
            'details' => []
        ];

        foreach ($bookingIds as $bookingId) {
            try {
                $booking = Booking::with([
                    'bookingUsers.course.sport',
                    'bookingUsers.bookingUserExtras.courseExtra',
                    'vouchersLogs.voucher',
                    'school'
                ])->find($bookingId);

                if (!$booking) {
                    $results['errors']++;
                    $results['details'][] = [
                        'booking_id' => $bookingId,
                        'status' => 'error',
                        'message' => 'Booking not found'
                    ];
                    continue;
                }

                // Verificar consistencia actual
                $consistency = $booking->checkPriceConsistency();

                if (!$forceUpdate && $consistency['is_consistent']) {
                    $results['details'][] = [
                        'booking_id' => $bookingId,
                        'status' => 'skipped',
                        'message' => 'Price already consistent',
                        'stored_price' => $consistency['stored_price'],
                        'calculated_price' => $consistency['calculated_price']
                    ];
                    $results['total_processed']++;
                    continue;
                }

                // Recalcular y actualizar
                $updateResult = $booking->recalculateAndUpdatePrice([
                    'exclude_courses' => self::EXCLUDED_COURSES
                ]);

                // Recalcular vouchers si se solicita
                if ($recalculateVouchers) {
                    $voucherAdjustments = $booking->recalculateVouchers();
                    $updateResult['voucher_adjustments'] = $voucherAdjustments;
                }

                $results['updated_bookings']++;
                $results['details'][] = [
                    'booking_id' => $bookingId,
                    'status' => 'updated',
                    'old_price' => $updateResult['old_total'],
                    'new_price' => $updateResult['new_total'],
                    'difference' => round($updateResult['new_total'] - $updateResult['old_total'], 2),
                    'voucher_adjustments' => $updateResult['voucher_adjustments'] ?? []
                ];

            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'booking_id' => $bookingId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];

                Log::error('Error recalculating booking price: ' . $e->getMessage(), [
                    'booking_id' => $bookingId,
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $results['total_processed']++;
        }

        return $this->sendResponse($results, 'Bulk price recalculation completed');
    }

    /**
     * NUEVO: Endpoint para validar consistencia de precios
     */
    public function validatePriceConsistency(Request $request)
    {
        $request->validate([
            'school_id' => 'integer|exists:schools,id',
            'tolerance' => 'numeric|min:0|max:10'
        ]);

        $schoolId = $request->get('school_id');
        $tolerance = $request->get('tolerance', 0.01);

        $bookings = Booking::where('school_id', $schoolId)
            ->whereIn('status', [1, 2, 3])
            ->with([
                'bookingUsers.course.sport',
                'bookingUsers.bookingUserExtras.courseExtra',
                'vouchersLogs.voucher',
                'school'
            ])
            ->get();

        $validation = [
            'total_bookings' => $bookings->count(),
            'consistent_bookings' => 0,
            'inconsistent_bookings' => 0,
            'total_discrepancy' => 0,
            'inconsistent_details' => []
        ];

        foreach ($bookings as $booking) {
            $consistency = $booking->checkPriceConsistency($tolerance);

            if ($consistency['is_consistent']) {
                $validation['consistent_bookings']++;
            } else {
                $validation['inconsistent_bookings']++;
                $validation['total_discrepancy'] += $consistency['discrepancy'];

                $validation['inconsistent_details'][] = [
                    'booking_id' => $booking->id,
                    'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,
                    'stored_price' => $consistency['stored_price'],
                    'calculated_price' => $consistency['calculated_price'],
                    'discrepancy' => $consistency['discrepancy'],
                    'status' => $booking->status
                ];
            }
        }

        $validation['consistency_percentage'] = $validation['total_bookings'] > 0
            ? round(($validation['consistent_bookings'] / $validation['total_bookings']) * 100, 2)
            : 100;

        $validation['total_discrepancy'] = round($validation['total_discrepancy'], 2);

        return $this->sendResponse($validation, 'Price consistency validation completed');
    }

    /**
     * NUEVO: Exportar estadísticas financieras detalladas
     */
    public function exportFinancialStatistics(Request $request)
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'nullable|in:json,csv,excel'
        ]);

        $schoolId = $request->get('school_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $format = $request->get('format', 'json');

        // Obtener estadísticas usando el método mejorado
        $tempRequest = new Request([
            'school_id' => $schoolId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $statistics = $this->checkBookingsImproved($tempRequest);
        $data = $statistics->getData()->data;

        // Procesar datos para exportación
        $exportData = [
            'summary' => [
                'school_id' => $schoolId,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'totals' => [
                    'bookings' => $data->total_bookings,
                    'calculated_revenue' => $data->total_calculated,
                    'stored_revenue' => $data->total_stored,
                    'paid_amount' => $data->total_paid_real,
                    'vouchers_used' => $data->total_vouchers_used,
                    'refunded_amount' => $data->total_refunded,
                    'pending_amount' => $data->total_pending,
                    'discrepancies_count' => $data->discrepancies_count,
                    'discrepancies_amount' => $data->discrepancies_amount
                ],
                'payment_methods' => $data->payment_methods,
                'status_breakdown' => [
                    'active' => $data->active_bookings,
                    'cancelled' => $data->cancelled_bookings,
                    'partial_cancelled' => $data->partial_cancelled_bookings
                ]
            ],
            'bookings_to_review' => $data->bookings_to_review,
            'detailed_bookings' => $data->booking_details,
            'exported_at' => now()->format('Y-m-d H:i:s'),
            'method' => 'new_centralized_service'
        ];

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($exportData);
            case 'excel':
                return $this->exportToExcel($exportData);
            default:
                return $this->sendResponse($exportData, 'Financial statistics exported successfully');
        }
    }

    /**
     * NUEVO: Obtener métricas de rendimiento del nuevo sistema
     */
    public function getSystemPerformanceMetrics(Request $request)
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id'
        ]);

        $schoolId = $request->get('school_id');

        // Medir tiempo de cálculo con ambos métodos
        $startTime = microtime(true);

        $bookingsToTest = Booking::where('school_id', $schoolId)
            ->whereIn('status', [1, 3])
            ->with([
                'bookingUsers.course.sport',
                'bookingUsers.bookingUserExtras.courseExtra',
                'vouchersLogs.voucher',
                'school'
            ])
            ->limit(10)
            ->get();

        $newServiceResults = [];
        $legacyResults = [];

        foreach ($bookingsToTest as $booking) {
            // Test nuevo servicio
            $newStart = microtime(true);
            $newResult = $booking->calculateCurrentTotal(['exclude_courses' => self::EXCLUDED_COURSES]);
            $newTime = microtime(true) - $newStart;

            $newServiceResults[] = [
                'booking_id' => $booking->id,
                'calculation_time' => $newTime,
                'calculated_total' => $newResult['total_final']
            ];
        }

        $totalTime = microtime(true) - $startTime;

        $metrics = [
            'school_id' => $schoolId,
            'test_bookings_count' => $bookingsToTest->count(),
            'total_execution_time' => round($totalTime, 4),
            'average_time_per_booking' => round($totalTime / max(1, $bookingsToTest->count()), 4),
            'new_service_results' => $newServiceResults,
            'service_status' => 'operational',
            'excluded_courses' => self::EXCLUDED_COURSES,
            'tested_at' => now()->format('Y-m-d H:i:s')
        ];

        return $this->sendResponse($metrics, 'System performance metrics calculated');
    }

    /**
     * MÉTODO AUXILIAR: Exportar a CSV
     */
    private function exportToCsv($data)
    {
        $filename = 'financial_statistics_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // Escribir encabezados del resumen
            fputcsv($file, ['Summary Statistics']);
            fputcsv($file, ['Total Bookings', $data['summary']['totals']['bookings']]);
            fputcsv($file, ['Calculated Revenue', $data['summary']['totals']['calculated_revenue']]);
            fputcsv($file, ['Paid Amount', $data['summary']['totals']['paid_amount']]);
            fputcsv($file, ['Pending Amount', $data['summary']['totals']['pending_amount']]);
            fputcsv($file, ['Discrepancies Count', $data['summary']['totals']['discrepancies_count']]);

            fputcsv($file, []); // Línea vacía

            // Escribir detalles de reservas
            fputcsv($file, ['Booking Details']);
            fputcsv($file, ['Booking ID', 'Client Name', 'Status', 'Stored Total', 'Calculated Total', 'Discrepancy',
                'Has Issues']);

            foreach ($data['detailed_bookings'] as $booking) {
                fputcsv($file, [
                    $booking['booking_id'],
                    $booking['client_name'],
                    $booking['status'],
                    $booking['stored_total'],
                    $booking['calculated_total'],
                    $booking['discrepancy_amount'],
                    !empty($booking['issues']) ? 'Yes' : 'No'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * MÉTODO AUXILIAR: Exportar a Excel (requiere maatwebsite/excel)
     */
    private function exportToExcel($data)
    {
        // Implementación básica - requiere instalar maatwebsite/excel
        $filename = 'financial_statistics_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        // Por ahora, devolver como JSON con sugerencia de implementación
        return $this->sendResponse([
            'message' => 'Excel export requires maatwebsite/excel package',
            'suggestion' => 'Install: composer require maatwebsite/excel',
            'data' => $data,
            'filename' => $filename
        ], 'Excel export functionality needs to be implemented');
    }

    /**
     * MÉTODO DE DEBUGGING: Analizar reservas específicas en detalle
     * Úsalo pasando los IDs de las reservas problemáticas para ver todos los datos
     */
    public function debugSpecificBookings(Request $request)
    {
        // Lista de IDs problemáticos para analizar
        $bookingIds = $request->input('booking_ids', [
            2634, 2785, 2801, 2937, 2962, 2980, 3016, 3109, 3125, 3157,
            3214, 3221, 3227, 3309, 3356, 3412, 3439, 3481, 3513, 3539,
            3590, 3632, 3634, 3993, 4000, 4052, 4117, 4120, 4121, 4122,
            4141, 4153, 4216, 4247, 4262, 4341, 4342, 4388, 4405, 4416,
            4526, 4543, 4557, 4654, 4729, 4764, 4797, 5312, 5447
        ]);

        $detailedAnalysis = [];
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);

        foreach ($bookingIds as $bookingId) {
            $booking = Booking::with([
                'bookingUsers.course.sport',
                'bookingUsers.client',
                'bookingUsers.bookingUserExtras.courseExtra',
                'payments',
                'vouchersLogs.voucher',
                'clientMain',
                'bookingLogs'
            ])->find($bookingId);

            if (!$booking) {
                $detailedAnalysis[] = [
                    'booking_id' => $bookingId,
                    'error' => 'Booking no encontrada'
                ];
                continue;
            }

            $analysis = $this->debugSingleBooking($booking, $excludedCourses);
            $detailedAnalysis[] = $analysis;
        }

        return response()->json([
            'success' => true,
            'debug_analysis' => $detailedAnalysis,
            'excluded_courses' => $excludedCourses,
            'analysis_date' => now()->format('Y-m-d H:i:s')
        ], 200);
    }

    /**
     * MÉTODO AUXILIAR: Analizar una reserva específica en detalle completo
     */
    private function debugSingleBooking($booking, $excludedCourses)
    {
        $bookingId = $booking->id;

        Log::info("=== DEBUGGING ENHANCED BOOKING {$bookingId} ===");

        $debug = [
            'booking_id' => $bookingId,
            'basic_info' => [],
            'courses_analysis' => [],
            'step_by_step_calculation' => [],
            'final_comparison' => [],
            'identified_issues' => []
        ];

        // ===== 1. INFORMACIÓN BÁSICA =====
        $debug['basic_info'] = [
            'id' => $booking->id,
            'status' => $booking->status,
            'status_text' => $this->getStatusText($booking->status),
            'source' => $booking->source,
            'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
            'price_total' => $booking->price_total,
            'has_cancellation_insurance' => $booking->has_cancellation_insurance,
            'price_cancellation_insurance' => $booking->price_cancellation_insurance,
            'has_reduction' => $booking->has_reduction,
            'price_reduction' => $booking->price_reduction,
            'client_email' => $booking->clientMain->email ?? 'N/A'
        ];

        // ===== 2. ANÁLISIS POR CURSO =====
        $allBookingUsers = $booking->bookingUsers;
        $nonExcludedBookingUsers = $allBookingUsers->filter(function($bu) use ($excludedCourses) {
            return !in_array((int) $bu->course_id, $excludedCourses);
        });

        $activeBookingUsers = $nonExcludedBookingUsers->where('status', 1);
        $cancelledBookingUsers = $nonExcludedBookingUsers->where('status', 2);

        foreach ($activeBookingUsers->groupBy('course_id') as $courseId => $courseBookingUsers) {
            $course = $courseBookingUsers->first()->course;

            $courseAnalysis = [
                'course_id' => $courseId,
                'course_name' => $course->name,
                'course_type' => $course->course_type,
                'course_type_text' => $course->course_type == 1 ? 'Colectivo' : ($course->course_type == 2 ? 'Privado' : 'Otros'),
                'is_flexible' => $course->is_flexible,
                'course_price' => $course->price,
                'total_booking_users' => $courseBookingUsers->count(),
                'unique_clients_count' => $courseBookingUsers->groupBy('client_id')->count(),
                'unique_dates_count' => $courseBookingUsers->groupBy('date')->count(),
                'clients_detail' => [],
                'calculation_method' => '',
                'expected_calculation' => '',
                'actual_calculation' => null
            ];

            // Analizar por cliente
            foreach ($courseBookingUsers->groupBy('client_id') as $clientId => $clientBookingUsers) {
                $client = $clientBookingUsers->first()->client;
                $dates = $clientBookingUsers->pluck('date')->unique()->sort()->values();

                $courseAnalysis['clients_detail'][] = [
                    'client_id' => $clientId,
                    'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
                    'booking_users_count' => $clientBookingUsers->count(),
                    'dates' => $dates->toArray(),
                    'dates_count' => $dates->count(),
                    'booking_user_ids' => $clientBookingUsers->pluck('id')->toArray()
                ];
            }

            // Determinar método de cálculo esperado
            if ($course->course_type == 1) { // Colectivo
                if ($course->is_flexible) {
                    $courseAnalysis['calculation_method'] = 'flexible_collective';
                    $courseAnalysis['expected_calculation'] = 'Precio base × número de fechas únicas × descuentos, por cada cliente';
                } else {
                    $courseAnalysis['calculation_method'] = 'fixed_collective';
                    $courseAnalysis['expected_calculation'] = 'Precio base × número de clientes únicos';
                }
            } elseif ($course->course_type == 2) { // Privado
                $courseAnalysis['calculation_method'] = 'private';
                $courseAnalysis['expected_calculation'] = 'Según price_range y duración, por cada sesión';
            }

            // Calcular con el método actual
            try {
                $actualCalculation = $this->calculateGroupedBookingUsersPrice($courseBookingUsers);
                $courseAnalysis['actual_calculation'] = $actualCalculation;
            } catch (\Exception $e) {
                $courseAnalysis['actual_calculation'] = ['error' => $e->getMessage()];
            }

            $debug['courses_analysis'][] = $courseAnalysis;
        }

        // ===== 3. CÁLCULO PASO A PASO =====
        $debug['step_by_step_calculation'] = $this->debugStepByStepCalculation($booking, $activeBookingUsers, $excludedCourses);

        // ===== 4. COMPARACIÓN FINAL =====
        $calculatedTotal = $debug['step_by_step_calculation']['final_total'] ?? 0;
        $storedTotal = $booking->price_total;

        $debug['final_comparison'] = [
            'stored_total' => $storedTotal,
            'calculated_total' => $calculatedTotal,
            'difference' => round($storedTotal - $calculatedTotal, 2),
            'difference_percentage' => $storedTotal > 0 ? round((($storedTotal - $calculatedTotal) / $storedTotal) * 100, 2) : 0
        ];

        // ===== 5. IDENTIFICAR PROBLEMAS =====
        $debug['identified_issues'] = $this->identifyCalculationIssues($debug);

        return $debug;
    }

    /**
     * MÉTODO AUXILIAR: Cálculo paso a paso detallado
     */
    private function debugStepByStepCalculation($booking, $activeBookingUsers, $excludedCourses)
    {
        $stepByStep = [
            'courses_total' => 0,
            'courses_breakdown' => [],
            'additional_concepts' => [],
            'final_total' => 0,
            'calculation_notes' => []
        ];

        // Paso 1: Calcular por curso
        foreach ($activeBookingUsers->groupBy('course_id') as $courseId => $courseBookingUsers) {
            $course = $courseBookingUsers->first()->course;

            $courseCalculation = [
                'course_id' => $courseId,
                'course_name' => $course->name,
                'method_used' => '',
                'clients_calculations' => [],
                'course_total' => 0
            ];

            if ($course->course_type == 1) { // Colectivo
                if ($course->is_flexible) {
                    $courseCalculation['method_used'] = 'calculateFlexibleCollectivePrice per client';

                    foreach ($courseBookingUsers->groupBy('client_id') as $clientId => $clientBookingUsers) {
                        $client = $clientBookingUsers->first()->client;
                        $clientPrice = $this->calculateFlexibleCollectivePrice($clientBookingUsers->first(), $clientBookingUsers);

                        $courseCalculation['clients_calculations'][] = [
                            'client_id' => $clientId,
                            'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
                            'dates_count' => $clientBookingUsers->pluck('date')->unique()->count(),
                            'calculated_price' => $clientPrice
                        ];

                        $courseCalculation['course_total'] += $clientPrice;
                    }
                } else {
                    $courseCalculation['method_used'] = 'calculateFixedCollectivePrice per client';

                    foreach ($courseBookingUsers->groupBy('client_id') as $clientId => $clientBookingUsers) {
                        $client = $clientBookingUsers->first()->client;
                        $clientPrice = $this->calculateFixedCollectivePrice($clientBookingUsers->first());

                        $courseCalculation['clients_calculations'][] = [
                            'client_id' => $clientId,
                            'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
                            'booking_users_count' => $clientBookingUsers->count(),
                            'calculated_price' => $clientPrice
                        ];

                        $courseCalculation['course_total'] += $clientPrice;
                    }
                }
            } elseif ($course->course_type == 2) { // Privado
                $courseCalculation['method_used'] = 'calculatePrivatePrice per session';

                // Agrupar por sesión
                $grouped = $courseBookingUsers->groupBy(function ($bookingUser) {
                    return $bookingUser->date . '|' . $bookingUser->hour_start . '|' . $bookingUser->hour_end . '|' .
                        $bookingUser->monitor_id . '|' . $bookingUser->group_id;
                });

                foreach ($grouped as $sessionKey => $sessionBookingUsers) {
                    $sessionPrice = $this->calculatePrivatePrice($sessionBookingUsers->first(), $course->price_range ?? []);

                    $courseCalculation['clients_calculations'][] = [
                        'session_key' => $sessionKey,
                        'participants_count' => $sessionBookingUsers->count(),
                        'date' => $sessionBookingUsers->first()->date,
                        'time' => $sessionBookingUsers->first()->hour_start . '-' . $sessionBookingUsers->first()->hour_end,
                        'calculated_price' => $sessionPrice
                    ];

                    $courseCalculation['course_total'] += $sessionPrice;
                }
            }

            $stepByStep['courses_breakdown'][] = $courseCalculation;
            $stepByStep['courses_total'] += $courseCalculation['course_total'];
        }

        // Paso 2: Conceptos adicionales
        $additionalTotal = 0;

        if ($booking->has_cancellation_insurance && $booking->price_cancellation_insurance > 0) {
            $stepByStep['additional_concepts'][] = [
                'concept' => 'cancellation_insurance',
                'amount' => $booking->price_cancellation_insurance,
                'note' => 'Seguro de cancelación'
            ];
            $additionalTotal += $booking->price_cancellation_insurance;
        }

        if ($booking->has_boukii_care && $booking->price_boukii_care > 0) {
            $stepByStep['additional_concepts'][] = [
                'concept' => 'boukii_care',
                'amount' => $booking->price_boukii_care
            ];
            $additionalTotal += $booking->price_boukii_care;
        }

        if ($booking->has_tva && $booking->price_tva > 0) {
            $stepByStep['additional_concepts'][] = [
                'concept' => 'tva',
                'amount' => $booking->price_tva
            ];
            $additionalTotal += $booking->price_tva;
        }

        if ($booking->has_reduction && $booking->price_reduction > 0) {
            $stepByStep['additional_concepts'][] = [
                'concept' => 'reduction',
                'amount' => -$booking->price_reduction,
                'note' => 'Descuento aplicado'
            ];
            $additionalTotal -= $booking->price_reduction;
        }

        $stepByStep['final_total'] = round($stepByStep['courses_total'] + $additionalTotal, 2);

        return $stepByStep;
    }

    /**
     * MÉTODO AUXILIAR: Identificar problemas en los cálculos
     */
    private function identifyCalculationIssues($debug)
    {
        $issues = [];

        // Verificar diferencias significativas
        $difference = abs($debug['final_comparison']['difference']);
        if ($difference > 0.50) {
            $issues[] = [
                'type' => 'price_mismatch',
                'severity' => $difference > 10 ? 'high' : 'medium',
                'description' => "Diferencia de {$difference}€ entre precio almacenado y calculado",
                'stored' => $debug['final_comparison']['stored_total'],
                'calculated' => $debug['final_comparison']['calculated_total']
            ];
        }

        // Verificar cálculos por curso
        foreach ($debug['courses_analysis'] as $courseAnalysis) {
            if ($courseAnalysis['course_type'] == 1 && !$courseAnalysis['is_flexible']) {
                // Para cursos fijos colectivos, debería ser precio × clientes únicos
                $expectedTotal = $courseAnalysis['course_price'] * $courseAnalysis['unique_clients_count'];
                $actualTotal = $courseAnalysis['actual_calculation']['totalPrice'] ?? 0;

                if (abs($expectedTotal - $actualTotal) > 0.50) {
                    $issues[] = [
                        'type' => 'fixed_collective_mismatch',
                        'severity' => 'high',
                        'course_id' => $courseAnalysis['course_id'],
                        'description' => "Curso fijo colectivo: esperado {$expectedTotal}€ (precio {$courseAnalysis['course_price']}€ × {$courseAnalysis['unique_clients_count']} clientes), actual {$actualTotal}€",
                        'expected' => $expectedTotal,
                        'actual' => $actualTotal
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * MÉTODO AUXILIAR: Analizar discrepancias paso a paso
     */
    private function analyzeDiscrepanciesStep($booking, $debug)
    {
        $totalPaid = $debug['payments_analysis']['total_paid'];
        $totalRefunded = $debug['payments_analysis']['total_refunded'];
        $totalNoRefund = $debug['payments_analysis']['total_no_refund'];
        $totalVouchers = $debug['vouchers_analysis']['total_amount'];
        $calculatedTotal = $debug['price_calculation']['total_calculated'];

        // Analizar no_refund pre/post pago
        $paidPayments = collect($debug['payments_analysis']['details'])->where('status', 'paid');
        $noRefundPayments = collect($debug['payments_analysis']['details'])->where('status', 'no_refund');

        $noRefundAnalysis = [];
        $noRefundPrePayment = 0;
        $noRefundPostPayment = 0;

        foreach ($noRefundPayments as $noRefund) {
            $isPrePayment = $this->determineIfNoRefundIsPrePayment($noRefund, $paidPayments->toArray());

            if ($isPrePayment) {
                $noRefundPrePayment += $noRefund['amount'];
            } else {
                $noRefundPostPayment += $noRefund['amount'];
            }

            $noRefundAnalysis[] = [
                'payment_id' => $noRefund['id'],
                'amount' => $noRefund['amount'],
                'created_at' => $noRefund['created_at'],
                'is_pre_payment' => $isPrePayment,
                'affects_balance' => !$isPrePayment,
                'notes' => $noRefund['notes']
            ];
        }

        // Cálculo de balance según estado
        $balanceAnalysis = [];

        switch ($booking->status) {
            case 1: // Activa
                $actualBalance = $totalPaid + abs($totalVouchers) - $totalRefunded;
                $expectedBalance = $calculatedTotal;
                $balanceAnalysis = [
                    'type' => 'active_booking',
                    'actual_balance' => $actualBalance,
                    'expected_balance' => $expectedBalance,
                    'discrepancy' => $expectedBalance - $actualBalance,
                    'explanation' => 'Para reserva activa: balance real vs precio calculado'
                ];
                break;

            case 2: // Cancelada
                $totalReceived = $totalPaid + abs($totalVouchers);
                $totalProcessedPostPayment = $totalRefunded + $noRefundPostPayment;
                $balanceAnalysis = [
                    'type' => 'cancelled_booking',
                    'total_received' => $totalReceived,
                    'total_processed_post_payment' => $totalProcessedPostPayment,
                    'unprocessed_amount' => $totalReceived - $totalProcessedPostPayment,
                    'no_refund_pre_payment_ignored' => $noRefundPrePayment,
                    'explanation' => 'Para cancelada: dinero recibido vs procesado (solo post-payment)'
                ];
                break;

            case 3: // Parcialmente cancelada
                $activeUsers = collect($debug['booking_users_analysis']['details'])->where('status', 1)->where('is_excluded_course', false);
                $expectedForActive = $activeUsers->sum('calculated_price');
                $actualBalance = $totalPaid + abs($totalVouchers) - $totalRefunded - $noRefundPostPayment;

                $balanceAnalysis = [
                    'type' => 'partially_cancelled_booking',
                    'expected_for_active_users' => $expectedForActive,
                    'actual_balance' => $actualBalance,
                    'discrepancy' => $expectedForActive - $actualBalance,
                    'active_users_count' => $activeUsers->count(),
                    'cancelled_users_count' => collect($debug['booking_users_analysis']['details'])->where('status', 2)->where('is_excluded_course', false)->count(),
                    'explanation' => 'Para parcial: precio de usuarios activos vs balance real'
                ];
                break;
        }

        return [
            'no_refund_analysis' => $noRefundAnalysis,
            'no_refund_pre_payment' => $noRefundPrePayment,
            'no_refund_post_payment' => $noRefundPostPayment,
            'balance_analysis' => $balanceAnalysis,
            'has_real_discrepancy' => abs($balanceAnalysis['discrepancy'] ?? $balanceAnalysis['unprocessed_amount'] ?? 0) > 0.50,
            'discrepancy_amount' => abs($balanceAnalysis['discrepancy'] ?? $balanceAnalysis['unprocessed_amount'] ?? 0)
        ];
    }

    /**
     * MÉTODO CORREGIDO: Análizar categorización considerando tipos de curso
     */
    private function analyzeCategorization($booking, $debug)
    {
        $discrepancyAmount = $debug['discrepancy_analysis']['discrepancy_amount'];
        $hasRealDiscrepancy = $debug['discrepancy_analysis']['has_real_discrepancy'];
        $noRefundPrePayment = $debug['discrepancy_analysis']['no_refund_pre_payment'];
        $noRefundPostPayment = $debug['discrepancy_analysis']['no_refund_post_payment'];

        $categorization = [
            'should_be_high_priority' => false,
            'should_be_medium_priority' => false,
            'should_be_cancelled_without_refund' => false,
            'should_not_be_categorized' => false,
            'reasons' => [],
            'calculation_details' => []
        ];

        // ✅ ANÁLISIS DETALLADO POR TIPO DE CURSO
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);
        $activeBookingUsers = $booking->bookingUsers
            ->where('status', '!=', 2)
            ->filter(function($bu) use ($excludedCourses) {
                return !in_array((int) $bu->course_id, $excludedCourses);
            });

        foreach ($activeBookingUsers->groupBy('course_id') as $courseId => $courseBookingUsers) {
            $course = $courseBookingUsers->first()->course;
            $courseType = $course->course_type == 1 ? 'colectivo' : 'privado';
            $isFlexible = $course->is_flexible ? 'flexible' : 'fijo';

            $categorization['calculation_details'][] = [
                'course_id' => $courseId,
                'course_name' => $course->name,
                'type' => "{$courseType} {$isFlexible}",
                'booking_users_count' => $courseBookingUsers->count(),
                'unique_clients' => $courseBookingUsers->groupBy('client_id')->count(),
                'calculation_method' => $this->getCalculationMethodName($course)
            ];
        }

        // Lógica de categorización paso a paso
        if (!$hasRealDiscrepancy) {
            $categorization['should_not_be_categorized'] = true;
            $categorization['reasons'][] = 'No hay discrepancia real de balance';

            if ($noRefundPrePayment > 0) {
                $categorization['reasons'][] = "Tiene no_refund pre-payment ({$noRefundPrePayment}€) que es normal";
            }
        } elseif ($discrepancyAmount > 10) {
            $categorization['should_be_high_priority'] = true;
            $categorization['reasons'][] = "Discrepancia mayor a 10€: {$discrepancyAmount}€";
        } elseif ($discrepancyAmount > 1) {
            $categorization['should_be_medium_priority'] = true;
            $categorization['reasons'][] = "Discrepancia menor: {$discrepancyAmount}€";
        }

        // Casos especiales para canceladas
        if ($booking->status == 2 && $debug['payments_analysis']['total_paid'] > 0) {
            $totalReceived = $debug['payments_analysis']['total_paid'] + abs($debug['vouchers_analysis']['total_amount']);
            $totalProcessed = $debug['payments_analysis']['total_refunded'] + $noRefundPostPayment;

            if ($totalReceived - $totalProcessed > 0.50) {
                $categorization['should_be_cancelled_without_refund'] = true;
                $categorization['reasons'][] = 'Cancelada con dinero sin procesar';
            }
        }

        return $categorization;
    }

    /**
     * MÉTODO AUXILIAR: Obtener nombre del método de cálculo
     */
    private function getCalculationMethodName($course)
    {
        if ($course->course_type == 1) { // Colectivo
            if ($course->is_flexible) {
                return 'Flexible: precio × fechas × descuentos, por cliente';
            } else {
                return 'Fijo: precio base × clientes únicos';
            }
        } elseif ($course->course_type == 2) { // Privado
            return 'Privado: según price_range y duración, por sesión';
        }
        return 'Desconocido';
    }

    /**
     * MÉTODO AUXILIAR: Determinar si no_refund es pre-payment (versión simplificada para debug)
     */
    private function determineIfNoRefundIsPrePayment($noRefundPayment, $paidPayments)
    {
        $noRefundDate = strtotime($noRefundPayment['created_at']);

        if (empty($paidPayments)) {
            // Si no hay pagos, verificar notas
            $notes = strtolower($noRefundPayment['notes'] ?? '');
            return str_contains($notes, 'pre') || str_contains($notes, 'antes') || str_contains($notes, 'before');
        }

        $firstPaymentDate = min(array_map(function($p) {
            return strtotime($p['created_at']);
        }, $paidPayments));

        return $noRefundDate < $firstPaymentDate;
    }

    /**
     * MÉTODO AUXILIAR: Obtener texto del estado
     */
    private function getStatusText($status)
    {
        $statusMap = [
            1 => 'Activa',
            2 => 'Cancelada',
            3 => 'Parcialmente Cancelada'
        ];

        return $statusMap[$status] ?? 'Desconocido';
    }

    /**
     * MÉTODO AUXILIAR: Obtener texto del estado de booking user
     */
    private function getBookingUserStatusText($status)
    {
        $statusMap = [
            1 => 'Activo',
            2 => 'Cancelado'
        ];

        return $statusMap[$status] ?? 'Desconocido';
    }

    /**
     * MÉTODO DE DEBUGGING: Analizar cancelaciones parciales específicamente
     */
    public function debugPartialCancellation(Request $request)
    {
        $bookingId = $request->get('booking_id');
        if (!$bookingId) {
            return response()->json(['error' => 'booking_id requerido'], 400);
        }

        $booking = Booking::with(['bookingUsers.course', 'payments', 'bookingLogs'])->find($bookingId);
        if (!$booking) {
            return response()->json(['error' => 'Booking no encontrada'], 404);
        }

        if ($booking->status != 3) {
            return response()->json(['error' => 'Esta reserva no está parcialmente cancelada'], 400);
        }

        $excludedCourses = array_map('intval', [260, 243]);

        $allBookingUsers = $booking->bookingUsers
            ->filter(function($bu) use ($excludedCourses) {
                return !in_array((int) $bu->course_id, $excludedCourses);
            });

        $activeBookingUsers = $allBookingUsers->where('status', 1);
        $cancelledBookingUsers = $allBookingUsers->where('status', 2);

        $analysis = [
            'booking_info' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
                'paxes' => $booking->paxes,
                'stored_total' => $booking->price_total
            ],
            'users_analysis' => [
                'total_users' => $allBookingUsers->count(),
                'active_users' => $activeBookingUsers->count(),
                'cancelled_users' => $cancelledBookingUsers->count(),
                'active_users_details' => $activeBookingUsers->map(function($bu) {
                    $priceData = $this->calculateTotalPrice($bu);
                    return [
                        'id' => $bu->id,
                        'client_id' => $bu->client_id,
                        'course_id' => $bu->course_id,
                        'date' => $bu->date,
                        'status' => $bu->status,
                        'price' => $priceData['totalPrice']
                    ];
                })->toArray(),
                'cancelled_users_details' => $cancelledBookingUsers->map(function($bu) {
                    $priceData = $this->calculateTotalPrice($bu);
                    return [
                        'id' => $bu->id,
                        'client_id' => $bu->client_id,
                        'course_id' => $bu->course_id,
                        'date' => $bu->date,
                        'status' => $bu->status,
                        'price' => $priceData['totalPrice']
                    ];
                })->toArray()
            ],
            'financial_analysis' => [
                'expected_for_active' => 0,
                'expected_for_cancelled' => 0,
                'total_original_price' => 0,
                'payments' => [],
                'refunds' => [],
                'no_refunds' => []
            ],
            'timeline' => [],
            'recommendations' => []
        ];

        // Calcular precios
        foreach ($activeBookingUsers as $bu) {
            $priceData = $this->calculateTotalPrice($bu);
            $analysis['financial_analysis']['expected_for_active'] += $priceData['totalPrice'];
        }

        foreach ($cancelledBookingUsers as $bu) {
            $priceData = $this->calculateTotalPrice($bu);
            $analysis['financial_analysis']['expected_for_cancelled'] += $priceData['totalPrice'];
        }

        $analysis['financial_analysis']['total_original_price'] =
            $analysis['financial_analysis']['expected_for_active'] +
            $analysis['financial_analysis']['expected_for_cancelled'];

        // Analizar pagos
        foreach ($booking->payments as $payment) {
            $paymentData = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'notes' => $payment->notes,
                'date' => $payment->created_at->format('Y-m-d H:i:s')
            ];

            if ($payment->status === 'paid') {
                $analysis['financial_analysis']['payments'][] = $paymentData;
            } elseif (in_array($payment->status, ['refund', 'partial_refund'])) {
                $analysis['financial_analysis']['refunds'][] = $paymentData;
            } elseif ($payment->status === 'no_refund') {
                $analysis['financial_analysis']['no_refunds'][] = $paymentData;
            }
        }

        // Timeline
        $logs = $booking->bookingLogs->sortBy('created_at');
        foreach ($logs as $log) {
            $analysis['timeline'][] = [
                'action' => $log->action,
                'description' => $log->description,
                'date' => $log->created_at->format('Y-m-d H:i:s'),
                'user_id' => $log->user_id
            ];
        }

        // Generar recomendaciones
        $analysis['recommendations'] = $this->generatePartialCancellationRecommendations($analysis);

        return response()->json($analysis);
    }

    /**
     * MÉTODO HELPER: Generar recomendaciones para cancelaciones parciales
     */
    private function generatePartialCancellationRecommendations($analysis)
    {
        $recommendations = [];

        $expectedForActive = $analysis['financial_analysis']['expected_for_active'];
        $totalPaid = array_sum(array_column($analysis['financial_analysis']['payments'], 'amount'));
        $totalNoRefund = array_sum(array_column($analysis['financial_analysis']['no_refunds'], 'amount'));

        if ($totalPaid <= $expectedForActive + 5 && $totalNoRefund > 0) {
            $recommendations[] = "⚠️ INCONSISTENCIA: Se aplicó no_refund ({$totalNoRefund}€) pero solo se pagó por usuarios activos ({$totalPaid}€)";
            $recommendations[] = "💡 SOLUCIÓN: Revisar si el no_refund es correcto o si se debe eliminar";
        }

        if ($totalPaid > $expectedForActive + 5) {
            $recommendations[] = "ℹ️ Se pagó más del precio de usuarios activos - verificar si es correcto";
        }

        if (empty($analysis['financial_analysis']['payments']) && $totalNoRefund > 0) {
            $recommendations[] = "❌ PROBLEMA: Hay no_refund sin pagos previos";
        }

        return $recommendations;
    }


    /**
     * REEMPLAZAR el método checkBookings en StatisticsController.php
     * Añadir filtro para excluir reservas que solo tienen cursos excluidos
     */
    public function checkBookings(Request $request)
    {
        $query = Booking::query()
            ->with([
                'bookingUsers.course.sport',
                'bookingUsers.client',
                'bookingUsers.bookingUserExtras.courseExtra',
                'payments',
                'vouchersLogs.voucher',
                'clientMain',
                'bookingLogs' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ])
            // ✅ INCLUIR TODAS LAS RESERVAS (también las canceladas)
            ->whereIn('status', [1, 2, 3]); // Activas, canceladas y parcialmente canceladas

        if ($request->school_id) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->booking_id) {
            $query->where('id', $request->booking_id);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereHas('bookingUsers', function($q) use ($request) {
                $q->whereBetween('date', [$request->start_date, $request->end_date]);
            });
        }

        $allBookings = $query->get();

        // ✅ FILTRAR RESERVAS QUE SOLO TIENEN CURSOS EXCLUIDOS
        // ✅ DEBUGGING DETALLADO DEL FILTRADO
        Log::info('=== INICIO ANÁLISIS DE FILTRADO DE CURSOS EXCLUIDOS ===', [
            'excluded_courses' => self::EXCLUDED_COURSES,
            'total_bookings_before_filter' => $allBookings->count()
        ]);

        $debugInfo = [
            'should_be_excluded' => [],
            'should_be_included' => [],
            'problematic_cases' => []
        ];

        $bookings = $allBookings->filter(function($booking) use (&$debugInfo) {
            $bookingId = $booking->id;

            // Obtener todos los booking_users activos (no cancelados)
            $activeBookingUsers = $booking->bookingUsers->where('status', '!=', 2);

            // Obtener cursos únicos de booking_users activos
            $activeCourseIds = $booking->bookingUsers
                ->where('status', '!=', 2)
                ->pluck('course_id')
                ->filter() // Eliminar nulls
                ->map(function($courseId) {
                    return (int) $courseId; // ✅ CONVERTIR A INTEGER
                })
                ->unique()
                ->toArray();

            // Info detallada para debugging
            $bookingInfo = [
                'booking_id' => $bookingId,
                'booking_status' => $booking->status,
                'total_booking_users' => $booking->bookingUsers->count(),
                'active_booking_users' => $activeBookingUsers->count(),
                'cancelled_booking_users' => $booking->bookingUsers->where('status', 2)->count(),
                'active_course_ids' => $activeCourseIds,
                'all_course_ids' => $booking->bookingUsers->pluck('course_id')->filter()->unique()->toArray(),
                'booking_users_details' => $booking->bookingUsers->map(function($bu) {
                    return [
                        'id' => $bu->id,
                        'course_id' => $bu->course_id,
                        'status' => $bu->status,
                        'date' => $bu->date
                    ];
                })->toArray()
            ];

            // Si no hay cursos activos, mantener la reserva
            if (empty($activeCourseIds)) {
                $bookingInfo['decision'] = 'INCLUIR - no hay cursos activos';
                $bookingInfo['reason'] = 'Reserva completamente cancelada o sin cursos';
                $debugInfo['should_be_included'][] = $bookingInfo;
                return true;
            }

            // Verificar si TODOS los cursos activos están en la lista de excluidos
            $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);
            $nonExcludedCourses = array_diff($activeCourseIds, $excludedCourses);

            $bookingInfo['non_excluded_courses'] = $nonExcludedCourses;
            $bookingInfo['excluded_courses'] = $excludedCourses;
            $bookingInfo['only_has_excluded'] = empty($nonExcludedCourses);

            // Decisión: Solo mantener la reserva si tiene al menos un curso NO excluido
            $shouldInclude = !empty($nonExcludedCourses);

            if ($shouldInclude) {
                $bookingInfo['decision'] = 'INCLUIR - tiene cursos no excluidos';
                $debugInfo['should_be_included'][] = $bookingInfo;
            } else {
                $bookingInfo['decision'] = 'EXCLUIR - solo tiene cursos excluidos';
                $debugInfo['should_be_excluded'][] = $bookingInfo;
            }

            // Detectar casos problemáticos
            if (!$shouldInclude && count($excludedCourses) > 0 && count($activeCourseIds) === count($excludedCourses)) {
                $debugInfo['problematic_cases'][] = $bookingInfo;
            }

            return !empty($nonExcludedCourses);
        });

        // ✅ LOGGING DETALLADO DEL RESULTADO
        Log::info('Filtrado de reservas por cursos excluidos - CORREGIDO', [
            'total_bookings_before_filter' => $allBookings->count(),
            'total_bookings_after_filter' => $bookings->count(),
            'excluded_courses' => self::EXCLUDED_COURSES,
            'excluded_courses_as_int' => array_map('intval', self::EXCLUDED_COURSES),
            'bookings_excluded' => $allBookings->count() - $bookings->count()
        ]);

        // Log de casos que deberían ser excluidos
        if (!empty($debugInfo['should_be_excluded'])) {
            Log::debug('=== RESERVAS QUE SERÁN EXCLUIDAS ===');
            foreach ($debugInfo['should_be_excluded'] as $case) {
                Log::debug("Booking {$case['booking_id']} EXCLUIDA", $case);
            }
        }

        // Log de casos problemáticos (que solo tienen cursos excluidos)
        if (!empty($debugInfo['problematic_cases'])) {
            Log::warning('=== CASOS PROBLEMÁTICOS (solo cursos excluidos) ===');
            foreach ($debugInfo['problematic_cases'] as $case) {
                Log::warning("Booking {$case['booking_id']} - SOLO CURSOS EXCLUIDOS", $case);
            }
        }

        // ✅ VERIFICACIÓN ADICIONAL: Revisar qué reservas llegan al análisis
        Log::debug('=== VERIFICACIÓN POST-FILTRADO ===');
        $finalBookingIds = $bookings->pluck('id')->toArray();
        $excludedBookingIds = collect($debugInfo['should_be_excluded'])->pluck('booking_id')->toArray();

        Log::debug('IDs finales después del filtro: ' . implode(', ', $finalBookingIds));
        Log::debug('IDs que deberían estar excluidos: ' . implode(', ', $excludedBookingIds));

        // Verificar si alguna reserva excluida está en el resultado final
        $leakedIds = array_intersect($finalBookingIds, $excludedBookingIds);
        if (!empty($leakedIds)) {
            Log::error('⚠️ PROBLEMA: Estas reservas deberían estar excluidas pero están en el resultado: ' . implode(', ', $leakedIds));
        }

/*        // Contar bookings que tienen cursos excluidos (entre las que NO se excluyeron completamente)
        foreach ($bookings as $booking) {
            $hasExcludedCourses = $booking->bookingUsers()
                ->whereIn('course_id', self::EXCLUDED_COURSES)
                ->exists();

            if ($hasExcludedCourses) {
                $excludedCoursesInfo['total_bookings_with_excluded_courses']++;
                $excludedCoursesInfo['total_excluded_booking_users'] += $booking->bookingUsers()
                    ->whereIn('course_id', self::EXCLUDED_COURSES)
                    ->count();
            }
        }*/

        $summary = [
            'total_bookings' => 0,
            'total_calculated' => 0,
            'total_stored' => 0,
            'total_paid_real' => 0,
            'total_paid_payrexx' => 0,
            'total_vouchers_used' => 0,
            'total_vouchers_refunded' => 0,
            'total_refunded' => 0,
            'total_pending' => 0,
            'discrepancies_count' => 0,
            'discrepancies_amount' => 0,

            // ✅ NUEVOS CONTADORES PARA ESTADOS
            'active_bookings' => 0,
            'cancelled_bookings' => 0,
            'partial_cancelled_bookings' => 0,

            'payment_methods' => [
                'cash' => 0,
                'card' => 0,
                'transfer' => 0,
                'boukii' => 0,
                'online' => 0,
                'vouchers' => 0,
                'other' => 0
            ],
            'refund_analysis' => [
                'total_refunds' => 0,
                'refund_full' => 0,
                'refund_partial' => 0,
                'no_refund' => 0,
                'refund_pending' => 0
            ],
            'cancellation_analysis' => [
                'active' => 0,
                'partial_cancel' => 0,
                'total_cancel' => 0,
                'finished' => 0,
                // ✅ NUEVOS CAMPOS PARA ANÁLISIS DE CANCELACIONES
                'cancelled_with_full_refund' => 0,
                'cancelled_with_partial_refund' => 0,
                'cancelled_no_refund' => 0,
                'cancelled_refund_pending' => 0,
                'cancelled_with_money_retained' => 0,  // Canceladas con no_refund
                'total_amount_retained' => 0           // Total de dinero retenido
            ],
            'payrexx_comparison' => [
                'total_transactions_found' => 0,
                'total_amount_payrexx' => 0,
                'total_amount_system' => 0,
                'missing_in_system' => [],
                'missing_in_payrexx' => [],
                'amount_discrepancies' => []
            ],
            'bookings_to_review' => [
                'high_priority' => [],
                'medium_priority' => [],
                'cancelled_with_money' => [],
                'unpaid_but_attended' => [],
                'payrexx_discrepancies' => [],
                // ✅ NUEVAS CATEGORÍAS
                'cancelled_without_refund' => [],
                'partial_cancelled_issues' => []
            ],
        //    'excluded_courses_info' => $excludedCoursesInfo,
            'booking_details' => []
        ];

        // Preparar datos para comparación con Payrexx si se solicita
        $payrexxAnalysis = null;
        if ($request->boolean('include_payrexx_comparison', false)) {
            Log::info('Iniciando análisis de Payrexx para ' . $bookings->count() . ' reservas (excluyendo reservas con solo cursos ' . implode(',', self::EXCLUDED_COURSES) . ')');

            $payrexxAnalysis = \App\Http\Controllers\PayrexxHelpers::analyzeBookingsWithPayrexx(
                $bookings,
                $request->start_date,
                $request->end_date
            );

            Log::info('Análisis de Payrexx completado', [
                'total_transactions' => count($payrexxAnalysis['payrexx_transactions']),
                'total_payrexx_amount' => $payrexxAnalysis['total_payrexx_amount'],
                'total_system_amount' => $payrexxAnalysis['total_system_amount']
            ]);

            // Incorporar datos de Payrexx al resumen
            $summary['payrexx_comparison'] = [
                'total_transactions_found' => count($payrexxAnalysis['payrexx_transactions']),
                'total_amount_payrexx' => $payrexxAnalysis['total_payrexx_amount'],
                'total_amount_system_payrexx_only' => 0,
                'total_difference' => 0,
                'successful_verifications' => $payrexxAnalysis['successful_verifications'],
                'failed_verifications' => $payrexxAnalysis['failed_verifications'],
                'missing_transactions' => $payrexxAnalysis['missing_transactions'],
                'unmatched_payrexx_count' => count($payrexxAnalysis['unmatched_payrexx_transactions']),
                'amount_discrepancies' => []
            ];
        }

        foreach ($bookings as $booking) {

            $onlyExcludedCourses = $booking->bookingUsers->pluck('course_id')->unique()->every(fn($id) => in_array($id, self::EXCLUDED_COURSES));

            if($onlyExcludedCourses) {
                continue;
            }
            $bookingAnalysis = $this->analyzeBookingDetailed($booking, $payrexxAnalysis);

            $summary['total_bookings']++;

            // ✅ CONTAR POR ESTADO
            switch ($booking->status) {
                case 1:
                    $summary['active_bookings']++;
                    $summary['cancellation_analysis']['active']++;
                    break;
                case 2:
                    $summary['cancelled_bookings']++;
                    $summary['cancellation_analysis']['total_cancel']++;
                    break;
                case 3:
                    $summary['partial_cancelled_bookings']++;
                    $summary['cancellation_analysis']['partial_cancel']++;
                    break;
            }

            $summary['total_calculated'] += $bookingAnalysis['calculated_total'];
            $summary['total_stored'] += $bookingAnalysis['stored_total'];
            $summary['total_paid_real'] += $bookingAnalysis['payments']['total_paid'];
            $summary['total_paid_payrexx'] += $bookingAnalysis['payments']['total_paid_payrexx'];
            $summary['total_vouchers_used'] += $bookingAnalysis['vouchers']['total_used'];
            $summary['total_vouchers_refunded'] += $bookingAnalysis['vouchers']['total_refunded'];
            $summary['total_refunded'] += $bookingAnalysis['refunds']['total_refunded'];
            $summary['total_pending'] += $bookingAnalysis['pending_amount'];

            // Contar discrepancias
            if ($bookingAnalysis['has_discrepancy']) {
                $summary['discrepancies_count']++;
                $summary['discrepancies_amount'] += abs($bookingAnalysis['discrepancy_amount']);
            }

            // Sumar métodos de pago
            foreach ($bookingAnalysis['payments']['by_method'] as $method => $amount) {
                if (isset($summary['payment_methods'][$method])) {
                    $summary['payment_methods'][$method] += $amount;
                }
            }

            // ✅ CORREGIR: Mover el análisis de no_refund FUERA del loop de refunds
            // Análisis de refunds
            foreach ($bookingAnalysis['refunds']['breakdown'] as $type => $amount) {
                if (isset($summary['refund_analysis'][$type])) {
                    $summary['refund_analysis'][$type] += $amount;
                }
            }

            // ✅ ANÁLISIS ESPECÍFICO PARA NO_REFUND (CORREGIDO - FUERA DEL LOOP ANTERIOR)
            $noRefundPayments = $booking->payments->whereIn('status', ['no_refund']);
            $noRefundTotal = $noRefundPayments->sum('amount');

            if ($noRefundTotal > 0) {
                $summary['refund_analysis']['no_refund'] += $noRefundTotal;

                if ($booking->status == 2) {
                    $summary['cancellation_analysis']['cancelled_with_money_retained']++;
                    $summary['cancellation_analysis']['total_amount_retained'] += $noRefundTotal;
                }
            }

            // ✅ ANÁLISIS ESPECÍFICO PARA CANCELADAS
            if ($booking->status == 2) {
                $totalPaid = $bookingAnalysis['payments']['total_paid'] + $bookingAnalysis['vouchers']['total_used'];
                $totalRefunded = $bookingAnalysis['refunds']['total_refunded'] + $bookingAnalysis['vouchers']['total_refunded'];
                $totalProcessed = $totalRefunded + $noRefundTotal;

                if ($totalProcessed >= ($totalPaid - 0.50)) {
                    if ($noRefundTotal > 0.50) {
                        $summary['cancellation_analysis']['cancelled_no_refund']++;
                    } else {
                        $summary['cancellation_analysis']['cancelled_with_full_refund']++;
                    }
                } elseif ($totalRefunded > 0.50) {
                    $summary['cancellation_analysis']['cancelled_with_partial_refund']++;
                } elseif ($totalPaid > 0.50) {
                    $summary['cancellation_analysis']['cancelled_refund_pending']++;
                } else {
                    $summary['cancellation_analysis']['cancelled_no_refund']++;
                }
            }

            // Categorizar reservas para revisar (con nueva lógica para canceladas)
            $this->categorizeBookingForReviewWithCancelled($booking, $bookingAnalysis, $summary);

            // Añadir discrepancias de Payrexx al resumen si existen
            if ($payrexxAnalysis && isset($bookingAnalysis['payrexx_comparison']['has_discrepancy']) && $bookingAnalysis['payrexx_comparison']['has_discrepancy']) {
                // Solo sumar pagos que se hicieron por Payrexx
                $paid = $booking->payments()->whereNotNull('payrexx_reference')->whereIn('status', ['paid'])->sum('amount');
                $refunded = $booking->payments()->whereNotNull('payrexx_reference')->whereIn('status', ['refund', 'partial_refund'])->sum('amount');
                $systemPayrexxAmount = $paid - $refunded;

                $summary['payrexx_comparison']['total_amount_system_payrexx_only'] += $systemPayrexxAmount;

                $summary['payrexx_comparison']['amount_discrepancies'][] = [
                    'booking_id' => $booking->id,
                    'booking_status' => $booking->status,
                    'system_amount_payrexx_only' => $systemPayrexxAmount,
                    'payrexx_amount' => $bookingAnalysis['payrexx_comparison']['total_amount'],
                    'difference' => $bookingAnalysis['payrexx_comparison']['difference']
                ];
            } elseif ($payrexxAnalysis) {
                // Sumar pagos de Payrexx aunque no haya discrepancia
                $paid = $booking->payments()->whereNotNull('payrexx_reference')->whereIn('status', ['paid'])->sum('amount');
                $refunded = $booking->payments()->whereNotNull('payrexx_reference')->whereIn('status', ['refund', 'partial_refund'])->sum('amount');
                $systemPayrexxAmount = $paid - $refunded;

                $summary['payrexx_comparison']['total_amount_system_payrexx_only'] += $systemPayrexxAmount;
            }

            $summary['booking_details'][] = $bookingAnalysis;
        }

        // Redondear totales
        foreach (['total_calculated', 'total_stored', 'total_paid_real', 'total_paid_payrexx', 'total_vouchers_used', 'total_vouchers_refunded', 'total_refunded', 'total_pending', 'discrepancies_amount'] as $field) {
            $summary[$field] = round($summary[$field], 2);
        }

        $cleanedPaymentMethods = [];
        foreach ($summary['payment_methods'] as $method => $amount) {
            $cleanedMethod = trim((string)$method);
            $cleanedPaymentMethods[$cleanedMethod] = round($amount, 2);
        }
        $summary['payment_methods'] = $cleanedPaymentMethods;

        $cleanedRefundAnalysis = [];
        foreach ($summary['refund_analysis'] as $type => $amount) {
            $cleanedType = trim((string)$type);
            $cleanedRefundAnalysis[$cleanedType] = round($amount, 2);
        }
        $summary['refund_analysis'] = $cleanedRefundAnalysis;

        foreach (['total_amount_retained'] as $field) {
            if (isset($summary['cancellation_analysis'][$field])) {
                $summary['cancellation_analysis'][$field] = round($summary['cancellation_analysis'][$field], 2);
            }
        }

        // Completar análisis de Payrexx si se solicitó
        if ($payrexxAnalysis !== null) {
            $summary['payrexx_comparison']['total_amount_payrexx'] = round($summary['payrexx_comparison']['total_amount_payrexx'], 2);
            $summary['payrexx_comparison']['total_amount_system_payrexx_only'] = round($summary['payrexx_comparison']['total_amount_system_payrexx_only'], 2);
            $summary['payrexx_comparison']['total_difference'] = round(
                $summary['payrexx_comparison']['total_amount_system_payrexx_only'] - $summary['payrexx_comparison']['total_amount_payrexx'], 2
            );
        }

        return $this->sendResponse($summary, 'Detailed booking analysis completed (excluding ' . 0 . ' bookings with only courses ' . implode(', ', self::EXCLUDED_COURSES) . ')');
    }

    /**
     * Categorizar reservas para revisar - VERSIÓN MEJORADA con no_refund
     * REEMPLAZAR el método categorizeBookingForReviewWithCancelled en StatisticsController.php
     */
    private function categorizeBookingForReviewWithCancelled($booking, $analysis, &$summary)
    {
        $bookingId = $booking->id;

        // ✅ VERIFICAR SI DEBE SER ANALIZADA
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);
        $activeNonExcludedCourses = $booking->bookingUsers
            ->where('status', '!=', 2)
            ->filter(function($bu) use ($excludedCourses) {
                return !in_array((int) $bu->course_id, $excludedCourses);
            });

        if ($activeNonExcludedCourses->isEmpty()) {
            Log::debug("Booking {$bookingId} no categorizada - solo cursos excluidos");
            return;
        }

        // ✅ SOLO CATEGORIZAR SI HAY DISCREPANCIA REAL
        if (!($analysis['has_discrepancy'] ?? false)) {
            Log::debug("Booking {$bookingId} no categorizada - sin discrepancia real", [
                'balance_analysis' => $analysis['balance_analysis'] ?? [],
                'stored_vs_calculated' => $analysis['stored_total_analysis'] ?? []
            ]);
            return;
        }

        $discrepancyAmount = $analysis['discrepancy_amount'] ?? 0;
        $balanceType = $analysis['balance_analysis']['type'] ?? 'unknown';

        $hasExcludedCourses = $booking->bookingUsers
            ->where('status', '!=', 2)
            ->filter(function($bu) use ($excludedCourses) {
                return in_array((int) $bu->course_id, $excludedCourses);
            })
            ->isNotEmpty();

        $exclusionNote = $hasExcludedCourses
            ? " (cursos " . implode(',', self::EXCLUDED_COURSES) . " excluidos del cálculo)"
            : "";

        // ✅ CATEGORIZACIÓN MEJORADA
        $wasAdded = false;

        // 1. ALTA PRIORIDAD - Discrepancias significativas
        if (!$wasAdded && $discrepancyAmount > 10) {
            $summary['bookings_to_review']['high_priority'][] = [
                'id' => $bookingId,
                'status' => $booking->status,
                'balance_type' => $balanceType,
                'reason' => 'Discrepancia mayor a 10€' . $exclusionNote,
                'amount' => $discrepancyAmount,
                'balance_details' => $analysis['balance_analysis'] ?? [],
                'has_excluded_courses' => $hasExcludedCourses
            ];
            $wasAdded = true;
        }

        // 2. RESERVAS ACTIVAS MARCADAS COMO PAGADAS PERO CON FALTA DE DINERO
        if (!$wasAdded && $booking->paid && $analysis['pending_amount'] > 5 && $booking->status == 1) {
            $summary['bookings_to_review']['high_priority'][] = [
                'id' => $bookingId,
                'status' => $booking->status,
                'balance_type' => $balanceType,
                'reason' => 'Marcada como pagada pero falta dinero significativo' . $exclusionNote,
                'amount' => $analysis['pending_amount'],
                'has_excluded_courses' => $hasExcludedCourses
            ];
            $wasAdded = true;
        }

        // 3. CANCELADAS CON DINERO SIN PROCESAR
        if (!$wasAdded && $booking->status == 2 && $balanceType == 'cancelled_with_payment') {
            $actualBalance = $analysis['balance_analysis']['actual_balance'] ?? 0;

            if ($actualBalance > 0.50) {
                $summary['bookings_to_review']['cancelled_without_refund'][] = [
                    'id' => $bookingId,
                    'status' => $booking->status,
                    'balance_type' => $balanceType,
                    'reason' => 'Cancelada con dinero sin procesar' . $exclusionNote,
                    'amount_pending_process' => round($actualBalance, 2),
                    'balance_details' => $analysis['balance_analysis'],
                    'has_excluded_courses' => $hasExcludedCourses
                ];
                $wasAdded = true;
            }
        }

        // 4. PRIORIDAD MEDIA - Discrepancias menores
        if (!$wasAdded && $discrepancyAmount > 1 && $discrepancyAmount <= 10) {
            $summary['bookings_to_review']['medium_priority'][] = [
                'id' => $bookingId,
                'status' => $booking->status,
                'balance_type' => $balanceType,
                'reason' => 'Discrepancia menor' . $exclusionNote,
                'amount' => $discrepancyAmount,
                'balance_details' => $analysis['balance_analysis'] ?? [],
                'has_excluded_courses' => $hasExcludedCourses
            ];
            $wasAdded = true;
        }

        // 5. CANCELACIONES PARCIALES PROBLEMÁTICAS
        if (!$wasAdded && $booking->status == 3) {
            $summary['bookings_to_review']['partial_cancelled_issues'][] = [
                'id' => $bookingId,
                'status' => $booking->status,
                'balance_type' => $balanceType,
                'reason' => 'Cancelación parcial con discrepancia real' . $exclusionNote,
                'discrepancy_amount' => $discrepancyAmount,
                'balance_details' => $analysis['balance_analysis'],
                'has_excluded_courses' => $hasExcludedCourses
            ];
            $wasAdded = true;
        }

        // 6. OTRAS CATEGORÍAS (solo si no se añadió antes)
        if (!$wasAdded) {
            // No pagadas pero con asistencia (solo activas)
            if (!$booking->paid && $booking->attendance && $booking->status == 1) {
                $summary['bookings_to_review']['unpaid_but_attended'][] = [
                    'id' => $bookingId,
                    'status' => $booking->status,
                    'balance_type' => $balanceType,
                    'reason' => 'No pagada pero marcada como asistida' . $exclusionNote,
                    'has_excluded_courses' => $hasExcludedCourses
                ];
                $wasAdded = true;
            }

            // Problemas con Payrexx
            if (!$wasAdded && isset($analysis['payrexx_comparison']) && $analysis['payrexx_comparison']['has_discrepancy']) {
                $summary['bookings_to_review']['payrexx_discrepancies'][] = [
                    'id' => $bookingId,
                    'status' => $booking->status,
                    'balance_type' => $balanceType,
                    'reason' => 'Discrepancia con Payrexx' . $exclusionNote,
                    'payrexx_details' => $analysis['payrexx_comparison'],
                    'has_excluded_courses' => $hasExcludedCourses
                ];
            }
        }

        // ✅ LOG PARA DEBUGGING
        if ($wasAdded) {
            Log::info("Booking {$bookingId} categorizada correctamente", [
                'has_discrepancy' => $analysis['has_discrepancy'],
                'discrepancy_amount' => $discrepancyAmount,
                'balance_type' => $balanceType,
                'booking_status' => $booking->status
            ]);
        } else {
            Log::info("Booking {$bookingId} con discrepancia real NO categorizada en secciones principales", [
                'discrepancy_amount' => $discrepancyAmount,
                'balance_type' => $balanceType,
                'reason' => 'Posiblemente discrepancia muy pequeña o caso edge'
            ]);
        }
    }

    private function analyzeBookingDetailed($booking, $payrexxAnalysis = null)
    {
        $analysis = [
            'booking_id' => $booking->id,
            'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,
            'client_email' => $booking->clientMain->email,
            'booking_date' => $booking->created_at->format('Y-m-d H:i:s'),
            'source' => $booking->source ?? 'unknown',
            'paxes' => $booking->paxes,

            // Precios almacenados en la booking
            'stored_total' => $booking->price_total,
            'stored_paid_total' => $booking->paid_total,
            'stored_has_insurance' => $booking->has_cancellation_insurance,
            'stored_insurance_price' => $booking->price_cancellation_insurance,
            'stored_has_boukii_care' => $booking->has_boukii_care,
            'stored_boukii_care_price' => $booking->price_boukii_care,
            'stored_has_reduction' => $booking->has_reduction,
            'stored_reduction_price' => $booking->price_reduction,
            'stored_has_tva' => $booking->has_tva,
            'stored_tva_price' => $booking->price_tva,

            // Precios calculados
            'calculated_total' => 0,
            'calculated_breakdown' => [],

            // Análisis de pagos
            'payments' => [
                'total_paid' => 0,
                'total_paid_payrexx' => 0,
                'by_method' => [
                    'cash' => 0,
                    'card' => 0,
                    'transfer' => 0,
                    'boukii' => 0,
                    'online' => 0,
                    'other' => 0
                ],
                'details' => []
            ],

            // Análisis de vouchers CORREGIDO
            'vouchers' => [
                'total_used' => 0,
                'total_refunded' => 0, // NUEVO
                'details' => []
            ],

            // Análisis de reembolsos
            'refunds' => [
                'total_refunded' => 0,
                'breakdown' => [
                    'refund_full' => 0,
                    'refund_partial' => 0,
                    'no_refund' => 0,
                    'refund_pending' => 0
                ],
                'details' => []
            ],

            // Comparación con Payrexx
            'payrexx_comparison' => null,

            // Estado de cancelación
            'cancellation' => [],

            // Discrepancias
            'has_discrepancy' => false,
            'discrepancy_amount' => 0,
            'discrepancy_details' => [],

            // Cantidad pendiente
            'pending_amount' => 0,

            // Problemas detectados
            'issues' => [],

            // Historial de la reserva
            'booking_history' => []
        ];

        // 1. CALCULAR PRECIOS REALES
        $this->calculateRealPrices($booking, $analysis);

        // 2. ANALIZAR PAGOS
        $this->analyzePayments($booking, $analysis);

        // 3. ANALIZAR VOUCHERS (MÉTODO CORREGIDO)
        $this->analyzeVouchers($booking, $analysis);

        // 4. ANALIZAR REEMBOLSOS DETALLADOS
        $this->analyzeRefundsDetailed($booking, $analysis);

        // 5. COMPARAR CON PAYREXX
        if ($payrexxAnalysis) {
            $this->addPayrexxComparison($booking, $analysis, $payrexxAnalysis);
        }

        // 6. ANALIZAR CANCELACIONES
        $this->analyzeCancellations($booking, $analysis);

        // 7. DETECTAR DISCREPANCIAS (MÉTODO CORREGIDO)
        $this->detectDiscrepancies($booking, $analysis);

        // 8. CALCULAR PENDIENTES (MÉTODO CORREGIDO)
        $this->calculatePendingAmount($booking, $analysis);

        // 9. ANALIZAR HISTORIAL
        $this->analyzeBookingHistory($booking, $analysis);

        return $analysis;
    }

    /**
     * CORRECCIÓN PRINCIPAL: calculateRealPrices
     * Evitar duplicación de seguros y calcular correctamente
     */
    private function calculateRealPrices($booking, &$analysis)
    {
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);

        $bookingUsers = $booking->bookingUsers
            ->where('status', '!=', 2) // No cancelados
            ->filter(function($bu) use ($excludedCourses) {
                return !in_array((int) $bu->course_id, $excludedCourses);
            });

        $total = 0;
        $breakdown = [];
        $totalInsuranceFromCourses = 0;

        foreach ($bookingUsers->groupBy('course_id') as $courseId => $courseBookingUsers) {
            $course = optional($courseBookingUsers->first())->course;
            if (!$course) continue;

            // ✅ CALCULAR PRECIO SIN SEGURO PRIMERO
            $courseTotal = 0;
            $courseInsurance = 0;

            if ($course->course_type === 2) {
                // PRIVADOS — por sesión
                $grouped = $courseBookingUsers->groupBy(function ($bookingUser) {
                    return $bookingUser->date . '|' . $bookingUser->hour_start . '|' . $bookingUser->hour_end . '|' .
                        $bookingUser->monitor_id . '|' . $bookingUser->group_id;
                });

                foreach ($grouped as $group) {
                    $sessionPrice = $this->calculatePrivatePrice($group->first(), $course->price_range ?? []);
                    $sessionExtras = $group->sum(fn($bu) => $this->calculateExtrasPrice($bu));
                    $courseTotal += ($sessionPrice + $sessionExtras);
                }
            } else {
                // COLECTIVOS — por cliente
                $clientGroups = $courseBookingUsers->groupBy('client_id');

                foreach ($clientGroups as $clientId => $clientBookingUsers) {
                    if ($course->is_flexible) {
                        $clientPrice = $this->calculateFlexibleCollectivePrice($clientBookingUsers->first(), $clientBookingUsers);
                    } else {
                        $clientPrice = $this->calculateFixedCollectivePrice($clientBookingUsers->first());
                    }

                    // Añadir extras del cliente
                    $clientExtras = $clientBookingUsers->sum(fn($bu) => $this->calculateExtrasPrice($bu));
                    $courseTotal += ($clientPrice + $clientExtras);
                }
            }

            // ✅ CALCULAR SEGURO UNA SOLA VEZ POR CURSO
            if ($booking->has_cancellation_insurance && $courseTotal > 0) {
                $courseInsurance = round($courseTotal * 0.10, 2);
                $totalInsuranceFromCourses += $courseInsurance;
            }

            $courseTotalWithInsurance = $courseTotal + $courseInsurance;

            $breakdown[] = [
                'course_id' => (int) $courseId,
                'course_name' => $course->name,
                'course_type' => $course->course_type,
                'participants' => $courseBookingUsers->groupBy('client_id')->count(),
                'dates_count' => $courseBookingUsers->groupBy('date')->count(),
                'base_price' => $courseTotal,
                'extras_price' => $courseBookingUsers->sum(fn($bu) => $this->calculateExtrasPrice($bu)),
                'insurance_price' => $courseInsurance,
                'total_price' => $courseTotalWithInsurance,
                'booking_users_ids' => $courseBookingUsers->pluck('id')->toArray()
            ];

            $total += $courseTotalWithInsurance;
        }

        // ✅ CONCEPTOS ADICIONALES - NO duplicar seguro
        if ($booking->has_cancellation_insurance && $booking->price_cancellation_insurance > 0) {
            if ($totalInsuranceFromCourses == 0) {
                // Solo añadir si NO está incluido en cursos
                $total += $booking->price_cancellation_insurance;
                $breakdown[] = [
                    'type' => 'cancellation_insurance',
                    'amount' => $booking->price_cancellation_insurance,
                    'status' => 'added_separately'
                ];
            } else {
                // Ya incluido, solo informativo
                $breakdown[] = [
                    'type' => 'cancellation_insurance_info',
                    'amount' => 0,
                    'status' => 'already_included_in_courses',
                    'note' => "Seguro incluido en cursos: {$totalInsuranceFromCourses}€"
                ];
            }
        }

        if ($booking->has_boukii_care && $booking->price_boukii_care > 0) {
            $total += $booking->price_boukii_care;
            $breakdown[] = [
                'type' => 'boukii_care',
                'amount' => $booking->price_boukii_care
            ];
        }

        if ($booking->has_tva && $booking->price_tva > 0) {
            $total += $booking->price_tva;
            $breakdown[] = [
                'type' => 'tva',
                'amount' => $booking->price_tva
            ];
        }

        if ($booking->has_reduction && $booking->price_reduction > 0) {
            $total -= $booking->price_reduction;
            $breakdown[] = [
                'type' => 'reduction',
                'amount' => -$booking->price_reduction
            ];
        }

        $analysis['calculated_total'] = round($total, 2);
        $analysis['calculated_breakdown'] = $breakdown;
        $analysis['insurance_from_courses'] = $totalInsuranceFromCourses;

        Log::debug("calculateRealPrices CORREGIDO Booking {$booking->id}", [
            'total_calculated' => $total,
            'insurance_from_courses' => $totalInsuranceFromCourses,
            'insurance_from_booking' => $booking->price_cancellation_insurance ?? 0,
            'breakdown_count' => count($breakdown)
        ]);
    }

    /**
     * Analizar pagos - VERSIÓN ACTUALIZADA con no_refund
     * REEMPLAZAR el método analyzePayments en StatisticsController.php
     */
    private function analyzePayments($booking, &$analysis)
    {
        // ✅ SEPARAR PAGOS POR STATUS CORRECTAMENTE
        $paidPayments = $booking->payments->whereIn('status', ['paid']);
        $refundPayments = $booking->payments->whereIn('status', ['refund', 'partial_refund']);
        $noRefundPayments = $booking->payments->whereIn('status', ['no_refund']);

        foreach ($paidPayments as $payment) {
            $method = $this->determinePaymentMethod($payment, $booking);

            $analysis['payments']['total_paid'] += $payment->amount;

            // Si es un pago de Payrexx, sumarlo también al total específico
            if ($payment->payrexx_reference) {
                $analysis['payments']['total_paid_payrexx'] += $payment->amount;
            }

            $analysis['payments']['by_method'][$method] += $payment->amount;

            $analysis['payments']['details'][] = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $method,
                'status' => $payment->status,
                'notes' => $payment->notes,
                'date' => $payment->created_at->format('Y-m-d H:i:s'),
                'has_payrexx' => !empty($payment->payrexx_reference),
                'payrexx_reference' => $payment->payrexx_reference,
                'type' => 'payment'
            ];
        }

        // ✅ ANALIZAR REFUNDS (incluyendo partial_refund)
        foreach ($refundPayments as $payment) {
            $analysis['payments']['details'][] = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $this->determinePaymentMethod($payment, $booking),
                'status' => $payment->status,
                'notes' => $payment->notes,
                'date' => $payment->created_at->format('Y-m-d H:i:s'),
                'has_payrexx' => !empty($payment->payrexx_reference),
                'payrexx_reference' => $payment->payrexx_reference,
                'type' => 'refund'
            ];
        }

        // ✅ ANALIZAR NO_REFUNDS
        foreach ($noRefundPayments as $payment) {
            $analysis['payments']['details'][] = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $this->determinePaymentMethod($payment, $booking),
                'status' => $payment->status,
                'notes' => $payment->notes,
                'date' => $payment->created_at->format('Y-m-d H:i:s'),
                'has_payrexx' => !empty($payment->payrexx_reference),
                'payrexx_reference' => $payment->payrexx_reference,
                'type' => 'no_refund'
            ];
        }

        $analysis['payments']['total_paid'] = round($analysis['payments']['total_paid'], 2);
        $analysis['payments']['total_paid_payrexx'] = round($analysis['payments']['total_paid_payrexx'], 2);

        foreach ($analysis['payments']['by_method'] as $method => $amount) {
            $analysis['payments']['by_method'][$method] = round($amount, 2);
        }
    }

    private function analyzeVouchers($booking, &$analysis)
    {
        $voucherLogs = $booking->vouchersLogs;

        $totalUsed = 0;
        $totalRefunded = 0;

        Log::info("=== ANÁLISIS INTELIGENTE DE VOUCHERS - Booking {$booking->id} ===");

        foreach ($voucherLogs as $voucherLog) {
            $voucher = $voucherLog->voucher;
            $logAmount = $voucherLog->amount;

            if (!$voucher) {
                Log::warning("VoucherLog {$voucherLog->id} sin voucher asociado");
                continue;
            }

            // ✅ LÓGICA INTELIGENTE PARA DETERMINAR SI ES PAYMENT O REFUND
            $intelligentAnalysis = $this->determineVoucherLogType($voucherLog, $voucher, $booking);

            if ($intelligentAnalysis['type'] === 'payment') {
                $totalUsed += $intelligentAnalysis['amount'];
            } else {
                $totalRefunded += $intelligentAnalysis['amount'];
            }

            $analysis['vouchers']['details'][] = [
                'voucher_log_id' => $voucherLog->id,
                'voucher_id' => $voucherLog->voucher_id,
                'voucher_code' => $voucher->code ?? 'N/A',
                'original_amount' => $logAmount,
                'interpreted_amount' => $intelligentAnalysis['amount'],
                'interpreted_type' => $intelligentAnalysis['type'],
                'reason' => $intelligentAnalysis['reason'],
                'voucher_quantity' => $voucher->quantity,
                'voucher_remaining_balance' => $voucher->remaining_balance,
                'voucher_payed' => $voucher->payed,
                'status' => $voucherLog->status,
                'date' => $voucherLog->created_at->format('Y-m-d H:i:s')
            ];

            Log::info("VoucherLog {$voucherLog->id} interpretado", [
                'original_amount' => $logAmount,
                'interpreted_as' => $intelligentAnalysis['type'],
                'interpreted_amount' => $intelligentAnalysis['amount'],
                'reason' => $intelligentAnalysis['reason'],
                'voucher_code' => $voucher->code
            ]);
        }

        $analysis['vouchers']['total_used'] = round($totalUsed, 2);
        $analysis['vouchers']['total_refunded'] = round($totalRefunded, 2);

        Log::info("Resultado análisis vouchers Booking {$booking->id}", [
            'total_used' => $totalUsed,
            'total_refunded' => $totalRefunded,
            'net_voucher_contribution' => $totalUsed - $totalRefunded
        ]);
    }

    /**
     * MÉTODO CLAVE: Determinar si un voucherLog es payment o refund
     */
    public function determineVoucherLogType($voucherLog, $voucher, $booking)
    {
        $logAmount = $voucherLog->amount;
        $voucherQuantity = $voucher->quantity ?? 0;
        $voucherRemainingBalance = $voucher->remaining_balance ?? 0;
        $voucherPayed = $voucher->payed ?? false;

        // Calcular cuánto se ha usado del voucher (quantity - remaining_balance)
        $voucherUsedAmount = $voucherQuantity - $voucherRemainingBalance;

        // Obtener todos los logs de este voucher para contexto
        $allLogsForVoucher = $booking->vouchersLogs->where('voucher_id', $voucherLog->voucher_id);
        $positiveLogsSum = $allLogsForVoucher->where('amount', '>', 0)->sum('amount');
        $negativeLogsSum = $allLogsForVoucher->where('amount', '<', 0)->sum('amount'); // Ya es negativo

        Log::debug("Analizando VoucherLog {$voucherLog->id}", [
            'log_amount' => $logAmount,
            'voucher_quantity' => $voucherQuantity,
            'voucher_remaining_balance' => $voucherRemainingBalance,
            'voucher_used_amount' => $voucherUsedAmount,
            'voucher_payed' => $voucherPayed,
            'positive_logs_sum' => $positiveLogsSum,
            'negative_logs_sum' => $negativeLogsSum
        ]);

        // ✅ LÓGICA DE DECISIÓN

        if ($logAmount > 0) {
            // Positivo = claramente es uso del voucher
            return [
                'type' => 'payment',
                'amount' => $logAmount,
                'reason' => 'Cantidad positiva = uso de voucher'
            ];
        } else {
            // Negativo = necesita análisis inteligente
            $absAmount = abs($logAmount);

            // REGLA 1: Si el voucher NO está pagado, probablemente es un payment (uso)
            if (!$voucherPayed) {
                return [
                    'type' => 'payment',
                    'amount' => $absAmount,
                    'reason' => 'Voucher no pagado + cantidad negativa = probable uso de voucher'
                ];
            }

            // REGLA 2: Si el total de logs positivos + este negativo se acerca al voucher_used_amount
            $potentialTotalUsed = $positiveLogsSum + $absAmount;
            $usageMatch = abs($potentialTotalUsed - $voucherUsedAmount);

            if ($usageMatch < 0.50) {
                return [
                    'type' => 'payment',
                    'amount' => $absAmount,
                    'reason' => "Negativo + positivos ({$potentialTotalUsed}) coincide con uso del voucher ({$voucherUsedAmount})"
                ];
            }

            // REGLA 3: Si ya hay suficientes logs positivos para cubrir el uso del voucher
            if ($positiveLogsSum >= $voucherUsedAmount - 0.50) {
                return [
                    'type' => 'refund',
                    'amount' => $absAmount,
                    'reason' => "Ya hay suficientes logs positivos ({$positiveLogsSum}) para el uso ({$voucherUsedAmount}) = probable refund"
                ];
            }

            // REGLA 4: Si el voucher tiene remaining_balance = 0, significa se usó completamente
            if ($voucherRemainingBalance <= 0.01) {
                // Si se usó completamente, los negativos después son probablemente refunds
                if ($positiveLogsSum > 0) {
                    return [
                        'type' => 'refund',
                        'amount' => $absAmount,
                        'reason' => 'Voucher usado completamente + ya hay logs positivos = probable refund'
                    ];
                } else {
                    return [
                        'type' => 'payment',
                        'amount' => $absAmount,
                        'reason' => 'Voucher usado completamente pero sin logs positivos = probable payment'
                    ];
                }
            }

            // REGLA 5: Análisis por fecha (logs más antiguos probablemente son payments)
            $allLogsSorted = $allLogsForVoucher->sortBy('created_at');
            $isFirstLog = $allLogsSorted->first()->id === $voucherLog->id;

            if ($isFirstLog) {
                return [
                    'type' => 'payment',
                    'amount' => $absAmount,
                    'reason' => 'Primer log chronológico + negativo = probable payment'
                ];
            }

            // REGLA 6: Fallback - si el booking está cancelado, más probable que sea refund
            if ($booking->status == 2) {
                return [
                    'type' => 'refund',
                    'amount' => $absAmount,
                    'reason' => 'Booking cancelada + cantidad negativa = probable refund'
                ];
            }

            // REGLA 7: Fallback final - tratar como payment si no hay evidencia clara
            return [
                'type' => 'payment',
                'amount' => $absAmount,
                'reason' => 'Sin evidencia clara - asumido como payment (uso de voucher)'
            ];
        }
    }

    /**
     * MÉTODO CORREGIDO: Determinar si un no_refund es pre-pago
     * REEMPLAZAR el método isNoRefundPrePayment en StatisticsController.php
     */
    private function isNoRefundPrePayment($noRefundPayment, $paidPayments, $timeline)
    {
        $noRefundDate = $noRefundPayment->created_at;

        Log::info("Analizando si no_refund {$noRefundPayment->id} es pre-pago", [
            'no_refund_date' => $noRefundDate->format('Y-m-d H:i:s'),
            'no_refund_timestamp' => $noRefundDate->timestamp,
            'paid_payments_count' => $paidPayments->count(),
            'paid_payments' => $paidPayments->map(function($p) {
                return [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'status' => $p->status,
                    'created_at' => $p->created_at->format('Y-m-d H:i:s'),
                    'timestamp' => $p->created_at->timestamp
                ];
            })->toArray()
        ]);

        // MÉTODO 1: Comparar fechas directamente con TODOS los pagos exitosos
        if ($paidPayments->count() > 0) {
            $firstPaymentDate = $paidPayments->min('created_at');

            Log::info("Comparación de fechas", [
                'no_refund_timestamp' => $noRefundDate->timestamp,
                'first_payment_timestamp' => $firstPaymentDate->timestamp,
                'no_refund_is_before' => $noRefundDate->timestamp < $firstPaymentDate->timestamp,
                'difference_seconds' => $firstPaymentDate->timestamp - $noRefundDate->timestamp
            ]);

            if ($noRefundDate->timestamp < $firstPaymentDate->timestamp) {
                Log::info("✅ No_refund {$noRefundPayment->id} es PRE-PAGO por timestamp", [
                    'no_refund_date' => $noRefundDate->format('Y-m-d H:i:s'),
                    'first_payment_date' => $firstPaymentDate->format('Y-m-d H:i:s'),
                    'difference_minutes' => round(($firstPaymentDate->timestamp - $noRefundDate->timestamp) / 60, 2)
                ]);
                return true;
            } else {
                Log::info("❌ No_refund {$noRefundPayment->id} es POST-PAGO por timestamp", [
                    'no_refund_date' => $noRefundDate->format('Y-m-d H:i:s'),
                    'first_payment_date' => $firstPaymentDate->format('Y-m-d H:i:s'),
                    'difference_minutes' => round(($noRefundDate->timestamp - $firstPaymentDate->timestamp) / 60, 2)
                ]);
                return false;
            }
        }

        // MÉTODO 2: Si no hay pagos aún, verificar timeline de cancelaciones
        if ($paidPayments->isEmpty()) {
            Log::info("No hay pagos exitosos, verificando timeline para no_refund {$noRefundPayment->id}");

            // Si no hay pagos, verificar si hay cancelaciones previas al no_refund
            $hasPriorCancellation = false;
            if (isset($timeline['events'])) {
                foreach ($timeline['events'] as $event) {
                    if (str_contains(strtolower($event['action']), 'cancel') &&
                        $event['date']->timestamp <= $noRefundDate->timestamp) {
                        $hasPriorCancellation = true;
                        Log::info("Encontrada cancelación previa", [
                            'cancel_date' => $event['date']->format('Y-m-d H:i:s'),
                            'cancel_action' => $event['action']
                        ]);
                        break;
                    }
                }
            }

            if ($hasPriorCancellation) {
                Log::info("✅ No_refund {$noRefundPayment->id} es PRE-PAGO por cancelación previa sin pagos");
                return true;
            }
        }

        // MÉTODO 3: Verificar notas específicas
        $notes = strtolower($noRefundPayment->notes ?? '');
        $prePaymentKeywords = ['pre-payment', 'before payment', 'cancellation before payment', 'pre payment'];

        foreach ($prePaymentKeywords as $keyword) {
            if (str_contains($notes, $keyword)) {
                Log::info("✅ No_refund {$noRefundPayment->id} es PRE-PAGO por keywords en notas", [
                    'notes' => $notes,
                    'matched_keyword' => $keyword
                ]);
                return true;
            }
        }

        // MÉTODO 4: Fallback - si no se puede determinar claramente, asumir POST-PAGO para ser conservadores
        Log::info("❌ No_refund {$noRefundPayment->id} clasificado como POST-PAGO (fallback)", [
            'reason' => 'No se pudo determinar claramente, asumiendo post-pago por seguridad'
        ]);

        return false;
    }

    /**
     * MÉTODO FALTANTE: Analizar timeline de la reserva
     */
    private function analyzeBookingTimeline($booking)
    {
        $timeline = [
            'events' => [],
            'creation_date' => $booking->created_at,
            'last_update' => $booking->updated_at
        ];

        // Obtener logs de la reserva ordenados por fecha
        $bookingLogs = $booking->bookingLogs()->orderBy('created_at', 'asc')->get();

        foreach ($bookingLogs as $log) {
            $timeline['events'][] = [
                'action' => $log->action,
                'description' => $log->description,
                'date' => $log->created_at,
                'user_id' => $log->user_id,
                'timestamp' => $log->created_at->timestamp
            ];
        }

        // Añadir eventos de pagos ordenados cronológicamente
        $payments = $booking->payments()->orderBy('created_at', 'asc')->get();

        foreach ($payments as $payment) {
            $timeline['events'][] = [
                'action' => 'payment_' . $payment->status,
                'description' => "Pago {$payment->status}: {$payment->amount}€" .
                    ($payment->notes ? " - {$payment->notes}" : ""),
                'date' => $payment->created_at,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'timestamp' => $payment->created_at->timestamp
            ];
        }

        // Añadir eventos de vouchers
        $voucherLogs = $booking->vouchersLogs()->orderBy('created_at', 'asc')->get();

        foreach ($voucherLogs as $voucherLog) {
            $timeline['events'][] = [
                'action' => 'voucher_used',
                'description' => "Voucher usado: {$voucherLog->amount}€",
                'date' => $voucherLog->created_at,
                'voucher_log_id' => $voucherLog->id,
                'amount' => $voucherLog->amount,
                'timestamp' => $voucherLog->created_at->timestamp
            ];
        }

        // Ordenar todos los eventos por timestamp
        usort($timeline['events'], function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return $timeline;
    }

    /**
     * Analizar reembolsos detallados - VERSIÓN ACTUALIZADA con no_refund
     * REEMPLAZAR el método analyzeRefundsDetailed en StatisticsController.php
     */
    private function analyzeRefundsDetailed($booking, &$analysis)
    {
        $refundPayments = $booking->payments->whereIn('status', ['refund', 'partial_refund']);
        $noRefundPayments = $booking->payments->whereIn('status', ['no_refund']);
        $paidPayments = $booking->payments->whereIn('status', ['paid']);

        Log::info("=== INICIO ANÁLISIS REFUNDS DETALLADO - Booking {$booking->id} ===", [
            'refund_payments_count' => $refundPayments->count(),
            'no_refund_payments_count' => $noRefundPayments->count(),
            'paid_payments_count' => $paidPayments->count(),
            'booking_status' => $booking->status
        ]);

        // ✅ ANALIZAR TIMELINE PARA CONTEXTO
        $timeline = $this->analyzeBookingTimeline($booking);

        // Inicializar breakdown con todas las categorías
        // ✅ INICIALIZAR TODAS LAS CATEGORÍAS DE BREAKDOWN
        $analysis['refunds']['breakdown'] = array_merge([
            'refund_full' => 0,
            'refund_partial' => 0,
            'no_refund' => 0,                    // Solo POST-PAYMENT
            'no_refund_pre_payment' => 0,        // Solo PRE-PAYMENT
            'refund_pending' => 0
        ], $analysis['refunds']['breakdown']);

        // ✅ PROCESAR REFUNDS Y PARTIAL_REFUNDS NORMALES
        foreach ($refundPayments as $payment) {
            $analysis['refunds']['total_refunded'] += $payment->amount;

            switch ($payment->status) {
                case 'refund':
                    $analysis['refunds']['breakdown']['refund_full'] += $payment->amount;
                    break;
                case 'partial_refund':
                    $analysis['refunds']['breakdown']['refund_partial'] += $payment->amount;
                    break;
            }

            $analysis['refunds']['details'][] = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'reason' => $this->determineRefundReason($payment),
                'notes' => $payment->notes,
                'date' => $payment->created_at->format('Y-m-d H:i:s'),
                'days_from_booking' => $booking->created_at->diffInDays($payment->created_at),
                'type' => 'refund_normal'
            ];
        }

        // ✅ PROCESAR NO_REFUNDS CON LÓGICA PRE/POST PAGO INTELIGENTE
        foreach ($noRefundPayments as $payment) {
            Log::info("Procesando no_refund payment {$payment->id} para Booking {$booking->id}");

            // 🔍 DETERMINAR SI ES PRE-PAGO O POST-PAGO
            $isPrePayment = $this->isNoRefundPrePayment($payment, $paidPayments, $timeline);

            if ($isPrePayment) {
                // ✅ NO_REFUND PRE-PAGO: No afecta balance (es normal)
                $analysis['refunds']['breakdown']['no_refund_pre_payment'] += $payment->amount;

                Log::info("✅ No_refund {$payment->id} clasificado como PRE-PAGO", [
                    'amount' => $payment->amount,
                    'reason' => 'No afecta balance - cancelación antes del pago'
                ]);
            } else {
                // ❌ NO_REFUND POST-PAGO: Afecta balance (problemático)
                $analysis['refunds']['breakdown']['no_refund'] += $payment->amount;

                Log::info("❌ No_refund {$payment->id} clasificado como POST-PAGO", [
                    'amount' => $payment->amount,
                    'reason' => 'Afecta balance - dinero retenido después del pago'
                ]);
            }

            $analysis['refunds']['details'][] = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'reason' => $this->determineRefundReason($payment),
                'notes' => $payment->notes,
                'date' => $payment->created_at->format('Y-m-d H:i:s'),
                'days_from_booking' => $booking->created_at->diffInDays($payment->created_at),
                'type' => 'no_refund',
                'is_pre_payment' => $isPrePayment,
                'affects_balance' => !$isPrePayment,
                'classification_reason' => $isPrePayment
                    ? 'Aplicado antes del pago - no problemático'
                    : 'Aplicado después del pago - dinero retenido'
            ];
        }

        // ✅ CALCULAR REFUNDS PENDIENTES - LÓGICA ACTUALIZADA
        $this->calculateRefundsPending($booking, $analysis, $paidPayments);

        // ✅ REDONDEAR TODOS LOS VALORES
        $analysis['refunds']['total_refunded'] = round($analysis['refunds']['total_refunded'], 2);

        foreach ($analysis['refunds']['breakdown'] as $type => $amount) {
            $analysis['refunds']['breakdown'][$type] = round($amount, 2);
        }

        // ✅ LOGGING FINAL DEL RESULTADO
        Log::info("=== FIN ANÁLISIS REFUNDS DETALLADO - Booking {$booking->id} ===", [
            'total_refunded' => $analysis['refunds']['total_refunded'],
            'refund_full' => $analysis['refunds']['breakdown']['refund_full'],
            'refund_partial' => $analysis['refunds']['breakdown']['refund_partial'],
            'no_refund_post_payment' => $analysis['refunds']['breakdown']['no_refund'],
            'no_refund_pre_payment' => $analysis['refunds']['breakdown']['no_refund_pre_payment'],
            'refund_pending' => $analysis['refunds']['breakdown']['refund_pending'],
            'interpretation' => 'Solo no_refund POST-PAYMENT afecta el balance'
        ]);
    }

    private function addPayrexxComparison($booking, &$analysis, $payrexxAnalysis)
    {
        // Buscar la comparación de esta reserva en el análisis de Payrexx
        $bookingComparison = null;
        foreach ($payrexxAnalysis['booking_comparisons'] as $comparison) {
            if ($comparison['booking_id'] == $booking->id) {
                $bookingComparison = $comparison;
                break;
            }
        }

        if ($bookingComparison) {
            $analysis['payrexx_comparison'] = [
                'transactions' => $bookingComparison['transactions'],
                'verified_payments' => $bookingComparison['verified_payments'],
                'total_amount' => $bookingComparison['total_payrexx_amount'],
                'has_discrepancy' => $bookingComparison['has_discrepancy'],
                'difference' => $bookingComparison['difference'],
                'missing_transactions' => $bookingComparison['missing_transactions'],
                'summary' => $bookingComparison['summary']
            ];

            // Log para debugging
            Log::info("Comparación Payrexx para booking {$booking->id}", [
                'system_amount' => $bookingComparison['total_system_amount'],
                'payrexx_amount' => $bookingComparison['total_payrexx_amount'],
                'difference' => $bookingComparison['difference'],
                'has_discrepancy' => $bookingComparison['has_discrepancy'],
                'transactions_found' => count($bookingComparison['transactions']),
                'verified_payments' => count($bookingComparison['verified_payments'])
            ]);

            // Añadir issues basados en la comparación
            if ($bookingComparison['has_discrepancy']) {
                $difference = $bookingComparison['difference'];
                if ($difference > 0) {
                    $analysis['issues'][] = "Sistema tiene más dinero que Payrexx: +{$difference}€";
                } else {
                    $analysis['issues'][] = "Payrexx tiene más dinero que el sistema: " . abs($difference) . "€";
                }
            }

            if ($bookingComparison['summary']['missing_in_payrexx'] > 0) {
                $analysis['issues'][] = "{$bookingComparison['summary']['missing_in_payrexx']} transacciones no encontradas en Payrexx";
            }

            if ($bookingComparison['summary']['amount_mismatches'] > 0) {
                $analysis['issues'][] = "{$bookingComparison['summary']['amount_mismatches']} discrepancias de montos con Payrexx";
            }

            if ($bookingComparison['summary']['status_mismatches'] > 0) {
                $analysis['issues'][] = "{$bookingComparison['summary']['status_mismatches']} discrepancias de estados con Payrexx";
            }
        } else {
            $analysis['payrexx_comparison'] = [
                'error' => 'No se pudo analizar esta reserva con Payrexx',
                'has_discrepancy' => false,
                'total_amount' => 0,
                'transactions' => [],
                'verified_payments' => []
            ];

            Log::warning("No se encontró comparación Payrexx para booking {$booking->id}");
        }
    }

    /**
     * MÉTODO CLAVE: Calcular refunds pendientes - LÓGICA COMPLETA
     * Este método determina si hay dinero que debería ser procesado (refundado o marcado como no_refund)
     * pero que aún no ha sido manejado correctamente.
     */
    private function calculateRefundsPending($booking, &$analysis, $paidPayments)
    {
        $bookingId = $booking->id;
        $bookingStatus = $booking->status;

        // Obtener todos los montos relevantes
        $totalPaid = $paidPayments->sum('amount');
        $totalRefunded = $analysis['refunds']['total_refunded'] ?? 0;
        $totalVouchersUsed = $analysis['vouchers']['total_used'] ?? 0;
        $totalVouchersRefunded = $analysis['vouchers']['total_refunded'] ?? 0;

        // ✅ SOLO CONTAR NO_REFUND POST-PAYMENT PARA EL BALANCE
        $totalNoRefundPostPayment = $analysis['refunds']['breakdown']['no_refund'] ?? 0;
        $totalNoRefundPrePayment = $analysis['refunds']['breakdown']['no_refund_pre_payment'] ?? 0;

        Log::info("=== CÁLCULO REFUNDS PENDIENTES - Booking {$bookingId} ===", [
            'booking_status' => $bookingStatus,
            'total_paid' => $totalPaid,
            'total_vouchers_used' => $totalVouchersUsed,
            'total_refunded' => $totalRefunded,
            'total_vouchers_refunded' => $totalVouchersRefunded,
            'total_no_refund_post_payment' => $totalNoRefundPostPayment,
            'total_no_refund_pre_payment' => $totalNoRefundPrePayment,
            'explanation' => 'Solo no_refund post-payment cuenta para balance'
        ]);

        // Inicializar refunds pendientes
        $refundsPending = 0;
        $pendingReason = '';

        // ✅ LÓGICA ESPECÍFICA POR ESTADO DE RESERVA
        switch ($bookingStatus) {
            case 1: // RESERVA ACTIVA
                $refundsPending = $this->calculatePendingForActiveBooking(
                    $booking, $totalPaid, $totalVouchersUsed, $totalRefunded, $totalVouchersRefunded, $totalNoRefundPostPayment
                );
                $pendingReason = $refundsPending > 0 ? 'Reserva activa con refunds inesperados' : 'Reserva activa normal';
                break;

            case 2: // RESERVA CANCELADA TOTALMENTE
                $result = $this->calculatePendingForCancelledBooking(
                    $booking, $totalPaid, $totalVouchersUsed, $totalRefunded, $totalVouchersRefunded, $totalNoRefundPostPayment
                );
                $refundsPending = $result['pending'];
                $pendingReason = $result['reason'];
                break;

            case 3: // RESERVA PARCIALMENTE CANCELADA
                $result = $this->calculatePendingForPartiallyCancelledBooking(
                    $booking, $totalPaid, $totalVouchersUsed, $totalRefunded, $totalVouchersRefunded, $totalNoRefundPostPayment, $analysis
                );
                $refundsPending = $result['pending'];
                $pendingReason = $result['reason'];
                break;

            default:
                $refundsPending = 0;
                $pendingReason = "Estado de reserva desconocido: {$bookingStatus}";
                Log::warning("Estado de reserva desconocido para Booking {$bookingId}", [
                    'status' => $bookingStatus
                ]);
        }

        // ✅ ASIGNAR RESULTADO Y LOGGING
        $analysis['refunds']['breakdown']['refund_pending'] = round(max(0, $refundsPending), 2);

        // Agregar información detallada al análisis
        $analysis['refunds']['pending_calculation'] = [
            'booking_status' => $bookingStatus,
            'pending_amount' => round($refundsPending, 2),
            'reason' => $pendingReason,
            'calculation_date' => now()->format('Y-m-d H:i:s'),
            'total_received' => round($totalPaid + $totalVouchersUsed, 2),
            'total_processed_post_payment' => round($totalRefunded + $totalVouchersRefunded + $totalNoRefundPostPayment, 2),
            'excluded_pre_payment_no_refund' => round($totalNoRefundPrePayment, 2)
        ];

        Log::info("=== RESULTADO REFUNDS PENDIENTES - Booking {$bookingId} ===", [
            'pending_amount' => $analysis['refunds']['breakdown']['refund_pending'],
            'reason' => $pendingReason,
            'booking_status' => $bookingStatus
        ]);

        // ✅ AGREGAR ISSUE SI HAY PENDIENTES SIGNIFICATIVOS
        if ($refundsPending > 0.50) {
            $analysis['issues'] = $analysis['issues'] ?? [];
            $analysis['issues'][] = "Refunds pendientes: " . round($refundsPending, 2) . "€ - " . $pendingReason;
        }
    }

    /**
     * MÉTODO AUXILIAR: Calcular pendientes para reserva activa
     */
    private function calculatePendingForActiveBooking($booking, $totalPaid, $totalVouchersUsed, $totalRefunded, $totalVouchersRefunded, $totalNoRefundPostPayment)
    {
        // Para reservas activas, normalmente NO debería haber refunds
        // Si los hay, podría ser un error o una situación especial

        $totalRefundsProcessed = $totalRefunded + $totalVouchersRefunded + $totalNoRefundPostPayment;

        if ($totalRefundsProcessed > 0.50) {
            Log::warning("Reserva activa {$booking->id} tiene refunds procesados", [
                'total_refunds_processed' => $totalRefundsProcessed,
                'might_be_error' => 'Verificar si la reserva debería estar cancelada'
            ]);

            // En principio, una reserva activa no debería tener pendientes por refunds
            return 0;
        }

        return 0;
    }

    /**
     * MÉTODO AUXILIAR: Calcular pendientes para reserva cancelada
     */
    private function calculatePendingForCancelledBooking($booking, $totalPaid, $totalVouchersUsed, $totalRefunded, $totalVouchersRefunded, $totalNoRefundPostPayment)
    {
        $totalReceived = $totalPaid + $totalVouchersUsed;

        Log::info("Analizando reserva cancelada {$booking->id}", [
            'total_received' => $totalReceived,
            'total_paid' => $totalPaid,
            'total_vouchers_used' => $totalVouchersUsed
        ]);

        // ✅ CASO ESPECIAL: Cancelada sin pago previo
        if ($totalReceived <= 0.01) {
            Log::info("Booking {$booking->id} cancelada sin pago previo - OK", [
                'total_received' => $totalReceived,
                'conclusion' => 'No hay nada que refundar'
            ]);

            return [
                'pending' => 0,
                'reason' => 'Cancelada sin pago previo - correcto'
            ];
        }

        // ✅ CASO NORMAL: Cancelada con pago previo
        $totalProcessedPostPayment = $totalRefunded + $totalVouchersRefunded + $totalNoRefundPostPayment;
        $unreimbursedAmount = $totalReceived - $totalProcessedPostPayment;

        Log::info("Booking {$booking->id} cancelada con pago previo", [
            'total_received' => $totalReceived,
            'total_processed_post_payment' => $totalProcessedPostPayment,
            'unreimbursed_amount' => $unreimbursedAmount,
            'breakdown' => [
                'refunded' => $totalRefunded,
                'vouchers_refunded' => $totalVouchersRefunded,
                'no_refund_post_payment' => $totalNoRefundPostPayment
            ]
        ]);

        if ($unreimbursedAmount > 0.50) {
            return [
                'pending' => $unreimbursedAmount,
                'reason' => "Cancelada: faltan " . round($unreimbursedAmount, 2) . "€ por procesar"
            ];
        } elseif ($unreimbursedAmount < -0.50) {
            // Se procesó más dinero del que se recibió - posible error
            return [
                'pending' => 0,
                'reason' => "ADVERTENCIA: Se procesó más dinero del recibido (diferencia: " . round(abs($unreimbursedAmount), 2) . "€)"
            ];
        } else {
            return [
                'pending' => 0,
                'reason' => 'Cancelada correctamente procesada'
            ];
        }
    }

    /**
     * MÉTODO AUXILIAR: Calcular pendientes para reserva parcialmente cancelada
     */
    private function calculatePendingForPartiallyCancelledBooking($booking, $totalPaid, $totalVouchersUsed, $totalRefunded, $totalVouchersRefunded, $totalNoRefundPostPayment, $analysis)
    {
        $totalReceived = $totalPaid + $totalVouchersUsed;

        // ✅ CORRECCIÓN: Calcular precio esperado para usuarios activos agrupando por CLIENTE
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);

        $allBookingUsers = $booking->bookingUsers
            ->filter(function($bu) use ($excludedCourses) {
                return !in_array((int) $bu->course_id, $excludedCourses);
            });

        $activeBookingUsers = $allBookingUsers->where('status', 1);
        $cancelledBookingUsers = $allBookingUsers->where('status', 2);

        // ✅ CÁLCULO CORRECTO: Usar la misma lógica que calculateGroupedBookingUsersPrice
        $expectedForActive = 0;

        foreach ($activeBookingUsers->groupBy('course_id') as $courseId => $courseBookingUsers) {
            $course = $courseBookingUsers->first()->course;

            if ($course->course_type == 1) { // Colectivo
                if ($course->is_flexible) {
                    // Para flexibles: calcular por cliente considerando sus fechas
                    foreach ($courseBookingUsers->groupBy('client_id') as $clientId => $clientBookingUsers) {
                        $clientPrice = $this->calculateFlexibleCollectivePrice($clientBookingUsers->first(), $clientBookingUsers);
                        $expectedForActive += $clientPrice;
                    }
                } else {
                    // Para fijos: precio base × clientes únicos
                    $uniqueClients = $courseBookingUsers->groupBy('client_id')->count();
                    $expectedForActive += ($course->price * $uniqueClients);
                }
            } elseif ($course->course_type == 2) { // Privado
                // Para privados: calcular por sesión
                $grouped = $courseBookingUsers->groupBy(function ($bookingUser) {
                    return $bookingUser->date . '|' . $bookingUser->hour_start . '|' . $bookingUser->hour_end . '|' .
                        $bookingUser->monitor_id . '|' . $bookingUser->group_id;
                });

                foreach ($grouped as $group) {
                    $sessionPrice = $this->calculatePrivatePrice($group->first(), $course->price_range ?? []);
                    $expectedForActive += $sessionPrice;
                }
            }
        }

        // ✅ AÑADIR EXTRAS E SEGUROS
        foreach ($activeBookingUsers as $bu) {
            $expectedForActive += $this->calculateExtrasPrice($bu);
        }

        // Seguro de cancelación (solo una vez si aplica)
        if ($booking->has_cancellation_insurance && $expectedForActive > 0) {
            $expectedForActive += ($expectedForActive * 0.10);
        }

        Log::info("Análisis cancelación parcial CORREGIDO {$booking->id}", [
            'active_clients_by_course' => $activeBookingUsers->groupBy('course_id')->map(function($courseUsers) {
                return $courseUsers->groupBy('client_id')->count();
            })->toArray(),
            'cancelled_clients_by_course' => $cancelledBookingUsers->groupBy('course_id')->map(function($courseUsers) {
                return $courseUsers->groupBy('client_id')->count();
            })->toArray(),
            'expected_for_active_corrected' => $expectedForActive,
            'total_received' => $totalReceived
        ]);

        // ✅ LÓGICA DE ANÁLISIS SEGÚN EL FLUJO DE DINERO
        if ($totalReceived <= $expectedForActive + 5) {
            // CASO A: Solo se pagó por usuarios activos
            $expectedBalance = $expectedForActive;
            $actualBalance = $totalReceived - ($totalRefunded + $totalVouchersRefunded + $totalNoRefundPostPayment);
            $discrepancy = $expectedBalance - $actualBalance;

            if (abs($discrepancy) > 0.50) {
                return [
                    'pending' => abs($discrepancy),
                    'reason' => "Cancelación parcial con discrepancia de balance: " . round($discrepancy, 2) . "€"
                ];
            } else {
                return [
                    'pending' => 0,
                    'reason' => 'Cancelación parcial correctamente manejada (flujo normal)'
                ];
            }
        } else {
            // CASO B: Se pagó por múltiples usuarios, verificar si refunds están procesados
            $totalProcessed = $totalRefunded + $totalVouchersRefunded + $totalNoRefundPostPayment;
            $expectedRefund = $totalReceived - $expectedForActive;
            $actualRefund = $totalProcessed;

            $refundDiscrepancy = $expectedRefund - $actualRefund;

            if ($refundDiscrepancy > 0.50) {
                return [
                    'pending' => $refundDiscrepancy,
                    'reason' => "Cancelación parcial: faltan " . round($refundDiscrepancy, 2) . "€ por refundar"
                ];
            } else {
                return [
                    'pending' => 0,
                    'reason' => 'Cancelación parcial con refunds correctamente procesados'
                ];
            }
        }
    }

    private function verifyTransactionWithPayrexx($payment, $booking)
    {
        try {
            $school = $booking->school;

            // Usar PayrexxHelpers existente para verificar la transacción
            if ($payment->payrexx_transaction) {
                $transactionData = $payment->getPayrexxTransaction();
                $transactionId = $transactionData['id'] ?? null;

                if ($transactionId) {
                    $payrexxTransaction = \App\Http\Controllers\PayrexxHelpers::retrieveTransaction(
                        $school->getPayrexxInstance(),
                        $school->getPayrexxKey(),
                        $transactionId
                    );

                    if ($payrexxTransaction) {
                        return [
                            'found' => true,
                            'transaction' => [
                                'id' => $payrexxTransaction->getId(),
                                'reference' => $payrexxTransaction->getReferenceId(),
                                'amount' => $payrexxTransaction->getAmount() / 100,
                                'currency' => $payrexxTransaction->getCurrency(),
                                'status' => $payrexxTransaction->getStatus(),
                                'date' => date('Y-m-d H:i:s', $payrexxTransaction->getCreatedAt())
                            ],
                            'matches_system' => abs($payment->amount - ($payrexxTransaction->getAmount() / 100)) < 0.01
                        ];
                    }
                }
            }

            return [
                'found' => false,
                'error' => 'No se pudo verificar la transacción en Payrexx',
                'payment_id' => $payment->id,
                'reference' => $payment->payrexx_reference
            ];

        } catch (\Exception $e) {
            Log::error('Error verifying Payrexx transaction: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'booking_id' => $booking->id,
                'reference' => $payment->payrexx_reference
            ]);

            return [
                'found' => false,
                'error' => 'Error al verificar: ' . $e->getMessage(),
                'payment_id' => $payment->id
            ];
        }
    }

    private function verifyIndividualTransaction($payment, $booking)
    {
        try {
            $verification = [
                'payment_id' => $payment->id,
                'reference' => $payment->payrexx_reference,
                'system_amount' => $payment->amount,
                'payrexx_amount' => null,
                'status_match' => false,
                'amount_match' => false,
                'issues' => []
            ];

            // Si tenemos datos de la transacción guardados
            if ($payment->payrexx_transaction) {
                $transactionData = $payment->getPayrexxTransaction();

                if (isset($transactionData['amount'])) {
                    $payrexxAmount = is_numeric($transactionData['amount']) ?
                        $transactionData['amount'] / 100 : $transactionData['amount'];
                    $verification['payrexx_amount'] = $payrexxAmount;
                    $verification['amount_match'] = abs($payment->amount - $payrexxAmount) < 0.01;

                    if (!$verification['amount_match']) {
                        $verification['issues'][] = "Monto no coincide: Sistema {$payment->amount}€ vs Payrexx {$payrexxAmount}€";
                    }
                }

                if (isset($transactionData['status'])) {
                    $verification['status_match'] = $this->statusMatches($payment->status, $transactionData['status']);

                    if (!$verification['status_match']) {
                        $verification['issues'][] = "Estado no coincide: Sistema '{$payment->status}' vs Payrexx '{$transactionData['status']}'";
                    }
                }
            } else {
                $verification['issues'][] = 'No hay datos de transacción Payrexx guardados';
            }

            return $verification;

        } catch (\Exception $e) {
            Log::error('Error in individual transaction verification: ' . $e->getMessage());
            return [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ];
        }
    }

    private function statusMatches($systemStatus, $payrexxStatus)
    {
        $statusMap = [
            'paid' => ['confirmed', 'authorized'],
            'refund' => ['refunded'],
            'partial_refund' => ['partially_refunded'],
            'pending' => ['waiting', 'processing']
        ];

        return in_array($payrexxStatus, $statusMap[$systemStatus] ?? []);
    }

    private function getPayrexxPaymentMethod($transaction)
    {
        // Intentar obtener el método de pago de la transacción
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
            Log::warning('Error getting payment method from Payrexx transaction: ' . $e->getMessage());
            return 'unknown';
        }
    }

    private function determineRefundReason($payment)
    {
        $notes = strtolower($payment->notes ?? '');
        $status = $payment->status;

        // ✅ MANEJO ESPECÍFICO PARA NO_REFUND
        if ($status === 'no_refund') {
            if (str_contains($notes, 'policy') || str_contains($notes, 'politica') || str_contains($notes, 'condiciones')) {
                return 'policy_no_refund';
            }

            if (str_contains($notes, 'late') || str_contains($notes, 'tardio') || str_contains($notes, 'tiempo')) {
                return 'late_cancellation_no_refund';
            }

            if (str_contains($notes, 'admin') || str_contains($notes, 'administrative') || str_contains($notes, 'administrat')) {
                return 'administrative_no_refund';
            }

            if (str_contains($notes, 'partial') || str_contains($notes, 'parcial')) {
                return 'partial_no_refund';
            }

            return 'no_refund_other';
        }

        // ✅ LÓGICA EXISTENTE PARA REFUNDS NORMALES
        if (str_contains($notes, 'cancellation insurance') || str_contains($notes, 'seguro')) {
            return 'insurance_claim';
        }

        if (str_contains($notes, 'weather') || str_contains($notes, 'clima') || str_contains($notes, 'lluvia')) {
            return 'weather_cancellation';
        }

        if (str_contains($notes, 'illness') || str_contains($notes, 'enfermedad') || str_contains($notes, 'sick')) {
            return 'illness';
        }

        if (str_contains($notes, 'voluntary') || str_contains($notes, 'voluntaria') || str_contains($notes, 'cliente')) {
            return 'voluntary_cancellation';
        }

        if (str_contains($notes, 'school') || str_contains($notes, 'escuela') || str_contains($notes, 'monitor')) {
            return 'school_cancellation';
        }

        // ✅ DIFERENTES DEFAULTS SEGÚN STATUS
        switch ($status) {
            case 'refund':
                return 'full_refund_other';
            case 'partial_refund':
                return 'partial_refund_other';
            case 'no_refund':
                return 'no_refund_other';
            default:
                return 'other';
        }
    }

    private function analyzeCancellations($booking, &$analysis)
    {
        $analysis['cancellation'] = [
            'status' => $booking->getCancellationStatusAttribute(),
            'booking_status' => $booking->status,
            'total_booking_users' => $booking->bookingUsers->count(),
            'cancelled_booking_users' => $booking->bookingUsers->where('status', 2)->count(),
            'active_booking_users' => $booking->bookingUsers->where('status', 1)->count(),
        ];

        // Buscar logs de cancelación
        $cancelLogs = $booking->bookingLogs->where('action', 'LIKE', '%cancel%');
        $analysis['cancellation']['cancel_logs'] = $cancelLogs->map(function($log) {
            return [
                'action' => $log->action,
                'description' => $log->description,
                'date' => $log->created_at->format('Y-m-d H:i:s'),
                'user_id' => $log->user_id
            ];
        })->toArray();
    }

    /**
     * CORRECCIÓN: Lógica corregida de detección de discrepancias
     * REEMPLAZAR el método detectDiscrepancies en StatisticsController.php
     */
    /**
     * CORRECCIÓN PRINCIPAL: detectDiscrepancies
     * Manejo correcto de cancelaciones parciales y balance real
     */
    private function detectDiscrepancies($booking, &$analysis)
    {
        $storedTotal = $booking->price_total;
        $calculatedTotal = $analysis['calculated_total'];
        $paidTotal = $analysis['payments']['total_paid'];
        $vouchersUsed = $analysis['vouchers']['total_used'];
        $vouchersRefunded = $analysis['vouchers']['total_refunded'];
        $refunded = $analysis['refunds']['total_refunded'];
        $noRefundPostPayment = $analysis['refunds']['breakdown']['no_refund'] ?? 0;

        Log::debug("detectDiscrepancies Booking {$booking->id}", [
            'status' => $booking->status,
            'stored_total' => $storedTotal,
            'calculated_total' => $calculatedTotal,
            'paid_total' => $paidTotal,
            'vouchers_used' => $vouchersUsed,
            'refunded' => $refunded,
            'no_refund_post_payment' => $noRefundPostPayment
        ]);

        // ✅ ANÁLISIS DE BALANCE REAL SEGÚN ESTADO
        $actualBalance = 0;
        $expectedBalance = 0;
        $balanceType = '';

        switch ($booking->status) {
            case 1: // ACTIVA
                $actualBalance = $paidTotal + $vouchersUsed - $vouchersRefunded - $refunded - $noRefundPostPayment;
                $expectedBalance = $calculatedTotal;
                $balanceType = 'active_booking';
                break;

            case 2: // CANCELADA TOTALMENTE
                $totalReceived = $paidTotal + $vouchersUsed;

                if ($totalReceived <= 0.01) {
                    // Cancelada sin pago previo - balance correcto
                    $actualBalance = 0;
                    $expectedBalance = 0;
                    $balanceType = 'cancelled_no_payment';
                } else {
                    // Cancelada con pago - verificar procesamiento
                    $totalProcessed = $refunded + $vouchersRefunded + $noRefundPostPayment;
                    $actualBalance = $totalReceived - $totalProcessed;
                    $expectedBalance = 0;
                    $balanceType = 'cancelled_with_payment';
                }
                break;

            case 3: // PARCIALMENTE CANCELADA
                // ✅ USAR CALCULATED_TOTAL PARA USUARIOS ACTIVOS (ya es correcto)
                $actualBalance = $paidTotal + $vouchersUsed - $vouchersRefunded - $refunded - $noRefundPostPayment;
                $expectedBalance = $calculatedTotal;
                $balanceType = 'partially_cancelled';
                break;
        }

        // ✅ EVALUAR DISCREPANCIA REAL
        $balanceDiscrepancy = round($expectedBalance - $actualBalance, 2);
        $hasRealDiscrepancy = abs($balanceDiscrepancy) > 0.50;

        $analysis['has_discrepancy'] = $hasRealDiscrepancy;
        $analysis['discrepancy_amount'] = abs($balanceDiscrepancy);

        $analysis['balance_analysis'] = [
            'type' => $balanceType,
            'actual_balance' => round($actualBalance, 2),
            'expected_balance' => round($expectedBalance, 2),
            'discrepancy' => $balanceDiscrepancy,
            'is_real_discrepancy' => $hasRealDiscrepancy
        ];

        // ✅ ANÁLISIS DE STORED_TOTAL vs CALCULATED_TOTAL (solo informativo)
        $storedVsCalculatedDiff = round($storedTotal - $calculatedTotal, 2);

        if (abs($storedVsCalculatedDiff) > 0.50) {
            $analysis['stored_total_analysis'] = [
                'stored_total' => $storedTotal,
                'calculated_total' => $calculatedTotal,
                'difference' => $storedVsCalculatedDiff,
                'is_problem' => false,
                'explanation' => $booking->status == 3
                    ? 'NORMAL: stored_total es precio original, calculated_total es precio de usuarios activos'
                    : 'INFO: stored_total puede diferir por vouchers o seguros'
            ];
        }

        // ✅ DETECTAR PROBLEMAS ESPECÍFICOS (solo para discrepancias reales)
        if ($hasRealDiscrepancy) {
            $analysis['issues'] = $analysis['issues'] ?? [];

            switch ($booking->status) {
                case 1: // ACTIVA
                    if ($booking->paid && $actualBalance < $calculatedTotal - 0.50) {
                        $analysis['issues'][] = 'Marcada como pagada pero falta dinero real';
                    }
                    break;

                case 2: // CANCELADA
                    if ($balanceType == 'cancelled_with_payment' && $actualBalance > 0.50) {
                        $analysis['issues'][] = "Cancelada con {$actualBalance}€ sin procesar";
                    }
                    break;

                case 3: // PARCIALMENTE CANCELADA
                    $analysis['issues'][] = "Cancelación parcial con discrepancia de balance: {$balanceDiscrepancy}€";
                    break;
            }
        }

        Log::debug("detectDiscrepancies resultado Booking {$booking->id}", [
            'balance_type' => $balanceType,
            'actual_balance' => $actualBalance,
            'expected_balance' => $expectedBalance,
            'has_real_discrepancy' => $hasRealDiscrepancy,
            'discrepancy_amount' => $analysis['discrepancy_amount']
        ]);
    }
        /**
     * Calcular cantidad pendiente - VERSIÓN FINAL con no_refund
     * REEMPLAZAR el método calculatePendingAmount en StatisticsController.php
     */
    private function calculatePendingAmount($booking, &$analysis)
    {
        $paidTotal = $analysis['payments']['total_paid'];
        $vouchersUsed = $analysis['vouchers']['total_used'];
        $vouchersRefunded = $analysis['vouchers']['total_refunded'];
        $refunded = $analysis['refunds']['total_refunded'];
        $noRefundPostPayment = $analysis['refunds']['breakdown']['no_refund'] ?? 0;
        $calculatedTotal = $analysis['calculated_total'];

        // ✅ USAR LA MISMA LÓGICA QUE detectDiscrepancies
        $pendingAmount = 0;

        switch ($booking->status) {
            case 1: // ACTIVA
                $actualBalance = $paidTotal + $vouchersUsed - $vouchersRefunded - $refunded - $noRefundPostPayment;
                $pendingAmount = max(0, $calculatedTotal - $actualBalance);
                break;

            case 2: // CANCELADA TOTALMENTE
                $totalReceived = $paidTotal + $vouchersUsed;

                if ($totalReceived > 0.01) {
                    $totalProcessed = $refunded + $vouchersRefunded + $noRefundPostPayment;
                    $pendingAmount = max(0, $totalReceived - $totalProcessed);
                }
                break;

            case 3: // PARCIALMENTE CANCELADA
                $actualBalance = $paidTotal + $vouchersUsed - $vouchersRefunded - $refunded - $noRefundPostPayment;
                $pendingAmount = max(0, $calculatedTotal - $actualBalance);
                break;
        }

        $analysis['pending_amount'] = round($pendingAmount, 2);

        $analysis['balance_info'] = [
            'total_paid' => $paidTotal,
            'vouchers_used' => $vouchersUsed,
            'vouchers_refunded' => $vouchersRefunded,
            'refunded' => $refunded,
            'no_refund_post_payment' => $noRefundPostPayment,
            'calculated_total' => $calculatedTotal,
            'booking_status' => $booking->status,
            'pending_calculation_method' => 'same_as_detectDiscrepancies'
        ];

        Log::debug("calculatePendingAmount CORREGIDO Booking {$booking->id}", [
            'booking_status' => $booking->status,
            'pending_amount' => $pendingAmount,
            'calculated_total' => $calculatedTotal
        ]);
    }
    private function analyzeBookingHistory($booking, &$analysis)
    {
        $analysis['booking_history'] = $booking->bookingLogs->map(function($log) {
            return [
                'action' => $log->action,
                'description' => $log->description,
                'date' => $log->created_at->format('Y-m-d H:i:s'),
                'user_id' => $log->user_id
            ];
        })->toArray();
    }

    private function determinePaymentMethod($payment, $booking)
    {
        $notes = strtolower($payment->notes ?? '');

        if ($payment->payrexx_reference) {
            if ($booking->payment_method_id == Booking::ID_BOUKIIPAY) {
                return 'boukii';
            } else {
                return 'online';
            }
        }

        if (in_array($notes, ['cash', 'efectivo', 'contado'])) {
            return 'cash';
        }

        if (in_array($notes, ['card', 'tarjeta', 'tpv'])) {
            return 'card';
        }

        if (in_array($notes, ['transfer', 'transferencia', 'bank'])) {
            return 'transfer';
        }

        // Fallback basado en payment_method_id
        switch ($booking->payment_method_id) {
            case Booking::ID_CASH:
                return 'cash';
            case Booking::ID_BOUKIIPAY:
                return 'boukii';
            case Booking::ID_ONLINE:
                return 'online';
            default:
                return 'other';
        }
    }

    /**
     * @OA\Get(
     *      path="/admin/statistics/monitors/active",
     *      summary="Get collective bookings for season",
     *      tags={"Admin"},
     *      description="Get collective bookings for the specified season or date range",
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          description="Start date to filter bookings",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              format="date"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          description="End date to filter bookings",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              format="date"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      ref="#/components/schemas/Booking"
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function getActiveMonitors(Request $request): JsonResponse
    {
        $schoolId = $this->getSchool($request)->id;

        $today = Carbon::now()->format('Y-m-d'); // Obtiene la fecha actual en formato YYYY-MM-DD

        $season = Season::whereDate('start_date', '<=', $today) // Fecha de inicio menor o igual a hoy
        ->whereDate('end_date', '>=', $today)   // Fecha de fin mayor o igual a hoy
        ->first();

        // Utiliza start_date y end_date de la request si están presentes, sino usa las fechas de la temporada
        $startDate = $request->start_date ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;
        $onlyWeekends = $request->boolean('onlyWeekends', false); // default false

        // Obtener los monitores totales filtrados por escuela y deporte si se proporciona
        $totalMonitorsQuery = Monitor::whereHas('monitorsSchools', function ($query) use ($request, $schoolId) {
            $query->where('school_id', $schoolId)->where('active_school', 1);
        });

        if ($request->filled('sport_id')) {
            $totalMonitorsQuery->whereHas('monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($request) {
                $query->where('sport_id', $request->sport_id);
            });
        }

        $totalMonitors = $totalMonitorsQuery->pluck('id'); // Obtener solo los IDs de los monitores

        // Obtener los monitores ocupados por las reservas
        $bookingUsersCollective = BookingUser::where('school_id', $schoolId)
            ->where('status', 1)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            }, function ($query) {
                return $query->where('monitor_id', '!=', null);
            })
            ->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('course', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->pluck('monitor_id');

        // Obtener los monitores no disponibles y filtrarlos por los IDs de los monitores totales
        $nwds = MonitorNwd::where('school_id', $schoolId)
            ->where('user_nwd_subtype_id', 2)
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            }, function ($query) {
                return $query->where('monitor_id', '!=', null);
            })
            ->whereBetween('start_date', [$startDate, $endDate])
            ->whereIn('monitor_id', $totalMonitors)
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->pluck('monitor_id');

        $activeMonitors = $bookingUsersCollective->merge($nwds)->unique()->count();

        return $this->sendResponse(['total' => $totalMonitors->count(), 'busy' => $activeMonitors],
            'Active monitors of the season retrieved successfully');
    }

    private function calculateGroupedBookingUsersPrice($bookingGroupedUsers): array
    {
        $course = optional($bookingGroupedUsers->first())->course;
        if (!$course) {
            return [
                'basePrice' => 0,
                'extrasPrice' => 0,
                'insurancePrice' => 0,
                'totalPrice' => 0,
            ];
        }

        $basePrice = 0;
        $extrasPrice = 0;
        $insurancePrice = 0;
        $totalPrice = 0;

        if ($course->course_type === 2) {
            // PRIVADOS — agrupar por clase
            $grouped = $bookingGroupedUsers->groupBy(function ($bookingUser) {
                return $bookingUser->date . '|' . $bookingUser->hour_start . '|' . $bookingUser->hour_end . '|' .
                    $bookingUser->monitor_id . '|' . $bookingUser->group_id . '|' . $bookingUser->booking_id;
            });

            foreach ($grouped as $group) {
                $res = $this->calculateTotalPrice($group->first());
                $basePrice += $res['basePrice'];
                $extrasPrice += $res['extrasPrice'];
                $insurancePrice += $res['cancellationInsurancePrice'];
                $totalPrice += $res['totalPrice'];
            }

        } else {
            // COLECTIVOS — agrupar por cliente y calcular por cada uno
            $clientGroups = $bookingGroupedUsers->groupBy('client_id');

            foreach ($clientGroups as $clientBookingUsers) {
                $res = $this->calculateTotalPrice($clientBookingUsers->first(), $clientBookingUsers);
                $basePrice += $res['basePrice'];
                $extrasPrice += $res['extrasPrice'];
                $insurancePrice += $res['cancellationInsurancePrice'];
                $totalPrice += $res['totalPrice'];
            }
        }

        return compact('basePrice', 'extrasPrice', 'insurancePrice', 'totalPrice');
    }


    private function assignVoucherAmount($booking, $bookingGroupedUsers, &$courseSummary, $groupPrice)
    {
        $vouchersLogs = $booking->vouchersLogs ?? collect(); // Asegúrate de eager load

        $totalVoucherAmount = $vouchersLogs->sum('amount');

        if ($totalVoucherAmount <= 0) return;

        // Prorratear según precio del grupo respecto al total de booking
        $totalCalculated = $booking->bookingUsers->sum(fn ($bu) => $this->calculateTotalPrice($bu)['totalPrice']);
        $proportion = $totalCalculated > 0 ? ($groupPrice / $totalCalculated) : 0;

        $voucherAmount = round($totalVoucherAmount * $proportion, 2);

        $courseSummary['vouchers'] += $voucherAmount;
    }

    private function getPaymentMethods($booking, $bookingGroupedUsers, &$courseSummary, $groupPrice)
    {
        if ($booking->payments->isEmpty()) {
            $courseSummary['no_paid'] += 1;
            return;
        }

        // Calcular el total de pagos válidos (no refunds)
        $validPayments = $booking->payments->filter(fn ($p) => !in_array($p->status, ['refund', 'partial_refund']) && !str_contains(strtolower($p->notes ?? ''), 'voucher'));
        $totalPaid = $validPayments->sum('amount');

        if ($totalPaid <= 0) {
            $courseSummary['no_paid'] += 1;
            return;
        }

        // Repartimos el total calculado de este grupo en proporción a los pagos reales
        foreach ($validPayments as $payment) {
            $note = strtolower($payment->notes ?? '');
            $hasPayrexx = !empty($payment->payrexx_reference);

            $proportion = $payment->amount / $totalPaid;
            $amount = round($groupPrice * $proportion, 2); // Este es el valor exacto que sumaremos

            if ($note === 'cash' || str_contains($note, 'efectivo')) {
                $courseSummary['cash'] += $amount;
            } elseif ($hasPayrexx) {
                if ($booking->payment_method_id === Booking::ID_BOUKIIPAY) {
                    if ($booking->created_from === 'web') {
                        $courseSummary['boukii_web'] += $amount;
                    } else {
                        $courseSummary['boukii'] += $amount;
                    }
                } else {
                    $courseSummary['online'] += $amount;
                }
            } elseif ($note === 'transferencia') {
                $courseSummary['transfer'] += $amount;
            } elseif ($note === 'card' || $note === 'tarjeta') {
                $courseSummary['other'] += $amount;
            } else {
                // Fallback
                switch ($booking->payment_method_id) {
                    case Booking::ID_CASH:
                        $courseSummary['cash'] += $amount;
                        break;
                    case Booking::ID_BOUKIIPAY:
                        $courseSummary['boukii'] += $amount;
                        break;
                    case Booking::ID_ONLINE:
                        $courseSummary['online'] += $amount;
                        break;
                    case Booking::ID_OTHER:
                        $courseSummary['other'] += $amount;
                        break;
                    default:
                        $courseSummary['other'] += $amount;
                }
            }
        }
    }


    public function getCoursesWithDetails(Request $request)
    {
        $schoolId = $this->getSchool($request)->id;
        $today = Carbon::now()->format('Y-m-d');

        $season = Season::whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        $startDate = $request->start_date ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;
        $sportId = $request->input('sport_id');
        $courseType = $request->input('course_type');
        $onlyWeekends = $request->boolean('onlyWeekends', false);

        $bookingusersReserved = BookingUser::whereBetween('date', [$startDate, $endDate])
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2)
                    ->where(function ($q) {
                        $q->whereHas('payments', fn($p) => $p->where('status', 'paid'))
                            ->orWhereHas('vouchersLogs');
                    });
            })
            ->where('status', 1)
            ->where('school_id', $request->school_id)
            ->with('booking.vouchersLogs', 'booking.payments')
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->get();

        $result = [];
        $monitorsGrouped = $this->getGroupedMonitors($schoolId, $request->monitor_id, $request->sport_id);

        foreach ($bookingusersReserved->groupBy('course_id') as $courseId => $bookingCourseUsers) {
            $course = Course::find($courseId);
            if (!$course) continue;

            $payments = [
                'cash' => 0,
                'other' => 0,
                'boukii' => 0,
                'boukii_web' => 0,
                'online' => 0,
                'refunds' => 0,
                'vouchers' => 0,
                'no_paid' => 0,
                'web' => 0,
                'admin' => 0,
            ];

            $courseTotal = 0;
            $extrasByCourse = 0;
            $cancellationInsuranceByCourse = 0;
            $underpaidBookings = [];

            $availability = $this->getCourseAvailability($course, $monitorsGrouped, $startDate, $endDate, $onlyWeekends);

            foreach ($bookingCourseUsers->groupBy('booking_id') as $bookingId => $bookingGroupedUsers) {
                $booking = $bookingGroupedUsers->first()->booking;
                if ($booking->status == 2) continue;

// 1. Calcular pagos reales
                $realPayments = $booking->payments()
                    ->whereNotIn('status', ['refund', 'partial_refund', 'no_refund'])
                    ->sum('amount');

                $refunds = $booking->payments()
                    ->whereIn('status', ['refund', 'partial_refund', 'no_refund'])
                    ->sum('amount');

                $payments['refunds'] += $refunds;

                $voucherLogs = $booking->vouchersLogs ?? collect();

                $totalVoucherAmount = abs($voucherLogs->sum('amount'));

// 2. Calcular precios por bookingUsers agrupados
                $calculated = $this->calculateGroupedBookingUsersPrice($bookingGroupedUsers);


                // Todos los booking_users válidos de esta reserva
                $allValidBookingUsers = $booking->bookingUsers->where('status', 1)->whereBetween('date', [$startDate, $endDate]);

// BookingUsers que estás procesando ahora (solo este curso)
                $currentIds = $bookingGroupedUsers->pluck('id')->toArray();

// BookingUsers que no son de este curso (otros cursos dentro de la misma reserva)
                $otherBookingUsers = $allValidBookingUsers->filter(function ($bu) use ($currentIds) {
                    return !in_array($bu->id, $currentIds);
                });

                $fullBookingTotal = $calculated['totalPrice'];

                if ($otherBookingUsers->count() > 0) {
                    $otherTotal = 0;
                    $otherGrouped = $otherBookingUsers->groupBy('course_id');

                    foreach ($otherGrouped as $group) {
                        $flag = false;
                        if($bookingId == 5053) {
                           $flag = true;
                        }
                        $otherTotal += $this->calculateGroupedBookingUsersPrice($group, $flag)['totalPrice'];

                    }

                    $fullBookingTotal = $calculated['totalPrice'] + $otherTotal;

                }

                // 3. Check discrepancia
                $totalReal = $realPayments + $totalVoucherAmount - $refunds;

                // Ignorar si la diferencia es solo por un reembolso total del mismo valor
                if (round($totalReal, 2) < round($fullBookingTotal, 2)
                    && !(round($realPayments, 2) === round($fullBookingTotal, 2) && round($refunds, 2) === round($realPayments, 2))) {

                    Log::debug('Error en el calculo de pagos', [
                        '❌ Discrepancia en reserva' => $bookingId,
                        'Pagado real' => $realPayments,
                        'VOucher real' => $totalVoucherAmount,
                        'REfund real' => $refunds,
                        'Total real' => $totalReal,
                        'Calculado' => $calculated,
                        'Booking' => $booking->id,
                        'Curso' => $bookingGroupedUsers->first()?->course_id,
                        'Tipo' => $bookingGroupedUsers->first()?->course?->course_type,
                        'Flex' => $bookingGroupedUsers->first()?->course?->is_flexible,
                        'BookingUsers' => $bookingGroupedUsers->pluck('id'),
                        'Fechas' => $bookingGroupedUsers->pluck('date'),
                        'Precios individuales' => $bookingGroupedUsers->mapWithKeys(fn($bu) => [$bu->id => $this->calculateTotalPrice($bu)]),
                    ]);
                }

                // 4. Clasificación de métodos de pago y vouchers
                $this->getPaymentMethods($booking, $bookingGroupedUsers, $payments, $calculated['totalPrice']);
                $this->assignVoucherAmount($booking, $bookingGroupedUsers, $payments, $calculated['totalPrice']);

                // 5. Extras y seguros
                $extrasByCourse += $calculated['extrasPrice'];
                $cancellationInsuranceByCourse += $calculated['insurancePrice'];
                $courseTotal += $calculated['totalPrice'];

                // 6. Underpaid check
                if ($booking->paid) {
                    $amountPaidForCheck = $totalReal;
/*                    if ($booking->vouchersLogs()->exists()) {
                        $amountPaidForCheck += abs($booking->vouchersLogs()->sum('amount'));
                    }*/

                    if ($amountPaidForCheck + 0.5 < $calculated['totalPrice']) {
                        $underpaidBookings[] = [
                            'booking_id' => $booking->id,
                            'client_name' => $booking->client->full_name ?? '',
                            'paxes' => $bookingGroupedUsers->groupBy('client_id')->count(),
                            'should_pay' => $calculated['totalPrice'],
                            'paid' => $amountPaidForCheck,
                            'difference' => round($calculated['totalPrice'] - $amountPaidForCheck, 2),
                        ];
                    }
                }
            }
            // ✅ CONTAR PLAZAS POR SOURCE
            $bookingUsersGrouped = $course->bookingUsersActive->groupBy('client_id');
            foreach ($bookingUsersGrouped as $clientBookingUsers) {
                $booking = $clientBookingUsers->first()->booking;
                $source = $booking->source;
                $bookingUsersCount = $clientBookingUsers->count();
                $bookingUsersCount = !$course->is_flexible ? $bookingUsersCount / max(1, $course->courseDates->count()) : $bookingUsersCount;

                if (isset($payments[$source])) {
                    $payments[$source] += $bookingUsersCount;
                } else {
                    $payments[$source] = $bookingUsersCount;
                }
            }

            // ✅ RESULTADO FINAL
            $settings = json_decode($this->getSchool($request)->settings);
            $currency = $settings->taxes->currency ?? 'CHF';
            $totalCostFromPayments =
                $payments['cash'] +
                $payments['other'] +
                $payments['boukii'] +
                $payments['boukii_web'] +
                $payments['online'] +
                $payments['vouchers'] -
                $payments['refunds'];

            $result[] = [
                'underpaid_bookings' => $underpaidBookings,
                'underpaid_count' => collect($underpaidBookings)->sum('difference'),
                'course_id' => $course->id,
                'icon' => $course->icon,
                'name' => $course->name,
                'total_places' => $course->course_type == 1 ?
                    round($availability['total_places']) : 'NDF',
                'booked_places' => $course->course_type == 1 ?
                    round($availability['total_reservations_places']) : round($payments['web']) + round($payments['admin']),
                'available_places' => $course->course_type == 1 ?
                    round($availability['total_available_places']) : 'NDF',
                'cash' => round($payments['cash'], 2),
                'other' => round($payments['other'], 2),
                'boukii' => round($payments['boukii'], 2),
                'boukii_web' => round($payments['boukii_web'], 2),
                'online' => round($payments['online'], 2),
                'extras' => round($extrasByCourse, 2),
                'insurance' => round($cancellationInsuranceByCourse, 2),
                'refunds' => round($payments['refunds'], 2),
                'vouchers' => round($payments['vouchers'], 2),
                'no_paid' => round($payments['no_paid'], 2),
                'web' => round($payments['web'], 2),
                'admin' => round($payments['admin'],2 ),
                'currency' => $currency,
                'total_cost' => round($totalCostFromPayments, 2),
                'total_cost_expected' => round($courseTotal, 2), // ✅ Ahora debería funcionar para privados
                'difference_vs_expected' => round($courseTotal - $totalCostFromPayments, 2)
            ];
        }

        // ✅ TOTALES
        $totals = [
            'name' => 'TOTAL',
            'cash' => 0,
            'other' => 0,
            'boukii' => 0,
            'boukii_web' => 0,
            'online' => 0,
            'no_paid' => 0,
            'vouchers' => 0,
            'extras' => 0,
            'insurance' => 0,
            'refunds' => 0,
            'underpaid_count' => 0,
            'total_cost' => 0,
            'total_cost_expected' => 0,
            'difference_vs_expected' => 0
        ];


        foreach ($result as $row) {
            foreach ($totals as $key => $val) {
                if ($key === 'name') continue;
                $totals[$key] += round($row[$key] ?? 0, 2);
            }
        }

// 🔒 Aplicar round final por seguridad
        foreach ($totals as $key => $val) {
            if ($key === 'name') continue;
            $totals[$key] = round($val, 2);
        }

        $result[] = $totals;
        return $this->sendResponse($result, 'Total worked hours by sport retrieved successfully');
    }

    public function getCoursesWithDetails2(Request $request)
    {
        $schoolId = $request->user()->schools[0]->id;
        $start = $request->get('start_date') ?? Carbon::now()->startOfMonth()->toDateString();
        $end = $request->get('end_date') ?? Carbon::now()->endOfMonth()->toDateString();

        $bookingUsers = BookingUser::whereHas('courseDate', function ($q) use ($start, $end) {
            $q->whereBetween('date', [$start, $end]);
        })
            ->whereHas('booking', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            ->with(['course', 'booking', 'booking.payments', 'booking.clientMain', 'courseDate'])
            ->get();

        $coursesSummary = [];

        foreach ($bookingUsers as $bu) {
            $course = $bu->course;
            $booking = $bu->booking;

            if (!isset($coursesSummary[$course->id])) {
                $coursesSummary[$course->id] = $this->initCourseSummary($course);
            }

            $summary = &$coursesSummary[$course->id];

            // Calcular precios detallados
            $priceData = $this->calculateTotalPrice($bu);
            $summary['total_cost'] += $priceData['totalPrice'];
            $summary['extras'] += $priceData['extrasPrice'];
            $summary['insurance'] += $priceData['cancellationInsurancePrice'];

            // Origen de la reserva
            if ($booking->created_from === 'admin') $summary['admin']++;
            if ($booking->created_from === 'web') $summary['web']++;

            // Calcular pagos proporcionados
            $totalBuCount = $booking->bookingUsers->count();
            $relevantBuCount = $booking->bookingUsers->where('course_id', $course->id)->count();
            $proportion = $relevantBuCount / $totalBuCount;

            foreach ($booking->payments as $payment) {
                $this->assignToPaymentMethod($summary, $payment, $payment->amount * $proportion);
            }

            // Comprobar pagos insuficientes
            $paid = $booking->payments->sum('amount') * $proportion;
            $shouldPay = $priceData['totalPrice'];
            $difference = round($shouldPay - $paid, 2);

            if ($difference > 0) {
                $summary['underpaid_count'] += $difference;
                $summary['underpaid_bookings'][] = [
                    'booking_id' => $booking->id,
                    'client_name' => $booking->client->full_name ?? '',
                    'paxes' => $booking->bookingUsers->count(),
                    'should_pay' => $shouldPay,
                    'paid' => $paid,
                    'difference' => $difference,
                ];
            }

            $summary['booked_places']++;
        }

        $totals = $this->calculateTotalRow($coursesSummary);
        $coursesSummary[] = $totals;

        return response()->json(array_values($coursesSummary));
    }

    private function initCourseSummary($course)
    {
        return [
            'course_id' => $course->id,
            'name' => $course->name,
            'icon' => $course->icon,
            'currency' => 'CHF',
            'total_places' => $course->capacity ?? 'NDF',
            'booked_places' => 0,
            'available_places' => $course->capacity ?? 'NDF',
            'cash' => 0,
            'card' => 0,
            'transfer' => 0,
            'other' => 0,
            'boukii' => 0,
            'boukii_web' => 0,
            'online' => 0,
            'vouchers' => 0,
            'insurance' => 0,
            'refunds' => 0,
            'extras' => 0,
            'no_paid' => 0,
            'underpaid_count' => 0,
            'underpaid_bookings' => [],
            'admin' => 0,
            'web' => 0,
            'total_cost' => 0,
        ];
    }

    private function assignToPaymentMethod(&$summary, $payment, $amount)
    {
        $method = strtolower($payment->notes ?? 'other');

        if ($payment->status === 'paid') {
            if ($payment->payrexx_reference) {
                if ($payment->booking->payment_method_id == 2) {
                    $summary['boukii'] += $amount;
                } elseif ($payment->booking->payment_method_id == 3) {
                    $summary['boukii_web'] += $amount;
                }
                $summary['online'] += $amount;
            } else {
                $summary[$method] = ($summary[$method] ?? 0) + $amount;
            }
            $summary['total_cost'] += $amount;
        }

        if (in_array($payment->status, ['refund', 'partial_refund'])) {
            $summary['refunds'] += $amount;
            $summary['total_cost'] -= $amount;
        }
    }

    private function calculateTotalRow($coursesSummary)
    {
        $fields = ['cash', 'card', 'transfer', 'other', 'boukii', 'boukii_web', 'online', 'vouchers', 'insurance',
            'refunds', 'extras', 'no_paid', 'underpaid_count', 'total_cost'];

        $total = ['name' => 'TOTAL'];
        foreach ($fields as $field) {
            $total[$field] = array_sum(array_column($coursesSummary, $field));
        }

        return $total;
    }

    private function calculateTotalForCourse($booking, $courseId)
    {
        $result = [
            'total' => 0,
            'extras' => 0,
            'insurance' => 0,
            'base' => 0,
        ];

        $booking->bookingUsers
            ->where('course_id', $courseId)
            ->each(function ($bookingUser) use (&$result) {
                $priceData = $this->calculateTotalPrice($bookingUser);
                $result['total'] += $priceData['totalPrice'];
                $result['extras'] += $priceData['extrasPrice'];
                $result['insurance'] += $priceData['cancellationInsurancePrice'];
                $result['base'] += $priceData['priceWithoutExtras'];
            });

        return $result;
    }

    /**
     * CORRECCIÓN: calculateTotalPrice
     * NO calcular seguro aquí, solo en nivel superior
     */
    function calculateTotalPrice($bookingUser, $bookingGroupedUsers = null)
    {
        $courseType = $bookingUser->course->course_type;
        $isFlexible = $bookingUser->course->is_flexible;
        $totalPrice = 0;

        if ($courseType == 1) { // Colectivo
            if ($isFlexible) {
                $totalPrice = $this->calculateFlexibleCollectivePrice($bookingUser, $bookingGroupedUsers);
            } else {
                $totalPrice = $this->calculateFixedCollectivePrice($bookingUser);
            }
        } elseif ($courseType == 2) { // Privado
            if ($isFlexible) {
                $totalPrice = $this->calculatePrivatePrice($bookingUser, $bookingUser->course->price_range);
            } else {
                $totalPrice = $bookingUser->course->price;
            }
        } else {
            Log::debug("Invalid course type: $courseType");
            return [
                'basePrice' => 0,
                'totalPrice' => 0,
                'extrasPrice' => 0,
                'cancellationInsurancePrice' => 0,
            ];
        }

        // Calcular extras
        $extrasPrice = $this->calculateExtrasPrice($bookingUser);
        $totalPrice += $extrasPrice;

        // ✅ CRÍTICO: NO calcular seguro aquí - se hace en calculateRealPrices
        $cancellationInsurancesPrice = 0;

        return [
            'basePrice' => $totalPrice - $extrasPrice,
            'totalPrice' => $totalPrice,
            'extrasPrice' => $extrasPrice,
            'cancellationInsurancePrice' => $cancellationInsurancesPrice, // Siempre 0
        ];
    }

    function calculateFixedCollectivePrice($bookingUser)
    {
        $course = $bookingUser->course;

        // Agrupar BookingUsers por participante (course_id, participant_id)
        $participants = BookingUser::select(
            'client_id',
            DB::raw('COUNT(*) as total_bookings'), // Contar cuántos BookingUsers tiene cada participante
            DB::raw('SUM(price) as total_price') // Sumar el precio total por participante
        )
            ->where('course_id', $course->id)
            ->where('client_id', $bookingUser->client_id)
            ->groupBy('client_id')
            ->get();


        // Tomar el precio del curso para cada participante
        return count($participants) ? $course->price : 0;
    }

    function calculateFlexibleCollectivePrice($bookingUser, $bookingGroupedUsers = null)
    {
        $course = $bookingUser->course;

        // Filtrar fechas solo del cliente actual
        $dates = $bookingGroupedUsers
            ?  $bookingGroupedUsers
                ->pluck('date')
                ->unique()
                ->sort()
                ->values()
            : BookingUser::where('course_id', $course->id)
                ->where('status', '!=', 2)
                ->where('client_id', $bookingUser->client_id)
                ->where('booking_id', $bookingUser->booking_id)
                ->pluck('date')
                ->unique()
                ->sort()
                ->values();

        $totalPrice = 0;

        $discounts = is_array($course->discounts) ? $course->discounts : json_decode($course->discounts, true);
        //Log::debug('Dates de la booking cliente: '.$bookingUser->booking_id, [json_encode($dates->all())]);
        foreach ($dates as $index => $date) {
            $price = $course->price;

            if (!empty($discounts)) {
                foreach ($discounts as $discount) {
                    if ($index + 1 == $discount['day']) {
                        $price -= ($price * $discount['reduccion'] / 100);
                        break;
                    }
                }
            }

            $totalPrice += $price ;
        }

        return round($totalPrice, 2);
    }

    function calculatePrivatePrice($bookingUser, $priceRange)
    {
        $course = $bookingUser->course;
        $groupId = $bookingUser->group_id;

        // Agrupar BookingUsers por fecha, hora y monitor
        $groupBookings = BookingUser::where('course_id', $course->id)
            ->where('date', $bookingUser->date)
            ->where('hour_start', $bookingUser->hour_start)
            ->where('hour_end', $bookingUser->hour_end)
            ->where('monitor_id', $bookingUser->monitor_id)
            ->where('group_id', $groupId)
            ->where('booking_id', $bookingUser->booking_id)
            ->where('school_id', $bookingUser->school_id)
            ->where('status', 1)
            ->count();

        $duration = Carbon::parse($bookingUser->hour_end)->diffInMinutes(Carbon::parse($bookingUser->hour_start));
        $interval = $this->getIntervalFromDuration($duration); // Función para mapear duración al intervalo (e.g., "1h 30m").


        // Buscar el precio en el price range
        $priceForInterval = collect($priceRange)->firstWhere('intervalo', $interval);
        $pricePerParticipant = $priceForInterval[$groupBookings] ?? null;

        if (!$pricePerParticipant) {
            Log::debug("Precio no definido curso $course->id para $groupBookings participantes en intervalo $interval");
            return 0;
        }

        // Calcular extras
        $extraPrices = $bookingUser->bookingUserExtras->sum(function ($extra) {
            return $extra->price;
        });

        // Calcular precio total
        $totalPrice = $pricePerParticipant + $extraPrices;

        return $totalPrice;
    }
    function getIntervalFromDuration($duration)
    {
        $mapping = [
            15 => "15m",
            30 => "30m",
            45 => "45m",
            60 => "1h",
            75 => "1h 15m",
            90 => "1h 30m",
            120 => "2h",
            180 => "3h",
            240 => "4h",
        ];

        return $mapping[$duration] ?? null;
    }

    function calculateExtrasPrice($bookingUser)
    {
        $extras = $bookingUser->bookingUserExtras; // Relación con BookingUserExtras

        $totalExtrasPrice = 0;
        foreach ($extras as $extra) {
            //  Log::debug('extra price:'. $extra->courseExtra->price);
            $extraPrice = $extra->courseExtra->price ?? 0;
            $totalExtrasPrice += $extraPrice;
        }

        return $totalExtrasPrice;
    }

    public function getTotalWorkedHoursBySport(Request $request): JsonResponse
    {
        $schoolId = $this->getSchool($request)->id;

        $today = Carbon::now()->format('Y-m-d'); // Obtiene la fecha actual en formato YYYY-MM-DD

        $season = Season::whereDate('start_date', '<=', $today) // Fecha de inicio menor o igual a hoy
        ->whereDate('end_date', '>=', $today)   // Fecha de fin mayor o igual a hoy
        ->first();

        // Utiliza start_date y end_date de la request si están presentes, sino usa las fechas de la temporada
        $startDate = $request->start_date ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;
        $onlyWeekends = $request->boolean('onlyWeekends', false); // default false

        // Obtener monitor_id si está presente en la request
        $monitorId = $request->monitor_id;
        $sportId = $request->sport_id; // Obtener sport_id si está presente en la request

        $hoursBySport = $this->calculateTotalWorkedHoursBySport($schoolId, $startDate, $endDate, $monitorId, $sportId, $onlyWeekends);

        return $this->sendResponse($hoursBySport, 'Total worked hours by sport retrieved successfully');
    }

    private function calculateTotalWorkedHoursBySport($schoolId, $startDate, $endDate, $monitorId = null, $sportId = null, $onlyWeekends=false)
    {
        $bookingUsersQuery = BookingUser::with('course.sport')
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->where('status', 1)
            ->where('school_id', $schoolId)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends());

        // Aplicar filtro por monitor_id si está presente
        if ($monitorId) {
            $bookingUsersQuery->where('monitor_id', $monitorId);
        }

        // Aplicar filtro por sport_id si está presente
        if ($sportId) {
            $bookingUsersQuery->whereHas('course', function ($query) use ($sportId) {
                $query->where('sport_id', $sportId);
            });
        }

        $bookingUsers = $bookingUsersQuery->get();

        $hoursBySport = [];

        foreach ($bookingUsers as $bookingUser) {
            if (!$bookingUser->course) {
                \Log::warning("BookingUser sin curso", ['booking_user_id' => $bookingUser->id]);
                continue; // Salta este registro para evitar el error
            }

            $sportId = $bookingUser->course->sport_id;
            $duration = $this->convertDurationToHours($bookingUser->duration);

            if (!isset($hoursBySport[$sportId])) {
                $hoursBySport[$sportId]['hours'] = 0;
                $hoursBySport[$sportId]['sport'] = $bookingUser->course->sport;
            }

            $hoursBySport[$sportId]['hours'] += $duration;
        }

        return $hoursBySport;
    }


    public function getTotalWorkedHours(Request $request): JsonResponse
    {
        $schoolId = $this->getSchool($request)->id;

        $today = Carbon::now()->format('Y-m-d'); // Obtiene la fecha actual en formato YYYY-MM-DD

        $season = Season::whereDate('start_date', '<=', $today) // Fecha de inicio menor o igual a hoy
        ->whereDate('end_date', '>=', $today)   // Fecha de fin mayor o igual a hoy
        ->first();

        // Utiliza start_date y end_date de la request si están presentes, sino usa las fechas de la temporada
        $startDate = $request->start_date ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;
        $onlyWeekends = $request->boolean('onlyWeekends', false); // default false

        $totalWorkedHours = $this->calculateTotalWorkedHours($schoolId, $startDate, $endDate, $season,
            $request->monitor_id, $request->sport_id, $onlyWeekends);

        return $this->sendResponse($totalWorkedHours, 'Total worked hours retrieved successfully');
    }

    public function getBookingUsersByDateRange(Request $request)
    {
        $schoolId = $this->getSchool($request)->id;
        $monitorId = $request->monitor_id ?? null;

        $today = Carbon::now()->format('Y-m-d'); // Obtiene la fecha actual en formato YYYY-MM-DD

        $season = Season::whereDate('start_date', '<=', $today) // Fecha de inicio menor o igual a hoy
        ->whereDate('end_date', '>=', $today)   // Fecha de fin mayor o igual a hoy
        ->first();

        // Utiliza start_date y end_date de la request si están presentes, sino usa las fechas de la temporada
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::parse($season->start_date);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::parse($season->end_date);
        $onlyWeekends = $request->boolean('onlyWeekends', false); // default false

        // Determinar el intervalo de agrupación
        $interval = $this->determineInterval($startDate, $endDate);

        // Generar el rango de fechas completas basado en el intervalo
        $dateRange = $this->generateDateRange($startDate, $endDate, $interval);

        // Obtener y agrupar los datos
        $bookings = BookingUser::with('course')
            ->where('school_id', $schoolId)
            ->when($monitorId, function ($query) use ($monitorId) {
                return $query->where('monitor_id', $monitorId);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->get();

        // Agrupar los datos por el intervalo determinado y luego por el tipo de curso
        $groupedData = $bookings->groupBy(function ($booking) use ($interval) {
            return Carbon::parse($booking->date)->format($interval);
        })->map(function ($group) {
            return $group->groupBy('course.course_type')->map->count();
        });

        // Rellenar el rango de fechas con valores por defecto si no hay datos
        $data = [];
        foreach ($dateRange as $date) {
            $data[$date] = $groupedData->get($date, collect([1 => 0, 2 => 0, 3 => 0]));
        }

        return $this->sendResponse($data, 'Booking users retrieved successfully');
    }

    public function getBookingUsersBySport(Request $request)
    {
        $schoolId = $this->getSchool($request)->id;
        $monitorId = $request->monitor_id ?? null;

        $today = Carbon::now()->format('Y-m-d'); // Obtiene la fecha actual en formato YYYY-MM-DD

        $season = Season::whereDate('start_date', '<=', $today) // Fecha de inicio menor o igual a hoy
        ->whereDate('end_date', '>=', $today)   // Fecha de fin mayor o igual a hoy
        ->first();

        // Utiliza start_date y end_date de la request si están presentes, sino usa las fechas de la temporada
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::parse($season->start_date);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::parse($season->end_date);
        $onlyWeekends = $request->boolean('onlyWeekends', false); // default false

        // Determinar el intervalo de agrupación
        $interval = $this->determineInterval($startDate, $endDate);

        // Generar el rango de fechas completas basado en el intervalo
        $dateRange = $this->generateDateRange($startDate, $endDate, $interval);

        // Obtener y agrupar los datos
        $bookings = BookingUser::with('course.sport')
            ->where('school_id', $schoolId)
            ->when($monitorId, function ($query) use ($monitorId) {
                return $query->where('monitor_id', $monitorId);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->get();

        // Agrupar los datos por el intervalo determinado y luego por el deporte
        $groupedData = $bookings->groupBy(function ($booking) use ($interval) {
            return Carbon::parse($booking->date)->format($interval);
        })->map(function ($group) {
            return $group->groupBy('course.sport.name')->map->count();
        });

        // Rellenar el rango de fechas con valores por defecto si no hay datos
        $data = [];
        foreach ($dateRange as $date) {
            $data[$date] = $groupedData->get($date, collect([])); // Suponiendo que los deportes se añaden dinámicamente
        }

        return $this->sendResponse($data, 'Booking users by sport retrieved successfully');
    }


    private function determineInterval(Carbon $startDate, Carbon $endDate)
    {
        $daysDiff = $endDate->diffInDays($startDate);

        if ($daysDiff <= 30) {
            return 'Y-m-d'; // Agrupar por día
        } elseif ($daysDiff <= 180) {
            return 'Y-W'; // Agrupar por semana
        } else {
            return 'Y-m'; // Agrupar por mes
        }
    }

    private function generateDateRange(Carbon $startDate, Carbon $endDate, $interval)
    {
        $dateRange = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateRange[] = $currentDate->format($interval);
            switch ($interval) {
                case 'Y-m-d':
                    $currentDate->addDay();
                    break;
                case 'Y-W':
                    $currentDate->addWeek();
                    break;
                case 'Y-m':
                    $currentDate->addMonth();
                    break;
            }
        }

        return $dateRange;
    }


    private function calculateTotalWorkedHours($schoolId, $startDate, $endDate, $season, $monitor, $sportId = null, $onlyWeekends = false)
    {
        $bookingUsers = BookingUser::with('monitor')
            ->where('school_id', $schoolId)
            ->when($monitor, function ($query) use ($monitor) {
                return $query->where('monitor_id', $monitor);
            })
            ->when($sportId, function ($query) use ($sportId) {
                return $query->whereHas('course', function ($query) use ($sportId) {
                    $query->where('sport_id', $sportId);
                });
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->get();

        $nwds = MonitorNwd::with('monitor')
            ->where('school_id', $schoolId)
            ->where('user_nwd_subtype_id', 2)
            ->when($monitor, function ($query) use ($monitor) {
                return $query->where('monitor_id', $monitor);
            })
            ->whereBetween('start_date', [$startDate, $endDate])
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->when($sportId, function ($query) use ($sportId) {
                return $query->whereHas('monitor.monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($sportId) {
                    $query->where('sport_id', $sportId);
                });
            })
            ->get();

            $courses = Course::whereHas('courseDates', function ($query) use ($startDate, $endDate, $onlyWeekends) {
                $query->whereBetween('date', [$startDate, $endDate])
                    ->when($onlyWeekends, fn($q) => $q->onlyWeekends());
            })
            ->where('school_id', $schoolId)
            ->when($sportId, function ($query) use ($sportId) {
                return $query->where('sport_id', $sportId);
            })
            ->get();

        $totalBookingHours = 0;
        $totalCourseHours = 0;
        $totalNwdHours = 0;
        $totalCourseAvailableHours = 0;
        $monitorsBySportAndDegree = $this->getGroupedMonitors($schoolId);

        foreach ($courses as $course) {
            $durations = $this->getCourseAvailability($course, $monitorsBySportAndDegree, $startDate, $endDate);
            $totalCourseHours += $durations['total_hours'];
            $totalCourseAvailableHours += $durations['total_available_hours'];
        }

        foreach ($bookingUsers as $bookingUser) {
            $duration = $this->convertDurationToHours($bookingUser->duration);
            $totalBookingHours += $duration;
        }

        $fullDayDuration = $this->convertDurationToHours($this->calculateDuration($season->hour_start, $season->hour_end));

        foreach ($nwds as $nwd) {
            $duration = $nwd->full_day ? $fullDayDuration : $this->convertDurationToHours($this->calculateDuration($nwd->start_time, $nwd->end_time));
            $totalNwdHours += $duration;
        }

        // Calcular el número de días entre startDate y endDate
        $startDateTime = new DateTime($startDate);
        $endDateTime = new DateTime($endDate);
        $interval = $startDateTime->diff($endDateTime);
        $numDays = $interval->days + 1; // Incluir ambos extremos

        // Calcular el número de monitores disponibles, filtrados por deporte si se proporciona
        $totalMonitorsQuery = Monitor::whereHas('monitorsSchools', function ($query) use ($schoolId) {
            $query->where('school_id', $schoolId);
        });

        if ($sportId) {
            $totalMonitorsQuery->whereHas('monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($sportId) {
                $query->where('sport_id', $sportId);
            });
        }

        $totalMonitors = $monitor ? 1 : $totalMonitorsQuery->count();



        // Calcular la duración diaria en horas
        $dailyDurationHours = $this->convertDurationToHours($this->calculateDuration($season->hour_start, $season->hour_end));

        // Multiplicar por el número de días y el número de monitores
        $totalMonitorHours = $numDays * $dailyDurationHours * $totalMonitors;

        return [
            'totalBookingHours' => $totalBookingHours,
            'totalNwdHours' => $totalNwdHours,
            'totalCourseHours' => $totalCourseHours,
            'totalAvailableHours' => $totalCourseAvailableHours,
            'totalMonitorHours' => $totalMonitorHours,
            'totalWorkedHours' => $totalBookingHours + $totalNwdHours
        ];
    }

    public function getTotalPrice(Request $request)
    {
        $schoolId = $this->getSchool($request)->id;
        $today = Carbon::now()->format('Y-m-d');

        $season = Season::whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        $startDate = $request->start_date ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;
        $onlyWeekends = $request->boolean('onlyWeekends', false);

        if (!$startDate || !$endDate) {
            return $this->sendError('Start date and end date are required.');
        }

        $bookingUsersReserved = BookingUser::whereBetween('date', [$startDate, $endDate])
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2)
                    ->where(function ($q) {
                        $q->whereHas('payments', fn($p) => $p->where('status', 'paid'))
                            ->orWhereHas('vouchersLogs');
                    });
            })
            ->where('status', 1)
            ->where('school_id', $schoolId)
            ->with('booking.vouchersLogs', 'booking.payments', 'course')
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('course', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })
            ->when($request->has('course_type'), function ($query) use ($request) {
                return $query->whereHas('course', function ($query) use ($request) {
                    $query->where('course_type', $request->course_type);
                });
            })
            ->get();

        $totalPrice = 0;
        $processedBookings = [];

        foreach ($bookingUsersReserved->groupBy('course_id') as $courseId => $bookingCourseUsers) {
            $course = Course::find($courseId);
            if (!$course) continue;

            foreach ($bookingCourseUsers->groupBy('booking_id') as $bookingId => $bookingGroupedUsers) {
                if (in_array($bookingId, $processedBookings)) continue;
                $processedBookings[] = $bookingId;

                $booking = $bookingGroupedUsers->first()->booking;
                if ($booking->status == 2) continue;

                // Usar exactamente la misma lógica de cálculo
                $calculated = $this->calculateGroupedBookingUsersPrice($bookingGroupedUsers);

                // Manejar multi-curso
                $allValidBookingUsers = $booking->bookingUsers
                    ->where('status', 1)
                    ->whereBetween('date', [$startDate, $endDate]);

                $currentIds = $bookingGroupedUsers->pluck('id')->toArray();
                $otherBookingUsers = $allValidBookingUsers->filter(function ($bu) use ($currentIds) {
                    return !in_array($bu->id, $currentIds);
                });

                $fullBookingTotal = $calculated['totalPrice'];

                if ($otherBookingUsers->count() > 0) {
                    $otherTotal = 0;
                    $otherGrouped = $otherBookingUsers->groupBy('course_id');

                    foreach ($otherGrouped as $group) {
                        $otherTotal += $this->calculateGroupedBookingUsersPrice($group)['totalPrice'];
                    }

                    $fullBookingTotal = $calculated['totalPrice'] + $otherTotal;
                }

                $totalPrice += $fullBookingTotal;
            }
        }

        return $this->sendResponse(round($totalPrice, 2), 'Total price retrieved successfully');
    }


    public function getTotalAvailablePlacesByCourseType(Request $request)
    {
        $schoolId = $this->getSchool($request)->id;
        $today = Carbon::now()->format('Y-m-d'); // Obtiene la fecha actual en formato YYYY-MM-DD

        $season = Season::whereDate('start_date', '<=', $today) // Fecha de inicio menor o igual a hoy
        ->whereDate('end_date', '>=', $today)   // Fecha de fin mayor o igual a hoy
        ->first();

        // Utiliza start_date y end_date de la request si están presentes, sino usa las fechas de la temporada
        $startDate = $request->start_date ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;
        $onlyWeekends = $request->boolean('onlyWeekends', false); // default false

        if (!$startDate || !$endDate) {
            return $this->sendError('Start date and end date are required.');
        }

        $bookingUsersTotalPrice = BookingUser::with('course.sport') // <--- Agregado aquí
        ->where('school_id', $schoolId)
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            })
            ->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('course', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->get();


        $totalPricesByType = [
            'total_price_type_1' => 0,
            'total_price_type_2' => 0,
            'total_price_type_3' => 0,
        ];

        foreach ($bookingUsersTotalPrice as $bookingUser) {
            if (!$bookingUser->course) {
                \Log::warning("BookingUser sin curso", ['booking_user_id' => $bookingUser->id]);
                continue; // Salta este registro para evitar el error
            }

            if ($bookingUser->course->course_type == 1) {
                $totalPricesByType['total_price_type_1'] += $bookingUser->price;
            } elseif ($bookingUser->course->course_type == 2) {
                $totalPricesByType['total_price_type_2'] += $bookingUser->price;
            } else {
                $totalPricesByType['total_price_type_3'] += $bookingUser->price;
            }
        }

        // Obtener todos los cursos dentro del rango de fechas
        $courses = Course::whereHas('courseDates', function ($query) use ($startDate, $endDate, $onlyWeekends) {
            $query->whereBetween('date', [$startDate, $endDate])
                ->when($onlyWeekends, fn($q) => $q->onlyWeekends());
        })->where('school_id', $schoolId)
            ->when($request->has('type'), function ($query) use ($request) {
                return $query->where('course_type', $request->type);
            })
            ->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->where('sport_id', $request->sport_id);
            })
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                $query->whereHas('bookingUsers', function ($q) use($request) {
                    return $q->where('monitor_id', $request->monitor_id);
                });
            })
            ->get();

        $monitorsGrouped = $this->getGroupedMonitors($schoolId, $request->monitor_id, $request->sport_id);

        $courseAvailabilityByType = [
            'total_places_type_1' => 0,
            'total_available_places_type_1' => 0,
            'total_hours_type_1' => 0,
            'total_available_hours_type_1' => 0,
            'total_reservations_places_type_1' => 0,
            'total_reservations_hours_type_1' => 0,
            'total_price_type_1' => $totalPricesByType['total_price_type_1'],
            'total_places_type_2' => 0,
            'total_available_places_type_2' => 0,
            'total_hours_type_2' => 0,
            'total_available_hours_type_2' => 0,
            'total_reservations_places_type_2' => 0,
            'total_reservations_hours_type_2' => 0,
            'total_price_type_2' => $totalPricesByType['total_price_type_2'],
            'total_places_type_3' => 0,
            'total_available_places_type_3' => 0,
            'total_hours_type_3' => 0,
            'total_available_hours_type_3' => 0,
            'total_reservations_places_type_3' => 0,
            'total_reservations_hours_type_3' => 0,
            'total_price_type_3' => $totalPricesByType['total_price_type_3'],
        ];

        foreach ($courses as $course) {
            $availability = $this->getCourseAvailability($course, $monitorsGrouped, $startDate, $endDate);
            if ($availability) {
                if ($course->course_type == 1) {
                    $courseAvailabilityByType['total_places_type_1'] += $availability['total_places'];
                    $courseAvailabilityByType['total_available_places_type_1'] += $availability['total_available_places'];
                    $courseAvailabilityByType['total_hours_type_1'] += $availability['total_hours'];
                    $courseAvailabilityByType['total_available_hours_type_1'] += $availability['total_available_hours'];
                    $courseAvailabilityByType['total_reservations_places_type_1'] += $availability['total_reservations_places'];
                    $courseAvailabilityByType['total_reservations_hours_type_1'] += $availability['total_reservations_hours'];
                } elseif ($course->course_type == 2) {
                    $courseAvailabilityByType['total_places_type_2'] += $availability['total_places'];
                    $courseAvailabilityByType['total_available_places_type_2'] += $availability['total_available_places'];
                    $courseAvailabilityByType['total_hours_type_2'] += $availability['total_hours'];
                    $courseAvailabilityByType['total_available_hours_type_2'] += $availability['total_available_hours'];
                    $courseAvailabilityByType['total_reservations_places_type_2'] += $availability['total_reservations_places'];
                    $courseAvailabilityByType['total_reservations_hours_type_2'] += $availability['total_reservations_hours'];
                } else {
                    $courseAvailabilityByType['total_places_type_3'] += $availability['total_places'];
                    $courseAvailabilityByType['total_available_places_type_3'] += $availability['total_available_places'];
                    $courseAvailabilityByType['total_hours_type_3'] += $availability['total_hours'];
                    $courseAvailabilityByType['total_available_hours_type_3'] += $availability['total_available_hours'];
                    $courseAvailabilityByType['total_reservations_places_type_3'] += $availability['total_reservations_places'];
                    $courseAvailabilityByType['total_reservations_hours_type_3'] += $availability['total_reservations_hours'];
                }
            }
        }

        // Filtrar la respuesta por tipo de curso si se proporciona
        if ($request->has('type')) {
            $courseType = $request->type;
            return $this->sendResponse([
                'total_places' => round($courseAvailabilityByType['total_places_type_' . $courseType]),
                'total_available_places' =>  round($courseAvailabilityByType['total_available_places_type_' . $courseType]),
                'total_price' =>  round($courseAvailabilityByType['total_price_type_' . $courseType]),
                'total_hours' => round( $courseAvailabilityByType['total_hours_type_' . $courseType]),
                'total_available_hours' =>  round($courseAvailabilityByType['total_available_hours_type_' . $courseType]),
                'total_reservations_places' =>  round($courseAvailabilityByType['total_reservations_places_type_' . $courseType]),
                'total_reservations_hours' =>  round($courseAvailabilityByType['total_reservations_hours_type_' . $courseType]),
            ], 'Total available places and prices for the specified course type retrieved successfully');
        }


        return $this->sendResponse($courseAvailabilityByType, 'Total available places by course type retrieved successfully');
    }

    /**
     * @OA\Get(
     *      path="/admin/statistics/bookings/monitors",
     *      summary="Get monitors bookings for season",
     *      tags={"Admin"},
     *      description="Get monitors bookings for the specified season or date range",
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          description="Start date to filter bookings",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              format="date"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          description="End date to filter bookings",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              format="date"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      ref="#/components/schemas/Booking"
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function getMonitorsBookings(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'monitor_id' => 'integer|exists:monitors,id|nullable',
            'sport_id' => 'integer|exists:sports,id|nullable',
        ]);

        $schoolId = $this->getSchool($request)->id;

        $today = Carbon::now()->format('Y-m-d');

        $season = Season::whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if (!$season) {
            return response()->json(['error' => 'No se encontró una temporada activa'], 404);
        }

        $startDate = $request->start_date ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;
        $onlyWeekends = $request->boolean('onlyWeekends', false); // default false

        $bookingUsersWithMonitor = BookingUser::with(['monitor.monitorSportsDegrees.salary', 'course.sport'])
            ->where('school_id', $schoolId)
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2);
            })
            ->where('status', 1)
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            }, function ($query) {
                return $query->where('monitor_id', '!=', null);
            })
            ->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('course', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->get()->unique(function ($item) {
                return $item->hour_start . $item->hour_end . $item->date . $item->monitor_id;
            });




        $settings = json_decode($this->getSchool($request)->settings);

        $nwds = MonitorNwd::with(['monitor.monitorSportsDegrees.salary', 'monitor.monitorSportsDegrees.sport'])
            ->where('school_id', $schoolId)
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            }, function ($query) {
                return $query->where('monitor_id', '!=', null);
            })
            ->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('monitor.monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get();

        $subgroupsWithoutBookings = CourseSubgroup::with('monitor.monitorSportsDegrees.salary',
            'monitor.monitorSportsDegrees.sport', 'courseDate')
            ->whereHas('course', function($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            }, function ($query) {
                return $query->where('monitor_id', '!=', null);
            })->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('monitor.monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })->whereHas('courseDate', function($query) use ($startDate, $endDate, $onlyWeekends) {
                $query->whereBetween('date', [$startDate, $endDate])
                    ->when($onlyWeekends, fn($q) => $q->onlyWeekends());
            })->whereDoesntHave('bookingUsers')
            ->get();


        $currency = $settings->taxes->currency ?? 'CHF';

        $monitorSummary = $this->initializeMonitorSummary($schoolId, $request);

        foreach ($subgroupsWithoutBookings as $subgroupsWithoutBooking) {
            $monitor = $subgroupsWithoutBooking->monitor;
            $sport = $subgroupsWithoutBooking->course->sport;
            $courseType = $subgroupsWithoutBooking->course->course_type;
            $duration = $this->calculateDuration($subgroupsWithoutBooking->courseDate->hour_start,
                $subgroupsWithoutBooking->courseDate->hour_end);

            $durationInMinutes = $this->parseDurationToMinutes($duration);
            $hourlyRate = $this->getHourlyRate($monitor, $sport->id, $schoolId);

            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);


            $this->updateMonitorSummary($monitorSummary, $monitor->id, $courseType, $durationInMinutes,
                $formattedData['totalCost'], $hourlyRate);
        }

        // Procesar reservas con monitor
        foreach ($bookingUsersWithMonitor as $bookingUser) {
            if (!$bookingUser->course) {
                \Log::warning("BookingUser sin curso", ['booking_user_id' => $bookingUser->id]);
                continue; // Salta este registro para evitar el error
            }

            $monitor = $bookingUser->monitor;
            $sport = $bookingUser->course->sport;
            $courseType = $bookingUser->course->course_type;

            $durationInMinutes = $this->parseDurationToMinutes($bookingUser->duration);
            $hourlyRate = $this->getHourlyRate($monitor, $sport->id, $schoolId);

            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);

            $this->updateMonitorSummary($monitorSummary, $monitor->id, $courseType, $durationInMinutes,
                $formattedData['totalCost'], $hourlyRate);
        }

        // Procesar NWDs
        foreach ($nwds as $nwd) {
            $monitor = $nwd->monitor;
            $durationInMinutes = $nwd->full_day
                ? $this->calculateDurationInMinutes($season->hour_start, $season->hour_end)
                : $this->calculateDurationInMinutes($nwd->start_time, $nwd->end_time);

            $hourlyRate = $this->getHourlyRate($monitor, null, $schoolId, $nwd);
            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);

            $this->updateMonitorSummary($monitorSummary, $monitor->id, 'nwd', $durationInMinutes,
                $formattedData['totalCost'], $hourlyRate, $nwd->user_nwd_subtype_id == 2);
        }

        // Convertir las duraciones totales a formato "Xh Ym"
        foreach ($monitorSummary as &$summary) {
            $summary['hours_collective'] = $this->formatMinutesToHourMinute($summary['hours_collective'] ?? 0);
            $summary['hours_private'] = $this->formatMinutesToHourMinute($summary['hours_private'] ?? 0);
            $summary['hours_activities'] = $this->formatMinutesToHourMinute($summary['hours_activities'] ?? 0);
            $summary['hours_nwd'] = $this->formatMinutesToHourMinute($summary['hours_nwd'] ?? 0);
            $summary['hours_nwd_payed'] = $this->formatMinutesToHourMinute($summary['hours_nwd_payed'] ?? 0);
            $summary['total_hours'] = $this->formatMinutesToHourMinute($summary['total_minutes'] ?? 0);
            unset($summary['total_minutes']); // Eliminar minutos brutos si no se necesitan
        }

        return $this->sendResponse(array_values($monitorSummary), 'Monitor bookings of the season retrieved successfully');
    }

    public function getTotalMonitorPrice(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable',
            'monitor_id' => 'integer|exists:monitors,id|nullable',
            'sport_id' => 'integer|exists:sports,id|nullable',
        ]);

        $schoolId = $this->getSchool($request)->id;

        $today = Carbon::now()->format('Y-m-d');

        $season = Season::whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if (!$season) {
            return response()->json(['error' => 'No se encontró una temporada activa'], 404);
        }

        $startDate = $request->start_date ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;

        $onlyWeekends = $request->boolean('onlyWeekends', false); // default false

        $bookingUsersWithMonitor = BookingUser::with(['monitor.monitorSportsDegrees.salary', 'course.sport'])
            ->where('school_id', $schoolId)
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2);
            })
            ->where('status', 1)
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            }, function ($query) {
                return $query->where('monitor_id', '!=', null);
            })
            ->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('course', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->get()->unique(function ($item) {
                return $item->hour_start . $item->hour_end . $item->date . $item->monitor_id;
            });

        // dd($bookingUsersWithMonitor->pluck('duration'));


        $settings = json_decode($this->getSchool($request)->settings);

        $nwds = MonitorNwd::with(['monitor.monitorSportsDegrees.salary', 'monitor.monitorSportsDegrees.sport'])
            ->where('school_id', $schoolId)
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            }, function ($query) {
                return $query->where('monitor_id', '!=', null);
            })
            ->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('monitor.monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get();

        $subgroupsWithoutBookings = CourseSubgroup::with('monitor.monitorSportsDegrees.salary',
            'monitor.monitorSportsDegrees.sport', 'courseDate')
            ->whereHas('course', function($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            }, function ($query) {
                return $query->where('monitor_id', '!=', null);
            })->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('monitor.monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })->whereHas('courseDate', function($query) use ($startDate, $endDate, $onlyWeekends) {
                $query->whereBetween('date', [$startDate, $endDate])
                    ->when($onlyWeekends, fn($q) => $q->onlyWeekends());
            })->whereDoesntHave('bookingUsers')
            ->get();


        $currency = $settings->taxes->currency ?? 'CHF';

        $monitorSummary = $this->initializeMonitorSummary($schoolId, $request);

        foreach ($subgroupsWithoutBookings as $subgroupsWithoutBooking) {
            $monitor = $subgroupsWithoutBooking->monitor;
            $sport = $subgroupsWithoutBooking->course->sport;
            $courseType = $subgroupsWithoutBooking->course->course_type;
            $duration = $this->calculateDuration($subgroupsWithoutBooking->courseDate->hour_start,
                $subgroupsWithoutBooking->courseDate->hour_end);

            $durationInMinutes = $this->parseDurationToMinutes($duration);
            $hourlyRate = $this->getHourlyRate($monitor, $sport->id, $schoolId);

            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);


            $this->updateMonitorSummary($monitorSummary, $monitor->id, $courseType, $durationInMinutes,
                $formattedData['totalCost'], $hourlyRate);
        }

        $groupedBookingUsers = $bookingUsersWithMonitor->groupBy(function ($bu) {
            return $bu->monitor_id . '|' . $bu->date . '|' . $bu->hour_start . '|' . $bu->hour_end;
        });

        foreach ($groupedBookingUsers as $groupKey => $group) {
            $bookingUser = $group->first();

            $monitor = $bookingUser->monitor;
            $sport = $bookingUser->course->sport;
            $courseType = $bookingUser->course->course_type;

            $durationInMinutes = $this->parseDurationToMinutes($bookingUser->duration);
            $hourlyRate = $this->getHourlyRate($monitor, $sport->id, $schoolId);
            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);

            $this->updateMonitorSummary(
                $monitorSummary,
                $monitor->id,
                $courseType,
                $durationInMinutes,
                $formattedData['totalCost'],
                $hourlyRate
            );
        }

        // Procesar reservas con monitor
/*        foreach ($bookingUsersWithMonitor as $bookingUser) {
            $monitor = $bookingUser->monitor;
            $sport = $bookingUser->course->sport;
            $courseType = $bookingUser->course->course_type;

            $durationInMinutes = $this->parseDurationToMinutes($bookingUser->duration);
            $hourlyRate = $this->getHourlyRate($monitor, $sport->id, $schoolId);

            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);

            $this->updateMonitorSummary($monitorSummary, $monitor->id, $courseType, $durationInMinutes,
                $formattedData['totalCost'], $hourlyRate);
        }*/

        // Procesar NWDs
        foreach ($nwds as $nwd) {
            $monitor = $nwd->monitor;
            $durationInMinutes = $nwd->full_day
                ? $this->calculateDurationInMinutes($season->hour_start, $season->hour_end)
                : $this->calculateDurationInMinutes($nwd->start_time, $nwd->end_time);

            $hourlyRate = $this->getHourlyRate($monitor, null, $schoolId, $nwd);
            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);

            $this->updateMonitorSummary($monitorSummary, $monitor->id, 'nwd', $durationInMinutes,
                $formattedData['totalCost'], $hourlyRate, $nwd->user_nwd_subtype_id == 2);
        }
        $totalPrice = 0;
        // Convertir las duraciones totales a formato "Xh Ym"
        foreach ($monitorSummary as &$summary) {
            $summary['hours_collective'] = $this->formatMinutesToHourMinute($summary['hours_collective'] ?? 0);
            $summary['hours_private'] = $this->formatMinutesToHourMinute($summary['hours_private'] ?? 0);
            $summary['hours_activities'] = $this->formatMinutesToHourMinute($summary['hours_activities'] ?? 0);
            $summary['hours_nwd'] = $this->formatMinutesToHourMinute($summary['hours_nwd'] ?? 0);
            $summary['hours_nwd_payed'] = $this->formatMinutesToHourMinute($summary['hours_nwd_payed'] ?? 0);
            $summary['total_hours'] = $this->formatMinutesToHourMinute($summary['total_minutes'] ?? 0);
            $totalPrice +=  $summary['total_cost'];
            unset($summary['total_minutes']); // Eliminar minutos brutos si no se necesitan
        }

        return $this->sendResponse(round($totalPrice, 2), 'Monitor bookings of the season retrieved successfully');

    }


    private function calculateDurationInMinutes($startTime, $endTime)
    {
        // Asegúrate de que ambos tiempos sean válidos
        if (!$startTime || !$endTime) {
            return 0; // Si alguno de los valores no es válido, devuelve 0
        }

        try {
            // Convierte los tiempos a instancias de Carbon
            $start = Carbon::createFromFormat('H:i:s', $startTime);
            $end = Carbon::createFromFormat('H:i:s', $endTime);

            // Si el tiempo de fin es menor que el de inicio, asumimos que pasa al día siguiente
            if ($end->lt($start)) {
                $end->addDay();
            }

            // Calcula la diferencia en minutos
            return $start->diffInMinutes($end);
        } catch (\Exception $e) {
            // Si ocurre un error en el formato, registra el error y devuelve 0
            Log::error('Error calculating duration in minutes: ' . $e->getMessage());
            Log::error('Startime: ' .$startTime);
            Log::error('Endtime: ' .$endTime);
            return 0;
        }
    }


    private function updateMonitorSummary(
        &$monitorSummary,
        $monitorId,
        $courseType,
        $durationInMinutes,
        $totalCost,
        $hourlyRate,
        $isPaid = false
    ) {
        if (!isset($monitorSummary[$monitorId])) {
            return;
        }

        // Redondear el costo total a 2 decimales
        $totalCost = round($totalCost, 2);

        // Actualizar el precio por hora
        $monitorSummary[$monitorId]['hour_price'] = round($hourlyRate, 2);

        // Acumular horas y costos según el tipo de curso o bloque
        switch ($courseType) {
            case 1: // Collective
                $monitorSummary[$monitorId]['hours_collective'] += $durationInMinutes;
                $monitorSummary[$monitorId]['cost_collective'] = round(($monitorSummary[$monitorId]['cost_collective'] ?? 0) + $totalCost, 2);
                break;
            case 2: // Private
                $monitorSummary[$monitorId]['hours_private'] += $durationInMinutes;
                $monitorSummary[$monitorId]['cost_private'] = round(($monitorSummary[$monitorId]['cost_private'] ?? 0) + $totalCost, 2);
                break;
            case 'nwd': // Bloques NWD
                if ($isPaid) {
                    // NWD pagado
                    $monitorSummary[$monitorId]['hours_nwd_payed'] += $durationInMinutes;
                    $monitorSummary[$monitorId]['cost_nwd'] = round(($monitorSummary[$monitorId]['cost_nwd'] ?? 0) + $totalCost, 2);
                    /*   $monitorSummary[$monitorId]['total_minutes'] = ($monitorSummary[$monitorId]['total_minutes'] ?? 0) + $durationInMinutes;
                       $monitorSummary[$monitorId]['total_cost'] = round(($monitorSummary[$monitorId]['total_cost'] ?? 0) + $totalCost, 2);*/
                } /*else {
                    // NWD no pagado (solo acumula horas)
                    $monitorSummary[$monitorId]['hours_nwd'] += $durationInMinutes;
                    $monitorSummary[$monitorId]['total_minutes'] = ($monitorSummary[$monitorId]['total_minutes'] ?? 0) + $durationInMinutes;
                }*/
                break;
            default: // Activities
                $monitorSummary[$monitorId]['hours_activities'] += $durationInMinutes;
                $monitorSummary[$monitorId]['cost_activities'] = round(($monitorSummary[$monitorId]['cost_activities'] ?? 0) + $totalCost, 2);
                break;
        }

        // Actualizar totales generales (solo para Collective, Private y Activities, o NWD pagados)
        if ($courseType != 'nwd' || $isPaid) {
            $monitorSummary[$monitorId]['total_minutes'] = ($monitorSummary[$monitorId]['total_minutes'] ?? 0) + $durationInMinutes;
            $monitorSummary[$monitorId]['total_cost'] = round(($monitorSummary[$monitorId]['total_cost'] ?? 0) + $totalCost, 2);
        }
    }



    private function formatDurationAndCost($durationInMinutes, $hourlyRate)
    {
        $totalCost = round(($durationInMinutes / 60) * $hourlyRate, 2);
        return [
            'formattedDuration' => $this->formatMinutesToHourMinute($durationInMinutes),
            'totalCost' => $totalCost,
        ];
    }

    private function parseDurationToMinutes($duration)
    {
        if (strpos($duration, ':') !== false) {
            [$hours, $minutes] = explode(':', $duration);
            return ((int) $hours * 60) + (int) $minutes;
        }
        return 0;
    }

    private function formatMinutesToHourMinute($minutes)
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        return sprintf('%dh %02dm', $hours, $remainingMinutes);
    }


    private function initializeMonitorSummary($schoolId, $request)
    {
        $totalMonitorsQuery = Monitor::whereHas('monitorsSchools', function ($query) use ($schoolId) {
            $query->where('school_id', $schoolId)->where('active_school', 1);
        });

        if ($request->filled('sport_id')) {
            $totalMonitorsQuery->whereHas('monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($request) {
                $query->where('sport_id', $request->sport_id);
            });
        }

        $monitors = $totalMonitorsQuery->get();
        $monitorSummary = [];

        foreach ($monitors as $monitor) {
            $monitorSummary[$monitor->id] = [
                'id' => $monitor->id,
                'first_name' => $monitor->first_name . ' ' . $monitor->last_name,
                'address' => $monitor->address,
                'language1_id' => $monitor->language1_id,
                'country' => $monitor->country,
                'birth_date' => $monitor->birth_date,
                'work_license' => $monitor->work_license,
                'bank_details' => $monitor->bank_details,
                'image' => $monitor->image,
                'sport' => null, // Este se actualiza más adelante
                'currency' => 'CHF', // Moneda por defecto, se puede ajustar según settings
                'hours_collective' => 0,
                'hours_private' => 0,
                'hours_activities' => 0,
                'hours_nwd' => 0,
                'hours_nwd_payed' => 0,
                'cost_collective' => 0,
                'cost_private' => 0,
                'cost_activities' => 0,
                'cost_nwd' => 0,
                'total_hours' => 0,
                'total_cost' => 0,
                'hour_price' => 0, // Precio por hora se actualizará dinámicamente
            ];

            // Asignar el deporte relacionado si corresponde
            foreach ($monitor->monitorSportsDegrees as $degree) {
                if ($degree->school_id == $schoolId && (!$request->filled('sport_id') || $degree->sport_id == $request->sport_id)) {
                    $monitorSummary[$monitor->id]['sport'] = $degree->sport;
                    break;
                }
            }
        }

        return $monitorSummary;
    }

    //TODO: Monitor new field for nwd
    private function getHourlyRate($monitor, $sportId, $schoolId, $nwd=null)
    {
        if ($nwd) {
            // Buscar el block_price del monitor para esa escuela
            $monitorSchool = $monitor->monitorsSchools
                ->firstWhere('school_id', $schoolId);

            if ($monitorSchool && $monitorSchool->block_price > 0) {
                return $monitorSchool->block_price;
            }

            // Si no hay block_price, usar el precio del bloqueo
            if (isset($nwd->price) && $nwd->price > 0) {
                return $nwd->price;
            }
        }

        // 2. Si no aplica lo anterior, buscar salario por degree
        foreach ($monitor->monitorSportsDegrees as $degree) {
            if ($sportId) {
                if ($degree->sport_id == $sportId && $degree->school_id == $schoolId) {
                    return $degree->salary ? $degree->salary->pay : 0;
                }
            } else {
                if ($degree->school_id == $schoolId) {
                    return $degree->salary ? $degree->salary->pay : 0;
                }
            }
        }

        return 0; // Si no se encuentra nada
    }
    public function getMonitorDailyBookings(Request $request, $monitorId): JsonResponse
    {
        $schoolId = $this->getSchool($request)->id;
        $today = Carbon::now()->format('Y-m-d');
        $season = Season::whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        $startDate = $request->start_date ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;
        $onlyWeekends = $request->boolean('onlyWeekends', false); // default false
        $sportId = $request->sport_id;

        $bookingUsersWithMonitor = BookingUser::with(['monitor.monitorSportsDegrees.salary', 'course.sport'])
            ->where('school_id', $schoolId)
            ->where('monitor_id', $monitorId)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2);
            })
            ->where('status', 1)
            ->whereHas('course.sport', function ($query) use ($sportId) {
                if ($sportId) {
                    $query->where('id', $sportId);
                }
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->get()
            ->unique(function ($item) {
                return $item->hour_start . $item->hour_end . $item->date;
            });

        $settings = json_decode($this->getSchool($request)->settings);

        $nwds = MonitorNwd::with(['monitor.monitorSportsDegrees.salary', 'monitor.monitorSportsDegrees.sport'])
            ->where('school_id', $schoolId)
            ->where('monitor_id', $monitorId)
            ->where('user_nwd_subtype_id', 2)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->when($onlyWeekends, fn($q) => $q->onlyWeekends())
            ->whereHas('monitor.monitorSportsDegrees.sport', function ($query) use ($sportId) {
                if ($sportId) {
                    $query->where('id', $sportId);
                }
            })
            ->get();

        $subgroupsWithoutBookings = CourseSubgroup::with('monitor.monitorSportsDegrees.salary',
            'monitor.monitorSportsDegrees.sport', 'courseDate')
            ->whereHas('course', function($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when($request->has('monitor_id'), function ($query) use ($request) {
                return $query->where('monitor_id', $request->monitor_id);
            }, function ($query) {
                return $query->where('monitor_id', '!=', null);
            })->when($request->filled('sport_id'), function ($query) use ($request) {
                return $query->whereHas('monitor.monitorSportsDegrees.monitorSportAuthorizedDegrees.degree', function ($query) use ($request) {
                    $query->where('sport_id', $request->sport_id);
                });
            })->whereHas('courseDate', function($query) use ($startDate, $endDate, $onlyWeekends) {
                $query->whereBetween('date', [$startDate, $endDate])
                    ->when($onlyWeekends, fn($q) => $q->onlyWeekends());
            })->whereDoesntHave('bookingUsers')
            ->get();

        $currency = $settings->taxes->currency ?? 'CHF';

        $monitorDailySummary = [];

        foreach ($subgroupsWithoutBookings as $subgroupsWithoutBooking) {
            $monitor = $subgroupsWithoutBooking->monitor;
            $sport = $subgroupsWithoutBooking->course->sport;
            $courseType = $subgroupsWithoutBooking->course->course_type;
            $duration = $this->calculateDuration($subgroupsWithoutBooking->courseDate->hour_start,
                $subgroupsWithoutBooking->courseDate->hour_end);

            $durationInMinutes = $this->parseDurationToMinutes($duration);
            $hourlyRate = $this->getHourlyRate($monitor, $sport->id, $schoolId);

            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);

            $date = Carbon::parse($subgroupsWithoutBooking->courseDate->date)->format('Y-m-d');

            if (!isset($monitorDailySummary[$date])) {
                $monitorDailySummary[$date] = $this->initializeDailyMonitorSummary
                ($monitor, $sport, $currency, $date);
            }

            $this->updateDailySummary($monitorDailySummary[$date],
                $courseType, $formattedData, $duration, $hourlyRate);

        }

        // Procesar reservas con monitor
        foreach ($bookingUsersWithMonitor as $bookingUser) {
            $monitor = $bookingUser->monitor;
            $sport = $bookingUser->course->sport;
            $courseType = $bookingUser->course->course_type;
            $durationInMinutes = $this->parseDurationToMinutes($bookingUser->duration);
            $hourlyRate = $this->getHourlyRate($monitor, $sport->id, $schoolId);

            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);

            $date = Carbon::parse($bookingUser->date)->format('Y-m-d');

            if (!isset($monitorDailySummary[$date])) {
                $monitorDailySummary[$date] = $this->initializeDailyMonitorSummary
                ($monitor, $sport, $currency, $date);
            }


            $this->updateDailySummary($monitorDailySummary[$date], $courseType, $formattedData,
                $bookingUser->duration, $hourlyRate);
        }

        // Procesar NWDs
        foreach ($nwds as $nwd) {
            $monitor = $nwd->monitor;
            $duration = $this->calculateDuration($nwd->start_time, $nwd->end_time);
            $durationInMinutes = $nwd->full_day
                ? $this->calculateDurationInMinutes($season->hour_start, $season->hour_end)
                : $this->calculateDurationInMinutes($nwd->start_time, $nwd->end_time);

            $hourlyRate = $this->getHourlyRate($monitor, null, $schoolId, $nwd);
            $formattedData = $this->formatDurationAndCost($durationInMinutes, $hourlyRate);

            $date = Carbon::parse($nwd->start_date)->format('Y-m-d');

            if (!isset($monitorDailySummary[$date])) {
                $monitorDailySummary[$date] = $this->initializeDailyMonitorSummary($monitor, null, $currency, $date);
            }



            $this->updateDailySummary($monitorDailySummary[$date], 'nwd', $formattedData, $duration, $hourlyRate, $nwd->user_nwd_subtype_id == 2);

        }

        foreach ($monitorDailySummary as &$summary) {
            $summary['hours_collective'] = $this->formatMinutesToHourMinute($summary['hours_collective'] ?? 0);
            $summary['total_hours'] = $this->formatMinutesToHourMinute($summary['total_minutes']);
            $summary['hours_private'] = $this->formatMinutesToHourMinute($summary['hours_private'] ?? 0);
            $summary['hours_activities'] = $this->formatMinutesToHourMinute($summary['hours_activities'] ?? 0);
            $summary['hours_nwd'] = $this->formatMinutesToHourMinute($summary['hours_nwd'] ?? 0);
            $summary['hours_nwd_payed'] = $this->formatMinutesToHourMinute($summary['hours_nwd_payed'] ?? 0);
            $summary['total_hours'] = $this->formatMinutesToHourMinute($summary['total_minutes'] ?? 0);
            unset($summary['total_minutes']);
        }

        $monitorDailySummaryJson = array_values($monitorDailySummary);
        return $this->sendResponse($monitorDailySummaryJson, 'Monitor daily bookings retrieved successfully');
    }

    private function updateDailySummary(&$summary, $courseType, $formattedData, $duration, $hourlyRate, $isPaid = false)
    {
        $summary['hour_price'] = $hourlyRate;

        $durationInMinutes = $this->parseDurationToMinutes($duration);
        $totalCost = round($formattedData['totalCost'], 2);

        // dd($durationInMinutes);
        switch ($courseType) {
            case 1: // Collective
                $summary['hours_collective'] += $durationInMinutes;
                $summary['cost_collective'] += $totalCost;
                break;
            case 2: // Private
                $summary['hours_private'] += $durationInMinutes;
                $summary['cost_private'] += $totalCost;
                break;
            case 'nwd': // Bloques NWD
                if ($isPaid) {
                    $summary['hours_nwd_payed'] += $durationInMinutes;
                    $summary['cost_nwd'] += $totalCost;
                }
                break;
            default: // Activities
                $summary['hours_activities'] += $durationInMinutes;
                $summary['cost_activities'] += $totalCost;
                break;
        }

        if ($courseType != 'nwd' || $isPaid) {
            $summary['total_minutes'] += $durationInMinutes;
            $summary['total_cost'] += $totalCost;
        }
    }

    private function initializeDailyMonitorSummary($monitor, $sport, $currency, $date)
    {
        return [
            'date' => $date,
            'first_name' => $monitor->first_name . ' ' . $monitor->last_name,
            'language1_id' => $monitor->language1_id,
            'country' => $monitor->country,
            'birth_date' => $monitor->birth_date,
            'image' => $monitor->image,
            'id' => $monitor->id,
            'sport' => $sport,
            'currency' => $currency,
            'hours_collective' => 0,
            'hours_nwd' => 0,
            'hours_nwd_payed' => 0,
            'hours_private' => 0,
            'hours_activities' => 0,
            'cost_collective' => 0,
            'cost_nwd' => 0,
            'cost_private' => 0,
            'cost_activities' => 0,
            'total_minutes' => 0,
            'total_cost' => 0,
            'hour_price' => 0,
        ];

    }



    // Función para convertir la duración en formato HH:MM:SS a horas decimales
    private function convertDurationToHours($duration): float|int
    {
        $parts = explode(':', $duration);
        $hours = (int) $parts[0];
        $minutes = (int) $parts[1];
        $seconds = (int) $parts[2];

        return $hours + ($minutes / 60) + ($seconds / 3600);
    }
}
