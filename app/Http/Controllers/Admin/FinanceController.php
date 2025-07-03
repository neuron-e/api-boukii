<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Controllers\PayrexxHelpers;
use App\Http\Services\BookingPriceCalculatorService;
use App\Models\Booking;
use App\Models\Season;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\FinanceCacheKeyTrait;

class FinanceController extends AppBaseController
{
    use FinanceCacheKeyTrait;

    // ✅ CURSOS A EXCLUIR DE LOS CÁLCULOS
    const EXCLUDED_COURSES = [260, 243];

    protected $priceCalculator;

    public function __construct(BookingPriceCalculatorService $priceCalculator)
    {
        $this->priceCalculator = $priceCalculator;
    }

    /**
     * MÉTODO ACTUALIZADO: Endpoint principal usando nuevos métodos
     */
    public function getSeasonFinancialDashboard(Request $request): JsonResponse
    {
/*        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'season_id' => 'nullable|integer|exists:seasons,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'include_test_detection' => 'boolean',
            'include_payrexx_analysis' => 'boolean',
            'optimization_level' => 'nullable|in:fast,balanced,detailed'
        ]);*/

        $this->ensureSchoolInRequest($request);

        $optimizationLevel = $request->get('optimization_level', 'balanced');

        $cacheKey = $this->generateCacheKeyFromRequest($request);

        Log::info('=== INICIANDO DASHBOARD EJECUTIVO CON CLASIFICACIÓN ===', [
            'school_id' => $request->school_id,
            'optimization_level' => $optimizationLevel,
            'include_test_detection' => $request->boolean('include_test_detection', true),
            'include_payrexx' => $request->boolean('include_payrexx_analysis', false)
        ]);

        try {
            $dashboard = Cache::remember($cacheKey, 300, function () use ($request, $optimizationLevel) {
                return $this->buildDashboard($request, $optimizationLevel);
            });

            return $this->sendResponse($dashboard, 'Dashboard ejecutivo con clasificación generado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error en dashboard ejecutivo con clasificación: ' . $e->getMessage(), [
                'school_id' => $request->school_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->sendError('Error generando dashboard ejecutivo: ' . $e->getMessage(), 500);
        }
    }

    private function buildDashboard(Request $request, string $optimizationLevel): array
    {
        $startTime = microtime(true);
        // 1. DETERMINAR PERÍODO DE ANÁLISIS
        $dateRange = $this->getSeasonDateRange($request);

        // 2. OBTENER RESERVAS DE LA TEMPORADA CON OPTIMIZACIÓN
        $bookings = $this->getSeasonBookingsOptimized($request, $dateRange, $optimizationLevel);

        // 3. GENERAR DASHBOARD CON CLASIFICACIÓN
        $dashboard = $this->generateSeasonDashboard($bookings, $dateRange, $request, $optimizationLevel);

        // 4. CALCULAR TIEMPOS DE EJECUCIÓN
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $dashboard['performance_metrics'] = [
            'execution_time_ms' => $executionTime,
            'total_bookings_analyzed' => $bookings->count(),
            'production_bookings_count' => $dashboard['season_info']['booking_classification']['production_count'],
            'test_bookings_excluded' => $dashboard['season_info']['booking_classification']['test_count'],
            'cancelled_bookings_count' => $dashboard['season_info']['booking_classification']['cancelled_count'],
            'bookings_per_second' => $executionTime > 0 ? round($bookings->count() / ($executionTime / 1000), 2) : 0,
            'optimization_level' => $optimizationLevel,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'analysis_timestamp' => now()->toDateTimeString()
        ];

        Log::info('=== DASHBOARD EJECUTIVO CON CLASIFICACIÓN COMPLETADO ===', [
            'execution_time_ms' => $executionTime,
            'total_bookings' => $bookings->count(),
            'production_count' => $dashboard['season_info']['booking_classification']['production_count'],
            'test_excluded' => $dashboard['season_info']['booking_classification']['test_count'],
            'optimization_level' => $optimizationLevel
        ]);

        return $dashboard;
    }

    /**
     * LIMPIAR CACHE ESPECÍFICO
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            // Limpiar cache para todos los niveles de optimización
            $levels = ['fast', 'balanced', 'detailed'];
            $clearedKeys = [];

            foreach ($levels as $level) {
                $cacheKey = $this->generateFinanceCacheKey(
                    $request->school_id,
                    $request->start_date,
                    $request->end_date,
                    $request->season_id,
                    $level
                );

                if (Cache::forget($cacheKey)) {
                    $clearedKeys[] = $level;
                }
            }

            Log::info('Cache limpiado para dashboard financiero', [
                'school_id' => $request->school_id,
                'levels_cleared' => $clearedKeys
            ]);

            return $this->sendResponse([
                'cleared' => true,
                'levels' => $clearedKeys,
                'school_id' => $request->school_id
            ], 'Cache limpiado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error limpiando cache: ' . $e->getMessage());
            return $this->sendError('Error limpiando cache: ' . $e->getMessage(), 500);
        }
    }

    /**
     * MÉTODO AUXILIAR: Determinar rango de fechas para la temporada
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
     * MÉTODO AUXILIAR: Obtener reservas optimizadas según nivel de optimización
     */
    private function getSeasonBookingsOptimized(Request $request, array $dateRange, string $optimizationLevel)
    {
        $query = Booking::query()
            ->with([
                'bookingUsers' => function($q) {
                    $q->with(['course.sport', 'course.courseDates',
                        'client', 'bookingUserExtras.courseExtra']);
                },
                'payments',
                'vouchersLogs.voucher',
                'clientMain',
                'school'
            ])
            ->where('school_id', $request->school_id);

        // Filtrar por fechas de booking users
        $query->whereHas('bookingUsers', function($q) use ($dateRange) {
            $q->whereBetween('date', [$dateRange['start_date'], $dateRange['end_date']]);
        });

        // Aplicar límites según optimización
        switch ($optimizationLevel) {
            case 'fast':
                // Solo últimas 500 reservas para análisis rápido
                $query->latest()->limit(800);
                break;
            case 'detailed':
                // Sin límites para análisis completo
                break;
            default: // balanced
                // Límite razonable para balance entre velocidad y completitud
                $query->latest()->limit(2000);
                break;
        }

        $bookings = $query->get();

        // Filtrar reservas que solo tienen cursos excluidos
        return $this->filterBookingsWithExcludedCourses($bookings, self::EXCLUDED_COURSES);
    }

    /**
     * MÉTODO PRINCIPAL: Generar dashboard ejecutivo completo
     */
    /**
     * MÉTODO ACTUALIZADO: Dashboard de temporada con clasificación real
     */
    private function generateSeasonDashboard($bookings, array $dateRange, Request $request, string $optimizationLevel): array
    {
        // 🔍 CLASIFICAR RESERVAS CON LÓGICA CORRECTA
        $classification = $this->classifyBookings($bookings);

        $dashboard = [
            'season_info' => [
                'season_name' => $dateRange['season_name'],
                'date_range' => [
                    'start' => $dateRange['start_date'],
                    'end' => $dateRange['end_date'],
                    'total_days' => $dateRange['total_days']
                ],
                'school_id' => $request->school_id,
                'optimization_level' => $optimizationLevel,
                'total_bookings' => $bookings->count(),
                'booking_classification' => $classification['summary']
            ]
        ];

        // 📊 KPIs EJECUTIVOS CON EXPECTED CORRECTO
        $dashboard['executive_kpis'] = $this->calculateProductionKpis($classification, $request);

        // 📱 ANÁLISIS DE SOURCES/ORÍGENES DE RESERVAS
        $dashboard['booking_sources'] = $this->analyzeBookingSources($bookings);

        // 💳 ANÁLISIS MEJORADO DE MÉTODOS DE PAGO (solo producción)
        $dashboard['payment_methods'] = $this->analyzePaymentMethodsImproved($bookings);

        // 📈 MÉTRICAS POR ESTADO (solo producción que genera expected)
        $productionBookings = array_merge($classification['production_active'], $classification['production_partial']);
        $dashboard['booking_status_analysis'] = $this->analyzeBookingsByStatus($productionBookings);

        // 💰 ANÁLISIS FINANCIERO (solo expected real)
        $dashboard['financial_summary'] = $this->calculateFinancialSummary($productionBookings, $optimizationLevel);

        // 🔍 PROBLEMAS CRÍTICOS (solo de expected)
        $dashboard['critical_issues'] = $this->identifyCriticalIssues($productionBookings, $optimizationLevel);

        // 🧪 ANÁLISIS SEPARADO DE TEST
        if ($request->boolean('include_test_detection', true)) {
            $dashboard['test_analysis'] = $this->analyzeTestBookingsDetailed($classification['test']);
        }

        // ❌ ANÁLISIS SEPARADO DE CANCELADAS (procesamiento, no expected)
        $dashboard['cancelled_analysis'] = $this->analyzeCancelledBookings($classification['cancelled']);

        // 🔗 ANÁLISIS DE PAYREXX (usando todas las reservas para comparación)
        if ($request->boolean('include_payrexx_analysis', false)) {
            $dashboard['payrexx_analysis'] = $this->analyzeSeasonPayrexx($bookings, $dateRange, $classification);
        }

        // 🚨 ALERTAS BASADAS EN EXPECTED CORRECTO
        $dashboard['executive_alerts'] = $this->generateProductionAlerts($dashboard);
        $dashboard['priority_recommendations'] = $this->generateProductionRecommendations($dashboard);

        // 📊 TENDENCIAS (solo expected real)
        $dashboard['trend_analysis'] = $this->calculateProductionTrends($productionBookings, $dateRange);

        // 💼 RESUMEN COMPLETO PARA EXPORTACIÓN CON LÓGICA CORRECTA
        $dashboard['export_summary'] = $this->prepareExportSummary($dashboard, $classification);

        $dashboard['courses'] = $this->generateCourseAnalytics($bookings);

        return $dashboard;
    }

    private function generateCourseAnalytics($bookings)
    {
        $courses = [];

        foreach ($bookings as $booking) {
            $activities = $booking->getGroupedActivitiesAttribute();
            $paidTotal = $booking->payments->where('status', 'paid')->sum('amount');
            $totalDue = collect($activities)->sum('price') ?: 1;

            foreach ($activities as $activity) {
                $course = $activity['course'];
                if (!$course) continue;

                $courseId = $course->id;

                if (!isset($courses[$courseId])) {
                    $courses[$courseId] = [
                        'id' => $courseId,
                        'name' => $course->name,
                        'type' => $course->course_type,
                        'is_flexible' => $course->is_flexible,
                        'sport' => optional($course->sport)->name,
                        'revenue' => 0,
                        'participants' => 0,
                        'bookings' => 0,
                        'average_price' => 0,
                        'payment_methods' => [
                            'cash' => 0,
                            'card' => 0,
                            'online' => 0,
                            'transfer' => 0,
                            'voucher' => 0,
                            'other' => 0
                        ],
                        'status_breakdown' => [],
                        'source_breakdown' => [],
                    ];
                }

                // Revenue proporcional del grupo
                $revenueAssigned = ($activity['price'] / $totalDue) * $paidTotal;
                $courses[$courseId]['revenue'] += $revenueAssigned;
                $courses[$courseId]['bookings']++;
                $courses[$courseId]['participants'] += count($activity['utilizers'] ?? []);

                // Métodos de pago
                $methods = $this->getProportionalPaymentMethods($booking, $activity['price'], $totalDue);
                foreach ($methods as $method => $amount) {
                    if (isset($courses[$courseId]['payment_methods'][$method])) {
                        $courses[$courseId]['payment_methods'][$method] += $amount;
                    }
                }

                // Desglose de estados
                foreach ($activity['statusList'] ?? [] as $status) {
                    if (!isset($courses[$courseId]['status_breakdown'][$status])) {
                        $courses[$courseId]['status_breakdown'][$status] = 0;
                    }
                    $courses[$courseId]['status_breakdown'][$status]++;
                }

                // Desglose de fuente
                $source = $booking->source ?? 'unknown';
                if (!isset($courses[$courseId]['source_breakdown'][$source])) {
                    $courses[$courseId]['source_breakdown'][$source] = 0;
                }
                $courses[$courseId]['source_breakdown'][$source]++;
            }
        }

        // Postprocesado
        foreach ($courses as &$course) {
            $course['average_price'] = $course['participants'] > 0
                ? round($course['revenue'] / $course['participants'], 2)
                : 0;

            $course['revenue'] = round($course['revenue'], 2);

            foreach ($course['payment_methods'] as &$value) {
                $value = round($value, 2);
            }
        }

        return array_values($courses);
    }


    private function getProportionalPaymentMethods($booking, $segmentPrice, $totalPrice)
    {
        $result = [
            'cash' => 0,
            'card' => 0,
            'online' => 0,
            'transfer' => 0,
            'voucher' => 0,
            'other' => 0
        ];

        $factor = $segmentPrice / ($totalPrice ?: 1);

        // Pagos reales
        foreach ($booking->payments->where('status', 'paid') as $payment) {
            $method = $this->resolvePaymentMethod($payment);
            if (!isset($result[$method])) $method = 'other';
            $result[$method] += $payment->amount * $factor;
        }

        // Vouchers aplicados
        foreach ($booking->vouchersLogs as $log) {
            $amount = $log->amount ?? 0;
            $result['voucher'] += $amount * $factor;
        }

        return array_map(fn($v) => round($v, 2), $result);
    }

    private function resolvePaymentMethod($payment)
    {
        if ($payment->payrexx_reference) return 'online';

        $notes = strtolower($payment->notes ?? '');
        return match (true) {
            str_contains($notes, 'cash') => 'cash',
            str_contains($notes, 'card') => 'card',
            str_contains($notes, 'transfer') => 'transfer',
            default => 'other'
        };
    }

    /**
     * MÉTODO ACTUALIZADO: Análisis de reservas por estado (solo producción)
     */
    private function analyzeProductionBookingsByStatus($productionBookings): array
    {
        $statusAnalysis = [
            'active' => ['count' => 0, 'revenue' => 0, 'issues' => 0],
            'partial_cancel' => ['count' => 0, 'revenue' => 0, 'issues' => 0],
            'finished' => ['count' => 0, 'revenue' => 0, 'issues' => 0]
        ];

        foreach ($productionBookings as $booking) {
            $statusKey = $booking->getCancellationStatusAttribute();

            // Solo analizamos estados que pueden aparecer en producción
            if (!isset($statusAnalysis[$statusKey])) {
                $statusKey = 'active'; // Fallback
            }

            $statusAnalysis[$statusKey]['count']++;

            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);
            $statusAnalysis[$statusKey]['revenue'] += $quickAnalysis['calculated_amount'];

            if ($quickAnalysis['has_issues']) {
                $statusAnalysis[$statusKey]['issues']++;
            }
        }

        // Calcular porcentajes basados solo en producción
        $totalProductionBookings = count($productionBookings);
        foreach ($statusAnalysis as $status => &$data) {
            $data['percentage'] = $totalProductionBookings > 0 ? round(($data['count'] / $totalProductionBookings) * 100, 2) : 0;
            $data['revenue'] = round($data['revenue'], 2);
        }

        return $statusAnalysis;
    }
    /**
     * MÉTODO ACTUALIZADO: Resumen financiero de producción
     */
    private function calculateProductionFinancialSummary($productionBookings, string $optimizationLevel): array
    {
        $summary = [
            'revenue_breakdown' => [
                'total_expected' => 0,
                'total_received' => 0,
                'total_pending' => 0,
                'total_refunded' => 0
            ],
            'payment_methods' => [],
            'voucher_usage' => [
                'total_vouchers_used' => 0,
                'total_voucher_amount' => 0,
                'unique_vouchers' => 0
            ],
            'booking_value_distribution' => [
                'under_100' => 0,
                'between_100_500' => 0,
                'between_500_1000' => 0,
                'over_1000' => 0
            ],
            'consistency_metrics' => [
                'consistent_bookings' => 0,
                'inconsistent_bookings' => 0,
                'consistency_rate' => 0
            ]
        ];

        $paymentMethodCounts = [];
        $voucherCodes = [];
        $consistentCount = 0;

        foreach ($productionBookings as $booking) {
            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

            $summary['revenue_breakdown']['total_expected'] += $quickAnalysis['calculated_amount'];
            $summary['revenue_breakdown']['total_received'] += $quickAnalysis['received_amount'];

            // Contabilizar consistencia
            if (!$quickAnalysis['has_issues']) {
                $consistentCount++;
            }

            // Distribución por valor de reserva
            $bookingValue = $quickAnalysis['calculated_amount'];
            if ($bookingValue < 100) {
                $summary['booking_value_distribution']['under_100']++;
            } elseif ($bookingValue < 500) {
                $summary['booking_value_distribution']['between_100_500']++;
            } elseif ($bookingValue < 1000) {
                $summary['booking_value_distribution']['between_500_1000']++;
            } else {
                $summary['booking_value_distribution']['over_1000']++;
            }

            // Métodos de pago
            if ($optimizationLevel === 'detailed' || count($paymentMethodCounts) < 100) {
                foreach ($booking->payments as $payment) {
                    $method = $this->determinePaymentMethodImproved($payment);
                    $paymentMethodCounts[$method] = ($paymentMethodCounts[$method] ?? 0) + 1;

                    if ($payment->status === 'refund') {
                        $summary['revenue_breakdown']['total_refunded'] += $payment->amount;
                    }
                }
            }

            // Análisis de vouchers
            foreach ($booking->vouchersLogs as $voucherLog) {
                $summary['voucher_usage']['total_voucher_amount'] += $voucherLog->amount;
                $summary['voucher_usage']['total_vouchers_used']++;

                if ($voucherLog->voucher && $voucherLog->voucher->code) {
                    $voucherCodes[] = $voucherLog->voucher->code;
                }
            }
        }

        // Calcular métricas finales
        $totalBookings = count($productionBookings);
        $summary['consistency_metrics']['consistent_bookings'] = $consistentCount;
        $summary['consistency_metrics']['inconsistent_bookings'] = $totalBookings - $consistentCount;
        $summary['consistency_metrics']['consistency_rate'] = $totalBookings > 0
            ? round(($consistentCount / $totalBookings) * 100, 2) : 100;

        $summary['revenue_breakdown']['total_pending'] = $summary['revenue_breakdown']['total_expected'] - $summary['revenue_breakdown']['total_received'];
        $summary['payment_methods'] = $paymentMethodCounts;
        $summary['voucher_usage']['unique_vouchers'] = count(array_unique($voucherCodes));

        // Redondear valores
        foreach ($summary['revenue_breakdown'] as $key => $value) {
            $summary['revenue_breakdown'][$key] = round($value, 2);
        }
        $summary['voucher_usage']['total_voucher_amount'] = round($summary['voucher_usage']['total_voucher_amount'], 2);

        return $summary;
    }

    /**
     * MÉTODO ACTUALIZADO: Problemas críticos en producción
     */
    private function identifyProductionCriticalIssues($productionBookings, string $optimizationLevel): array
    {
        $criticalIssues = [
            'high_value_discrepancies' => [],
            'payment_processing_issues' => [],
            'voucher_inconsistencies' => [],
            'pricing_anomalies' => []
        ];

        $highValueThreshold = 50; // Reducido porque en producción queremos ser más estrictos
        $processed = 0;
        $maxToAnalyze = $optimizationLevel === 'fast' ? 100 : ($optimizationLevel === 'detailed' ? PHP_INT_MAX : 300);

        foreach ($productionBookings as $booking) {
            if ($processed >= $maxToAnalyze) break;

            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

            // Discrepancias de alto valor
            $difference = abs($quickAnalysis['calculated_amount'] - $quickAnalysis['received_amount']);
            if ($difference > $highValueThreshold) {
                $criticalIssues['high_value_discrepancies'][] = [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->clientMain->email ?? 'N/A',
                    'difference_amount' => round($difference, 2),
                    'calculated' => round($quickAnalysis['calculated_amount'], 2),
                    'received' => round($quickAnalysis['received_amount'], 2),
                    'severity' => $difference > 100 ? 'critical' : 'high'
                ];
            }

            // Problemas de procesamiento de pagos
            if ($booking->status == 1 && $quickAnalysis['received_amount'] < $quickAnalysis['calculated_amount'] * 0.8) {
                $criticalIssues['payment_processing_issues'][] = [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->clientMain->email ?? 'N/A',
                    'expected_amount' => round($quickAnalysis['calculated_amount'], 2),
                    'received_amount' => round($quickAnalysis['received_amount'], 2),
                    'missing_amount' => round($quickAnalysis['calculated_amount'] - $quickAnalysis['received_amount'], 2)
                ];
            }

            // Inconsistencias de vouchers
            $voucherTotal = $booking->vouchersLogs->sum('amount');
            if ($voucherTotal > $quickAnalysis['calculated_amount']) {
                $criticalIssues['voucher_inconsistencies'][] = [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->clientMain->email ?? 'N/A',
                    'voucher_amount' => round($voucherTotal, 2),
                    'booking_amount' => round($quickAnalysis['calculated_amount'], 2),
                    'excess_amount' => round($voucherTotal - $quickAnalysis['calculated_amount'], 2)
                ];
            }

            // Anomalías de precios (precios muy altos o muy bajos)
            if ($quickAnalysis['calculated_amount'] > 2000 || ($quickAnalysis['calculated_amount'] > 0 && $quickAnalysis['calculated_amount'] < 10)) {
                $criticalIssues['pricing_anomalies'][] = [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->clientMain->email ?? 'N/A',
                    'amount' => round($quickAnalysis['calculated_amount'], 2),
                    'anomaly_type' => $quickAnalysis['calculated_amount'] > 2000 ? 'very_high' : 'very_low'
                ];
            }

            $processed++;
        }

        // Agregar contadores
        foreach ($criticalIssues as $type => &$issues) {
            $issues = [
                'count' => count($issues),
                'items' => $issues
            ];
        }

        return $criticalIssues;
    }

    /**
     * MÉTODO ACTUALIZADO: Calcular tendencias de producción
     */
    private function calculateProductionTrends($productionBookings, array $dateRange): array
    {
        $trends = [
            'monthly_breakdown' => [],
            'booking_velocity' => [],
            'revenue_evolution' => [],
            'quality_metrics' => []
        ];

        try {
            // Agrupar por meses solo reservas de producción
            $monthlyData = [];
            foreach ($productionBookings as $booking) {
                $month = Carbon::parse($booking->created_at)->format('Y-m');

                if (!isset($monthlyData[$month])) {
                    $monthlyData[$month] = [
                        'bookings' => 0,
                        'revenue' => 0,
                        'clients' => [],
                        'consistent_bookings' => 0
                    ];
                }

                $monthlyData[$month]['bookings']++;
                $monthlyData[$month]['clients'][] = $booking->client_main_id;

                $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);
                $monthlyData[$month]['revenue'] += $quickAnalysis['calculated_amount'];

                if (!$quickAnalysis['has_issues']) {
                    $monthlyData[$month]['consistent_bookings']++;
                }
            }

            // Procesar datos mensuales
            foreach ($monthlyData as $month => $data) {
                $consistencyRate = $data['bookings'] > 0 ? round(($data['consistent_bookings'] / $data['bookings']) * 100, 2) : 100;

                $trends['monthly_breakdown'][] = [
                    'month' => $month,
                    'bookings' => $data['bookings'],
                    'revenue' => round($data['revenue'], 2),
                    'unique_clients' => count(array_unique($data['clients'])),
                    'consistency_rate' => $consistencyRate,
                    'avg_booking_value' => $data['bookings'] > 0 ? round($data['revenue'] / $data['bookings'], 2) : 0
                ];
            }

            // Calcular velocidad de reservas de producción
            $recentBookings = array_filter($productionBookings, function($booking) {
                return Carbon::parse($booking->created_at)->gt(Carbon::now()->subWeeks(4));
            });

            $trends['booking_velocity'] = [
                'recent_production_bookings' => count($recentBookings),
                'bookings_per_week' => round(count($recentBookings) / 4, 1),
                'trend_direction' => $this->calculateTrendDirection($monthlyData),
                'quality_trend' => $this->calculateQualityTrend($monthlyData)
            ];

        } catch (\Exception $e) {
            Log::warning('Error calculando tendencias de producción: ' . $e->getMessage());
            $trends['error'] = 'No se pudieron calcular las tendencias';
        }

        return $trends;
    }

    /**
     * NUEVO MÉTODO: Calcular tendencia de calidad
     */
    private function calculateQualityTrend(array $monthlyData): string
    {
        if (count($monthlyData) < 2) return 'insufficient_data';

        $months = array_keys($monthlyData);
        sort($months);

        $recent = array_slice($months, -2);
        $oldConsistency = $monthlyData[$recent[0]]['consistent_bookings'] / max(1, $monthlyData[$recent[0]]['bookings']);
        $newConsistency = $monthlyData[$recent[1]]['consistent_bookings'] / max(1, $monthlyData[$recent[1]]['bookings']);

        if ($newConsistency > $oldConsistency + 0.1) return 'improving';
        if ($newConsistency < $oldConsistency - 0.1) return 'declining';
        return 'stable';
    }

    /**
     * MÉTODO ACTUALIZADO: Recomendaciones específicas para producción
     */
    private function generateProductionRecommendations(array $dashboard): array
    {
        $recommendations = [];

        // Recomendación de consistencia en producción
        $consistencyRate = $dashboard['executive_kpis']['consistency_rate'] ?? 100;
        if ($consistencyRate < 95) {
            $inconsistentCount = $dashboard['executive_kpis']['consistency_issues'] ?? 0;
            $severity = $consistencyRate < 85 ? 'critical' : 'high';

            $recommendations[] = [
                'priority' => $severity,
                'category' => 'production_consistency',
                'title' => 'Optimizar Consistencia en Producción',
                'description' => "El {$consistencyRate}% de consistencia en reservas reales requiere mejora",
                'impact' => $severity,
                'effort' => 'medium',
                'timeline' => '1-2 semanas',
                'actions' => [
                    "Revisar {$inconsistentCount} reservas de producción inconsistentes",
                    'Implementar validaciones en tiempo real',
                    'Mejorar proceso de cálculo de precios',
                    'Entrenar al equipo en detección de problemas'
                ],
                'expected_benefit' => 'Reducir pérdidas financieras y mejorar precisión',
                'affected_bookings' => $inconsistentCount
            ];
        }

        // Recomendación de cobros pendientes en producción
        $revenueAtRisk = $dashboard['executive_kpis']['revenue_at_risk'] ?? 0;
        if ($revenueAtRisk > 500) {
            $recommendations[] = [
                'priority' => $revenueAtRisk > 2000 ? 'critical' : 'high',
                'category' => 'production_collection',
                'title' => 'Acelerar Cobros de Producción',
                'description' => "Hay {$revenueAtRisk}€ pendientes en reservas reales",
                'impact' => 'high',
                'effort' => 'low',
                'timeline' => '1 semana',
                'actions' => [
                    'Priorizar seguimiento de reservas reales',
                    'Implementar recordatorios automáticos urgentes',
                    'Ofrecer facilidades de pago',
                    'Contacto directo con clientes de alto valor'
                ],
                'expected_benefit' => "Recuperar hasta {$revenueAtRisk}€ en ingresos reales",
                'potential_recovery' => $revenueAtRisk
            ];
        }

        // Recomendación sobre test detectados
        $testCount = $dashboard['season_info']['booking_classification']['test_count'] ?? 0;
        if ($testCount > 0 && env('APP_ENV') === 'production') {
            $testRevenue = $dashboard['season_info']['booking_classification']['test_revenue'] ?? 0;

            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'test_cleanup',
                'title' => 'Limpiar Transacciones de Test',
                'description' => "Se detectaron {$testCount} transacciones de test con {$testRevenue}€ en producción",
                'impact' => 'medium',
                'effort' => 'low',
                'timeline' => '1-2 días',
                'actions' => [
                    'Identificar origen de las transacciones test',
                    'Migrar transacciones válidas si procede',
                    'Implementar validaciones para prevenir test en producción',
                    'Revisar proceso de migración de datos'
                ],
                'expected_benefit' => 'Datos más limpios y métricas más precisas',
                'test_count' => $testCount,
                'test_revenue' => $testRevenue
            ];
        }

        // Recomendación de problemas críticos específicos
        if (isset($dashboard['critical_issues'])) {
            $highValueIssues = $dashboard['critical_issues']['high_value_discrepancies']['count'] ?? 0;
            if ($highValueIssues > 0) {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'critical_issues_resolution',
                    'title' => 'Resolver Discrepancias de Alto Valor',
                    'description' => "Hay {$highValueIssues} reservas con discrepancias significativas",
                    'impact' => 'high',
                    'effort' => 'medium',
                    'timeline' => '3-5 días',
                    'actions' => [
                        'Revisar reservas con mayor discrepancia',
                        'Verificar cálculos de precios',
                        'Comprobar pagos y vouchers',
                        'Actualizar registros según corresponda'
                    ],
                    'expected_benefit' => 'Eliminar discrepancias y mejorar precisión financiera',
                    'affected_bookings' => $highValueIssues
                ];
            }
        }

        // Ordenar por prioridad
        $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($recommendations, function($a, $b) use ($priorityOrder) {
            return $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
        });

        return array_slice($recommendations, 0, 5); // Top 5 recomendaciones
    }

    /**
     * MÉTODO AUXILIAR: Determinar método de pago
     */
    private function determinePaymentMethod($payment): string
    {
        if ($payment->payrexx_reference) {
            return 'payrexx';
        }

        $notes = strtolower($payment->notes ?? '');

        if (str_contains($notes, 'cash') || str_contains($notes, 'efectivo')) {
            return 'cash';
        }

        if (str_contains($notes, 'transfer') || str_contains($notes, 'transferencia')) {
            return 'transfer';
        }

        if (str_contains($notes, 'voucher') || str_contains($notes, 'bono')) {
            return 'voucher';
        }

        return 'other';
    }

    /**
 * MÉTODO ACTUALIZADO: Generar alertas basadas en producción
 */
    private function generateProductionAlerts(array $dashboard): array
    {
        $alerts = [];

        // Alerta de consistencia (solo producción)
        $consistencyRate = $dashboard['executive_kpis']['consistency_rate'] ?? 100;
        if ($consistencyRate < 80) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'production_consistency',
                'title' => 'Problemas de Consistencia en Producción',
                'description' => "Solo el {$consistencyRate}% de las reservas reales son financieramente consistentes",
                'impact' => 'high',
                'action_required' => true
            ];
        }

        // Alerta de ingresos en riesgo (solo producción)
        $revenueAtRisk = $dashboard['executive_kpis']['revenue_at_risk'] ?? 0;
        if ($revenueAtRisk > 1000) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'production_revenue_risk',
                'title' => 'Ingresos Reales en Riesgo',
                'description' => "Hay {$revenueAtRisk}€ de ingresos reales pendientes de cobro",
                'impact' => 'medium',
                'action_required' => true
            ];
        }

        // Alerta de reservas de test detectadas
        $testCount = $dashboard['season_info']['booking_classification']['test_count'] ?? 0;
        if ($testCount > 0) {
            $testRevenue = $dashboard['season_info']['booking_classification']['test_revenue'] ?? 0;
            $alerts[] = [
                'level' => 'info',
                'type' => 'test_bookings_detected',
                'title' => 'Reservas de Test Detectadas',
                'description' => "Se detectaron {$testCount} reservas de test con {$testRevenue}€ (excluidas del cómputo)",
                'impact' => 'low',
                'action_required' => false
            ];
        }

        // Alerta de cancelaciones sin procesar
        if (isset($dashboard['cancelled_analysis'])) {
            $unprocessedAmount = $dashboard['cancelled_analysis']['unprocessed_payments'] ?? 0;
            if ($unprocessedAmount > 100) {
                $alerts[] = [
                    'level' => 'warning',
                    'type' => 'unprocessed_cancellations',
                    'title' => 'Cancelaciones Sin Procesar',
                    'description' => "Hay {$unprocessedAmount}€ en cancelaciones pendientes de procesar",
                    'impact' => 'medium',
                    'action_required' => true
                ];
            }
        }

        return $alerts;
    }

    /**
     * MÉTODO ACTUALIZADO: Exportación CSV mejorada con separación
     */
    private function generateCsvExportWithClassification(array $exportData, array $dashboardData): JsonResponse
    {
        $csvContent = '';
        $filename = 'dashboard_temporada_' . $exportData['metadata']['school_id'] . '_' . date('Y-m-d_H-i') . '.csv';

        // BOM para UTF-8
        $csvContent .= "\xEF\xBB\xBF";

        // ENCABEZADO
        $csvContent .= "DASHBOARD EJECUTIVO DE TEMPORADA - RESERVAS REALES\n";
        $csvContent .= "Escuela ID:," . $exportData['metadata']['school_id'] . "\n";
        $csvContent .= "Período:," . $exportData['metadata']['period']['start'] . " a " . $exportData['metadata']['period']['end'] . "\n";

        // RESUMEN DE CLASIFICACIÓN
        if (isset($dashboardData['season_info']['booking_classification'])) {
            $classification = $dashboardData['season_info']['booking_classification'];
            $csvContent .= "\nCLASIFICACIÓN DE RESERVAS\n";
            $csvContent .= "Total Reservas:," . $classification['total_bookings'] . "\n";
            $csvContent .= "Reservas Producción:," . $classification['production_count'] . "\n";
            $csvContent .= "Reservas Test (excluidas):," . $classification['test_count'] . "\n";
            $csvContent .= "Reservas Canceladas:," . $classification['cancelled_count'] . "\n";
            $csvContent .= "Ingresos Producción:," . number_format($classification['production_revenue'], 2) . " EUR\n";
            $csvContent .= "Ingresos Test (excluidos):," . number_format($classification['test_revenue'], 2) . " EUR\n";
        }

        $csvContent .= "Generado:," . $exportData['metadata']['export_date'] . "\n\n";

        // SECCIONES PRINCIPALES (solo producción)
        foreach ($exportData['sections'] as $sectionKey => $section) {
            $csvContent .= strtoupper($section['title']) . "\n";
            foreach ($section['data'] as $row) {
                $escapedRow = array_map(function($field) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }, $row);
                $csvContent .= implode(',', $escapedRow) . "\n";
            }
            $csvContent .= "\n";
        }

        // SECCIÓN SEPARADA: RESERVAS DE TEST DETECTADAS
        if (isset($dashboardData['test_analysis']) && !empty($dashboardData['test_analysis']['client_analysis'])) {
            $csvContent .= "RESERVAS DE TEST DETECTADAS (EXCLUIDAS DEL CÓMPUTO)\n";
            $csvContent .= '"Cliente ID","Nombre","Email","Reservas Test","Ingresos Test","Cliente Confirmado Test"' . "\n";

            foreach ($dashboardData['test_analysis']['client_analysis'] as $client) {
                $row = [
                    $client['client_id'],
                    $client['client_name'],
                    $client['client_email'],
                    $client['bookings_count'],
                    number_format($client['total_revenue'], 2) . ' EUR',
                    $client['is_confirmed_test_client'] ? 'SÍ' : 'NO'
                ];
                $escapedRow = array_map(function($field) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }, $row);
                $csvContent .= implode(',', $escapedRow) . "\n";
            }
            $csvContent .= "\n";
        }

        // SECCIÓN SEPARADA: CANCELACIONES SIN PROCESAR
        if (isset($dashboardData['cancelled_analysis']) && !empty($dashboardData['cancelled_analysis']['pending_processing'])) {
            $csvContent .= "CANCELACIONES PENDIENTES DE PROCESAR\n";
            $csvContent .= '"Booking ID","Cliente","Importe Sin Procesar","Pagos Recibidos"' . "\n";

            foreach ($dashboardData['cancelled_analysis']['pending_processing'] as $pending) {
                $row = [
                    $pending['booking_id'],
                    $pending['client_email'],
                    number_format($pending['unprocessed_amount'], 2) . ' EUR',
                    $pending['payments_count']
                ];
                $escapedRow = array_map(function($field) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }, $row);
                $csvContent .= implode(',', $escapedRow) . "\n";
            }
            $csvContent .= "\n";
        }

        // Resto del código igual...
        $tempPath = storage_path('temp/' . $filename);
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        file_put_contents($tempPath, $csvContent);

        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $filename,
                'file_path' => $tempPath,
                'content_type' => 'text/csv; charset=utf-8',
                'size' => strlen($csvContent),
                'download_url' => route('finance.download-export', ['filename' => $filename])
            ],
            'message' => 'Exportación CSV con clasificación generada exitosamente'
        ]);
    }

    /**
     * MÉTODO ACTUALIZADO: Preparar datos completos para exportación
     */
    private function prepareCompleteExportSummary(array $dashboard, array $classification): array
    {
        return [
            'csv_ready_data' => [
                'executive_summary' => [
                    ['Métrica', 'Valor', 'Unidad'],
                    ['=== RESERVAS DE PRODUCCIÓN ===', '', ''],
                    ['Total Reservas Producción', $dashboard['executive_kpis']['totalgenerateSeasonDashboard_production_bookings'], 'reservas'],
                    ['Total Clientes Únicos', $dashboard['executive_kpis']['total_clients'], 'clientes'],
                    ['Ingresos Esperados', $dashboard['executive_kpis']['revenue_expected'], 'EUR'],
                    ['Ingresos Recibidos', $dashboard['executive_kpis']['revenue_received'], 'EUR'],
                    ['Eficiencia de Cobro', $dashboard['executive_kpis']['collection_efficiency'], '%'],
                    ['Consistencia Financiera', $dashboard['executive_kpis']['consistency_rate'], '%'],
                    ['', '', ''],
                    ['=== RESERVAS EXCLUIDAS ===', '', ''],
                    ['Reservas de Test', $classification['summary']['test_count'], 'reservas'],
                    ['Ingresos Test Excluidos', $classification['summary']['test_revenue'], 'EUR'],
                    ['Reservas Canceladas', $classification['summary']['cancelled_count'], 'reservas'],
                    ['Ingresos Cancelados', $classification['summary']['cancelled_revenue'], 'EUR'],
                    ['', '', ''],
                    ['=== TOTALES GENERALES ===', '', ''],
                    ['Total General Reservas', $classification['summary']['total_bookings'], 'reservas'],
                    ['Porcentaje Producción', round(($classification['summary']['production_count'] / $classification['summary']['total_bookings']) * 100, 2), '%'],
                    ['Porcentaje Test', round(($classification['summary']['test_count'] / $classification['summary']['total_bookings']) * 100, 2), '%'],
                    ['Porcentaje Canceladas', round(($classification['summary']['cancelled_count'] / $classification['summary']['total_bookings']) * 100, 2), '%']
                ],

                'test_analysis' => [
                    ['Análisis de Reservas Test', '', ''],
                    ['Booking ID', 'Cliente', 'Email', 'Importe', 'Confianza', 'Razón'],
                    // Se llenará dinámicamente en el método de exportación
                ],

                'cancelled_analysis' => [
                    ['Análisis de Reservas Canceladas', '', ''],
                    ['Booking ID', 'Cliente', 'Email', 'Importe', 'Dinero Sin Procesar', 'Estado'],
                    // Se llenará dinámicamente en el método de exportación
                ]
            ]
        ];
    }


    /**
     * NUEVO MÉTODO: KPIs ejecutivos basados solo en reservas de producción
     */
    private function calculateProductionKpis($classification, Request $request): array
    {
        // Combinar activas y parciales para el conteo total de producción
        $allProductionBookings = array_merge(
            $classification['production_active'],
            $classification['production_partial']
        );

        $stats = [
            'total_production_bookings' => count($allProductionBookings),
            'active_bookings' => $classification['summary']['production_active_count'],
            'partial_cancelled_bookings' => $classification['summary']['production_partial_count'],
            'total_clients' => collect($allProductionBookings)->pluck('client_main_id')->unique()->count(),
            'total_participants' => collect($allProductionBookings)->sum(function($booking) {
                return $booking->bookingUsers->where('status', 1)->count(); // Solo usuarios activos
            }),
            // 🎯 EXPECTED = Solo lo que realmente esperamos cobrar
            'revenue_expected' => $classification['summary']['expected_revenue'],
            'revenue_received' => 0,
            'revenue_pending' => 0,
            'consistency_rate' => 0,
            'consistency_issues' => 0,
            // 📊 MÉTRICAS ADICIONALES PARA CONTEXTO
            'cancelled_revenue_excluded' => $classification['summary']['cancelled_revenue_processed'],
            'test_revenue_excluded' => $classification['summary']['test_revenue_excluded']
        ];

        $consistentBookings = 0;
        $totalReceived = 0;

        // Calcular received solo de reservas que SÍ esperamos que generen ingresos
        foreach ($allProductionBookings as $booking) {
            if ($booking->status == 2) {
                // Las canceladas NO cuentan en received porque no esperamos cobrar
                continue;
            }

            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

            if ($booking->status == 3) {
                // Para parciales, solo contar la parte que corresponde a usuarios activos
                $activeRevenue = $this->calculateActivePortionRevenue($booking);
                $activeProportion = $activeRevenue > 0 ? $activeRevenue / $quickAnalysis['calculated_amount'] : 0;
                $totalReceived += $quickAnalysis['received_amount'] * $activeProportion;
            } else {
                // Para activas, contar todo lo recibido
                $totalReceived += $quickAnalysis['received_amount'];
            }

            if (!$quickAnalysis['has_issues']) {
                $consistentBookings++;
            } else {
                $stats['consistency_issues']++;
            }
        }

        $stats['revenue_received'] = round($totalReceived, 2);
        $stats['revenue_pending'] = round($stats['revenue_expected'] - $stats['revenue_received'], 2);

        // Calcular métricas
        $productionCount = count($allProductionBookings);
        $stats['consistency_rate'] = $productionCount > 0
            ? round(($consistentBookings / $productionCount) * 100, 2)
            : 100;

        $stats['collection_efficiency'] = $stats['revenue_expected'] > 0
            ? round(($stats['revenue_received'] / $stats['revenue_expected']) * 100, 2)
            : 100;

        $stats['average_booking_value'] = $stats['total_production_bookings'] > 0
            ? round($stats['revenue_expected'] / $stats['total_production_bookings'], 2)
            : 0;

        return $stats;
    }

    /**
     * NUEVO MÉTODO: Análisis detallado de reservas de test
     */
    private function analyzeTestBookingsDetailed($testBookings): array
    {
        $testCollection = collect($testBookings);

        $analysis = [
            'total_test_bookings' => $testCollection->count(),
            'total_test_revenue' => 0,
            'test_clients' => [],
            'confidence_breakdown' => ['high' => 0, 'medium' => 0, 'low' => 0],
            'indicators_summary' => [],
            'client_analysis' => []
        ];

        $clientStats = [];
        $allIndicators = [];

        foreach ($testCollection as $booking) {
            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);
            $testBookingAnalysis = $this->isTestBooking($booking);

            $analysis['total_test_revenue'] += $quickAnalysis['calculated_amount'];

            // Estadísticas por confianza
            $confidence = $testBookingAnalysis['confidence_level'];
            $analysis['confidence_breakdown'][$confidence]++;

            // Recopilar indicadores
            $allIndicators = array_merge($allIndicators, $testBookingAnalysis['test_indicators']);

            // Estadísticas por cliente
            $clientId = $booking->client_main_id;
            if (!isset($clientStats[$clientId])) {
                $clientStats[$clientId] = [
                    'client_id' => $clientId,
                    'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,
                    'client_email' => $booking->clientMain->email,
                    'bookings_count' => 0,
                    'total_revenue' => 0,
                    'is_confirmed_test_client' => in_array($clientId, [18956, 14479, 13583, 13524])
                ];
            }

            $clientStats[$clientId]['bookings_count']++;
            $clientStats[$clientId]['total_revenue'] += $quickAnalysis['calculated_amount'];
        }

        // Procesar indicadores
        $indicatorCounts = array_count_values($allIndicators);
        arsort($indicatorCounts);
        $analysis['indicators_summary'] = $indicatorCounts;

        // Procesar clientes
        $analysis['client_analysis'] = array_values($clientStats);
        $analysis['unique_test_clients'] = count($clientStats);

        $analysis['total_test_revenue'] = round($analysis['total_test_revenue'], 2);

        return $analysis;
    }

    /**
     * NUEVO MÉTODO: Análisis de reservas canceladas
     */
    private function analyzeCancelledBookings($cancelledBookings): array
    {
        $analysis = [
            'total_cancelled_bookings' => count($cancelledBookings),
            'total_original_value' => 0,     // Lo que valían cuando se crearon
            'money_to_process' => 0,         // Dinero que había que procesar
            'money_processed' => 0,          // Dinero ya procesado (refunds + no-refunds)
            'money_pending' => 0,            // Dinero aún sin procesar
            'processing_breakdown' => [
                'refunds_issued' => 0,
                'no_refunds_applied' => 0,
                'still_unprocessed' => 0
            ],
            'pending_processing_details' => []
        ];

        foreach ($cancelledBookings as $booking) {
            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);
            $originalValue = $quickAnalysis['calculated_amount'];
            $receivedAmount = $quickAnalysis['received_amount'];

            $analysis['total_original_value'] += $originalValue;
            $analysis['money_to_process'] += $receivedAmount; // Solo lo que realmente se había recibido

            // Analizar cómo se procesó el dinero recibido
            $refunds = $booking->payments->whereIn('status', ['refund', 'partial_refund'])->sum('amount');
            $noRefunds = $booking->payments->where('status', 'no_refund')->sum('amount');
            $processed = $refunds + $noRefunds;
            $pending = max(0, $receivedAmount - $processed);

            $analysis['processing_breakdown']['refunds_issued'] += $refunds;
            $analysis['processing_breakdown']['no_refunds_applied'] += $noRefunds;
            $analysis['processing_breakdown']['still_unprocessed'] += $pending;

            if ($pending > 0.50) {
                $analysis['pending_processing_details'][] = [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->clientMain->email ?? 'N/A',
                    'original_value' => round($originalValue, 2),
                    'money_received' => round($receivedAmount, 2),
                    'money_processed' => round($processed, 2),
                    'money_pending' => round($pending, 2),
                    'action_needed' => $pending > 10 ? 'urgent' : 'routine'
                ];
            }
        }

        // Redondear valores
        foreach (['total_original_value', 'money_to_process', 'money_processed'] as $key) {
            $analysis[$key] = round($analysis[$key], 2);
        }

        foreach ($analysis['processing_breakdown'] as $key => $value) {
            $analysis['processing_breakdown'][$key] = round($value, 2);
        }

        $analysis['money_processed'] = $analysis['processing_breakdown']['refunds_issued'] +
            $analysis['processing_breakdown']['no_refunds_applied'];
        $analysis['money_pending'] = $analysis['processing_breakdown']['still_unprocessed'];

        $analysis['processing_rate'] = $analysis['money_to_process'] > 0
            ? round(($analysis['money_processed'] / $analysis['money_to_process']) * 100, 2)
            : 100;

        return $analysis;
    }


    /**
     * MÉTODOS AUXILIARES ADICIONALES PARA EL DASHBOARD EJECUTIVO
     */

    /**
     * Análisis de tendencias de temporada
     */
    private function calculateSeasonTrends($bookings, array $dateRange): array
    {
        $trends = [
            'monthly_breakdown' => [],
            'weekly_pattern' => [],
            'booking_velocity' => [],
            'revenue_evolution' => []
        ];

        try {
            // Agrupar por meses
            $monthlyData = [];
            foreach ($bookings as $booking) {
                $month = Carbon::parse($booking->created_at)->format('Y-m');

                if (!isset($monthlyData[$month])) {
                    $monthlyData[$month] = [
                        'bookings' => 0,
                        'revenue' => 0,
                        'clients' => [],
                        'issues' => 0
                    ];
                }

                $monthlyData[$month]['bookings']++;
                $monthlyData[$month]['clients'][] = $booking->client_main_id;

                $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);
                $monthlyData[$month]['revenue'] += $quickAnalysis['calculated_amount'];

                if ($quickAnalysis['has_issues']) {
                    $monthlyData[$month]['issues']++;
                }
            }

            // Procesar datos mensuales
            foreach ($monthlyData as $month => $data) {
                $trends['monthly_breakdown'][] = [
                    'month' => $month,
                    'bookings' => $data['bookings'],
                    'revenue' => round($data['revenue'], 2),
                    'unique_clients' => count(array_unique($data['clients'])),
                    'issues' => $data['issues'],
                    'avg_booking_value' => $data['bookings'] > 0 ? round($data['revenue'] / $data['bookings'], 2) : 0
                ];
            }

            // Calcular velocidad de reservas (últimas 4 semanas)
            $recentBookings = $bookings->filter(function($booking) {
                return Carbon::parse($booking->created_at)->gt(Carbon::now()->subWeeks(4));
            });

            $trends['booking_velocity'] = [
                'recent_bookings_count' => $recentBookings->count(),
                'bookings_per_week' => round($recentBookings->count() / 4, 1),
                'trend_direction' => $this->calculateTrendDirection($monthlyData)
            ];

        } catch (\Exception $e) {
            Log::warning('Error calculando tendencias: ' . $e->getMessage());
            $trends['error'] = 'No se pudieron calcular las tendencias';
        }

        return $trends;
    }

    /**
     * Dirección de la tendencia
     */
    private function calculateTrendDirection(array $monthlyData): string
    {
        if (count($monthlyData) < 2) return 'insufficient_data';

        $months = array_keys($monthlyData);
        sort($months);

        $recent = array_slice($months, -2);
        $oldValue = $monthlyData[$recent[0]]['bookings'] ?? 0;
        $newValue = $monthlyData[$recent[1]]['bookings'] ?? 0;

        if ($newValue > $oldValue * 1.1) return 'increasing';
        if ($newValue < $oldValue * 0.9) return 'decreasing';
        return 'stable';
    }

    /**
     * Análisis de Payrexx para temporada
     */
    private function analyzeSeasonPayrexx($bookings, array $dateRange, array $classification): array
    {
        try {
            Log::info('Iniciando análisis de Payrexx con clasificación correcta', [
                'total_bookings' => $bookings->count(),
                'production_expected_revenue' => $classification['summary']['expected_revenue'],
                'date_range' => $dateRange
            ]);

            // Obtener análisis de Payrexx usando el nuevo método con clasificación
            $payrexxAnalysis = PayrexxHelpers::analyzeBookingsWithPayrexxExcludingTest(
                $bookings,
                $dateRange['start_date'],
                $dateRange['end_date']
            );

            // Resumen ejecutivo con expected correcto
            $executiveSummary = [
                'total_payrexx_transactions' => count($payrexxAnalysis['payrexx_transactions'] ?? []),
                'expected_system_amount' => $classification['summary']['expected_revenue'], // Solo expected real
                'total_payrexx_amount' => $payrexxAnalysis['production_payrexx_amount'] ?? 0, // Solo producción
                'test_transactions_excluded' => $payrexxAnalysis['test_bookings'] ?? 0,
                'cancelled_transactions_info' => $payrexxAnalysis['cancelled_bookings'] ?? 0,
                'consistency_rate' => 0,
                'discrepancies_found' => $payrexxAnalysis['total_discrepancies'] ?? 0,
                'health_status' => 'unknown'
            ];

            // Calcular tasa de consistencia basada en expected real
            if ($executiveSummary['expected_system_amount'] > 0) {
                $consistency = 100 - (abs($executiveSummary['expected_system_amount'] - $executiveSummary['total_payrexx_amount']) / $executiveSummary['expected_system_amount'] * 100);
                $executiveSummary['consistency_rate'] = round(max($consistency, 0), 2);
            }

            // Determinar estado de salud basado en expected
            if ($executiveSummary['consistency_rate'] >= 95) {
                $executiveSummary['health_status'] = 'excellent';
            } elseif ($executiveSummary['consistency_rate'] >= 85) {
                $executiveSummary['health_status'] = 'good';
            } elseif ($executiveSummary['consistency_rate'] >= 70) {
                $executiveSummary['health_status'] = 'fair';
            } else {
                $executiveSummary['health_status'] = 'poor';
            }

            return [
                'executive_summary' => $executiveSummary,
                'detailed_analysis' => $payrexxAnalysis,
                'classification_impact' => [
                    'expected_revenue_analyzed' => $classification['summary']['expected_revenue'],
                    'test_revenue_excluded' => $classification['summary']['test_revenue_excluded'],
                    'cancelled_revenue_separate' => $classification['summary']['cancelled_revenue_processed']
                ],
                'analysis_timestamp' => now()->toDateTimeString()
            ];

        } catch (\Exception $e) {
            Log::error('Error en análisis de Payrexx con clasificación correcta: ' . $e->getMessage());

            return [
                'executive_summary' => [
                    'expected_system_amount' => $classification['summary']['expected_revenue'],
                    'health_status' => 'error',
                    'error_message' => 'No se pudo conectar con Payrexx'
                ],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generar recomendaciones prioritarias ejecutivas
     */
    private function generatePriorityRecommendations(array $dashboard): array
    {
        $recommendations = [];

        // Recomendación de consistencia financiera
        $consistencyRate = $dashboard['executive_kpis']['consistency_rate'] ?? 100;
        if ($consistencyRate < 90) {
            $severity = $consistencyRate < 70 ? 'critical' : 'high';
            $inconsistentCount = $dashboard['executive_kpis']['consistency_issues'] ?? 0;

            $recommendations[] = [
                'priority' => $severity,
                'category' => 'financial_consistency',
                'title' => 'Mejorar Consistencia Financiera',
                'description' => "El {$consistencyRate}% de consistencia requiere atención inmediata",
                'impact' => $severity,
                'effort' => 'medium',
                'timeline' => '1-2 semanas',
                'actions' => [
                    "Revisar {$inconsistentCount} reservas con problemas",
                    'Implementar validaciones automáticas',
                    'Capacitar equipo en procesos financieros'
                ],
                'expected_benefit' => 'Reducir pérdidas y mejorar control financiero'
            ];
        }

        // Recomendación de cobros pendientes
        $revenueAtRisk = $dashboard['executive_kpis']['revenue_at_risk'] ?? 0;
        if ($revenueAtRisk > 500) {
            $recommendations[] = [
                'priority' => $revenueAtRisk > 2000 ? 'critical' : 'high',
                'category' => 'revenue_collection',
                'title' => 'Acelerar Cobros Pendientes',
                'description' => "Hay {$revenueAtRisk}€ pendientes de cobro",
                'impact' => 'high',
                'effort' => 'low',
                'timeline' => '1 semana',
                'actions' => [
                    'Contactar clientes con pagos pendientes',
                    'Enviar recordatorios automáticos',
                    'Revisar métodos de pago disponibles'
                ],
                'expected_benefit' => "Recuperar hasta {$revenueAtRisk}€ en ingresos"
            ];
        }

        // Recomendación de cancelaciones sin procesar
        $cancelledIssues = $dashboard['critical_issues']['cancelled_with_unprocessed_payments']['count'] ?? 0;
        if ($cancelledIssues > 5) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'cancellation_processing',
                'title' => 'Procesar Cancelaciones Pendientes',
                'description' => "Hay {$cancelledIssues} cancelaciones con pagos sin procesar",
                'impact' => 'medium',
                'effort' => 'medium',
                'timeline' => '2-3 días',
                'actions' => [
                    'Revisar política de reembolsos',
                    'Procesar refunds o aplicar no-refund',
                    'Notificar estados a clientes'
                ],
                'expected_benefit' => 'Clarificar situación financiera y mejorar satisfacción del cliente'
            ];
        }

        // Recomendación de transacciones de test
        $testTransactions = $dashboard['test_transactions_analysis']['total_test_transactions'] ?? 0;
        if ($testTransactions > 0 && env('APP_ENV') === 'production') {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'test_cleanup',
                'title' => 'Limpiar Transacciones de Test',
                'description' => "Se detectaron {$testTransactions} transacciones de test en producción",
                'impact' => 'low',
                'effort' => 'low',
                'timeline' => '1 día',
                'actions' => [
                    'Identificar y marcar transacciones de test',
                    'Reemplazar con transacciones reales si procede',
                    'Implementar validaciones para prevenir test en producción'
                ],
                'expected_benefit' => 'Datos más limpios y reportes más precisos'
            ];
        }

        // Ordenar por prioridad
        $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($recommendations, function($a, $b) use ($priorityOrder) {
            return $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
        });

        return array_slice($recommendations, 0, 5); // Top 5 recomendaciones
    }

    /**
     * 🆕 NUEVO: Agregar a la exportación CSV - estructura para sources
     */
    private function formatBookingSourcesForExport($sourcesData): array
    {
        $csvData = [['Source', 'Bookings', 'Percentage', 'Revenue', 'Avg Booking Value', 'Unique Clients', 'Consistency Rate']];

        foreach ($sourcesData['source_breakdown'] as $source) {
            $csvData[] = [
                $source['source'],
                $source['bookings'],
                $source['percentage'] . '%',
                number_format($source['revenue'], 2) . ' EUR',
                number_format($source['avg_booking_value'], 2) . ' EUR',
                $source['unique_clients'],
                $source['consistency_rate'] . '%'
            ];
        }

        return $csvData;
    }

    /**
     * 🆕 NUEVO: Agregar a la exportación CSV - estructura para métodos de pago mejorados
     */
    private function formatPaymentMethodsForExport($paymentData): array
    {
        $csvData = [['Payment Method', 'Count', 'Count %', 'Revenue', 'Revenue %', 'Avg Amount']];

        foreach ($paymentData['methods'] as $method) {
            $csvData[] = [
                $method['display_name'],
                $method['count'],
                $method['percentage'] . '%',
                number_format($method['revenue'], 2) . ' EUR',
                $method['revenue_percentage'] . '%',
                number_format($method['avg_payment_amount'], 2) . ' EUR'
            ];
        }

        // Agregar resumen online vs offline
        $csvData[] = ['', '', '', '', '', '']; // Línea vacía
        $csvData[] = ['=== ONLINE VS OFFLINE ===', '', '', '', '', ''];
        $csvData[] = [
            'Online Total',
            $paymentData['online_vs_offline']['online']['count'],
            $paymentData['online_vs_offline']['online']['count_percentage'] . '%',
            number_format($paymentData['online_vs_offline']['online']['revenue'], 2) . ' EUR',
            $paymentData['online_vs_offline']['online']['revenue_percentage'] . '%',
            ''
        ];
        $csvData[] = [
            'Offline Total',
            $paymentData['online_vs_offline']['offline']['count'],
            $paymentData['online_vs_offline']['offline']['count_percentage'] . '%',
            number_format($paymentData['online_vs_offline']['offline']['revenue'], 2) . ' EUR',
            $paymentData['online_vs_offline']['offline']['revenue_percentage'] . '%',
            ''
        ];

        return $csvData;
    }

    /**
     * Calcular resumen financiero completo
     */
    private function calculateFinancialSummary($productionBookings, string $optimizationLevel): array
    {
        $summary = [
            'revenue_breakdown' => [
                'total_expected' => 0,        // Solo lo que realmente esperamos
                'total_received' => 0,        // De las reservas que esperamos cobrar
                'total_pending' => 0,         // Aún por cobrar de expected
                'total_refunded' => 0         // Solo de producción
            ],
            'expected_vs_reality' => [
                'expected_accuracy' => 0,     // Qué tan preciso es nuestro expected
                'collection_velocity' => 0,   // Velocidad de cobro
                'pending_risk_level' => 'low' // Nivel de riesgo de lo pendiente
            ],
            'payment_methods' => [],
            'voucher_usage' => [
                'total_vouchers_used' => 0,
                'total_voucher_amount' => 0,
                'unique_vouchers' => 0
            ],
            'booking_value_distribution' => [
                'under_100' => 0,
                'between_100_500' => 0,
                'between_500_1000' => 0,
                'over_1000' => 0
            ],
            'consistency_metrics' => [
                'consistent_bookings' => 0,
                'inconsistent_bookings' => 0,
                'consistency_rate' => 0,
                'major_discrepancies' => 0    // Discrepancias > 20€
            ]
        ];

        $paymentMethodCounts = [];
        $voucherCodes = [];
        $consistentCount = 0;
        $majorDiscrepancies = 0;

        foreach ($productionBookings as $booking) {
            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

            if ($booking->status == 3) {
                // Para parciales, calcular proporcionalmente
                $activeRevenue = $this->calculateActivePortionRevenue($booking);
                $activeProportion = $activeRevenue > 0 ? $activeRevenue / $quickAnalysis['calculated_amount'] : 0;

                $summary['revenue_breakdown']['total_expected'] += $activeRevenue;
                $summary['revenue_breakdown']['total_received'] += $quickAnalysis['received_amount'] * $activeProportion;
            } else {
                // Para activas, contar todo
                $summary['revenue_breakdown']['total_expected'] += $quickAnalysis['calculated_amount'];
                $summary['revenue_breakdown']['total_received'] += $quickAnalysis['received_amount'];
            }

            // Contabilizar consistencia
            if (!$quickAnalysis['has_issues']) {
                $consistentCount++;
            } else {
                // Verificar si es discrepancia mayor
                $difference = abs($quickAnalysis['calculated_amount'] - $quickAnalysis['received_amount']);
                if ($difference > 20) {
                    $majorDiscrepancies++;
                }
            }

            // Distribución por valor (usar expected, no total)
            $expectedValue = $booking->status == 3
                ? $this->calculateActivePortionRevenue($booking)
                : $quickAnalysis['calculated_amount'];

            if ($expectedValue < 100) {
                $summary['booking_value_distribution']['under_100']++;
            } elseif ($expectedValue < 500) {
                $summary['booking_value_distribution']['between_100_500']++;
            } elseif ($expectedValue < 1000) {
                $summary['booking_value_distribution']['between_500_1000']++;
            } else {
                $summary['booking_value_distribution']['over_1000']++;
            }

            // Métodos de pago (solo si no hay muchos para performance)
            if ($optimizationLevel === 'detailed' || count($paymentMethodCounts) < 100) {
                foreach ($booking->payments as $payment) {
                    $method = $this->determinePaymentMethodImproved($payment);
                    $paymentMethodCounts[$method] = ($paymentMethodCounts[$method] ?? 0) + 1;

                    if ($payment->status === 'refund') {
                        $summary['revenue_breakdown']['total_refunded'] += $payment->amount;
                    }
                }
            }

            // Análisis de vouchers
            foreach ($booking->vouchersLogs as $voucherLog) {
                $summary['voucher_usage']['total_voucher_amount'] += $voucherLog->amount;
                $summary['voucher_usage']['total_vouchers_used']++;

                if ($voucherLog->voucher && $voucherLog->voucher->code) {
                    $voucherCodes[] = $voucherLog->voucher->code;
                }
            }
        }

        // Calcular métricas finales
        $totalBookings = count($productionBookings);
        $summary['consistency_metrics']['consistent_bookings'] = $consistentCount;
        $summary['consistency_metrics']['inconsistent_bookings'] = $totalBookings - $consistentCount;
        $summary['consistency_metrics']['major_discrepancies'] = $majorDiscrepancies;
        $summary['consistency_metrics']['consistency_rate'] = $totalBookings > 0
            ? round(($consistentCount / $totalBookings) * 100, 2) : 100;

        $summary['revenue_breakdown']['total_pending'] = $summary['revenue_breakdown']['total_expected'] - $summary['revenue_breakdown']['total_received'];

        // Métricas de expected vs realidad
        $summary['expected_vs_reality']['expected_accuracy'] = $summary['revenue_breakdown']['total_expected'] > 0
            ? round(($summary['revenue_breakdown']['total_received'] / $summary['revenue_breakdown']['total_expected']) * 100, 2)
            : 100;

        $summary['expected_vs_reality']['collection_velocity'] = $this->calculateCollectionVelocity($productionBookings);
        $summary['expected_vs_reality']['pending_risk_level'] = $this->assessPendingRiskLevel($summary['revenue_breakdown']['total_pending'], $summary['revenue_breakdown']['total_expected']);

        $summary['payment_methods'] = $paymentMethodCounts;
        $summary['voucher_usage']['unique_vouchers'] = count(array_unique($voucherCodes));

        // Redondear valores
        foreach ($summary['revenue_breakdown'] as $key => $value) {
            $summary['revenue_breakdown'][$key] = round($value, 2);
        }
        $summary['voucher_usage']['total_voucher_amount'] = round($summary['voucher_usage']['total_voucher_amount'], 2);

        return $summary;
    }

    /**
     * MÉTODOS AUXILIARES NUEVOS
     */
    private function calculateCollectionVelocity($productionBookings): string
    {
        $recentBookings = array_filter($productionBookings, function($booking) {
            return Carbon::parse($booking->created_at)->gt(Carbon::now()->subDays(30));
        });

        if (empty($recentBookings)) return 'no_recent_data';

        $totalExpected = 0;
        $totalReceived = 0;

        foreach ($recentBookings as $booking) {
            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

            if ($booking->status == 3) {
                $activeRevenue = $this->calculateActivePortionRevenue($booking);
                $activeProportion = $activeRevenue > 0 ? $activeRevenue / $quickAnalysis['calculated_amount'] : 0;
                $totalExpected += $activeRevenue;
                $totalReceived += $quickAnalysis['received_amount'] * $activeProportion;
            } else {
                $totalExpected += $quickAnalysis['calculated_amount'];
                $totalReceived += $quickAnalysis['received_amount'];
            }
        }

        $velocity = $totalExpected > 0 ? ($totalReceived / $totalExpected) * 100 : 100;

        if ($velocity >= 90) return 'fast';
        if ($velocity >= 70) return 'moderate';
        return 'slow';
    }

    private function assessPendingRiskLevel($pendingAmount, $expectedAmount): string
    {
        if ($expectedAmount <= 0) return 'none';

        $pendingPercentage = ($pendingAmount / $expectedAmount) * 100;

        if ($pendingPercentage > 30) return 'high';
        if ($pendingPercentage > 15) return 'medium';
        return 'low';
    }


    /**
     * ENDPOINT: Exportar Dashboard de Temporada
     * GET /api/admin/finance/season-dashboard/export
     */
    public function exportSeasonDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'season_id' => 'nullable|integer|exists:seasons,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'required|in:csv,pdf,excel',
            'sections' => 'nullable|array',
            'sections.*' => 'in:executive_summary,financial_kpis,booking_analysis,critical_issues,test_analysis,payrexx_analysis'
        ]);

        try {
            // 1. Obtener datos del dashboard
            $dashboardRequest = new Request($request->except(['format', 'sections']));
            $dashboardResponse = $this->getSeasonFinancialDashboard($dashboardRequest);
            $dashboardData = json_decode($dashboardResponse->content(), true)['data'];

            // 2. Preparar datos para exportación
            $exportData = $this->prepareExportData($dashboardData, $request);

            // 3. Generar archivo según formato
            switch ($request->input('format')) {
                case 'csv':
                    return $this->generateCsvExport($exportData, $dashboardData);
                case 'pdf':
                    return $this->generatePdfExport($exportData, $dashboardData);
                case 'excel':
                    return $this->generateExcelExport($exportData, $dashboardData);
                default:
                    return $this->sendError('Formato de exportación no soportado', 422);
            }

        } catch (\Exception $e) {
            Log::error('Error exportando dashboard: ' . $e->getMessage());
            return $this->sendError('Error en exportación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Preparar datos estructurados para exportación
     */
    private function prepareExportData(array $dashboardData, Request $request): array
    {
        $sections = $request->get('sections', ['executive_summary', 'financial_kpis', 'critical_issues']);
        $exportData = [
            'metadata' => [
                'school_id' => $request->school_id,
                'export_date' => now()->format('Y-m-d H:i:s'),
                'period' => $dashboardData['season_info']['date_range'],
                'total_bookings' => $dashboardData['season_info']['total_bookings']
            ],
            'sections' => []
        ];

        // Sección: Resumen Ejecutivo
        if (in_array('executive_summary', $sections)) {
            $exportData['sections']['executive_summary'] = [
                'title' => 'Resumen Ejecutivo de Temporada',
                'data' => [
                    ['Métrica', 'Valor', 'Unidad'],
                    ['Período', $dashboardData['season_info']['date_range']['start'] . ' a ' . $dashboardData['season_info']['date_range']['end'], ''],
                    ['Total Reservas', $dashboardData['executive_kpis']['total_bookings'], 'reservas'],
                    ['Total Clientes', $dashboardData['executive_kpis']['total_clients'], 'clientes'],
                    ['Total Participantes', $dashboardData['executive_kpis']['total_participants'], 'personas'],
                    ['Ingresos Esperados', number_format($dashboardData['executive_kpis']['revenue_expected'], 2), 'EUR'],
                    ['Ingresos Recibidos', number_format($dashboardData['executive_kpis']['revenue_received'], 2), 'EUR'],
                    ['Dinero Pendiente', number_format($dashboardData['executive_kpis']['revenue_pending'], 2), 'EUR'],
                    ['Eficiencia de Cobro', $dashboardData['executive_kpis']['collection_efficiency'], '%'],
                    ['Consistencia Financiera', $dashboardData['executive_kpis']['consistency_rate'], '%'],
                    ['Valor Promedio por Reserva', number_format($dashboardData['executive_kpis']['average_booking_value'], 2), 'EUR']
                ]
            ];
        }

        // Sección: KPIs Financieros
        if (in_array('financial_kpis', $sections)) {
            $exportData['sections']['financial_kpis'] = [
                'title' => 'Análisis Financiero Detallado',
                'data' => $this->formatFinancialKpisForExport($dashboardData['financial_summary'] ?? [])
            ];
        }

        // Sección: Análisis por Estado
        if (in_array('booking_analysis', $sections)) {
            $exportData['sections']['booking_analysis'] = [
                'title' => 'Distribución por Estado de Reserva',
                'data' => $this->formatBookingAnalysisForExport($dashboardData['booking_status_analysis'] ?? [])
            ];
        }

        // Sección: Problemas Críticos
        if (in_array('critical_issues', $sections)) {
            $exportData['sections']['critical_issues'] = [
                'title' => 'Problemas Críticos Detectados',
                'data' => $this->formatCriticalIssuesForExport($dashboardData['critical_issues'] ?? [])
            ];
        }

        // Sección: Análisis de Test
        if (in_array('test_analysis', $sections) && isset($dashboardData['test_transactions_analysis'])) {
            $exportData['sections']['test_analysis'] = [
                'title' => 'Análisis de Transacciones de Test',
                'data' => $this->formatTestAnalysisForExport($dashboardData['test_transactions_analysis'])
            ];
        }

        // Sección: Análisis de Payrexx
        if (in_array('payrexx_analysis', $sections) && isset($dashboardData['payrexx_analysis'])) {
            $exportData['sections']['payrexx_analysis'] = [
                'title' => 'Análisis de Consistencia con Payrexx',
                'data' => $this->formatPayrexxAnalysisForExport($dashboardData['payrexx_analysis'])
            ];
        }

        if (in_array('booking_sources', $sections)) {
            $exportData['sections']['booking_sources'] = [
                'title' => 'Análisis de Orígenes de Reservas',
                'data' => $this->formatBookingSourcesForExport($dashboardData['booking_sources'] ?? [])
            ];
        }

// Sección: Métodos de Pago Mejorados
        if (in_array('payment_methods', $sections)) {
            $exportData['sections']['payment_methods'] = [
                'title' => 'Análisis Detallado de Métodos de Pago',
                'data' => $this->formatPaymentMethodsForExport($dashboardData['financial_summary']['payment_methods'] ?? [])
            ];
        }

        return $exportData;
    }

    private function generateCsvExport(array $exportData, array $dashboardData): JsonResponse
    {
        // Usar el método mejorado con clasificación
        return $this->generateCsvExportWithClassification($exportData, $dashboardData);
    }

    /**
     * Generar exportación CSV
     */
    private function generateCsvExportold(array $exportData, array $dashboardData): JsonResponse
    {
        $csvContent = '';
        $filename = 'dashboard_temporada_' . $exportData['metadata']['school_id'] . '_' . date('Y-m-d_H-i') . '.csv';

        // BOM para soporte UTF-8 en Excel
        $csvContent .= "\xEF\xBB\xBF";

        // Encabezado del archivo
        $csvContent .= "DASHBOARD EJECUTIVO DE TEMPORADA\n";
        $csvContent .= "Escuela ID:," . $exportData['metadata']['school_id'] . "\n";
        $csvContent .= "Período:," . $exportData['metadata']['period']['start'] . " a " . $exportData['metadata']['period']['end'] . "\n";
        $csvContent .= "Total Reservas:," . $exportData['metadata']['total_bookings'] . "\n";
        $csvContent .= "Generado:," . $exportData['metadata']['export_date'] . "\n\n";

        // Procesar cada sección
        foreach ($exportData['sections'] as $sectionKey => $section) {
            $csvContent .= strtoupper($section['title']) . "\n";

            foreach ($section['data'] as $row) {
                // Escapar comillas y agregar comillas a cada campo
                $escapedRow = array_map(function($field) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }, $row);
                $csvContent .= implode(',', $escapedRow) . "\n";
            }

            $csvContent .= "\n";
        }

        // Sección de alertas ejecutivas
        if (isset($dashboardData['executive_alerts']) && !empty($dashboardData['executive_alerts'])) {
            $csvContent .= "ALERTAS EJECUTIVAS\n";
            $csvContent .= '"Nivel","Tipo","Título","Descripción","Impacto"' . "\n";

            foreach ($dashboardData['executive_alerts'] as $alert) {
                $row = [
                    $alert['level'] ?? '',
                    $alert['type'] ?? '',
                    $alert['title'] ?? '',
                    $alert['description'] ?? '',
                    $alert['impact'] ?? ''
                ];
                $escapedRow = array_map(function($field) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }, $row);
                $csvContent .= implode(',', $escapedRow) . "\n";
            }
            $csvContent .= "\n";
        }

        // Sección de recomendaciones
        if (isset($dashboardData['priority_recommendations']) && !empty($dashboardData['priority_recommendations'])) {
            $csvContent .= "RECOMENDACIONES PRIORITARIAS\n";
            $csvContent .= '"Prioridad","Categoría","Título","Descripción","Impacto","Plazo","Acciones"' . "\n";

            foreach ($dashboardData['priority_recommendations'] as $rec) {
                $actions = isset($rec['actions']) && is_array($rec['actions'])
                    ? implode('; ', $rec['actions'])
                    : '';

                $row = [
                    $rec['priority'] ?? '',
                    $rec['category'] ?? '',
                    $rec['title'] ?? '',
                    $rec['description'] ?? '',
                    $rec['impact'] ?? '',
                    $rec['timeline'] ?? '',
                    $actions
                ];
                $escapedRow = array_map(function($field) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }, $row);
                $csvContent .= implode(',', $escapedRow) . "\n";
            }
        }

        // Crear archivo temporal o devolver contenido
        $tempPath = storage_path('temp/' . $filename);

        // Asegurar que el directorio existe
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        file_put_contents($tempPath, $csvContent);

        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $filename,
                'file_path' => $tempPath,
                'content_type' => 'text/csv; charset=utf-8',
                'size' => strlen($csvContent),
                'download_url' => route('finance.download-export', ['filename' => $filename])
            ],
            'message' => 'Exportación CSV generada exitosamente'
        ]);
    }


    /**
     * Formatear KPIs financieros para exportación
     */
    private function formatFinancialKpisForExport(array $financialSummary): array
    {
        $data = [['Concepto', 'Valor', 'Unidad']];

        if (isset($financialSummary['revenue_breakdown'])) {
            $rb = $financialSummary['revenue_breakdown'];
            $data[] = ['Ingresos Esperados', number_format($rb['total_expected'], 2), 'EUR'];
            $data[] = ['Ingresos Recibidos', number_format($rb['total_received'], 2), 'EUR'];
            $data[] = ['Dinero Pendiente', number_format($rb['total_pending'], 2), 'EUR'];
            $data[] = ['Total Reembolsado', number_format($rb['total_refunded'], 2), 'EUR'];
        }

        if (isset($financialSummary['voucher_usage'])) {
            $vu = $financialSummary['voucher_usage'];
            $data[] = ['Vouchers Utilizados', $vu['total_vouchers_used'], 'vouchers'];
            $data[] = ['Importe Total Vouchers', number_format($vu['total_voucher_amount'], 2), 'EUR'];
            $data[] = ['Vouchers Únicos', $vu['unique_vouchers'], 'códigos'];
        }

        return $data;
    }

    /**
     * Formatear análisis de reservas para exportación
     */
    private function formatBookingAnalysisForExport(array $bookingAnalysis): array
    {
        $data = [['Estado', 'Cantidad', 'Porcentaje', 'Ingresos', 'Problemas']];

        foreach ($bookingAnalysis as $status => $stats) {
            $data[] = [
                ucfirst(str_replace('_', ' ', $status)),
                $stats['count'],
                $stats['percentage'] . '%',
                number_format($stats['revenue'], 2) . ' EUR',
                $stats['issues']
            ];
        }

        return $data;
    }

    /**
     * Formatear problemas críticos para exportación
     */
    private function formatCriticalIssuesForExport(array $criticalIssues): array
    {
        $data = [['Tipo de Problema', 'Cantidad', 'Booking ID', 'Cliente', 'Importe', 'Descripción']];

        foreach ($criticalIssues as $issueType => $issueData) {
            if (isset($issueData['items']) && !empty($issueData['items'])) {
                foreach ($issueData['items'] as $item) {
                    $data[] = [
                        str_replace('_', ' ', ucfirst($issueType)),
                        $issueData['count'],
                        $item['booking_id'] ?? 'N/A',
                        $item['client_email'] ?? 'N/A',
                        isset($item['difference_amount']) ? number_format($item['difference_amount'], 2) . ' EUR' :
                            (isset($item['unprocessed_amount']) ? number_format($item['unprocessed_amount'], 2) . ' EUR' : 'N/A'),
                        $this->getIssueDescription($issueType, $item)
                    ];
                }
            } else {
                $data[] = [
                    str_replace('_', ' ', ucfirst($issueType)),
                    $issueData['count'] ?? 0,
                    'N/A',
                    'N/A',
                    'N/A',
                    'No se encontraron problemas de este tipo'
                ];
            }
        }

        return $data;
    }

    /**
     * Formatear análisis de test para exportación
     */
    private function formatTestAnalysisForExport(array $testAnalysis): array
    {
        $data = [
            ['Métrica de Test', 'Valor'],
            ['Reservas con Test', $testAnalysis['total_bookings_with_test']],
            ['Total Transacciones Test', $testAnalysis['total_test_transactions']],
            ['Importe Total Test', number_format($testAnalysis['test_amount_total'], 2) . ' EUR'],
            ['Confianza Alta', $testAnalysis['confidence_distribution']['high']],
            ['Confianza Media', $testAnalysis['confidence_distribution']['medium']],
            ['Confianza Baja', $testAnalysis['confidence_distribution']['low']]
        ];

        // Añadir indicadores más comunes
        if (!empty($testAnalysis['test_indicators_summary'])) {
            $data[] = ['', '']; // Fila vacía
            $data[] = ['Indicadores Principales', 'Frecuencia'];

            $indicators = $testAnalysis['test_indicators_summary'];
            arsort($indicators);

            foreach (array_slice($indicators, 0, 5) as $indicator => $count) {
                $data[] = [str_replace('_', ' ', ucfirst($indicator)), $count];
            }
        }

        return $data;
    }

    /**
     * Formatear análisis de Payrexx para exportación
     */
    private function formatPayrexxAnalysisForExport(array $payrexxAnalysis): array
    {
        $summary = $payrexxAnalysis['executive_summary'] ?? [];

        return [
            ['Métrica Payrexx', 'Valor'],
            ['Transacciones Payrexx', $summary['total_payrexx_transactions'] ?? 0],
            ['Importe Sistema', number_format($summary['total_system_amount'] ?? 0, 2) . ' EUR'],
            ['Importe Payrexx', number_format($summary['total_payrexx_amount'] ?? 0, 2) . ' EUR'],
            ['Tasa Consistencia', ($summary['consistency_rate'] ?? 0) . '%'],
            ['Discrepancias', $summary['discrepancies_found'] ?? 0],
            ['Transacciones No Coincidentes', $summary['unmatched_transactions'] ?? 0],
            ['Estado General', ucfirst($summary['health_status'] ?? 'unknown')]
        ];
    }


    /**
     * Generar exportación Excel usando PhpSpreadsheet
     */
    private function generateExcelExport(array $exportData, array $dashboardData): JsonResponse
    {
        try {
            // Verificar si PhpSpreadsheet está disponible
            if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                return $this->sendError('PhpSpreadsheet no está instalado. Use: composer require phpoffice/phpspreadsheet', 500);
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Dashboard Ejecutivo');

            $currentRow = 1;

            // Encabezado del archivo
            $sheet->setCellValue('A' . $currentRow, 'DASHBOARD EJECUTIVO DE TEMPORADA');
            $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(16);
            $currentRow += 2;

            $sheet->setCellValue('A' . $currentRow, 'Escuela ID:');
            $sheet->setCellValue('B' . $currentRow, $exportData['metadata']['school_id']);
            $currentRow++;

            $sheet->setCellValue('A' . $currentRow, 'Período:');
            $sheet->setCellValue('B' . $currentRow, $exportData['metadata']['period']['start'] . ' a ' . $exportData['metadata']['period']['end']);
            $currentRow++;

            $sheet->setCellValue('A' . $currentRow, 'Total Reservas:');
            $sheet->setCellValue('B' . $currentRow, $exportData['metadata']['total_bookings']);
            $currentRow++;

            $sheet->setCellValue('A' . $currentRow, 'Generado:');
            $sheet->setCellValue('B' . $currentRow, $exportData['metadata']['export_date']);
            $currentRow += 2;

            // Procesar cada sección
            foreach ($exportData['sections'] as $sectionKey => $section) {
                // Título de sección
                $sheet->setCellValue('A' . $currentRow, strtoupper($section['title']));
                $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A' . $currentRow . ':' . $this->getColumnLetter(count($section['data'][0] ?? [1])) . $currentRow)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E6E6E6');
                $currentRow++;

                // Datos de la sección
                foreach ($section['data'] as $rowIndex => $row) {
                    $col = 'A';
                    foreach ($row as $cell) {
                        $sheet->setCellValue($col . $currentRow, $cell);

                        // Hacer negrita la primera fila (encabezados)
                        if ($rowIndex === 0) {
                            $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
                        }

                        $col++;
                    }
                    $currentRow++;
                }
                $currentRow++; // Espacio entre secciones
            }

            // Agregar alertas si existen
            if (isset($dashboardData['executive_alerts']) && !empty($dashboardData['executive_alerts'])) {
                $sheet->setCellValue('A' . $currentRow, 'ALERTAS EJECUTIVAS');
                $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
                $currentRow++;

                // Encabezados de alertas
                $alertHeaders = ['Nivel', 'Tipo', 'Título', 'Descripción', 'Impacto'];
                $col = 'A';
                foreach ($alertHeaders as $header) {
                    $sheet->setCellValue($col . $currentRow, $header);
                    $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
                    $col++;
                }
                $currentRow++;

                // Datos de alertas
                foreach ($dashboardData['executive_alerts'] as $alert) {
                    $sheet->setCellValue('A' . $currentRow, $alert['level'] ?? '');
                    $sheet->setCellValue('B' . $currentRow, $alert['type'] ?? '');
                    $sheet->setCellValue('C' . $currentRow, $alert['title'] ?? '');
                    $sheet->setCellValue('D' . $currentRow, $alert['description'] ?? '');
                    $sheet->setCellValue('E' . $currentRow, $alert['impact'] ?? '');
                    $currentRow++;
                }
                $currentRow++;
            }

            // Agregar recomendaciones si existen
            if (isset($dashboardData['priority_recommendations']) && !empty($dashboardData['priority_recommendations'])) {
                $sheet->setCellValue('A' . $currentRow, 'RECOMENDACIONES PRIORITARIAS');
                $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
                $currentRow++;

                // Encabezados de recomendaciones
                $recHeaders = ['Prioridad', 'Categoría', 'Título', 'Descripción', 'Impacto', 'Plazo'];
                $col = 'A';
                foreach ($recHeaders as $header) {
                    $sheet->setCellValue($col . $currentRow, $header);
                    $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
                    $col++;
                }
                $currentRow++;

                // Datos de recomendaciones
                foreach ($dashboardData['priority_recommendations'] as $rec) {
                    $sheet->setCellValue('A' . $currentRow, $rec['priority'] ?? '');
                    $sheet->setCellValue('B' . $currentRow, $rec['category'] ?? '');
                    $sheet->setCellValue('C' . $currentRow, $rec['title'] ?? '');
                    $sheet->setCellValue('D' . $currentRow, $rec['description'] ?? '');
                    $sheet->setCellValue('E' . $currentRow, $rec['impact'] ?? '');
                    $sheet->setCellValue('F' . $currentRow, $rec['timeline'] ?? '');
                    $currentRow++;
                }
            }

            // Ajustar ancho de columnas
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Guardar archivo
            $filename = 'dashboard_temporada_' . $exportData['metadata']['school_id'] . '_' . date('Y-m-d_H-i') . '.xlsx';
            $tempPath = storage_path('temp/' . $filename);

            // Asegurar que el directorio existe
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'file_path' => $tempPath,
                    'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'size' => filesize($tempPath),
                    'download_url' => route('finance.download-export', ['filename' => $filename])
                ],
                'message' => 'Exportación Excel generada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generando Excel: ' . $e->getMessage());

            // Fallback a CSV si Excel falla
            $csvResponse = $this->generateCsvExport($exportData, $dashboardData);
            $csvData = json_decode($csvResponse->content(), true)['data'];

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => str_replace('.xlsx', '.csv', $filename ?? 'export.csv'),
                    'content_type' => 'text/csv',
                    'fallback' => true,
                    'message' => 'Excel falló, generando CSV como alternativa',
                    'csv_data' => $csvData
                ],
                'message' => 'Exportación generada como CSV (Excel no disponible)'
            ]);
        }
    }

    /**
     * Generar exportación PDF usando DomPDF
     */
    private function generatePdfExport(array $exportData, array $dashboardData): JsonResponse
    {
        try {
            // Verificar si DomPDF está disponible
            if (!class_exists('\Dompdf\Dompdf')) {
                return $this->sendError('DomPDF no está instalado. Use: composer require dompdf/dompdf', 500);
            }

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->set_option('isHtml5ParserEnabled', true);
            $dompdf->set_option('isRemoteEnabled', true);

            // Generar HTML para el PDF
            $html = $this->generatePdfHtml($exportData, $dashboardData);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Guardar archivo
            $filename = 'dashboard_temporada_' . $exportData['metadata']['school_id'] . '_' . date('Y-m-d_H-i') . '.pdf';
            $tempPath = storage_path('temp/' . $filename);

            // Asegurar que el directorio existe
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            file_put_contents($tempPath, $dompdf->output());

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'file_path' => $tempPath,
                    'content_type' => 'application/pdf',
                    'size' => filesize($tempPath),
                    'download_url' => route('finance.download-export', ['filename' => $filename])
                ],
                'message' => 'Exportación PDF generada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generando PDF: ' . $e->getMessage());

            // Fallback a CSV si PDF falla
            return response()->json([
                'success' => false,
                'data' => [
                    'error' => 'No se pudo generar el PDF',
                    'details' => $e->getMessage(),
                    'alternative' => 'Use formato CSV o Excel'
                ],
                'message' => 'Error generando PDF - use CSV como alternativa'
            ]);
        }
    }

    /**
     * Generar HTML para PDF
     */
    private function generatePdfHtml(array $exportData, array $dashboardData): string
    {
        $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Dashboard Ejecutivo de Temporada</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #2c3e50; margin-bottom: 10px; }
            .metadata { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .section { margin-bottom: 25px; page-break-inside: avoid; }
            .section-title { background-color: #34495e; color: white; padding: 10px; margin-bottom: 10px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
            .alert-critical { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
            .alert-warning { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
            .alert-info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
            .recommendation { background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px; }
            .footer { margin-top: 30px; text-align: center; color: #666; font-size: 10px; }
            .page-break { page-break-before: always; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Dashboard Ejecutivo de Temporada</h1>
            <p>Análisis Financiero Completo</p>
        </div>

        <div class="metadata">
            <strong>Escuela ID:</strong> ' . $exportData['metadata']['school_id'] . '<br>
            <strong>Período:</strong> ' . $exportData['metadata']['period']['start'] . ' a ' . $exportData['metadata']['period']['end'] . '<br>
            <strong>Total Reservas:</strong> ' . $exportData['metadata']['total_bookings'] . '<br>
            <strong>Generado:</strong> ' . $exportData['metadata']['export_date'] . '
        </div>';

        // Agregar cada sección
        foreach ($exportData['sections'] as $sectionKey => $section) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">' . strtoupper($section['title']) . '</div>';

            if (!empty($section['data'])) {
                $html .= '<table>';

                foreach ($section['data'] as $rowIndex => $row) {
                    $html .= '<tr>';
                    foreach ($row as $cell) {
                        $tag = $rowIndex === 0 ? 'th' : 'td';
                        $html .= '<' . $tag . '>' . htmlspecialchars($cell) . '</' . $tag . '>';
                    }
                    $html .= '</tr>';
                }

                $html .= '</table>';
            }

            $html .= '</div>';
        }

        // Agregar alertas si existen
        if (isset($dashboardData['executive_alerts']) && !empty($dashboardData['executive_alerts'])) {
            $html .= '<div class="page-break"></div>';
            $html .= '<div class="section">';
            $html .= '<div class="section-title">ALERTAS EJECUTIVAS</div>';

            foreach ($dashboardData['executive_alerts'] as $alert) {
                $alertClass = 'alert-info';
                if (($alert['level'] ?? '') === 'critical') $alertClass = 'alert-critical';
                elseif (($alert['level'] ?? '') === 'warning') $alertClass = 'alert-warning';

                $html .= '<div class="alert ' . $alertClass . '">';
                $html .= '<strong>' . ($alert['title'] ?? '') . '</strong><br>';
                $html .= $alert['description'] ?? '';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        // Agregar recomendaciones si existen
        if (isset($dashboardData['priority_recommendations']) && !empty($dashboardData['priority_recommendations'])) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">RECOMENDACIONES PRIORITARIAS</div>';

            foreach ($dashboardData['priority_recommendations'] as $rec) {
                $html .= '<div class="recommendation">';
                $html .= '<strong>Prioridad ' . strtoupper($rec['priority'] ?? '') . ': ' . ($rec['title'] ?? '') . '</strong><br>';
                $html .= '<strong>Categoría:</strong> ' . ($rec['category'] ?? '') . '<br>';
                $html .= '<strong>Descripción:</strong> ' . ($rec['description'] ?? '') . '<br>';
                $html .= '<strong>Impacto:</strong> ' . ($rec['impact'] ?? '') . '<br>';
                $html .= '<strong>Plazo:</strong> ' . ($rec['timeline'] ?? '') . '<br>';

                if (isset($rec['actions']) && is_array($rec['actions'])) {
                    $html .= '<strong>Acciones:</strong><ul>';
                    foreach ($rec['actions'] as $action) {
                        $html .= '<li>' . htmlspecialchars($action) . '</li>';
                    }
                    $html .= '</ul>';
                }

                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '
        <div class="footer">
            <p>Reporte generado automáticamente - ' . date('Y-m-d H:i:s') . '</p>
        </div>
    </body>
    </html>';

        return $html;
    }

    /**
     * Método auxiliar para obtener letra de columna Excel
     */
    private function getColumnLetter($index): string
    {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intval($index / 26);
        }
        return $letters;
    }

    /**
     * ENDPOINT: Descargar archivo exportado
     * GET /api/admin/finance/download-export/{filename}
     */
    public function downloadExport($filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filePath = storage_path('temp/' . $filename);

        if (!file_exists($filePath)) {
            abort(404, 'Archivo no encontrado');
        }

        // Determinar content type basado en extensión
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $contentTypes = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf'
        ];

        $contentType = $contentTypes[$extension] ?? 'application/octet-stream';

        return response()->download($filePath, $filename, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ])->deleteFileAfterSend(true);
    }

    /**
     * ENDPOINT: Limpiar archivos temporales antiguos
     */
    public function cleanTempFiles(): JsonResponse
    {
        try {
            $tempDir = storage_path('temp');
            $cleaned = 0;

            if (is_dir($tempDir)) {
                $files = glob($tempDir . '/*');
                $now = time();

                foreach ($files as $file) {
                    if (is_file($file)) {
                        // Eliminar archivos de más de 1 hora
                        if ($now - filemtime($file) > 3600) {
                            unlink($file);
                            $cleaned++;
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'files_cleaned' => $cleaned,
                    'temp_directory' => $tempDir
                ],
                'message' => "Se eliminaron {$cleaned} archivos temporales"
            ]);

        } catch (\Exception $e) {
            return $this->sendError('Error limpiando archivos temporales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * MÉTODO AUXILIAR: Calcular KPIs ejecutivos principales
     */
    private function calculateExecutiveKpis($bookings, Request $request): array
    {
        $stats = [
            'total_bookings' => $bookings->count(),
            'total_clients' => $bookings->pluck('client_main_id')->unique()->count(),
            'total_participants' => $bookings->sum(function($booking) {
                return $booking->bookingUsers->count();
            }),
            'revenue_expected' => 0,
            'revenue_received' => 0,
            'revenue_pending' => 0,
            'financial_health_score' => 0,
            'consistency_rate' => 0,
            'test_transactions_detected' => 0,
            'payrexx_consistency_rate' => null
        ];

        // Análisis financiero optimizado
        $financialStats = $this->calculateQuickFinancialStats($bookings);
        $stats = array_merge($stats, $financialStats);

        // Calcular ratios importantes
        $stats['collection_efficiency'] = $stats['revenue_expected'] > 0
            ? round(($stats['revenue_received'] / $stats['revenue_expected']) * 100, 2)
            : 100;

        $stats['average_booking_value'] = $stats['total_bookings'] > 0
            ? round($stats['revenue_expected'] / $stats['total_bookings'], 2)
            : 0;

        $stats['revenue_at_risk'] = $stats['revenue_expected'] - $stats['revenue_received'];

        return $stats;
    }

    /**
     * MÉTODO AUXILIAR: Análisis rápido de estados de reserva
     */
    /**
     * MÉTODO CORREGIDO: Análisis por estado con expected correcto
     */
    private function analyzeBookingsByStatus($productionBookings): array
    {
        $statusAnalysis = [
            'active' => ['count' => 0, 'expected_revenue' => 0, 'received_revenue' => 0, 'issues' => 0],
            'partial_cancel' => ['count' => 0, 'expected_revenue' => 0, 'received_revenue' => 0, 'issues' => 0],
            'finished' => ['count' => 0, 'expected_revenue' => 0, 'received_revenue' => 0, 'issues' => 0]
        ];

        foreach ($productionBookings as $booking) {
            $cancellationStatus = $booking->getCancellationStatusAttribute();

            // Mapear estados
            $statusKey = 'active'; // default
            if ($cancellationStatus === 'partial_cancel') {
                $statusKey = 'partial_cancel';
            } elseif ($cancellationStatus === 'finished') {
                $statusKey = 'finished';
            }

            $statusAnalysis[$statusKey]['count']++;

            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

            if ($booking->status == 3) {
                // Para parciales, calcular solo la parte activa
                $activeRevenue = $this->calculateActivePortionRevenue($booking);
                $activeProportion = $activeRevenue > 0 ? $activeRevenue / $quickAnalysis['calculated_amount'] : 0;

                $statusAnalysis[$statusKey]['expected_revenue'] += $activeRevenue;
                $statusAnalysis[$statusKey]['received_revenue'] += $quickAnalysis['received_amount'] * $activeProportion;
            } else {
                // Para activas y terminadas, contar todo
                $statusAnalysis[$statusKey]['expected_revenue'] += $quickAnalysis['calculated_amount'];
                $statusAnalysis[$statusKey]['received_revenue'] += $quickAnalysis['received_amount'];
            }

            if ($quickAnalysis['has_issues']) {
                $statusAnalysis[$statusKey]['issues']++;
            }
        }

        // Calcular porcentajes y métricas
        $totalProductionBookings = count($productionBookings);
        foreach ($statusAnalysis as $status => &$data) {
            $data['percentage'] = $totalProductionBookings > 0 ? round(($data['count'] / $totalProductionBookings) * 100, 2) : 0;
            $data['expected_revenue'] = round($data['expected_revenue'], 2);
            $data['received_revenue'] = round($data['received_revenue'], 2);
            $data['pending_revenue'] = round($data['expected_revenue'] - $data['received_revenue'], 2);
            $data['collection_efficiency'] = $data['expected_revenue'] > 0
                ? round(($data['received_revenue'] / $data['expected_revenue']) * 100, 2) : 100;
        }

        return $statusAnalysis;
    }

    /**
     * MÉTODO AUXILIAR: Análisis financiero rápido para dashboard
     */
    private function calculateQuickFinancialStats($bookings): array
    {
        $stats = [
            'revenue_expected' => 0,
            'revenue_received' => 0,
            'consistency_issues' => 0,
            'consistency_rate' => 0
        ];

        $consistentBookings = 0;
        $totalAnalyzed = 0;

        foreach ($bookings as $booking) {
            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

            $stats['revenue_expected'] += $quickAnalysis['calculated_amount'];
            $stats['revenue_received'] += $quickAnalysis['received_amount'];

            if (!$quickAnalysis['has_issues']) {
                $consistentBookings++;
            } else {
                $stats['consistency_issues']++;
            }

            $totalAnalyzed++;
        }

        $stats['consistency_rate'] = $totalAnalyzed > 0
            ? round(($consistentBookings / $totalAnalyzed) * 100, 2)
            : 100;

        $stats['revenue_expected'] = round($stats['revenue_expected'], 2);
        $stats['revenue_received'] = round($stats['revenue_received'], 2);
        $stats['revenue_pending'] = round($stats['revenue_expected'] - $stats['revenue_received'], 2);

        return $stats;
    }

    /**
     * MÉTODO AUXILIAR: Análisis financiero rápido de una reserva individual
     */
    private function getQuickBookingFinancialStatus($booking): array
    {
        $status = [
            'calculated_amount' => 0,
            'received_amount' => 0,
            'has_issues' => false,
            'issue_types' => []
        ];

        try {
            // Usar grouped activities del modelo para cálculo rápido
            $groupedActivities = $booking->getGroupedActivitiesAttribute();

            foreach ($groupedActivities as $activity) {
                $course = $activity['course'];

                // Saltar cursos excluidos
                if (in_array($course->id, self::EXCLUDED_COURSES)) {
                    continue;
                }

                // Solo sumar si no está completamente cancelado
                if ($activity['status'] !== 2) {
                    $status['calculated_amount'] += $activity['total'];
                }
            }

            $voucherPaid = 0;
            $voucherRefunded = 0;

            $hasVoucherRefundLog = $booking->booking_logs?->contains(function ($log) {
                return $log->action === 'voucher_refund';
            }) ?? false;

            foreach ($booking->vouchersLogs as $log) {
                $voucher = $log->voucher;
                if (!$voucher) continue;

                $logAmount = abs(floatval($log->amount));

                // Si hay un log de refund → tratamos este uso como devolución
                if ($hasVoucherRefundLog) {
                    $voucherRefunded += $logAmount;
                    continue;
                }

                $original = floatval($voucher->quantity);
                $remaining = floatval($voucher->remaining_balance);
                $used = $original - $remaining;

                if ($used >= $logAmount - 0.01) {
                    $voucherPaid += $logAmount;
                } else {
                    $voucherRefunded += $logAmount;
                }
            }

            // Calcular dinero recibido rápidamente
            $status['received_amount'] = $booking->payments->where('status', 'paid')->sum('amount')
                - $booking->payments->where('status', 'refund')->sum('amount')
                - $booking->payments->where('status', 'partial_refund')->sum('amount')
                + $voucherPaid - $voucherRefunded;

            // Detectar problemas básicos
            $difference = abs($status['calculated_amount'] - $status['received_amount']);

            if ($difference > 0.50) {
                $status['has_issues'] = true;
                $status['issue_types'][] = 'amount_discrepancy';
            }

            if ($booking->status == 2 && $status['received_amount'] > 0) {
                $status['has_issues'] = true;
                $status['issue_types'][] = 'cancelled_with_payments';
            }

        } catch (\Exception $e) {
            Log::warning("Error en análisis rápido de booking {$booking->id}: " . $e->getMessage());
            $status['has_issues'] = true;
            $status['issue_types'][] = 'analysis_error';
        }

        return $status;
    }

    /**
     * MÉTODO AUXILIAR: Detectar problemas críticos en la temporada
     */
    private function identifyCriticalIssues($bookings, string $optimizationLevel): array
    {
        $criticalIssues = [
            'high_expected_discrepancies' => [],     // Discrepancias en expected
            'expected_collection_issues' => [],      // Problemas de cobro en expected
            'expected_voucher_issues' => [],         // Problemas de vouchers en expected
            'expected_pricing_anomalies' => []       // Anomalías de precio en expected
        ];

        $highValueThreshold = 30; // Para expected, ser más estricto
        $processed = 0;
        $maxToAnalyze = $optimizationLevel === 'fast' ? 100 : ($optimizationLevel === 'detailed' ? PHP_INT_MAX : 300);

        foreach ($bookings as $booking) {
            if ($processed >= $maxToAnalyze) break;

            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

            // Calcular expected correcto para esta reserva
            $expectedRevenue = $booking->status == 3
                ? $this->calculateActivePortionRevenue($booking)
                : $quickAnalysis['calculated_amount'];

            $receivedRevenue = $booking->status == 3
                ? $quickAnalysis['received_amount'] * ($expectedRevenue / $quickAnalysis['calculated_amount'])
                : $quickAnalysis['received_amount'];

            // Discrepancias en expected
            $difference = abs($expectedRevenue - $receivedRevenue);
            if ($difference > $highValueThreshold) {
                $criticalIssues['high_expected_discrepancies'][] = [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->clientMain->email ?? 'N/A',
                    'expected_revenue' => round($expectedRevenue, 2),
                    'received_revenue' => round($receivedRevenue, 2),
                    'difference_amount' => round($difference, 2),
                    'severity' => $difference > 100 ? 'critical' : 'high',
                    'booking_status' => $booking->status == 3 ? 'partial' : 'active'
                ];
            }

            // Problemas de cobro en expected (activas con bajo cobro)
            if ($booking->status == 1 && $receivedRevenue < $expectedRevenue * 0.7) {
                $criticalIssues['expected_collection_issues'][] = [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->clientMain->email ?? 'N/A',
                    'expected_amount' => round($expectedRevenue, 2),
                    'received_amount' => round($receivedRevenue, 2),
                    'missing_amount' => round($expectedRevenue - $receivedRevenue, 2),
                    'collection_rate' => round(($receivedRevenue / $expectedRevenue) * 100, 2)
                ];
            }

            // Vouchers que exceden expected
            $voucherTotal = $booking->vouchersLogs->sum('amount');
            if ($voucherTotal > $expectedRevenue) {
                $criticalIssues['expected_voucher_issues'][] = [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->clientMain->email ?? 'N/A',
                    'expected_amount' => round($expectedRevenue, 2),
                    'voucher_amount' => round($voucherTotal, 2),
                    'excess_amount' => round($voucherTotal - $expectedRevenue, 2)
                ];
            }

            // Anomalías en expected (valores muy altos o muy bajos)
            if ($expectedRevenue > 2000 || ($expectedRevenue > 0 && $expectedRevenue < 5)) {
                $criticalIssues['expected_pricing_anomalies'][] = [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->clientMain->email ?? 'N/A',
                    'expected_amount' => round($expectedRevenue, 2),
                    'anomaly_type' => $expectedRevenue > 2000 ? 'very_high_expected' : 'very_low_expected',
                    'booking_status' => $booking->status == 3 ? 'partial' : 'active'
                ];
            }

            $processed++;
        }

        // Agregar contadores
        foreach ($criticalIssues as $type => &$issues) {
            $issues = [
                'count' => count($issues),
                'items' => $issues
            ];
        }

        return $criticalIssues;
    }

    /**
     * MÉTODO AUXILIAR: Análisis de transacciones de test en la temporada
     */
    private function analyzeTestTransactions($bookings): array
    {
        $testAnalysis = [
            'total_bookings_with_test' => 0,
            'total_test_transactions' => 0,
            'test_amount_total' => 0,
            'confidence_distribution' => ['high' => 0, 'medium' => 0, 'low' => 0],
            'test_indicators_summary' => [],
            'sample_test_transactions' => []
        ];

        $indicatorCounts = [];
        $processed = 0;

        foreach ($bookings as $booking) {
            if ($processed >= 200) break; // Limitar para performance

            $hasTestInBooking = false;

            $payrexxPayments = $booking->payments()->whereNotNull('payrexx_reference')->get();

            foreach ($payrexxPayments as $payment) {
                // Simular detección de test básica (sin llamar a Payrexx para dashboard rápido)
                $isTest = $this->quickTestDetection($payment);

                if ($isTest['is_test']) {
                    $hasTestInBooking = true;
                    $testAnalysis['total_test_transactions']++;
                    $testAnalysis['test_amount_total'] += $payment->amount;
                    $testAnalysis['confidence_distribution'][$isTest['confidence']]++;

                    // Contar indicadores
                    foreach ($isTest['indicators'] as $indicator) {
                        $indicatorCounts[$indicator] = ($indicatorCounts[$indicator] ?? 0) + 1;
                    }

                    // Muestra para debugging
                    if (count($testAnalysis['sample_test_transactions']) < 10) {
                        $testAnalysis['sample_test_transactions'][] = [
                            'booking_id' => $booking->id,
                            'payment_id' => $payment->id,
                            'amount' => $payment->amount,
                            'confidence' => $isTest['confidence'],
                            'indicators' => $isTest['indicators']
                        ];
                    }
                }
            }

            if ($hasTestInBooking) {
                $testAnalysis['total_bookings_with_test']++;
            }

            $processed++;
        }

        $testAnalysis['test_indicators_summary'] = $indicatorCounts;
        $testAnalysis['test_amount_total'] = round($testAnalysis['test_amount_total'], 2);

        return $testAnalysis;
    }

    /**
     * MÉTODO AUXILIAR: Detección rápida de test sin llamar a Payrexx
     */
    private function quickTestDetection($payment): array
    {
        $indicators = [];
        $confidence = 'low';

/*        // Detectar por ambiente
        if (env('APP_ENV') !== 'production') {
            $indicators[] = 'development_environment';
            $confidence = 'high';
        }*/

        // Detectar por referencia
        if (stripos($payment->payrexx_reference ?? '', 'test') !== false) {
            $indicators[] = 'reference_contains_test';
            $confidence = 'high';
        }

        // Detectar por patrones de importe
        if (in_array($payment->amount, [1, 5, 10, 100, 1.00, 5.00, 10.00, 100.00])) {
            $indicators[] = 'common_test_amount';
            if ($confidence === 'low') $confidence = 'medium';
        }

        return [
            'is_test' => !empty($indicators),
            'confidence' => $confidence,
            'indicators' => $indicators
        ];
    }

    /**
     * MÉTODO AUXILIAR: Generar alertas ejecutivas
     */
    private function generateExecutiveAlerts(array $dashboard): array
    {
        $alerts = [];

        // Alerta de consistencia financiera
        $consistencyRate = $dashboard['executive_kpis']['consistency_rate'] ?? 100;
        if ($consistencyRate < 80) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'financial_consistency',
                'title' => 'Baja Consistencia Financiera',
                'description' => "Solo el {$consistencyRate}% de las reservas son financieramente consistentes",
                'impact' => 'high',
                'action_required' => true
            ];
        }

        // Alerta de dinero en riesgo
        $revenueAtRisk = $dashboard['executive_kpis']['revenue_at_risk'] ?? 0;
        if ($revenueAtRisk > 1000) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'revenue_at_risk',
                'title' => 'Ingresos en Riesgo',
                'description' => "Hay {$revenueAtRisk}€ de ingresos pendientes de cobro",
                'impact' => 'medium',
                'action_required' => true
            ];
        }

        // Alerta de transacciones de test en producción
        if (env('APP_ENV') === 'production' &&
            ($dashboard['test_transactions_analysis']['total_test_transactions'] ?? 0) > 0) {
            $testCount = $dashboard['test_transactions_analysis']['total_test_transactions'];
            $alerts[] = [
                'level' => 'warning',
                'type' => 'test_transactions_in_production',
                'title' => 'Transacciones de Test en Producción',
                'description' => "Se detectaron {$testCount} transacciones de test en ambiente de producción",
                'impact' => 'medium',
                'action_required' => true
            ];
        }

        return $alerts;
    }

    /**
     * MÉTODO AUXILIAR: Preparar datos para exportación
     */
    private function prepareExportSummary(array $dashboard, array $classification): array
    {
        return [
            'csv_ready_data' => [
                'executive_summary' => [
                    ['Métrica', 'Valor', 'Unidad'],
                    ['=== RESERVAS DE PRODUCCIÓN ===', '', ''],
                    ['Total Reservas Producción', $dashboard['executive_kpis']['total_production_bookings'] ?? $classification['summary']['production_count'], 'reservas'],
                    ['Total Clientes Únicos', $dashboard['executive_kpis']['total_clients'] ?? 0, 'clientes'],
                    ['Ingresos Esperados', $dashboard['executive_kpis']['revenue_expected'] ?? $classification['summary']['expected_revenue'], 'EUR'],
                    ['Ingresos Recibidos', $dashboard['executive_kpis']['revenue_received'] ?? 0, 'EUR'],
                    ['Eficiencia de Cobro', $dashboard['executive_kpis']['collection_efficiency'] ?? 0, '%'],
                    ['Consistencia Financiera', $dashboard['executive_kpis']['consistency_rate'] ?? 0, '%'],
                    ['', '', ''],
                    ['=== RESERVAS EXCLUIDAS ===', '', ''],
                    ['Reservas de Test', $classification['summary']['test_count'], 'reservas'],
                    ['Ingresos Test Excluidos', $classification['summary']['test_revenue_excluded'], 'EUR'],
                    ['Reservas Canceladas', $classification['summary']['cancelled_count'], 'reservas'],
                    ['Ingresos Cancelados Procesados', $classification['summary']['cancelled_revenue_processed'], 'EUR'],
                    ['', '', ''],
                    ['=== TOTALES GENERALES ===', '', ''],
                    ['Total General Reservas', $classification['summary']['total_bookings'], 'reservas'],
                    ['Porcentaje Producción', round(($classification['summary']['production_count'] / max($classification['summary']['total_bookings'], 1)) * 100, 2), '%'],
                    ['Porcentaje Test', round(($classification['summary']['test_count'] / max($classification['summary']['total_bookings'], 1)) * 100, 2), '%'],
                    ['Porcentaje Canceladas', round(($classification['summary']['cancelled_count'] / max($classification['summary']['total_bookings'], 1)) * 100, 2), '%']
                ],

                'critical_issues_summary' => $this->formatCriticalIssuesForCsv($dashboard['critical_issues'] ?? []),

                'test_analysis' => [
                    ['Análisis de Reservas Test', '', ''],
                    ['Booking ID', 'Cliente', 'Email', 'Importe', 'Confianza', 'Razón'],
                    // Se llenará dinámicamente en el método de exportación
                ],

                'cancelled_analysis' => [
                    ['Análisis de Reservas Canceladas', '', ''],
                    ['Booking ID', 'Cliente', 'Email', 'Importe', 'Dinero Sin Procesar', 'Estado'],
                    // Se llenará dinámicamente en el método de exportación
                ]
            ],

            'pdf_sections' => [
                'executive_summary' => 'Resumen Ejecutivo',
                'financial_kpis' => 'KPIs Financieros (Solo Producción)',
                'booking_analysis' => 'Análisis por Estado de Reserva',
                'critical_issues' => 'Problemas Críticos',
                'test_analysis' => 'Reservas de Test Detectadas',
                'cancelled_analysis' => 'Análisis de Cancelaciones',
                'recommendations' => 'Recomendaciones Prioritarias'
            ]
        ];
    }

    /**
     * MÉTODO AUXILIAR: Formatear problemas críticos para CSV
     */
    private function formatCriticalIssuesForCsv(array $criticalIssues): array
    {
        $csvData = [['Tipo de Problema', 'Booking ID', 'Cliente', 'Importe', 'Descripción']];

        foreach ($criticalIssues as $issueType => $issueData) {
            if (isset($issueData['items'])) {
                foreach ($issueData['items'] as $item) {
                    $csvData[] = [
                        $issueType,
                        $item['booking_id'] ?? 'N/A',
                        $item['client_email'] ?? 'N/A',
                        $item['difference_amount'] ?? $item['unprocessed_amount'] ?? 'N/A',
                        $this->getIssueDescription($issueType, $item)
                    ];
                }
            }
        }

        return $csvData;
    }

    /**
     * MÉTODO AUXILIAR: Obtener descripción del problema
     */
    private function getIssueDescription(string $issueType, array $item): string
    {
        switch ($issueType) {
            case 'high_value_discrepancies':
                return "Diferencia de {$item['difference_amount']}€ entre calculado y recibido";
            case 'cancelled_with_unprocessed_payments':
                return "Reserva cancelada con {$item['unprocessed_amount']}€ sin procesar";
            default:
                return 'Problema detectado';
        }
    }

    /**
     * ENDPOINT PRINCIPAL: Análisis financiero detallado de una reserva específica
     * GET /api/admin/bookings/{id}/financial-debug
     */
    public function getBookingFinancialDebug(Request $request, $bookingId)
    {
        $request->validate([
            'include_timeline' => 'boolean',
            'include_logs' => 'boolean',
            'include_step_by_step' => 'boolean',
            'include_recommendations' => 'boolean'
        ]);

        try {
            $booking = Booking::with([
                'bookingUsers.course.sport',
                'bookingUsers.client',
                'bookingUsers.bookingUserExtras.courseExtra',
                'payments' => function($q) {
                    $q->orderBy('created_at', 'desc');
                },
                'vouchersLogs.voucher',
                'clientMain',
                'school',
                'bookingLogs' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ])->findOrFail($bookingId);

            $debug = [
                'booking_id' => $bookingId,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'basic_info' => $this->getBasicBookingInfo($booking),
                'financial_calculation' => $this->getStepByStepCalculation($booking),
                'discrepancy_analysis' => $this->getDiscrepancyAnalysis($booking),
                'recommendations' => []
            ];

            // ANÁLISIS PASO A PASO SI SE SOLICITA
            if ($request->boolean('include_step_by_step', true)) {
                $debug['step_by_step'] = $this->getDetailedStepByStep($booking);
            }

            // TIMELINE DE EVENTOS SI SE SOLICITA
            if ($request->boolean('include_timeline', false)) {
                $debug['timeline'] = $this->getBookingTimeline($booking);
            }

            // LOGS SI SE SOLICITAN
            if ($request->boolean('include_logs', false)) {
                $debug['logs'] = $this->getBookingLogs($booking);
            }

            // ✨ NUEVO: VERIFICACIÓN DE PAYREXX SI SE SOLICITA
            if ($request->boolean('include_payrexx_verification', true)) {
                $debug['payrexx_verification'] = PayrexxHelpers::verifyBookingPayrexxTransactions($booking);
            }

            // RECOMENDACIONES SI SE SOLICITAN
            if ($request->boolean('include_recommendations', true)) {
                $debug['recommendations'] = $this->getActionableRecommendations($debug);
            }

            return $this->sendResponse($debug, 'Análisis de debug completado');

        } catch (\Exception $e) {
            Log::error("Error en debug de booking {$bookingId}: " . $e->getLine());
            return $this->sendError('Error en análisis: ' . $e->getMessage(), 500);
        }
    }

    /**
     * MÉTODO AUXILIAR ACTUALIZADO: Recomendaciones accionables con Payrexx
     */
    private function getActionableRecommendations($debugData): array
    {
        $recommendations = [];
        $discrepancy = $debugData['discrepancy_analysis'];

        if (!$discrepancy['has_discrepancy']) {
            $recommendations[] = [
                'type' => 'success',
                'priority' => 'low',
                'title' => 'Reserva Financieramente Consistente',
                'description' => 'No se detectaron problemas financieros en esta reserva',
                'action' => 'No se requiere acción'
            ];

            // VERIFICAR PAYREXX AUN SI NO HAY DISCREPANCIAS FINANCIERAS
            if (isset($debugData['payrexx_verification'])) {
                $payrexxRecommendations = $this->getPayrexxRecommendations($debugData['payrexx_verification']);
                $recommendations = array_merge($recommendations, $payrexxRecommendations);
            }

            return $recommendations;
        }

        $severity = $discrepancy['severity'];
        $difference = $discrepancy['difference_amount'];
        $type = $discrepancy['difference_type'];

        if ($type === 'underpaid') {
            $recommendations[] = [
                'type' => 'warning',
                'priority' => $severity === 'high' ? 'high' : 'medium',
                'title' => 'Dinero Pendiente de Cobro',
                'description' => "Faltan " . abs($difference) . "€ por cobrar en esta reserva",
                'action' => 'Contactar al cliente para completar el pago o revisar si hay descuentos aplicados'
            ];
        } elseif ($type === 'overpaid') {
            $recommendations[] = [
                'type' => 'error',
                'priority' => 'high',
                'title' => 'Sobrepago Detectado',
                'description' => "El cliente ha pagado " . abs($difference) . "€ de más",
                'action' => 'Revisar si procede reembolso o crédito para futuras reservas'
            ];
        }

        // RECOMENDACIONES ESPECÍFICAS BASADAS EN LAS CAUSAS IDENTIFICADAS
        foreach ($discrepancy['possible_causes'] as $cause) {
            $recommendations[] = $this->getRecommendationForCause($cause);
        }

        // ✨ NUEVO: RECOMENDACIONES DE PAYREXX
        if (isset($debugData['payrexx_verification'])) {
            $payrexxRecommendations = $this->getPayrexxRecommendations($debugData['payrexx_verification']);
            $recommendations = array_merge($recommendations, $payrexxRecommendations);
        }

        return $recommendations;
    }

    /**
     * NUEVO MÉTODO: Generar recomendaciones específicas de Payrexx - ACTUALIZADO CON TEST DETECTION
     */
    private function getPayrexxRecommendations($payrexxVerification): array
    {
        $recommendations = [];

        if (!$payrexxVerification['has_payrexx_payments']) {
            return $recommendations;
        }

        $status = $payrexxVerification['overall_status'];
        $summary = $payrexxVerification['verification_summary'];

        // 🔍 ANALIZAR TRANSACCIONES DE TEST
        $testTransactions = 0;
        $testDetails = [];

        foreach ($payrexxVerification['payment_details'] as $paymentDetail) {
            if (isset($paymentDetail['test_detection']) &&
                $paymentDetail['test_detection']['is_test_transaction']) {
                $testTransactions++;
                $testDetails[] = [
                    'payment_id' => $paymentDetail['payment_id'],
                    'confidence' => $paymentDetail['test_detection']['confidence_level'],
                    'indicators' => $paymentDetail['test_detection']['test_indicators'],
                    'card_type' => $paymentDetail['test_detection']['test_card_type'] ?? null
                ];
            }
        }

        // ⚠️ RECOMENDACIÓN ESPECÍFICA PARA TRANSACCIONES DE TEST
        if ($testTransactions > 0) {
            $priority = $testTransactions === $summary['total_checked'] ? 'high' : 'medium';
            $type = $testTransactions === $summary['total_checked'] ? 'warning' : 'info';

            $recommendations[] = [
                'type' => $type,
                'priority' => $priority,
                'title' => 'Transacciones de Test Detectadas',
                'description' => "Se detectaron {$testTransactions} transacción(es) realizadas con tarjetas de test",
                'action' => $testTransactions === $summary['total_checked']
                    ? 'ATENCIÓN: Todas las transacciones son de test - verificar en producción'
                    : 'Verificar si las transacciones de test son intencionales',
                'details' => [
                    'test_transactions_count' => $testTransactions,
                    'total_transactions' => $summary['total_checked'],
                    'test_details' => $testDetails
                ]
            ];
        }

        // RECOMENDACIONES EXISTENTES SEGÚN ESTADO
        switch ($status) {
            case 'all_verified':
                if ($testTransactions === 0) {
                    $recommendations[] = [
                        'type' => 'success',
                        'priority' => 'low',
                        'title' => 'Pagos de Payrexx Verificados',
                        'description' => "Todos los pagos de Payrexx ({$summary['found_in_payrexx']}) están correctamente verificados con transacciones reales",
                        'action' => 'No se requiere acción para Payrexx'
                    ];
                } else {
                    $recommendations[] = [
                        'type' => 'info',
                        'priority' => 'low',
                        'title' => 'Pagos de Payrexx Verificados',
                        'description' => "Todos los pagos están verificados, pero {$testTransactions} son transacciones de test",
                        'action' => 'Verificar si las transacciones de test son apropiadas para este contexto'
                    ];
                }
                break;

            case 'missing_transactions':
                $recommendations[] = [
                    'type' => 'error',
                    'priority' => 'high',
                    'title' => 'Transacciones Faltantes en Payrexx',
                    'description' => "{$summary['missing_in_payrexx']} transacciones no se encontraron en Payrexx",
                    'action' => 'Verificar en el panel de Payrexx si las transacciones existen con referencias diferentes'
                ];
                break;

            case 'amount_mismatches':
                $recommendations[] = [
                    'type' => 'warning',
                    'priority' => 'medium',
                    'title' => 'Diferencias de Importe en Payrexx',
                    'description' => "{$summary['amount_discrepancies']} transacciones tienen diferencias de importe",
                    'action' => 'Revisar los importes en el panel de Payrexx y ajustar si es necesario'
                ];
                break;

            case 'error':
                $recommendations[] = [
                    'type' => 'error',
                    'priority' => 'high',
                    'title' => 'Error de Verificación de Payrexx',
                    'description' => 'No se pudo conectar con Payrexx para verificar las transacciones',
                    'action' => 'Verificar configuración de Payrexx e intentar nuevamente'
                ];
                break;

            case 'partial_issues':
                $recommendations[] = [
                    'type' => 'warning',
                    'priority' => 'medium',
                    'title' => 'Problemas Parciales en Payrexx',
                    'description' => 'Algunos pagos de Payrexx requieren revisión',
                    'action' => 'Revisar detalles específicos en la sección de verificación de Payrexx'
                ];
                break;
        }

        // RECOMENDACIONES ESPECÍFICAS POR TIPO DE PROBLEMA
        foreach ($payrexxVerification['issues_detected'] as $issue) {
            if ($issue['type'] === 'amount_mismatch') {
                $recommendations[] = [
                    'type' => 'warning',
                    'priority' => 'medium',
                    'title' => 'Diferencia de Importe en Payrexx',
                    'description' => $issue['description'],
                    'action' => 'Verificar el importe exacto en el panel de Payrexx'
                ];
            }
        }

        // 🎯 RECOMENDACIONES ESPECÍFICAS PARA TARJETAS DE TEST DETECTADAS
        foreach ($testDetails as $testDetail) {
            $cardInfo = $testDetail['card_type'] ? " (Tipo: {$testDetail['card_type']})" : '';

            if ($testDetail['confidence'] === 'high') {
                $recommendations[] = [
                    'type' => 'warning',
                    'priority' => 'medium',
                    'title' => 'Tarjeta de Test Confirmada',
                    'description' => "El pago #{$testDetail['payment_id']} se realizó con una tarjeta de test conocida{$cardInfo}",
                    'action' => 'Verificar si esta transacción debería ser reemplazada por una transacción real',
                    'technical_details' => [
                        'confidence' => $testDetail['confidence'],
                        'indicators' => $testDetail['indicators']
                    ]
                ];
            } elseif ($testDetail['confidence'] === 'medium') {
                $recommendations[] = [
                    'type' => 'info',
                    'priority' => 'low',
                    'title' => 'Posible Tarjeta de Test',
                    'description' => "El pago #{$testDetail['payment_id']} muestra indicadores de ser una transacción de test{$cardInfo}",
                    'action' => 'Revisar manualmente en el panel de Payrexx para confirmar',
                    'technical_details' => [
                        'confidence' => $testDetail['confidence'],
                        'indicators' => $testDetail['indicators']
                    ]
                ];
            }
        }

        return $recommendations;
    }

    /**
     * MÉTODO AUXILIAR: Información básica de la reserva
     */
    private function getBasicBookingInfo($booking): array
    {
        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'status_name' => $booking->getCancellationStatusAttribute(),
            'school_id' => $booking->school_id,
            'school_name' => $booking->school->name ?? 'N/A',
            'client_main' => [
                'id' => $booking->clientMain->id ?? null,
                'name' => $booking->clientMain->name ?? 'N/A',
                'email' => $booking->clientMain->email ?? 'N/A'
            ],
            'booking_users_count' => $booking->bookingUsers->count(),
            'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $booking->updated_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * MÉTODO AUXILIAR: Cálculo paso a paso detallado
     */
    private function getStepByStepCalculation($booking): array
    {
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);

        // USAR EL MÉTODO EXISTENTE DEL MODELO PARA OBTENER GRUPOS
        $groupedActivities = $booking->getGroupedActivitiesAttribute();

        $calculation = [
            'excluded_courses' => $excludedCourses,
            'grouped_activities' => [],
            'totals' => [
                'should_cost' => 0,
                'received_amount' => 0,
                'difference' => 0
            ]
        ];

        foreach ($groupedActivities as $index => $activity) {
            $course = $activity['course'];
            $isExcluded = in_array($course->id, $excludedCourses);

            $activityCalc = [
                'group_id' => $activity['group_id'],
                'course_info' => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'sport' => $activity['sport']->name ?? 'N/A',
                    'course_type' => $course->course_type,
                    'is_excluded' => $isExcluded
                ],
                'participants' => count($activity['utilizers']),
                'dates_count' => count($activity['dates']),
                'financial_detail' => []
            ];

            if ($isExcluded) {
                $activityCalc['financial_detail'] = [
                    'status' => 'excluded',
                    'reason' => 'Curso excluido del análisis financiero',
                    'price_base' => 0,
                    'extra_price' => 0,
                    'total_price' => 0
                ];
            } else {
                // USAR LOS CÁLCULOS YA HECHOS POR EL MODELO
                $activityCalc['financial_detail'] = [
                    'status' => 'calculated',
                    'price_base' => $activity['price_base'],
                    'extra_price' => $activity['extra_price'],
                    'total_price' => $activity['total'],
                    'calculation_method' => $this->getCalculationMethod($course),
                    'extras_breakdown' => $activity['extras'] ?? []
                ];

                // SOLO SUMAR SI NO ESTÁ CANCELADO COMPLETAMENTE
                if ($activity['status'] !== 2) {
                    $calculation['totals']['should_cost'] += $activity['total'];
                }
            }

            // INFORMACIÓN ADICIONAL PARA DEBUG
            $activityCalc['dates'] = array_map(function($date) {
                return [
                    'date' => $date['date'],
                    'start_hour' => $date['startHour'],
                    'end_hour' => $date['endHour'],
                    'duration' => $date['duration'],
                    'monitor' => $date['monitor']->name ?? 'N/A',
                    'participants' => count($date['utilizers'])
                ];
            }, $activity['dates']);

            $activityCalc['utilizers'] = array_map(function($utilizer) {
                return [
                    'id' => $utilizer['id'],
                    'name' => $utilizer['first_name'] . ' ' . $utilizer['last_name'],
                    'extras_count' => count($utilizer['extras'] ?? [])
                ];
            }, $activity['utilizers']);

            $activityCalc['activity_status'] = $activity['status'];
            $activityCalc['status_list'] = $activity['statusList'];

            $calculation['grouped_activities'][] = $activityCalc;
        }

        // CALCULAR DINERO RECIBIDO
        $receivedBreakdown = $this->getReceivedAmountBreakdown($booking);
        $calculation['received_breakdown'] = $receivedBreakdown;
        $calculation['totals']['received_amount'] = $receivedBreakdown['total'];
        $calculation['totals']['difference'] = $calculation['totals']['should_cost'] - $calculation['totals']['received_amount'];

        return $calculation;
    }

    private function getCalculationMethod($course): string
    {
        switch ($course->course_type) {
            case 1:
                return $course->is_flexible ? 'collective_flexible' : 'collective_fixed';
            case 2:
                return $course->is_flexible ? 'private_flexible' : 'private_fixed';
            case 3:
                return 'activity';
            default:
                return 'unknown';
        }
    }

    /**
     * MÉTODO AUXILIAR: Análisis de discrepancias
     */
    private function getDiscrepancyAnalysis($booking): array
    {
        $stepByStep = $this->getStepByStepCalculation($booking);
        $difference = $stepByStep['totals']['difference'];

        $analysis = [
            'has_discrepancy' => abs($difference) > 0.01,
            'difference_amount' => $difference,
            'difference_type' => $difference > 0 ? 'underpaid' : ($difference < 0 ? 'overpaid' : 'exact'),
            'severity' => $this->getDiscrepancySeverity(abs($difference)),
            'possible_causes' => [],
            'specific_issues' => []
        ];

        if ($analysis['has_discrepancy']) {
            $analysis['possible_causes'] = $this->identifyPossibleCauses($booking, $stepByStep);
            $analysis['specific_issues'] = $this->identifySpecificIssues($booking, $stepByStep);
        }

        return $analysis;
    }

    /**
     * MÉTODO AUXILIAR: Timeline de eventos de la reserva
     */
    private function getBookingTimeline($booking): array
    {
        $timeline = [];

        // Creación de la reserva
        $timeline[] = [
            'timestamp' => $booking->created_at->format('Y-m-d H:i:s'),
            'event' => 'booking_created',
            'description' => 'Reserva creada',
            'data' => ['status' => $booking->status]
        ];

        // Pagos
        foreach ($booking->payments as $payment) {
            $timeline[] = [
                'timestamp' => $payment->created_at->format('Y-m-d H:i:s'),
                'event' => 'payment_received',
                'description' => "Pago recibido: {$payment->amount}€",
                'data' => [
                    'amount' => $payment->amount,
                    'method' => $payment->method ?? 'N/A',
                    'reference' => $payment->reference ?? 'N/A'
                ]
            ];
        }

        // Vouchers
        foreach ($booking->vouchersLogs as $voucherLog) {
            $timeline[] = [
                'timestamp' => $voucherLog->created_at->format('Y-m-d H:i:s'),
                'event' => 'voucher_used',
                'description' => "Voucher aplicado: {$voucherLog->amount}€",
                'data' => [
                    'amount' => $voucherLog->amount,
                    'voucher_code' => $voucherLog->voucher->code ?? 'N/A'
                ]
            ];
        }

        // Logs de booking
        foreach ($booking->bookingLogs as $log) {
            $timeline[] = [
                'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                'event' => 'booking_log',
                'description' => $log->description ?? 'Cambio en la reserva',
                'data' => $log->data ? json_decode($log->data, true) : []
            ];
        }

        // Ordenar por timestamp
        usort($timeline, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });

        return $timeline;
    }

    /**
     * MÉTODO AUXILIAR: Severidad de la discrepancia
     */
    private function getDiscrepancySeverity($amount): string
    {
        if ($amount < 1) return 'low';
        if ($amount < 10) return 'medium';
        return 'high';
    }

    /**
     * MÉTODO AUXILIAR: Identificar posibles causas
     */
    private function identifyPossibleCauses($booking, $stepByStep): array
    {
        $causes = [];

        // Verificar si hay cursos excluidos
        $hasExcludedCourses = false;

      //  dd($stepByStep);
        foreach ($stepByStep['grouped_activities'] as $user) {
            if ($user['course_info']['is_excluded']) {
                $hasExcludedCourses = true;
                break;
            }
        }

        if ($hasExcludedCourses) {
            $causes[] = 'excluded_courses';
        }

        // Verificar estado de la reserva
        if ($booking->status == 3) { // Cancelada
            $causes[] = 'cancelled_booking';
        }

        // Verificar si hay extras
        $hasExtras = false;
        foreach ($booking->bookingUsers as $bookingUser) {
            if ($bookingUser->bookingUserExtras->count() > 0) {
                $hasExtras = true;
                break;
            }
        }

        if ($hasExtras) {
            $causes[] = 'booking_extras';
        }

        return $causes;
    }

    /**
     * MÉTODO AUXILIAR: Calcular precio base de un booking user
     */
    private function calculateBasePrice($bookingUser): float
    {
        $course = $bookingUser->course;

        if (in_array($course->id, self::EXCLUDED_COURSES)) {
            return 0;
        }

        // Usar el servicio de cálculo
        if ($course->course_type === 1) {
            // Colectivo
            return $this->priceCalculator->calculateCollectivePrice(collect([$bookingUser]), $course);
        } elseif ($course->course_type === 2) {
            // Privado
            return $this->priceCalculator->calculatePrivatePrice(collect([$bookingUser]), $course);
        } else {
            // Actividad
            return $this->priceCalculator->calculateActivityPrice(collect([$bookingUser]), $course);
        }
    }

    /**
     * MÉTODO AUXILIAR: Calcular precio de extras
     */
    private function calculateExtrasPrice($bookingUser): float
    {
        return $bookingUser->bookingUserExtras->sum(function($extra) {
            return $extra->courseExtra->price ?? 0;
        });
    }

    /**
     * MÉTODO AUXILIAR: Desglose de extras
     */
    private function getExtrasBreakdown($bookingUser): array
    {
        $breakdown = [];

        foreach ($bookingUser->bookingUserExtras as $extra) {
            $breakdown[] = [
                'name' => $extra->courseExtra->name ?? 'Extra sin nombre',
                'price' => $extra->courseExtra->price ?? 0
            ];
        }

        return $breakdown;
    }

    /**
     * MÉTODO AUXILIAR: Desglose del dinero recibido
     */
    private function getReceivedAmountBreakdown($booking): array
    {
        $breakdown = [
            'payments' => [],
            'vouchers' => [],
            'total_payments' => 0,
            'total_vouchers' => 0,
            'total' => 0
        ];

        // Pagos
        foreach ($booking->payments as $payment) {
            $paymentData = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->method ?? 'N/A',
                'reference' => $payment->reference ?? 'N/A',
                'created_at' => $payment->created_at->format('Y-m-d H:i:s')
            ];
            $breakdown['payments'][] = $paymentData;
            if($payment->status === 'paid') {
                $breakdown['total_payments'] += $payment->amount;
            } elseif ($payment->status === 'refund') {
                $breakdown['total_payments'] -= $payment->amount;
            }

        }

        // Vouchers
        foreach ($booking->vouchersLogs as $voucherLog) {
            $voucherData = [
                'id' => $voucherLog->id,
                'amount' => $voucherLog->amount,
                'voucher_code' => $voucherLog->voucher->code ?? 'N/A',
                'created_at' => $voucherLog->created_at->format('Y-m-d H:i:s')
            ];
            $breakdown['vouchers'][] = $voucherData;
            $breakdown['total_vouchers'] += $voucherLog->amount;
        }

        $breakdown['total'] = $breakdown['total_payments'] + $breakdown['total_vouchers'];

        return $breakdown;
    }

    /**
     * MÉTODO AUXILIAR: Análisis detallado paso a paso
     */
    private function getDetailedStepByStep($booking): array
    {
        $steps = [];

        // PASO 1: Identificación
        $steps[] = [
            'step' => 1,
            'title' => 'Identificación de la Reserva',
            'description' => "Analizando reserva #{$booking->id}",
            'data' => [
                'booking_id' => $booking->id,
                'status' => $booking->status,
                'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
                'booking_users_count' => $booking->bookingUsers->count()
            ],
            'result' => 'success'
        ];

        // PASO 2: Análisis de cursos
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);
        $coursesAnalysis = [];
        $totalCalculated = 0;

        foreach ($booking->bookingUsers as $bookingUser) {
            $course = $bookingUser->course;
            $isExcluded = in_array($course->id, $excludedCourses);

            if ($isExcluded) {
                $coursePrice = 0;
                $extrasPrice = 0;
                $status = 'excluded';
            } else {
                $coursePrice = $this->calculateBasePrice($bookingUser);
                $extrasPrice = $this->calculateExtrasPrice($bookingUser);
                $status = 'calculated';
            }

            $totalForUser = $coursePrice + $extrasPrice;
            $totalCalculated += $totalForUser;

            $coursesAnalysis[] = [
                'booking_user_id' => $bookingUser->id,
                'course_id' => $course->id,
                'course_name' => $course->name,
                'client_name' => $bookingUser->client->name ?? 'N/A',
                'status' => $status,
                'course_price' => $coursePrice,
                'extras_price' => $extrasPrice,
                'total' => $totalForUser
            ];
        }

        $steps[] = [
            'step' => 2,
            'title' => 'Análisis de Cursos y Precios',
            'description' => 'Calculando precios base y extras para cada booking user',
            'data' => [
                'courses_analysis' => $coursesAnalysis,
                'excluded_courses' => $excludedCourses,
                'total_calculated' => $totalCalculated
            ],
            'result' => 'success'
        ];

        // PASO 3: Análisis de dinero recibido
        $receivedBreakdown = $this->getReceivedAmountBreakdown($booking);

        $steps[] = [
            'step' => 3,
            'title' => 'Análisis de Dinero Recibido',
            'description' => 'Sumando pagos y vouchers aplicados',
            'data' => $receivedBreakdown,
            'result' => 'success'
        ];

        // PASO 4: Comparación final
        $difference = $totalCalculated - $receivedBreakdown['total'];
        $hasDiscrepancy = abs($difference) > 0.01;

        $steps[] = [
            'step' => 4,
            'title' => 'Comparación Final',
            'description' => 'Comparando lo que debería costar vs lo recibido',
            'data' => [
                'should_cost' => $totalCalculated,
                'received' => $receivedBreakdown['total'],
                'difference' => $difference,
                'has_discrepancy' => $hasDiscrepancy,
                'percentage_difference' => $totalCalculated > 0 ? round(($difference / $totalCalculated) * 100, 2) : 0
            ],
            'result' => $hasDiscrepancy ? 'warning' : 'success'
        ];

        return $steps;
    }

    /**
     * MÉTODO AUXILIAR: Logs de la reserva
     */
    private function getBookingLogs($booking): array
    {
        $logs = [];

        foreach ($booking->bookingLogs as $log) {
            $logs[] = [
                'id' => $log->id,
                'description' => $log->description ?? 'Sin descripción',
                'data' => $log->data ? json_decode($log->data, true) : null,
                'created_at' => $log->created_at->format('Y-m-d H:i:s')
            ];
        }

        return $logs;
    }

    /**
     * MÉTODO AUXILIAR: Identificar problemas específicos
     */
    private function identifySpecificIssues($booking, $stepByStep): array
    {
        $issues = [];

        // Verificar si hay booking users con cursos excluidos
        foreach ($stepByStep['grouped_activities'] as $user) {
            if ($user['course_info']['is_excluded']) {
                $issues[] = [
                    'type' => 'excluded_course',
                    'severity' => 'info',
                    'description' => "El curso {$user['course_info']['name']} está excluido del análisis financiero",
                    'booking_user_id' => $user['booking_user_id']
                ];
            }
        }

        // Verificar estado de cancelación
        if ($booking->status == 3) {
            $totalReceived = $stepByStep['totals']['received_amount'];
            if ($totalReceived > 0) {
                $issues[] = [
                    'type' => 'cancelled_with_payments',
                    'severity' => 'high',
                    'description' => "Reserva cancelada pero aún tiene {$totalReceived}€ sin procesar",
                    'amount' => $totalReceived
                ];
            }
        }

        // Verificar pagos duplicados o extraños
        $payments = $stepByStep['received_breakdown']['payments'];
        $paymentAmounts = array_column($payments, 'amount');
        $duplicateAmounts = array_filter(array_count_values($paymentAmounts), function($count) {
            return $count > 1;
        });

        if (!empty($duplicateAmounts)) {
            $issues[] = [
                'type' => 'duplicate_payments',
                'severity' => 'medium',
                'description' => 'Se detectaron posibles pagos duplicados',
                'duplicate_amounts' => array_keys($duplicateAmounts)
            ];
        }

        return $issues;
    }

    /**
     * MÉTODO AUXILIAR: Recomendación para causa específica
     */
    private function getRecommendationForCause($cause): array
    {
        $recommendations = [
            'excluded_courses' => [
                'type' => 'info',
                'priority' => 'low',
                'title' => 'Cursos Excluidos',
                'description' => 'La reserva contiene cursos excluidos del análisis financiero',
                'action' => 'Verificar si es correcto excluir estos cursos'
            ],
            'cancelled_booking' => [
                'type' => 'warning',
                'priority' => 'high',
                'title' => 'Reserva Cancelada',
                'description' => 'Esta reserva está cancelada pero puede tener dinero sin procesar',
                'action' => 'Revisar si procede reembolso o si ya se procesó'
            ],
            'booking_extras' => [
                'type' => 'info',
                'priority' => 'low',
                'title' => 'Reserva con Extras',
                'description' => 'La reserva incluye extras que afectan el precio final',
                'action' => 'Verificar que los extras están correctamente facturados'
            ]
        ];

        return $recommendations[$cause] ?? [
            'type' => 'info',
            'priority' => 'low',
            'title' => 'Causa Desconocida',
            'description' => "Se identificó la causa: {$cause}",
            'action' => 'Revisar manualmente'
        ];
    }

    /**
     * MÉTODO AUXILIAR: Nombre del estado
     */
    private function getStatusName($status): string
    {
        $statuses = [
            1 => 'Activa',
            2 => 'Confirmada',
            2 => 'Cancelada',
            4 => 'Completada'
        ];

        return $statuses[$status] ?? 'Desconocido';
    }



    /**
     * ENDPOINT PRINCIPAL: Análisis completo de realidad financiera
     */
    public function getCompleteFinancialAnalysis(Request $request): JsonResponse
    {
        /*        $request->validate([
                    'school_id' => 'required|integer|exists:schools,id',
                    'start_date' => 'nullable|date',
                    'end_date' => 'nullable|date',
                    'booking_ids' => 'nullable|array',
                    'booking_ids.*' => 'integer|exists:bookings,id',
                    'include_consistent' => 'boolean',
                    'min_discrepancy' => 'nullable|numeric|min:0',
                    'max_results' => 'nullable|integer|min:1|max:1000',
                    'include_payrexx_comparison' => 'boolean'
                ]);*/

        $startTime = microtime(true);

        $today = Carbon::today();
        $season = Season::whereDate('start_date', '<=', $today) // Fecha de inicio menor o igual a hoy
        /*->whereDate('end_date', '>=', $today)   // Fecha de fin mayor o igual a hoy*/
        ->where('school_id', $request->school_id)   // Fecha de fin mayor o igual a hoy
        ->first();

        // Utiliza start_date y end_date de la request si están presentes, sino usa las fechas de la temporada
        $startDate = $startDate ?? $season->start_date;
        $endDate = $request->end_date ?? $season->end_date;

        Log::info('=== INICIANDO ANÁLISIS FINANCIERO COMPLETO ===', [
            'school_id' => $request->school_id,
            'date_range' => [$startDate, $endDate],
            'include_payrexx' => $request->boolean('include_payrexx_comparison', false)
        ]);

        try {
            // OBTENER RESERVAS SEGÚN CRITERIOS
            $bookings = $this->getBookingsForAnalysis($request, $startDate, $endDate);

            // FILTRAR RESERVAS QUE SOLO TIENEN CURSOS EXCLUIDOS
            $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);
            $filteredBookings = $this->filterBookingsWithExcludedCourses($bookings, $excludedCourses);

            Log::info('Reservas filtradas para análisis', [
                'total_bookings_before_filter' => $bookings->count(),
                'total_bookings_after_filter' => $filteredBookings->count()
            ]);

            // ANÁLISIS CON PAYREXX SI SE SOLICITA
            $payrexxAnalysis = null;
            if ($request->boolean('include_payrexx_comparison', false)) {
                $payrexxAnalysis = PayrexxHelpers::analyzeBookingsWithPayrexx(
                    $filteredBookings,
                    $startDate,
                    $endDate
                );
            }

            // INICIALIZAR ESTADÍSTICAS GLOBALES
            $globalStats = $this->initializeGlobalStats();

            // PROCESAR CADA RESERVA
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

                // AÑADIR COMPARACIÓN CON PAYREXX SI DISPONIBLE
                if ($payrexxAnalysis) {
                    //TODO: Payrexx comparision
                }

                // FILTRAR POR CRITERIOS
                if (!$this->meetsCriteria($analysis, $request)) {
                    continue;
                }

                // ACUMULAR ESTADÍSTICAS GLOBALES
                $this->accumulateGlobalStats($globalStats, $analysis, $payrexxAnalysis);

                // AGREGAR A RESULTADOS DETALLADOS
                $detailedResults[] = $this->formatAnalysisForResponse($analysis);
                $processedCount++;

                if ($processedCount % 100 === 0) {
                    Log::info("Progreso del análisis: {$processedCount}/{$filteredBookings->count()}");
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
                        'start' => $startDate,
                        'end' => $endDate
                    ],
                    'excluded_courses' => $excludedCourses,
                    'filters_applied' => $this->getAppliedFilters($request),
                    'payrexx_included' => $payrexxAnalysis !== null
                ],

                'global_statistics' => $globalStats,

                'performance_metrics' => [
                    'total_bookings_analyzed' => $processedCount,
                    'bookings_per_second' => $executionTime > 0 ? round($processedCount / ($executionTime / 1000), 2) : 0,
                    'average_analysis_time_ms' => $processedCount > 0 ? round($executionTime / $processedCount, 2) : 0,
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
                ],

                'detailed_results' => $detailedResults,

                'summary_insights' => $this->generateSummaryInsights($globalStats, $processedCount),

                'recommendations' => $this->generateGlobalRecommendations($globalStats),

                'payrexx_analysis' => $payrexxAnalysis
            ];

            Log::info('=== ANÁLISIS FINANCIERO COMPLETO FINALIZADO ===', [
                'processed_bookings' => $processedCount,
                'execution_time_ms' => $executionTime,
                'inconsistent_bookings' => $globalStats['issues']['total_with_financial_issues']
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
     * ENDPOINT: Análisis de una reserva individual
     */
    public function getBookingFinancialAnalysis(Request $request, $bookingId): JsonResponse
    {
        $request->validate([
            'include_timeline' => 'boolean',
            'include_recommendations' => 'boolean',
            'include_payrexx_comparison' => 'boolean'
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

            // COMPARACIÓN CON PAYREXX SI SE SOLICITA
            if ($request->boolean('include_payrexx_comparison', false)) {
                $payrexxComparison = PayrexxHelpers::compareBookingWithPayrexx($booking);
                $analysis['payrexx_comparison'] = $payrexxComparison;
            }

            /*            // INFORMACIÓN ADICIONAL
                        if ($request->boolean('include_timeline', false)) {
                            $analysis['detailed_timeline'] = $this->getDetailedTimeline($booking);
                        }

                        if ($request->boolean('include_recommendations', true)) {
                            $analysis['actionable_recommendations'] = $this->getActionableRecommendations($analysis);
                        }*/

            return $this->sendResponse($analysis, 'Análisis financiero individual completado');

        } catch (\Exception $e) {
            Log::error("Error en análisis individual booking {$bookingId}: " . $e->getMessage());
            return $this->sendError('Error en análisis de reserva: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ENDPOINT: Dashboard financiero ejecutivo
     */
    public function getFinancialDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'period' => 'nullable|in:week,month,quarter,year',
            'quick_analysis' => 'boolean',
            'include_payrexx' => 'boolean'
        ]);

        $period = $request->get('period', 'month');
        $isQuickAnalysis = $request->boolean('quick_analysis', false);

        // DETERMINAR RANGO DE FECHAS
        $dateRange = $this->getDateRangeForPeriod($period);

        $tempRequest = new Request([
            'school_id' => $request->school_id,
            'start_date' => $dateRange['start'],
            'end_date' => $dateRange['end'],
            'include_consistent' => false,
            'max_results' => $isQuickAnalysis ? 100 : 300,
            'include_payrexx_comparison' => $request->boolean('include_payrexx', false)
        ]);

        // OBTENER ANÁLISIS COMPLETO
        $analysisResponse = $this->getCompleteFinancialAnalysis($tempRequest);
        $analysisData = json_decode($analysisResponse->content(), true)['data'];

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
                'processing_accuracy' => $this->calculateProcessingAccuracy($analysisData['global_statistics']),
                'payrexx_consistency' => $this->calculatePayrexxConsistency($analysisData['payrexx_analysis'] ?? null)
            ],

            'alerts' => $this->generateDashboardAlerts($analysisData['global_statistics']),

            'trends' => $this->analyzeTrends($analysisData['detailed_results']),

            'priority_actions' => $this->getPriorityActions($analysisData['detailed_results']),

            'payrexx_summary' => $this->getPayrexxSummary($analysisData['payrexx_analysis'] ?? null),

            'summary_stats' => $analysisData['global_statistics'],

            'generated_at' => now()->toDateTimeString()
        ];

        return $this->sendResponse($dashboard, 'Dashboard financiero generado exitosamente');
    }

    /**
     * ENDPOINT: Análisis específico de Payrexx
     */
    public function getPayrexxAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'booking_ids' => 'nullable|array'
        ]);

        try {
            $bookings = $this->getBookingsForAnalysis($request);
            $filteredBookings = $this->filterBookingsWithExcludedCourses($bookings, self::EXCLUDED_COURSES);

            // ANÁLISIS COMPLETO DE PAYREXX
            $payrexxAnalysis = PayrexxHelpers::analyzeBookingsWithPayrexx(
                $filteredBookings,
                $request->start_date,
                $request->end_date
            );

            // ESTADÍSTICAS DETALLADAS
            $detailedStats = $this->generatePayrexxDetailedStats($payrexxAnalysis);

            // TRANSACCIONES PROBLEMÁTICAS
            $problematicTransactions = $this->identifyProblematicPayrexxTransactions($payrexxAnalysis);

            // RECOMENDACIONES ESPECÍFICAS DE PAYREXX
            $payrexxRecommendations = $this->generatePayrexxRecommendations($payrexxAnalysis);

            $response = [
                'payrexx_analysis' => $payrexxAnalysis,
                'detailed_statistics' => $detailedStats,
                'problematic_transactions' => $problematicTransactions,
                'recommendations' => $payrexxRecommendations,
                'analysis_metadata' => [
                    'total_bookings_analyzed' => $filteredBookings->count(),
                    'payrexx_transactions_found' => count($payrexxAnalysis['payrexx_transactions']),
                    'analysis_timestamp' => now()->toDateTimeString()
                ]
            ];

            return $this->sendResponse($response, 'Análisis de Payrexx completado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error en análisis de Payrexx: ' . $e->getMessage());
            return $this->sendError('Error en análisis de Payrexx: ' . $e->getMessage(), 500);
        }
    }
    /**
     * MÉTODO MEJORADO: Generar estadísticas detalladas de Payrexx
     */
    private static function generatePayrexxDetailedStats($payrexxAnalysis): array
    {
        $stats = [
            'transaction_distribution' => [
                'total_system_transactions' => 0,
                'total_payrexx_transactions' => count($payrexxAnalysis['payrexx_transactions'] ?? []),
                'matched_transactions' => 0,
                'unmatched_system' => 0,
                'unmatched_payrexx' => count($payrexxAnalysis['unmatched_payrexx_transactions'] ?? [])
            ],
            'amount_analysis' => [
                'total_system_amount' => $payrexxAnalysis['total_system_amount'] ?? 0,
                'total_payrexx_amount' => $payrexxAnalysis['total_payrexx_amount'] ?? 0,
                'amount_difference' => 0,
                'percentage_difference' => 0
            ],
            'booking_status_breakdown' => [
                'bookings_by_status' => $payrexxAnalysis['bookings_by_status'] ?? [],
                'amounts_by_status' => $payrexxAnalysis['amounts_by_status'] ?? []
            ],
            'verification_quality' => [
                'successful_verifications' => $payrexxAnalysis['successful_verifications'] ?? 0,
                'failed_verifications' => $payrexxAnalysis['failed_verifications'] ?? 0,
                'verification_rate' => 0
            ]
        ];

        // Calcular diferencias de importes
        $systemAmount = $stats['amount_analysis']['total_system_amount'];
        $payrexxAmount = $stats['amount_analysis']['total_payrexx_amount'];

        $stats['amount_analysis']['amount_difference'] = round($systemAmount - $payrexxAmount, 2);

        if ($systemAmount > 0) {
            $stats['amount_analysis']['percentage_difference'] = round(
                (abs($systemAmount - $payrexxAmount) / $systemAmount) * 100, 2
            );
        }

        // Calcular tasa de verificación
        $totalVerifications = $stats['verification_quality']['successful_verifications'] +
            $stats['verification_quality']['failed_verifications'];

        if ($totalVerifications > 0) {
            $stats['verification_quality']['verification_rate'] = round(
                ($stats['verification_quality']['successful_verifications'] / $totalVerifications) * 100, 2
            );
        }

        // Estadísticas de transacciones
        foreach ($payrexxAnalysis['booking_comparisons'] ?? [] as $comparison) {
            $stats['transaction_distribution']['total_system_transactions'] +=
                count($comparison['verified_payments'] ?? []);

            $stats['transaction_distribution']['matched_transactions'] +=
                $comparison['summary']['successful_verifications'] ?? 0;

            $stats['transaction_distribution']['unmatched_system'] +=
                $comparison['summary']['missing_in_payrexx'] ?? 0;
        }

        return $stats;
    }

    /**
     * MÉTODO FALTANTE: Identificar transacciones problemáticas en Payrexx
     */
    private static function identifyProblematicPayrexxTransactions($payrexxAnalysis): array
    {
        $problematic = [
            'high_value_discrepancies' => [],
            'missing_transactions' => [],
            'amount_mismatches' => [],
            'unmatched_payrexx_only' => [],
            'test_transactions_in_production' => []
        ];

        // Analizar comparaciones de reservas
        foreach ($payrexxAnalysis['booking_comparisons'] ?? [] as $comparison) {
            $bookingId = $comparison['booking_id'] ?? 'unknown';

            // Discrepancias de alto valor
            if ($comparison['has_discrepancy'] && abs($comparison['difference']) > 50) {
                $problematic['high_value_discrepancies'][] = [
                    'booking_id' => $bookingId,
                    'system_amount' => $comparison['total_system_amount'],
                    'payrexx_amount' => $comparison['total_payrexx_amount'],
                    'difference' => $comparison['difference'],
                    'severity' => abs($comparison['difference']) > 100 ? 'critical' : 'high'
                ];
            }

            // Transacciones faltantes
            foreach ($comparison['missing_transactions'] ?? [] as $missing) {
                $problematic['missing_transactions'][] = [
                    'booking_id' => $bookingId,
                    'payment_reference' => $missing['reference'] ?? 'N/A',
                    'amount' => $missing['amount'] ?? 0,
                    'reason' => $missing['reason'] ?? 'Not found in Payrexx'
                ];
            }

            // Discrepancias de importes
            foreach ($comparison['verified_payments'] ?? [] as $payment) {
                if (isset($payment['amount_match']) && !$payment['amount_match']) {
                    $problematic['amount_mismatches'][] = [
                        'booking_id' => $bookingId,
                        'payment_id' => $payment['payment_id'],
                        'system_amount' => $payment['system_amount'],
                        'payrexx_amount' => $payment['payrexx_amount'],
                        'difference' => round($payment['system_amount'] - $payment['payrexx_amount'], 2)
                    ];
                }
            }
        }

        // Transacciones solo en Payrexx
        foreach ($payrexxAnalysis['unmatched_payrexx_transactions'] ?? [] as $unmatched) {
            $problematic['unmatched_payrexx_only'][] = [
                'reference' => $unmatched['reference'],
                'amount' => $unmatched['amount'],
                'date' => $unmatched['date'],
                'transaction_id' => $unmatched['id']
            ];
        }

        // Agregar conteos
        foreach ($problematic as $type => &$issues) {
            if (is_array($issues)) {
                $count = count($issues);
                $issues = [
                    'count' => $count,
                    'items' => $issues
                ];
            }
        }

        return $problematic;
    }

    /**
     * MÉTODO FALTANTE: Generar recomendaciones específicas de Payrexx
     */
    private static function generatePayrexxRecommendations($payrexxAnalysis): array
    {
        $recommendations = [];

        $totalSystemAmount = $payrexxAnalysis['total_system_amount'] ?? 0;
        $totalPayrexxAmount = $payrexxAnalysis['total_payrexx_amount'] ?? 0;
        $totalDiscrepancies = $payrexxAnalysis['total_discrepancies'] ?? 0;
        $missingTransactions = $payrexxAnalysis['missing_transactions'] ?? 0;

        // Recomendación para discrepancias altas
        $amountDifference = abs($totalSystemAmount - $totalPayrexxAmount);
        if ($amountDifference > 100) {
            $priority = $amountDifference > 1000 ? 'critical' : 'high';

            $recommendations[] = [
                'type' => 'amount_reconciliation',
                'priority' => $priority,
                'title' => 'Reconciliar Diferencias de Importes con Payrexx',
                'description' => "Hay una diferencia de {$amountDifference}€ entre el sistema y Payrexx",
                'impact' => $priority,
                'actions' => [
                    'Revisar transacciones con mayor discrepancia',
                    'Verificar tipos de cambio si aplica',
                    'Comprobar comisiones de Payrexx',
                    'Contactar soporte de Payrexx si es necesario'
                ],
                'estimated_effort' => 'medium',
                'timeline' => '1-2 días'
            ];
        }

        // Recomendación para transacciones faltantes
        if ($missingTransactions > 0) {
            $recommendations[] = [
                'type' => 'missing_transactions',
                'priority' => $missingTransactions > 10 ? 'high' : 'medium',
                'title' => 'Localizar Transacciones Faltantes',
                'description' => "Hay {$missingTransactions} transacciones no encontradas en Payrexx",
                'impact' => 'medium',
                'actions' => [
                    'Verificar referencias en el panel de Payrexx',
                    'Revisar si las transacciones están en otro período',
                    'Comprobar configuración de credenciales',
                    'Verificar filtros de fecha aplicados'
                ],
                'estimated_effort' => 'low',
                'timeline' => '1 día'
            ];
        }

        // Recomendación para múltiples discrepancias
        if ($totalDiscrepancies > 20) {
            $recommendations[] = [
                'type' => 'systematic_review',
                'priority' => 'medium',
                'title' => 'Revisión Sistemática de Payrexx',
                'description' => "Se detectaron {$totalDiscrepancies} discrepancias que sugieren un problema sistemático",
                'impact' => 'medium',
                'actions' => [
                    'Revisar configuración de Payrexx',
                    'Verificar proceso de sincronización',
                    'Analizar patrones en las discrepancias',
                    'Implementar monitoreo automático'
                ],
                'estimated_effort' => 'high',
                'timeline' => '1 semana'
            ];
        }

        // Recomendación para optimización si todo está bien
        if ($amountDifference < 10 && $missingTransactions === 0 && $totalDiscrepancies < 5) {
            $recommendations[] = [
                'type' => 'optimization',
                'priority' => 'low',
                'title' => 'Optimizar Integración con Payrexx',
                'description' => 'La integración funciona bien, considerar mejoras de eficiencia',
                'impact' => 'low',
                'actions' => [
                    'Implementar verificación automática diaria',
                    'Crear alertas para discrepancias',
                    'Optimizar consultas a la API',
                    'Documentar procesos de reconciliación'
                ],
                'estimated_effort' => 'medium',
                'timeline' => '2-3 semanas'
            ];
        }

        return $recommendations;
    }

    /**
     * MÉTODO AUXILIAR: Comparar métodos financieros individuales
     */
    private static function compareIndividualBookingMethods($booking): array
    {
        try {
            // Obtener precio almacenado
            $storedTotal = $booking->price_total;

            // Calcular precio usando el servicio
            $calculatedData = app(\App\Http\Services\BookingPriceCalculatorService::class)
                ->calculateBookingTotal($booking, ['exclude_courses' => [260, 243]]);
            $calculatedTotal = $calculatedData['total_final'];

            // Obtener análisis de realidad financiera
            $realityAnalysis = app(\App\Http\Services\BookingPriceCalculatorService::class)
                ->analyzeFinancialReality($booking, ['exclude_courses' => [260, 243]]);

            // Comparar con Payrexx si disponible
            $payrexxComparison = null;
            if ($booking->payments()->whereNotNull('payrexx_reference')->exists()) {
                $payrexxComparison = self::compareBookingWithPayrexx($booking);
            }

            return [
                'booking_id' => $booking->id,
                'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,
                'comparison_methods' => [
                    'stored_method' => [
                        'total' => $storedTotal,
                        'source' => 'price_total field',
                        'description' => 'Precio almacenado en la base de datos'
                    ],
                    'calculated_method' => [
                        'total' => $calculatedTotal,
                        'source' => 'BookingPriceCalculatorService',
                        'description' => 'Precio calculado dinámicamente',
                        'breakdown' => $calculatedData
                    ],
                    'reality_method' => [
                        'total' => $realityAnalysis['calculated_total'],
                        'net_balance' => $realityAnalysis['financial_reality']['net_balance'],
                        'source' => 'Financial reality analysis',
                        'description' => 'Análisis de realidad financiera',
                        'is_consistent' => $realityAnalysis['reality_check']['is_consistent']
                    ]
                ],
                'discrepancies' => [
                    'stored_vs_calculated' => round($storedTotal - $calculatedTotal, 2),
                    'calculated_vs_reality' => round($calculatedTotal - $realityAnalysis['financial_reality']['net_balance'], 2),
                    'stored_vs_reality' => round($storedTotal - $realityAnalysis['financial_reality']['net_balance'], 2)
                ],
                'consistency_analysis' => [
                    'stored_vs_calculated_consistent' => abs($storedTotal - $calculatedTotal) <= 0.50,
                    'reality_consistent' => $realityAnalysis['reality_check']['is_consistent'],
                    'overall_consistent' => abs($storedTotal - $calculatedTotal) <= 0.50 &&
                        $realityAnalysis['reality_check']['is_consistent']
                ],
                'payrexx_comparison' => $payrexxComparison,
                'recommendation' => self::getComparisonRecommendation($storedTotal, $calculatedTotal, $realityAnalysis),
                'analysis_timestamp' => now()->toDateTimeString()
            ];

        } catch (\Exception $e) {
            Log::error("Error comparando métodos para booking {$booking->id}: " . $e->getMessage());

            return [
                'booking_id' => $booking->id,
                'error' => true,
                'error_message' => $e->getMessage(),
                'analysis_timestamp' => now()->toDateTimeString()
            ];
        }
    }

    /**
     * MÉTODO AUXILIAR: Comparar métodos financieros globales
     */
    private static function compareGlobalFinancialMethods($request): array
    {
        try {
            $bookings = \App\Models\Booking::where('school_id', $request->school_id)
                ->with(['bookingUsers.course', 'payments', 'vouchersLogs.voucher', 'clientMain'])
                ->limit(100) // Limitar para performance
                ->get();

            $globalComparison = [
                'total_bookings_analyzed' => $bookings->count(),
                'method_totals' => [
                    'stored_method' => 0,
                    'calculated_method' => 0,
                    'reality_method' => 0
                ],
                'consistency_stats' => [
                    'fully_consistent' => 0,
                    'partially_consistent' => 0,
                    'inconsistent' => 0
                ],
                'discrepancy_analysis' => [
                    'stored_vs_calculated_issues' => 0,
                    'reality_issues' => 0,
                    'total_discrepancy_amount' => 0
                ],
                'sample_comparisons' => []
            ];

            foreach ($bookings as $booking) {
                $comparison = self::compareIndividualBookingMethods($booking);

                if (!isset($comparison['error'])) {
                    // Acumular totales
                    $globalComparison['method_totals']['stored_method'] += $comparison['comparison_methods']['stored_method']['total'];
                    $globalComparison['method_totals']['calculated_method'] += $comparison['comparison_methods']['calculated_method']['total'];
                    $globalComparison['method_totals']['reality_method'] += $comparison['comparison_methods']['reality_method']['net_balance'];

                    // Analizar consistencia
                    if ($comparison['consistency_analysis']['overall_consistent']) {
                        $globalComparison['consistency_stats']['fully_consistent']++;
                    } elseif ($comparison['consistency_analysis']['stored_vs_calculated_consistent'] ||
                        $comparison['consistency_analysis']['reality_consistent']) {
                        $globalComparison['consistency_stats']['partially_consistent']++;
                    } else {
                        $globalComparison['consistency_stats']['inconsistent']++;
                    }

                    // Analizar discrepancias
                    if (!$comparison['consistency_analysis']['stored_vs_calculated_consistent']) {
                        $globalComparison['discrepancy_analysis']['stored_vs_calculated_issues']++;
                    }

                    if (!$comparison['consistency_analysis']['reality_consistent']) {
                        $globalComparison['discrepancy_analysis']['reality_issues']++;
                    }

                    $globalComparison['discrepancy_analysis']['total_discrepancy_amount'] +=
                        abs($comparison['discrepancies']['stored_vs_reality']);

                    // Guardar muestra de comparaciones problemáticas
                    if (!$comparison['consistency_analysis']['overall_consistent'] &&
                        count($globalComparison['sample_comparisons']) < 10) {
                        $globalComparison['sample_comparisons'][] = $comparison;
                    }
                }
            }

            // Redondear totales
            foreach ($globalComparison['method_totals'] as $key => $value) {
                $globalComparison['method_totals'][$key] = round($value, 2);
            }
            $globalComparison['discrepancy_analysis']['total_discrepancy_amount'] =
                round($globalComparison['discrepancy_analysis']['total_discrepancy_amount'], 2);

            // Calcular porcentajes
            $totalAnalyzed = $globalComparison['total_bookings_analyzed'];
            if ($totalAnalyzed > 0) {
                $globalComparison['consistency_percentages'] = [
                    'fully_consistent' => round(($globalComparison['consistency_stats']['fully_consistent'] /
                            $totalAnalyzed) * 100, 2),
                    'partially_consistent' => round(($globalComparison['consistency_stats']['partially_consistent'] /
                            $totalAnalyzed) * 100, 2),
                    'inconsistent' => round(($globalComparison['consistency_stats']['inconsistent'] / $totalAnalyzed) *
                        100, 2)
                ];
            }

            return $globalComparison;

        } catch (\Exception $e) {
            Log::error('Error en comparación global de métodos: ' . $e->getMessage());

            return [
                'error' => true,
                'error_message' => $e->getMessage(),
                'analysis_timestamp' => now()->toDateTimeString()
            ];
        }
    }

    /**
     * ENDPOINT: Comparar métodos de análisis financiero
     */
    public function compareFinancialMethods(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'booking_id' => 'nullable|integer|exists:bookings,id'
        ]);

        try {
            if ($request->booking_id) {
                // Comparación individual
                $booking = Booking::findOrFail($request->booking_id);
                $comparison = $this->compareIndividualBookingMethods($booking);
            } else {
                // Comparación global para la escuela
                $comparison = $this->compareGlobalFinancialMethods($request);
            }

            return $this->sendResponse($comparison, 'Comparación de métodos financieros completada');

        } catch (\Exception $e) {
            Log::error('Error en comparación de métodos: ' . $e->getMessage());
            return $this->sendError('Error en comparación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ENDPOINT: Exportar reporte financiero
     */
    public function exportFinancialReport(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
            'format' => 'nullable|in:json,csv,excel',
            'include_details' => 'boolean',
            'include_payrexx' => 'boolean'
        ]);

        try {
            $format = $request->get('format', 'json');

            // Obtener análisis completo
            $analysisRequest = new Request([
                'school_id' => $request->school_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'include_payrexx_comparison' => $request->boolean('include_payrexx', false)
            ]);

            $analysisResponse = $this->getCompleteFinancialAnalysis($analysisRequest);
            $analysisData = json_decode($analysisResponse->content(), true)['data'];

            // Procesar datos para exportación
            $exportData = $this->prepareExportData($analysisData, $request);

            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($exportData);
                case 'excel':
                    return $this->exportToExcel($exportData);
                default:
                    return $this->sendResponse($exportData, 'Reporte financiero exportado exitosamente');
            }

        } catch (\Exception $e) {
            Log::error('Error en exportación de reporte: ' . $e->getMessage());
            return $this->sendError('Error en exportación: ' . $e->getMessage(), 500);
        }
    }

    // === MÉTODOS AUXILIARES IMPLEMENTADOS ===

    private function getAppliedFilters(Request $request): array
    {
        $filters = [];

        if ($request->has('start_date')) {
            $filters['date_range'] = [
                'start' => $request->start_date,
                'end' => $request->end_date
            ];
        }

        if ($request->has('booking_ids')) {
            $filters['specific_bookings'] = count($request->booking_ids);
        }

        if (!$request->boolean('include_consistent', true)) {
            $filters['only_inconsistent'] = true;
        }

        if ($request->has('min_discrepancy')) {
            $filters['min_discrepancy'] = $request->min_discrepancy;
        }

        if ($request->has('max_results')) {
            $filters['max_results'] = $request->max_results;
        }

        return $filters;
    }

    private function generateSummaryInsights(array $globalStats, int $processedCount): array
    {
        $insights = [];

        // INSIGHT: Tasa de consistencia general
        $consistencyRate = $globalStats['consistency']['consistency_rate'];
        if ($consistencyRate >= 95) {
            $insights[] = [
                'type' => 'positive',
                'category' => 'consistency',
                'title' => 'Excelente Consistencia Financiera',
                'description' => "El {$consistencyRate}% de las reservas tienen consistencia financiera perfecta",
                'score' => 'excellent'
            ];
        } elseif ($consistencyRate >= 80) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'consistency',
                'title' => 'Consistencia Aceptable',
                'description' => "El {$consistencyRate}% de consistencia es aceptable, pero hay margen de mejora",
                'score' => 'good'
            ];
        } else {
            $insights[] = [
                'type' => 'critical',
                'category' => 'consistency',
                'title' => 'Problemas de Consistencia',
                'description' => "Solo el {$consistencyRate}% de las reservas son consistentes - requiere atención inmediata",
                'score' => 'poor'
            ];
        }

        // INSIGHT: Dinero en riesgo
        $totalCalculated = $globalStats['totals']['total_calculated_revenue'];
        $totalReceived = $globalStats['totals']['total_received_amount'];
        $riskPercentage = $totalCalculated > 0 ? round((($totalCalculated - $totalReceived) / $totalCalculated) * 100, 2) : 0;

        if ($riskPercentage > 15) {
            $insights[] = [
                'type' => 'critical',
                'category' => 'revenue_risk',
                'title' => 'Alto Riesgo de Ingresos',
                'description' => "El {$riskPercentage}% de los ingresos esperados están en riesgo",
                'amount_at_risk' => $totalCalculated - $totalReceived,
                'score' => 'poor'
            ];
        } elseif ($riskPercentage > 5) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'revenue_risk',
                'title' => 'Riesgo Moderado de Ingresos',
                'description' => "El {$riskPercentage}% de los ingresos requiere seguimiento",
                'amount_at_risk' => $totalCalculated - $totalReceived,
                'score' => 'good'
            ];
        }

        // INSIGHT: Problemas por estado de reserva
        $activeIssues = $globalStats['by_status']['active']['issues'];
        $cancelledIssues = $globalStats['by_status']['cancelled']['issues'];

        if ($activeIssues > $cancelledIssues * 2) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'active_bookings',
                'title' => 'Problemas en Reservas Activas',
                'description' => "Las reservas activas tienen más problemas que las canceladas - revisar proceso de cobro",
                'score' => 'fair'
            ];
        }

        // INSIGHT: Eficiencia de procesamiento
        $totalWithIssues = $globalStats['issues']['total_with_financial_issues'];
        $issueRate = $processedCount > 0 ? round(($totalWithIssues / $processedCount) * 100, 2) : 0;

        if ($issueRate < 5) {
            $insights[] = [
                'type' => 'positive',
                'category' => 'processing',
                'title' => 'Procesamiento Eficiente',
                'description' => "Solo el {$issueRate}% de las reservas tienen problemas financieros",
                'score' => 'excellent'
            ];
        }

        return $insights;
    }

    private function generateGlobalRecommendations(array $globalStats): array
    {
        $recommendations = [];

        // RECOMENDACIÓN: Mejorar consistencia
        $inconsistentCount = $globalStats['consistency']['inconsistent_bookings'];
        if ($inconsistentCount > 0) {
            $priority = $inconsistentCount > 50 ? 'high' : ($inconsistentCount > 20 ? 'medium' : 'low');

            $recommendations[] = [
                'type' => 'consistency_improvement',
                'priority' => $priority,
                'title' => 'Mejorar Consistencia Financiera',
                'description' => "Se detectaron {$inconsistentCount} reservas con problemas de consistencia",
                'actions' => [
                    'Revisar reservas con mayor discrepancia',
                    'Actualizar precios almacenados incorrectos',
                    'Verificar cálculos de vouchers y seguros',
                    'Implementar validaciones automáticas'
                ],
                'estimated_impact' => 'high',
                'affected_bookings' => $inconsistentCount
            ];
        }

        // RECOMENDACIÓN: Procesar cancelaciones
        $unprocessedCancellations = 0;
        foreach ($globalStats['issues']['issues_by_type'] as $type => $count) {
            if (str_contains($type, 'unprocessed')) {
                $unprocessedCancellations += $count;
            }
        }

        if ($unprocessedCancellations > 0) {
            $recommendations[] = [
                'type' => 'cancellation_processing',
                'priority' => 'high',
                'title' => 'Procesar Cancelaciones Pendientes',
                'description' => "Hay {$unprocessedCancellations} cancelaciones sin procesar",
                'actions' => [
                    'Revisar política de reembolsos',
                    'Procesar refunds pendientes',
                    'Aplicar no-refunds según política',
                    'Notificar a clientes sobre estado'
                ],
                'estimated_impact' => 'medium',
                'affected_bookings' => $unprocessedCancellations
            ];
        }

        // RECOMENDACIÓN: Optimizar cobros
        $totalPending = $globalStats['totals']['total_pending_amount'];
        if ($totalPending > 1000) {
            $recommendations[] = [
                'type' => 'collection_optimization',
                'priority' => 'medium',
                'title' => 'Optimizar Proceso de Cobros',
                'description' => "Hay {$totalPending}€ pendientes de cobro",
                'actions' => [
                    'Implementar recordatorios automáticos',
                    'Ofrecer métodos de pago alternativos',
                    'Seguimiento proactivo de pagos pendientes',
                    'Revisar términos de pago'
                ],
                'estimated_impact' => 'high',
                'potential_recovery' => $totalPending
            ];
        }

        // RECOMENDACIÓN: Automatización de procesos
        $manualIssues = $globalStats['issues']['medium_priority_count'] + $globalStats['issues']['low_priority_count'];
        if ($manualIssues > 10) {
            $recommendations[] = [
                'type' => 'process_automation',
                'priority' => 'low',
                'title' => 'Automatizar Procesos Financieros',
                'description' => "Se pueden automatizar {$manualIssues} tareas de bajo y medio impacto",
                'actions' => [
                    'Implementar validaciones automáticas',
                    'Crear alertas proactivas',
                    'Automatizar actualizaciones de precios',
                    'Desarrollar dashboard de monitoreo'
                ],
                'estimated_impact' => 'medium',
                'efficiency_gain' => 'high'
            ];
        }

        return $recommendations;
    }

    private function getDateRangeForPeriod(string $period): array
    {
        $end = Carbon::now();

        switch ($period) {
            case 'week':
                $start = $end->copy()->subWeek();
                break;
            case 'quarter':
                $start = $end->copy()->subMonths(3);
                break;
            case 'year':
                $start = $end->copy()->subYear();
                break;
            default: // month
                $start = $end->copy()->subMonth();
                break;
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'period_name' => ucfirst($period),
            'days_count' => $start->diffInDays($end)
        ];
    }

    private function calculateFinancialHealthScore(array $globalStats): float
    {
        $consistencyWeight = 0.4;
        $collectionWeight = 0.3;
        $processingWeight = 0.2;
        $issuesWeight = 0.1;

        $consistencyScore = $globalStats['consistency']['consistency_rate'];

        $collectionScore = $globalStats['totals']['total_calculated_revenue'] > 0
            ? ($globalStats['totals']['total_received_amount'] / $globalStats['totals']['total_calculated_revenue']) * 100
            : 100;

        $processingScore = 100 - min($globalStats['issues']['total_with_financial_issues'] * 2, 100);

        $issuesScore = 100 - min($globalStats['issues']['critical_issues_count'] * 10, 100);

        $healthScore = ($consistencyScore * $consistencyWeight) +
            ($collectionScore * $collectionWeight) +
            ($processingScore * $processingWeight) +
            ($issuesScore * $issuesWeight);

        return round(min($healthScore, 100), 2);
    }

    private function calculateRevenueAtRisk(array $globalStats): float
    {
        return round($globalStats['totals']['total_calculated_revenue'] - $globalStats['totals']['total_received_amount'], 2);
    }

    private function calculateCollectionEfficiency(array $globalStats): float
    {
        if ($globalStats['totals']['total_calculated_revenue'] <= 0) {
            return 100;
        }

        return round(($globalStats['totals']['total_received_amount'] / $globalStats['totals']['total_calculated_revenue']) * 100, 2);
    }

    private function calculateProcessingAccuracy(array $globalStats): float
    {
        $totalBookings = $globalStats['totals']['total_bookings_analyzed'];
        if ($totalBookings <= 0) {
            return 100;
        }

        $issuesCount = $globalStats['issues']['total_with_financial_issues'];
        return round(((($totalBookings - $issuesCount) / $totalBookings) * 100), 2);
    }

    private function calculatePayrexxConsistency(?array $payrexxAnalysis): ?float
    {
        if (!$payrexxAnalysis) {
            return null;
        }

        $totalSystemAmount = $payrexxAnalysis['total_system_amount'];
        $totalPayrexxAmount = $payrexxAnalysis['total_payrexx_amount'];

        if ($totalSystemAmount <= 0) {
            return 100;
        }

        $consistency = 100 - (abs($totalSystemAmount - $totalPayrexxAmount) / $totalSystemAmount * 100);
        return round(max($consistency, 0), 2);
    }

    // === MÉTODOS AUXILIARES ADICIONALES ===

    private function getBookingsForAnalysis(Request $request, $startDate = null, $endDate = null)
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
            ->where('school_id', $request->school_id);

        if ($request->booking_ids) {
            $query->whereIn('id', $request->booking_ids);
        }

        if ($startDate && $endDate) {
            $query->whereHas('bookingUsers', function($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            });
        }

        return $query->get();
    }

    private function filterBookingsWithExcludedCourses($bookings, array $excludedCourses)
    {
        return $bookings->filter(function($booking) use ($excludedCourses) {
            $activeNonExcludedCourses = $booking->bookingUsers
                ->filter(function($bu) use ($excludedCourses) {
                    return !in_array((int) $bu->course_id, $excludedCourses);
                });

            return $activeNonExcludedCourses->isNotEmpty();
        });
    }

    // ... [Continúa con el resto de métodos auxiliares]

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
    private function meetsCriteria($analysis, $request): bool
    {
        if (!$request->boolean('include_consistent', true)) {
            if ($analysis['discrepancy_analysis']['is_financially_consistent']) {
                return false;
            }
        }

        if ($request->has('min_discrepancy')) {
            $minDiscrepancy = $request->get('min_discrepancy');
            if ($analysis['discrepancy_analysis']['main_discrepancy_amount'] < $minDiscrepancy) {
                return false;
            }
        }

        return true;
    }

    private function accumulateGlobalStats(&$globalStats, $analysis): void
    {
        $globalStats['totals']['total_bookings_analyzed']++;

        $totalFinal = $analysis['calculated_data']['total_final'] ?? 0;
        $globalStats['totals']['total_calculated_revenue'] += $totalFinal;
        $globalStats['totals']['total_received_amount'] += $analysis['financial_reality']['total_received'] ?? 0;
        $globalStats['totals']['net_financial_position'] += $analysis['financial_reality']['net_balance'] ?? 0;

        if ($analysis['discrepancy_analysis']['is_financially_consistent']) {
            $globalStats['consistency']['consistent_bookings']++;
        } else {
            $globalStats['consistency']['inconsistent_bookings']++;
            $globalStats['consistency']['total_discrepancy_amount'] += $analysis['discrepancy_analysis']['main_discrepancy_amount'] ?? 0;
        }

        $status = $analysis['booking_info']['status'] ?? null;
        $statusKey = $this->getStatusKey($status);
        $globalStats['by_status'][$statusKey]['count']++;
        $globalStats['by_status'][$statusKey]['revenue'] += $totalFinal;

        if (!$analysis['discrepancy_analysis']['is_financially_consistent']) {
            $globalStats['by_status'][$statusKey]['issues']++;
        }

        $globalStats['payment_analysis']['total_paid'] += $analysis['financial_reality']['total_paid'] ?? 0;
        $globalStats['payment_analysis']['total_vouchers_used'] += $analysis['financial_reality']['total_vouchers_used'] ?? 0;
        $globalStats['payment_analysis']['total_refunded'] += $analysis['financial_reality']['total_refunded'] ?? 0;

        if (!empty($analysis['detected_issues'])) {
            $globalStats['issues']['total_with_financial_issues']++;
        }

        $confidence = $analysis['confidence_score'] ?? 0;
        $globalStats['confidence_metrics']['average_confidence_score'] += $confidence;
        if ($confidence >= 80) {
            $globalStats['confidence_metrics']['high_confidence_count']++;
        } else {
            $globalStats['confidence_metrics']['low_confidence_count']++;
        }
    }

    private function calculateFinalMetrics(&$globalStats, $processedCount): void
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

        foreach ($globalStats['totals'] as $key => $value) {
            $globalStats['totals'][$key] = round($value, 2);
        }
    }

    private function formatAnalysisForResponse($analysis): array
    {
        return [
            'booking_id' => $analysis['booking_id'] ?? null,
            'client_name' => $analysis['booking_info']['client_name'] ?? 'Desconocido',
            'client_email' => $analysis['booking_info']['client_email'] ?? 'N/A',
            'status' => $analysis['booking_info']['status'] ?? null,
            'calculated_price' => $analysis['calculated_data']['total_final'] ?? 0,
            'net_balance' => $analysis['financial_reality']['net_balance'] ?? 0,
            'is_consistent' => $analysis['discrepancy_analysis']['is_financially_consistent'] ?? false,
            'discrepancy_amount' => $analysis['discrepancy_analysis']['main_discrepancy_amount'] ?? 0,
            'discrepancy_direction' => $analysis['discrepancy_analysis']['main_discrepancy_direction'] ?? 'none',
            'action_required' => $analysis['action_required'] ?? 'none',
            'confidence_score' => $analysis['confidence_score'] ?? 0,
            'payrexx_consistent' => $analysis['payrexx_comparison']['is_consistent'] ?? true
        ];
    }

    private function getStatusKey($status): string
    {
        $statusMap = [1 => 'active', 2 => 'cancelled', 3 => 'partial_cancelled'];
        return $statusMap[$status] ?? 'unknown';
    }

    // Métodos auxiliares para trends y análisis
    private function calculateConsistencyTrend($results): array
    {
        // Implementar análisis de tendencia de consistencia a lo largo del tiempo
        return ['trend' => 'stable', 'direction' => 'neutral'];
    }

    private function calculateAmountTrends($results): array
    {
        // Implementar análisis de tendencias de montos
        return ['average_amount' => 0, 'trend' => 'stable'];
    }

    private function calculateStatusDistribution($results): array
    {
        $distribution = ['active' => 0, 'cancelled' => 0, 'partial_cancelled' => 0];

        foreach ($results as $result) {
            $status = $this->getStatusKey($result['status'] ?? 1);
            $distribution[$status]++;
        }

        return $distribution;
    }

    private function calculateConfidenceTrend($results): array
    {
        $confidenceScores = array_column($results, 'confidence_score');
        $averageConfidence = !empty($confidenceScores) ? array_sum($confidenceScores) / count($confidenceScores) : 0;

        return [
            'average_confidence' => round($averageConfidence, 2),
            'high_confidence_count' => count(array_filter($confidenceScores, fn($score) => $score >= 80))
        ];
    }

    private function getPriorityReason($result): string
    {
        if ($result['discrepancy_amount'] > 50) {
            return 'Discrepancia alta: ' . $result['discrepancy_amount'] . '€';
        }

        if ($result['confidence_score'] < 50) {
            return 'Baja confianza en el análisis';
        }

        return 'Revisión estándar requerida';
    }

    private function analyzeVoucherIssues($voucherAnalysis): array
    {
        $recommendations = [];

        if ($voucherAnalysis['voucher_consistency_score'] < 80) {
            $recommendations[] = [
                'type' => 'voucher_inconsistency',
                'priority' => 'medium',
                'title' => 'Inconsistencias en vouchers',
                'description' => 'Los vouchers muestran inconsistencias que requieren revisión',
                'actions' => ['Verificar estado de vouchers', 'Revisar logs de voucher']
            ];
        }

        return $recommendations;
    }

    private function getComparisonRecommendation($storedTotal, $calculatedTotal, $realityAnalysis): string
    {
        $storedVsCalculated = abs($storedTotal - $calculatedTotal) <= 0.50;
        $realityConsistent = $realityAnalysis['reality_check']['is_consistent'];

        if ($storedVsCalculated && $realityConsistent) {
            return "✅ Ambos métodos coinciden - reserva consistente";
        } elseif (!$storedVsCalculated && $realityConsistent) {
            return "🔄 Actualizar price_total almacenado";
        } elseif ($storedVsCalculated && !$realityConsistent) {
            return "⚠️ Revisar movimientos de dinero reales";
        } else {
            return "🚨 Múltiples inconsistencias - revisar completamente";
        }
    }

    private function getDetailedPayrexxVerification($bookings, $payrexxAnalysis): array
    {
        $detailed = [];

        foreach ($bookings as $booking) {
            $payrexxPayments = $booking->payments()->whereNotNull('payrexx_reference')->get();

            if ($payrexxPayments->isNotEmpty()) {
                $bookingVerification = $this->verifyBookingWithPayrexx($booking);
                $detailed[] = [
                    'booking_id' => $booking->id,
                    'client_name' => $booking->clientMain->first_name . ' ' . $booking->clientMain->last_name,
                    'verification' => $bookingVerification
                ];
            }
        }

        return $detailed;
    }

    /**
     * MÉTODOS PARA DETECTAR Y MANEJAR RESERVAS DE TEST
     * Agregar al FinanceController.php
     */

    /**
     * MÉTODO PRINCIPAL: Detectar si una reserva completa es de test
     */
    private function isTestBooking($booking): array
    {
        $testAnalysis = [
            'is_test_booking' => false,
            'confidence_level' => 'unknown',
            'test_indicators' => [],
            'test_transactions_count' => 0,
            'total_transactions_count' => 0,
            'test_amount_percentage' => 0,
            'reasons' => []
        ];

        try {
            // 1. VERIFICAR TRANSACCIONES DE PAYREXX
            $payrexxPayments = $booking->payments()->whereNotNull('payrexx_reference')->get();
            $testTransactions = 0;
            $totalPayrexxAmount = 0;
            $testPayrexxAmount = 0;

            foreach ($payrexxPayments as $payment) {
                $testDetection = $this->quickTestDetection($payment);

                if ($testDetection['is_test']) {
                    $testTransactions++;
                    $testPayrexxAmount += $payment->amount;
                    $testAnalysis['test_indicators'] = array_merge(
                        $testAnalysis['test_indicators'],
                        $testDetection['indicators']
                    );
                }

                $totalPayrexxAmount += $payment->amount;
            }

            $testAnalysis['test_transactions_count'] = $testTransactions;
            $testAnalysis['total_transactions_count'] = $payrexxPayments->count();

            // 2. CALCULAR PORCENTAJE DE TRANSACCIONES DE TEST
            if ($payrexxPayments->count() > 0) {
                $testAnalysis['test_amount_percentage'] = ($testPayrexxAmount / $totalPayrexxAmount) * 100;
            }

            // 3. VERIFICAR CLIENTE DE TEST
            $client = $booking->clientMain;
            if ($client && $this->isTestClient($client)) {
                $testAnalysis['test_indicators'][] = 'test_client';
                $testAnalysis['reasons'][] = 'Cliente identificado como test';
            }

            // 4. VERIFICAR PATRONES EN LA RESERVA
            $bookingPatterns = $this->analyzeBookingTestPatterns($booking);
            if ($bookingPatterns['likely_test']) {
                $testAnalysis['test_indicators'] = array_merge(
                    $testAnalysis['test_indicators'],
                    $bookingPatterns['indicators']
                );
                $testAnalysis['reasons'] = array_merge(
                    $testAnalysis['reasons'],
                    $bookingPatterns['reasons']
                );
            }

            // 5. DETERMINAR SI ES RESERVA DE TEST
            $testAnalysis['is_test_booking'] = $this->determineIfTestBooking($testAnalysis, $payrexxPayments->count());
            $testAnalysis['confidence_level'] = $this->calculateTestConfidence($testAnalysis);

            Log::info("Análisis de test para booking {$booking->id}", [
                'is_test' => $testAnalysis['is_test_booking'],
                'confidence' => $testAnalysis['confidence_level'],
                'test_transactions' => $testTransactions,
                'total_transactions' => $payrexxPayments->count(),
                'indicators' => $testAnalysis['test_indicators']
            ]);

        } catch (\Exception $e) {
            Log::warning("Error analizando test booking {$booking->id}: " . $e->getMessage());
            $testAnalysis['test_indicators'][] = 'analysis_error';
            $testAnalysis['reasons'][] = 'Error en análisis: ' . $e->getMessage();
        }

        return $testAnalysis;
    }


    /**
     * NUEVO MÉTODO: Calcular el revenue original de una reserva (incluso si está cancelada)
     */
    private function getOriginalBookingRevenue($booking): float
    {
        $originalRevenue = 0;
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);

        // Para reservas canceladas, necesitamos calcular lo que valían ANTES de cancelarse
        $groupedActivities = $booking->getGroupedActivitiesAttribute();

        foreach ($groupedActivities as $activity) {
            $course = $activity['course'];

            // Saltar cursos excluidos
            if (in_array($course->id, $excludedCourses)) {
                continue;
            }

            // Para cancelled revenue, SIEMPRE sumar el total original
            // (independientemente del status actual)
            $originalRevenue += $activity['total'];
        }

        return $originalRevenue;
    }

    /**
     * NUEVO MÉTODO: Clasificar reservas por tipo real
     */
    private function classifyBookings($bookings): array
    {
        $classification = [
            'production_active' => [],      // Reservas reales que SÍ generan expected
            'production_partial' => [],    // Reservas parcialmente canceladas (solo parte activa cuenta)
            'test' => [],                  // Reservas de test (excluidas)
            'cancelled' => [],             // Reservas canceladas (NO cuentan en expected)
            'summary' => [
                'total_bookings' => $bookings->count(),
                'production_active_count' => 0,
                'production_partial_count' => 0,
                'test_count' => 0,
                'cancelled_count' => 0,
                // INGRESOS ESPERADOS = Solo lo que realmente esperamos cobrar
                'expected_revenue' => 0,      // Solo activas + parte activa de parciales
                'test_revenue_excluded' => 0,
                'cancelled_revenue_processed' => 0, // Ya no esperamos esto, pero analizamos cómo se procesó
                'partial_cancelled_revenue' => 0    // Parte cancelada de las parciales
            ]
        ];

        foreach ($bookings as $booking) {
            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);
            $totalRevenue = $quickAnalysis['calculated_amount'];

            // 1. DETECTAR TEST PRIMERO
            $testAnalysis = $this->isTestBooking($booking);
            if ($testAnalysis['is_test_booking'] && $testAnalysis['confidence_level'] !== 'low') {
                $classification['test'][] = $booking;
                $classification['summary']['test_count']++;
                $classification['summary']['test_revenue_excluded'] += $totalRevenue;
                continue;
            }

            // 2. CLASIFICAR POR ESTADO REAL
            switch ($booking->status) {
                case 1: // ACTIVA
                    $classification['production_active'][] = $booking;
                    $classification['summary']['production_active_count']++;
                    $classification['summary']['expected_revenue'] += $totalRevenue;
                    break;

                case 2: // COMPLETAMENTE CANCELADA
                    $classification['cancelled'][] = $booking;
                    $classification['summary']['cancelled_count']++;

                    // ✅ USAR EL REVENUE ORIGINAL (antes de cancelarse)
                    $originalRevenue = $this->getOriginalBookingRevenue($booking);
                    $classification['summary']['cancelled_revenue_processed'] += $originalRevenue;
                    // ❌ NO se añade a expected_revenue porque ya no esperamos cobrar nada
                    break;

                case 3: // PARCIALMENTE CANCELADA
                    $classification['production_partial'][] = $booking;
                    $classification['summary']['production_partial_count']++;

                    // 🔍 CALCULAR SOLO LA PARTE ACTIVA
                    $activeRevenue = $this->calculateActivePortionRevenue($booking);
                    $cancelledRevenue = $totalRevenue - $activeRevenue;

                    $classification['summary']['expected_revenue'] += $activeRevenue; // Solo la parte activa
                    $classification['summary']['partial_cancelled_revenue'] += $cancelledRevenue;
                    break;
            }
        }

        $classification['summary']['production_count'] =
            $classification['summary']['production_active_count'] +
            $classification['summary']['production_partial_count'];

        $classification['summary']['production_revenue'] =
            $classification['summary']['expected_revenue'];

        $classification['summary']['test_revenue'] =
            $classification['summary']['test_revenue_excluded'];

        $classification['summary']['cancelled_revenue'] =
            $classification['summary']['cancelled_revenue_processed'];

        // Redondear valores
        foreach (['expected_revenue', 'test_revenue_excluded', 'cancelled_revenue_processed', 'partial_cancelled_revenue'] as $key) {
            $classification['summary'][$key] = round($classification['summary'][$key], 2);
        }

        Log::info('Clasificación correcta de reservas completada', [
            'total_bookings' => $classification['summary']['total_bookings'],
            'expected_revenue' => $classification['summary']['expected_revenue'], // Solo lo que realmente esperamos
            'cancelled_excluded' => $classification['summary']['cancelled_revenue_processed'], // Ya no esperamos esto
            'test_excluded' => $classification['summary']['test_revenue_excluded']
        ]);

        return $classification;
    }


    /**
     * 🆕 NUEVO MÉTODO: Análisis de sources/orígenes de reservas
     */
    private function analyzeBookingSources($bookings): array
    {
        $sourceAnalysis = [
            'total_bookings' => $bookings->count(),
            'source_breakdown' => [],
            'source_revenue' => [],
            'source_performance' => []
        ];

        $sourceStats = [];

        foreach ($bookings as $booking) {
            $source = $booking->source ?? 'unknown';

            if (!isset($sourceStats[$source])) {
                $sourceStats[$source] = [
                    'count' => 0,
                    'revenue' => 0,
                    'clients' => [],
                    'avg_booking_value' => 0,
                    'consistency_issues' => 0
                ];
            }

            $sourceStats[$source]['count']++;
            $sourceStats[$source]['clients'][] = $booking->client_main_id;

            // Calcular revenue solo si no es test o cancelada
            $testAnalysis = $this->isTestBooking($booking);
            $isTest = $testAnalysis['is_test_booking'] && $testAnalysis['confidence_level'] !== 'low';

            if (!$isTest && $booking->status != 2) {
                $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);
                $sourceStats[$source]['revenue'] += $quickAnalysis['calculated_amount'];

                if ($quickAnalysis['has_issues']) {
                    $sourceStats[$source]['consistency_issues']++;
                }
            }
        }

        // Procesar estadísticas
        foreach ($sourceStats as $source => $stats) {
            $uniqueClients = count(array_unique($stats['clients']));
            $avgBookingValue = $stats['count'] > 0 ? $stats['revenue'] / $stats['count'] : 0;

            $sourceAnalysis['source_breakdown'][] = [
                'source' => $source,
                'bookings' => $stats['count'],
                'percentage' => round(($stats['count'] / $sourceAnalysis['total_bookings']) * 100, 2),
                'unique_clients' => $uniqueClients,
                'revenue' => round($stats['revenue'], 2),
                'avg_booking_value' => round($avgBookingValue, 2),
                'consistency_issues' => $stats['consistency_issues'],
                'consistency_rate' => $stats['count'] > 0
                    ? round((($stats['count'] - $stats['consistency_issues']) / $stats['count']) * 100, 2)
                    : 100
            ];
        }

        // Ordenar por cantidad de reservas
        usort($sourceAnalysis['source_breakdown'], function($a, $b) {
            return $b['bookings'] <=> $a['bookings'];
        });

        return $sourceAnalysis;
    }

    private function analyzePaymentMethodsImproved($productionBookings): array
    {
        $paymentMethodStats = [];
        $totalRevenue = 0;
        $totalPayments = 0;

        foreach ($productionBookings as $booking) {
            foreach ($booking->payments->where('status', 'paid') as $payment) {
                $method = $this->determinePaymentMethodImproved($payment);

                if (!isset($paymentMethodStats[$method])) {
                    $paymentMethodStats[$method] = [
                        'count' => 0,
                        'revenue' => 0,
                        'bookings' => []
                    ];
                }

                $paymentMethodStats[$method]['count']++;
                $paymentMethodStats[$method]['revenue'] += $payment->amount;
                $paymentMethodStats[$method]['bookings'][] = $booking->id;

                $totalRevenue += $payment->amount;
                $totalPayments++;
            }
        }

        // Procesar estadísticas
        $processedStats = [];
        foreach ($paymentMethodStats as $method => $stats) {
            $processedStats[] = [
                'method' => $method,
                'display_name' => $this->getPaymentMethodDisplayName($method),
                'count' => $stats['count'],
                'percentage' => $totalPayments > 0 ? round(($stats['count'] / $totalPayments) * 100, 2) : 0,
                'revenue' => round($stats['revenue'], 2),
                'revenue_percentage' => $totalRevenue > 0 ? round(($stats['revenue'] / $totalRevenue) * 100, 2) : 0,
                'unique_bookings' => count(array_unique($stats['bookings'])),
                'avg_payment_amount' => $stats['count'] > 0 ? round($stats['revenue'] / $stats['count'], 2) : 0
            ];
        }

        // Ordenar por revenue
        usort($processedStats, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        return [
            'total_payments' => $totalPayments,
            'total_revenue' => round($totalRevenue, 2),
            'methods' => $processedStats,
            'online_vs_offline' => $this->calculateOnlineOfflineRatio($processedStats)
        ];
    }

    /**
     * 🆕 MÉTODO AUXILIAR: Calcular ratio online vs offline
     */
    private function calculateOnlineOfflineRatio($methodStats): array
    {
        $onlineMethods = ['boukii_direct', 'online_link'];
        $offlineMethods = ['cash', 'card_offline', 'transfer', 'boukii_offline', 'online_manual'];

        $onlineRevenue = 0;
        $offlineRevenue = 0;
        $onlineCount = 0;
        $offlineCount = 0;

        foreach ($methodStats as $method) {
            if (in_array($method['method'], $onlineMethods)) {
                $onlineRevenue += $method['revenue'];
                $onlineCount += $method['count'];
            } elseif (in_array($method['method'], $offlineMethods)) {
                $offlineRevenue += $method['revenue'];
                $offlineCount += $method['count'];
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

    /**
     * 🆕 MÉTODO AUXILIAR: Nombres display para métodos de pago
     */
    private function getPaymentMethodDisplayName($method): string
    {
        $names = [
            'boukii_direct' => 'BoukiiPay (Pasarela Directa)',
            'online_link' => 'Online (Vía Link)',
            'cash' => 'Efectivo',
            'card_offline' => 'Tarjeta (Offline)',
            'transfer' => 'Transferencia',
            'boukii_offline' => 'BoukiiPay (Offline)',
            'online_manual' => 'Online (Manual)',
            'other' => 'Otros'
        ];

        return $names[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    /**
     * 🔧 MÉTODO MEJORADO: Determinar método de pago con distinción link vs pasarela
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
        if (str_contains($notes, 'cash') || str_contains($notes, 'efectivo')) {
            return 'cash';
        }

        if (str_contains($notes, 'card') || str_contains($notes, 'tarjeta')) {
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
                return 'online_manual';   // Online sin payrexx = manual
            default:
                return 'other';
        }
    }

    /**
     * NUEVO MÉTODO: Calcular solo la parte activa de una reserva parcialmente cancelada
     */
    private function calculateActivePortionRevenue($booking): float
    {
        $activeRevenue = 0;
        $excludedCourses = array_map('intval', self::EXCLUDED_COURSES);

        // Usar grouped activities pero solo contar las que tienen usuarios activos
        $groupedActivities = $booking->getGroupedActivitiesAttribute();

        foreach ($groupedActivities as $activity) {
            $course = $activity['course'];

            // Saltar cursos excluidos
            if (in_array($course->id, $excludedCourses)) {
                continue;
            }

            // Solo contar si tiene usuarios activos (status = 1)
            $hasActiveUsers = false;
            foreach ($activity['statusList'] as $status) {
                if ($status == 1) { // Al menos un usuario activo
                    $hasActiveUsers = true;
                    break;
                }
            }

            if ($hasActiveUsers) {
                // Calcular precio proporcionalmente a usuarios activos
                $activeUsers = count(array_filter($activity['statusList'], fn($status) => $status == 1));
                $totalUsers = count($activity['statusList']);

                if ($totalUsers > 0) {
                    $proportion = $activeUsers / $totalUsers;
                    $activeRevenue += $activity['total'] * $proportion;
                }
            }
        }

        return round($activeRevenue, 2);
    }


    /**
     * Verificar si un cliente es de test
     */
    private function isTestClient($client): bool
    {
        if (!$client) return false;

        // 🎯 CLIENTES TEST CONFIRMADOS 100%
        $confirmedTestClientIds = [18956, 14479, 13583, 13524];

        // 🤔 CLIENTES PROBABLEMENTE TEST
        $likelyTestClientIds = [10358, 10735];

        // Verificar por ID (más confiable)
        if (in_array($client->id, $confirmedTestClientIds)) {
            return true;
        }

        if (in_array($client->id, $likelyTestClientIds)) {
            return true; // Los incluimos como test también
        }

        // Verificaciones por patrones en datos (mantener las existentes)
        $testIndicators = [
            // Emails de test
            stripos($client->email ?? '', 'test') !== false,
            stripos($client->email ?? '', 'demo') !== false,
            stripos($client->email ?? '', 'example') !== false,
            stripos($client->email ?? '', 'fake') !== false,

            // Nombres de test
            stripos($client->first_name ?? '', 'test') !== false,
            stripos($client->last_name ?? '', 'test') !== false,
            stripos($client->first_name ?? '', 'demo') !== false,

            // Patrones específicos
            $client->email === 'test@test.com',
            $client->first_name === 'Test',
            $client->last_name === 'User'
        ];

        return in_array(true, $testIndicators);
    }


    /**
     * Analizar patrones de test en la reserva
     */
    private function analyzeBookingTestPatterns($booking): array
    {
        $patterns = [
            'likely_test' => false,
            'indicators' => [],
            'reasons' => []
        ];

        try {
            // 1. VERIFICAR AMBIENTE
/*            if (env('APP_ENV') !== 'production') {
                $patterns['likely_test'] = true;
                $patterns['indicators'][] = 'non_production_environment';
                $patterns['reasons'][] = 'Creada en ambiente de desarrollo';
            }*/

            // 2. VERIFICAR FECHAS SOSPECHOSAS
            $createdAt = $booking->created_at;

            // Reservas creadas fuera de horario laboral (pueden ser test)
            if ($createdAt->hour < 8 || $createdAt->hour > 22) {
                $patterns['indicators'][] = 'unusual_creation_time';
                $patterns['reasons'][] = 'Creada fuera de horario laboral (' . $createdAt->format('H:i') . ')';
            }

            // 3. VERIFICAR PRECIOS SOSPECHOSOS
            $totalCalculated = 0;
            $groupedActivities = $booking->getGroupedActivitiesAttribute();

            foreach ($groupedActivities as $activity) {
                if (!in_array($activity['course']->id, self::EXCLUDED_COURSES)) {
                    $totalCalculated += $activity['total'];
                }
            }

            // Importes típicos de test
            $testAmounts = [1, 5, 10, 50, 100, 1.00, 5.00, 10.00, 50.00, 100.00];
            if (in_array($totalCalculated, $testAmounts)) {
                $patterns['indicators'][] = 'test_amount_pattern';
                $patterns['reasons'][] = "Importe sospechoso de test: {$totalCalculated}€";
            }

            // 4. VERIFICAR CURSOS DE TEST
            foreach ($booking->bookingUsers as $bookingUser) {
                $course = $bookingUser->course;
                if (stripos($course->name ?? '', 'test') !== false) {
                    $patterns['likely_test'] = true;
                    $patterns['indicators'][] = 'test_course_name';
                    $patterns['reasons'][] = "Curso de test: {$course->name}";
                }
            }

            // 5. VERIFICAR REFERENCIA DE BOOKING
            if (stripos($booking->payrexx_reference ?? '', 'test') !== false) {
                $patterns['likely_test'] = true;
                $patterns['indicators'][] = 'test_reference';
                $patterns['reasons'][] = 'Referencia contiene "test"';
            }

        } catch (\Exception $e) {
            Log::warning("Error analizando patrones de test: " . $e->getMessage());
        }

        return $patterns;
    }

    /**
     * Determinar si es reserva de test basado en análisis
     */
    private function determineIfTestBooking($testAnalysis, $totalTransactions): bool
    {
        // REGLA 1: Si TODAS las transacciones de Payrexx son de test → es test
        if ($totalTransactions > 0 && $testAnalysis['test_transactions_count'] === $totalTransactions) {
            return true;
        }

        // REGLA 2: Si más del 80% del dinero es de test → es test
        if ($testAnalysis['test_amount_percentage'] > 80) {
            return true;
        }

        // REGLA 3: Si hay indicadores específicos → es test
        $strongIndicators = [
            'test_client',
            'test_course_name',
            'test_reference'
        ];

        foreach ($strongIndicators as $indicator) {
            if (in_array($indicator, $testAnalysis['test_indicators'])) {
                return true;
            }
        }

        // REGLA 4: Si hay múltiples indicadores débiles → es test
        $weakIndicators = [
            'development_environment',
            'unusual_creation_time',
            'test_amount_pattern'
        ];

        $weakCount = 0;
        foreach ($weakIndicators as $indicator) {
            if (in_array($indicator, $testAnalysis['test_indicators'])) {
                $weakCount++;
            }
        }

        return $weakCount >= 2;
    }

    /**
     * Calcular nivel de confianza del análisis
     */
    private function calculateTestConfidence($testAnalysis): string
    {
        if (!$testAnalysis['is_test_booking']) {
            return 'high'; // Alta confianza de que NO es test
        }

        $strongIndicators = ['test_client', 'test_course_name', 'test_reference'];
        $hasStrongIndicator = false;

        foreach ($strongIndicators as $indicator) {
            if (in_array($indicator, $testAnalysis['test_indicators'])) {
                $hasStrongIndicator = true;
                break;
            }
        }

        if ($hasStrongIndicator) {
            return 'high';
        }

        if ($testAnalysis['test_transactions_count'] === $testAnalysis['total_transactions_count'] &&
            $testAnalysis['total_transactions_count'] > 0) {
            return 'high';
        }

        if ($testAnalysis['test_amount_percentage'] > 80) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * MÉTODO ACTUALIZADO: Análisis financiero excluyendo reservas de test
     */
    private function calculateQuickFinancialStatsExcludingTest($bookings): array
    {
        $stats = [
            'revenue_expected' => 0,
            'revenue_received' => 0,
            'consistency_issues' => 0,
            'consistency_rate' => 0,
            // NUEVOS CAMPOS PARA TEST
            'test_bookings_detected' => 0,
            'test_revenue_excluded' => 0,
            'production_bookings_count' => 0
        ];

        $consistentBookings = 0;
        $totalAnalyzed = 0;
        $productionBookings = 0;

        foreach ($bookings as $booking) {
            // DETECTAR SI ES RESERVA DE TEST
            $testAnalysis = $this->isTestBooking($booking);

            if ($testAnalysis['is_test_booking'] && $testAnalysis['confidence_level'] !== 'low') {
                // EXCLUIR RESERVAS DE TEST DE LOS CÁLCULOS PRINCIPALES
                $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

                $stats['test_bookings_detected']++;
                $stats['test_revenue_excluded'] += $quickAnalysis['calculated_amount'];

                Log::info("Reserva de test excluida del cálculo financiero", [
                    'booking_id' => $booking->id,
                    'excluded_amount' => $quickAnalysis['calculated_amount'],
                    'confidence' => $testAnalysis['confidence_level'],
                    'indicators' => $testAnalysis['test_indicators']
                ]);

                continue; // SALTAR ESTA RESERVA
            }

            // PROCESAR SOLO RESERVAS DE PRODUCCIÓN
            $productionBookings++;
            $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);

            $stats['revenue_expected'] += $quickAnalysis['calculated_amount'];
            $stats['revenue_received'] += $quickAnalysis['received_amount'];

            if (!$quickAnalysis['has_issues']) {
                $consistentBookings++;
            } else {
                $stats['consistency_issues']++;
            }

            $totalAnalyzed++;
        }

        $stats['production_bookings_count'] = $productionBookings;

        $stats['consistency_rate'] = $totalAnalyzed > 0
            ? round(($consistentBookings / $totalAnalyzed) * 100, 2)
            : 100;

        $stats['revenue_expected'] = round($stats['revenue_expected'], 2);
        $stats['revenue_received'] = round($stats['revenue_received'], 2);
        $stats['revenue_pending'] = round($stats['revenue_expected'] - $stats['revenue_received'], 2);
        $stats['test_revenue_excluded'] = round($stats['test_revenue_excluded'], 2);

        return $stats;
    }

    /**
     * MÉTODO ACTUALIZADO: KPIs ejecutivos excluyendo test
     */
    private function calculateExecutiveKpisExcludingTest($bookings, Request $request): array
    {
        $stats = [
            'total_bookings' => $bookings->count(),
            'total_clients' => $bookings->pluck('client_main_id')->unique()->count(),
            'total_participants' => $bookings->sum(function($booking) {
                return $booking->bookingUsers->count();
            }),
            'revenue_expected' => 0,
            'revenue_received' => 0,
            'revenue_pending' => 0,
            'financial_health_score' => 0,
            'consistency_rate' => 0,
            'test_transactions_detected' => 0,
            'payrexx_consistency_rate' => null,
            // NUEVOS CAMPOS
            'production_bookings_count' => 0,
            'test_bookings_count' => 0,
            'test_revenue_excluded' => 0
        ];

        // Análisis financiero EXCLUYENDO test
        $financialStats = $this->calculateQuickFinancialStatsExcludingTest($bookings);
        $stats = array_merge($stats, $financialStats);

        // Calcular ratios basados SOLO en reservas de producción
        $stats['collection_efficiency'] = $stats['revenue_expected'] > 0
            ? round(($stats['revenue_received'] / $stats['revenue_expected']) * 100, 2)
            : 100;

        $stats['average_booking_value'] = $stats['production_bookings_count'] > 0
            ? round($stats['revenue_expected'] / $stats['production_bookings_count'], 2)
            : 0;

        $stats['revenue_at_risk'] = $stats['revenue_expected'] - $stats['revenue_received'];

        // Métricas adicionales
        $stats['test_bookings_count'] = $stats['test_bookings_detected'];
        $stats['test_percentage'] = $stats['total_bookings'] > 0
            ? round(($stats['test_bookings_count'] / $stats['total_bookings']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Análisis completo de reservas de test
     */
    private function analyzeTestBookingsComplete($bookings): array
    {
        $testAnalysis = [
            'total_test_bookings' => 0,
            'total_test_revenue' => 0,
            'test_confidence_distribution' => ['high' => 0, 'medium' => 0, 'low' => 0],
            'test_indicators_summary' => [],
            'test_booking_details' => [],
            'production_vs_test_ratio' => 0
        ];

        $indicatorCounts = [];
        $totalBookings = $bookings->count();

        foreach ($bookings as $booking) {
            $testBookingAnalysis = $this->isTestBooking($booking);

            if ($testBookingAnalysis['is_test_booking']) {
                $testAnalysis['total_test_bookings']++;

                // Calcular revenue de test
                $quickAnalysis = $this->getQuickBookingFinancialStatus($booking);
                $testAnalysis['total_test_revenue'] += $quickAnalysis['calculated_amount'];

                // Contar por confianza
                $confidence = $testBookingAnalysis['confidence_level'];
                $testAnalysis['test_confidence_distribution'][$confidence]++;

                // Contar indicadores
                foreach ($testBookingAnalysis['test_indicators'] as $indicator) {
                    $indicatorCounts[$indicator] = ($indicatorCounts[$indicator] ?? 0) + 1;
                }

                // Detalles de muestra (primeras 10)
                if (count($testAnalysis['test_booking_details']) < 10) {
                    $testAnalysis['test_booking_details'][] = [
                        'booking_id' => $booking->id,
                        'client_email' => $booking->clientMain->email ?? 'N/A',
                        'revenue' => $quickAnalysis['calculated_amount'],
                        'confidence' => $confidence,
                        'indicators' => $testBookingAnalysis['test_indicators'],
                        'reasons' => $testBookingAnalysis['reasons']
                    ];
                }
            }
        }

        $testAnalysis['test_indicators_summary'] = $indicatorCounts;
        $testAnalysis['total_test_revenue'] = round($testAnalysis['total_test_revenue'], 2);
        $testAnalysis['production_vs_test_ratio'] = $totalBookings > 0
            ? round((($totalBookings - $testAnalysis['total_test_bookings']) / $totalBookings) * 100, 2)
            : 100;

        return $testAnalysis;
    }
}
