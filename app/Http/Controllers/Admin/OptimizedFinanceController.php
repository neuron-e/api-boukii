<?php

/**
 * ===== OPTIMIZACIÓN COMPLETA DEL DASHBOARD FINANCIERO =====
 *
 * PROBLEMAS IDENTIFICADOS:
 * 1. Eager Loading muy pesado con relaciones anidadas profundas
 * 2. Filtrado con whereHas en lugar de joins optimizados
 * 3. Múltiples análisis que procesan la misma colección
 * 4. Falta de caché para datos que no cambian
 * 5. Consultas N+1 en análisis posteriores
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimizedFinanceController extends AppBaseController
{
    const EXCLUDED_COURSES = [260, 243];
    const CACHE_TTL = 300; // 5 minutos

    /**
     * =============== MÉTODO PRINCIPAL OPTIMIZADO ===============
     */
    public function getSeasonFinancialDashboard(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $optimizationLevel = $request->get('optimization_level', 'fast'); // Default a fast!

        // ⚡ GENERAR CACHE KEY ÚNICO
        $cacheKey = $this->generateCacheKey($request);

        Log::channel('finance')->info('=== DASHBOARD OPTIMIZADO INICIADO ===', [
            'school_id' => $request->school_id,
            'optimization_level' => $optimizationLevel,
            'cache_key' => $cacheKey
        ]);

        try {
            // 🚀 ESTRATEGIA 1: CACHÉ INTELIGENTE
            if ($optimizationLevel === 'fast') {
                $dashboard = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request, $optimizationLevel) {
                    return $this->buildOptimizedDashboard($request, $optimizationLevel);
                });
            } else {
                $dashboard = $this->buildOptimizedDashboard($request, $optimizationLevel);
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $dashboard['performance_metrics']['execution_time_ms'] = $executionTime;

            Log::channel('finance')->info('=== DASHBOARD COMPLETADO ===', [
                'execution_time_ms' => $executionTime,
                'optimization_level' => $optimizationLevel,
                'from_cache' => $optimizationLevel === 'fast' && Cache::has($cacheKey)
            ]);

            return $this->sendResponse($dashboard, 'Dashboard optimizado generado');

        } catch (\Exception $e) {
            Log::channel('finance')->error('Error en dashboard optimizado: ' . $e->getMessage());
            return $this->sendError('Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * =============== CONSTRUCCIÓN OPTIMIZADA DEL DASHBOARD ===============
     */
    private function buildOptimizedDashboard(Request $request, string $optimizationLevel): array
    {
        $dateRange = $this->getSeasonDateRange($request);

        // 🚀 ESTRATEGIA 2: CONSULTA ÚNICA CON RAW SQL
        $aggregatedData = $this->getAggregatedSeasonData($request, $dateRange, $optimizationLevel);

        // 🚀 ESTRATEGIA 3: CONSTRUCCIÓN EFICIENTE DEL DASHBOARD
        return [
            'season_info' => $this->buildSeasonInfo($dateRange, $request, $aggregatedData),
            'executive_kpis' => $this->buildExecutiveKpis($aggregatedData),
            'booking_sources' => $this->buildBookingSources($aggregatedData),
            'payment_methods' => $this->buildPaymentMethods($aggregatedData),
            'financial_summary' => $this->buildFinancialSummary($aggregatedData),
            'performance_metrics' => [
                'total_bookings_analyzed' => $aggregatedData['total_bookings'],
                'optimization_level' => $optimizationLevel,
                'data_freshness' => now()->toDateTimeString()
            ]
        ];
    }

    /**
     * =============== CONSULTA AGREGADA SUPER OPTIMIZADA ===============
     */
    private function getAggregatedSeasonData(Request $request, array $dateRange, string $optimizationLevel): array
    {
        // 🚀 LÍMITES AGRESIVOS POR NIVEL DE OPTIMIZACIÓN
        $limits = [
            'fast' => 1000,     // Solo las últimas 1000 reservas
            'balanced' => 5000,  // 5000 reservas
            'detailed' => null   // Sin límite
        ];

        $limit = $limits[$optimizationLevel];
        $limitClause = $limit ? "LIMIT {$limit}" : "";

        // 🚀 CONSULTA ÚNICA CON TODOS LOS DATOS NECESARIOS
        $sql = "
            WITH filtered_bookings AS (
                SELECT
                    b.id as booking_id,
                    b.school_id,
                    b.source,
                    b.price_expected,
                    b.price_actual,
                    b.status,
                    b.created_at,
                    cm.name as client_name,
                    cm.email as client_email,
                    -- Detección básica de test
                    CASE WHEN
                        (cm.email LIKE '%test%' OR
                         cm.email LIKE '%demo%' OR
                         cm.name LIKE '%test%' OR
                         cm.name LIKE '%demo%' OR
                         b.price_expected = 0)
                    THEN 1 ELSE 0 END as is_test_booking
                FROM bookings b
                INNER JOIN booking_users bu ON b.id = bu.booking_id
                LEFT JOIN clients cm ON b.client_main_id = cm.id
                WHERE b.school_id = ?
                  AND bu.date BETWEEN ? AND ?
                  AND b.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM booking_users bu2
                      INNER JOIN courses c ON bu2.course_id = c.id
                      WHERE bu2.booking_id = b.id
                      AND c.id IN (" . implode(',', self::EXCLUDED_COURSES) . ")
                      GROUP BY bu2.booking_id
                      HAVING COUNT(DISTINCT bu2.course_id) = (
                          SELECT COUNT(*) FROM booking_users bu3 WHERE bu3.booking_id = b.id
                      )
                  )
                GROUP BY b.id
                ORDER BY b.created_at DESC
                {$limitClause}
            ),
            payment_data AS (
                SELECT
                    fb.booking_id,
                    COALESCE(SUM(p.amount), 0) as total_paid,
                    GROUP_CONCAT(DISTINCT p.type) as payment_methods_used
                FROM filtered_bookings fb
                LEFT JOIN payments p ON fb.booking_id = p.booking_id AND p.deleted_at IS NULL
                GROUP BY fb.booking_id
            )
            SELECT
                -- CONTADORES BÁSICOS
                COUNT(*) as total_bookings,
                SUM(CASE WHEN fb.is_test_booking = 0 THEN 1 ELSE 0 END) as production_bookings,
                SUM(CASE WHEN fb.is_test_booking = 1 THEN 1 ELSE 0 END) as test_bookings,

                -- MÉTRICAS FINANCIERAS (solo producción)
                SUM(CASE WHEN fb.is_test_booking = 0 THEN fb.price_expected ELSE 0 END) as total_expected,
                SUM(CASE WHEN fb.is_test_booking = 0 THEN fb.price_actual ELSE 0 END) as total_actual,
                SUM(CASE WHEN fb.is_test_booking = 0 THEN pd.total_paid ELSE 0 END) as total_paid,

                -- ANÁLISIS POR ESTADO (solo producción)
                SUM(CASE WHEN fb.is_test_booking = 0 AND fb.status = 'register' THEN fb.price_expected ELSE 0 END) as expected_register,
                SUM(CASE WHEN fb.is_test_booking = 0 AND fb.status = 'payed' THEN fb.price_expected ELSE 0 END) as expected_paid,
                SUM(CASE WHEN fb.is_test_booking = 0 AND fb.status = 'cancelled' THEN fb.price_expected ELSE 0 END) as expected_cancelled,

                -- ANÁLISIS POR SOURCE (solo producción)
                SUM(CASE WHEN fb.is_test_booking = 0 AND fb.source = 'api' THEN 1 ELSE 0 END) as bookings_api,
                SUM(CASE WHEN fb.is_test_booking = 0 AND fb.source = 'page' THEN 1 ELSE 0 END) as bookings_page,
                SUM(CASE WHEN fb.is_test_booking = 0 AND fb.source IS NULL THEN 1 ELSE 0 END) as bookings_unknown,

                SUM(CASE WHEN fb.is_test_booking = 0 AND fb.source = 'api' THEN fb.price_expected ELSE 0 END) as revenue_api,
                SUM(CASE WHEN fb.is_test_booking = 0 AND fb.source = 'page' THEN fb.price_expected ELSE 0 END) as revenue_page,
                SUM(CASE WHEN fb.is_test_booking = 0 AND fb.source IS NULL THEN fb.price_expected ELSE 0 END) as revenue_unknown,

                -- MÉTODOS DE PAGO BÁSICOS
                SUM(CASE WHEN fb.is_test_booking = 0 AND pd.payment_methods_used LIKE '%cash%' THEN pd.total_paid ELSE 0 END) as paid_cash,
                SUM(CASE WHEN fb.is_test_booking = 0 AND pd.payment_methods_used LIKE '%online%' THEN pd.total_paid ELSE 0 END) as paid_online,
                SUM(CASE WHEN fb.is_test_booking = 0 AND pd.payment_methods_used LIKE '%other%' THEN pd.total_paid ELSE 0 END) as paid_other

            FROM filtered_bookings fb
            LEFT JOIN payment_data pd ON fb.booking_id = pd.booking_id
        ";

        $result = DB::selectOne($sql, [
            $request->school_id,
            $dateRange['start_date'],
            $dateRange['end_date']
        ]);

        return (array) $result;
    }

    /**
     * =============== CONSTRUCCIÓN EFICIENTE DE SECCIONES ===============
     */
    private function buildSeasonInfo(array $dateRange, Request $request, array $data): array
    {
        return [
            'season_name' => $dateRange['season_name'],
            'date_range' => [
                'start' => $dateRange['start_date'],
                'end' => $dateRange['end_date'],
                'total_days' => $dateRange['total_days']
            ],
            'school_id' => $request->school_id,
            'total_bookings' => (int) $data['total_bookings'],
            'booking_classification' => [
                'production_count' => (int) $data['production_bookings'],
                'test_count' => (int) $data['test_bookings'],
                'cancelled_count' => 0 // Calculado después si es necesario
            ]
        ];
    }

    private function buildExecutiveKpis(array $data): array
    {
        $expected = (float) $data['total_expected'];
        $actual = (float) $data['total_actual'];
        $paid = (float) $data['total_paid'];

        return [
            'revenueExpected' => $expected,
            'revenueActual' => $actual,
            'revenuePaid' => $paid,
            'revenueVariance' => $actual - $expected,
            'paymentEfficiency' => $expected > 0 ? round(($paid / $expected) * 100, 2) : 0,
            'averageBookingValue' => $data['production_bookings'] > 0 ?
                round($expected / $data['production_bookings'], 2) : 0,
            'totalBookingsAnalyzed' => (int) $data['production_bookings']
        ];
    }

    private function buildBookingSources(array $data): array
    {
        $totalBookings = (int) $data['production_bookings'];
        $totalRevenue = (float) $data['total_expected'];

        if ($totalBookings === 0) {
            return ['source_breakdown' => [], 'total_bookings' => 0];
        }

        $sources = [
            [
                'source' => 'Panel Admin',
                'bookings' => (int) $data['bookings_api'],
                'percentage' => round(($data['bookings_api'] / $totalBookings) * 100, 1),
                'revenue' => (float) $data['revenue_api'],
                'avg_booking_value' => $data['bookings_api'] > 0 ?
                    round($data['revenue_api'] / $data['bookings_api'], 2) : 0
            ],
            [
                'source' => 'Página de Reservas',
                'bookings' => (int) $data['bookings_page'],
                'percentage' => round(($data['bookings_page'] / $totalBookings) * 100, 1),
                'revenue' => (float) $data['revenue_page'],
                'avg_booking_value' => $data['bookings_page'] > 0 ?
                    round($data['revenue_page'] / $data['bookings_page'], 2) : 0
            ],
            [
                'source' => 'Origen Desconocido',
                'bookings' => (int) $data['bookings_unknown'],
                'percentage' => round(($data['bookings_unknown'] / $totalBookings) * 100, 1),
                'revenue' => (float) $data['revenue_unknown'],
                'avg_booking_value' => $data['bookings_unknown'] > 0 ?
                    round($data['revenue_unknown'] / $data['bookings_unknown'], 2) : 0
            ]
        ];

        return [
            'source_breakdown' => array_filter($sources, fn($s) => $s['bookings'] > 0),
            'total_bookings' => $totalBookings,
            'total_revenue' => $totalRevenue
        ];
    }

    private function buildPaymentMethods(array $data): array
    {
        $totalPaid = (float) ($data['paid_cash'] + $data['paid_online'] + $data['paid_other']);

        if ($totalPaid === 0) {
            return ['methods' => [], 'total_paid' => 0];
        }

        $methods = [
            [
                'display_name' => 'Efectivo',
                'revenue' => (float) $data['paid_cash'],
                'percentage' => round(($data['paid_cash'] / $totalPaid) * 100, 1)
            ],
            [
                'display_name' => 'Pago Online',
                'revenue' => (float) $data['paid_online'],
                'percentage' => round(($data['paid_online'] / $totalPaid) * 100, 1)
            ],
            [
                'display_name' => 'Otros',
                'revenue' => (float) $data['paid_other'],
                'percentage' => round(($data['paid_other'] / $totalPaid) * 100, 1)
            ]
        ];

        return [
            'methods' => array_filter($methods, fn($m) => $m['revenue'] > 0),
            'total_paid' => $totalPaid
        ];
    }

    private function buildFinancialSummary(array $data): array
    {
        return [
            'expected_register' => (float) $data['expected_register'],
            'expected_paid' => (float) $data['expected_paid'],
            'expected_cancelled' => (float) $data['expected_cancelled'],
            'total_expected' => (float) $data['total_expected'],
            'conversion_rate' => $data['total_expected'] > 0 ?
                round(($data['expected_paid'] / $data['total_expected']) * 100, 2) : 0
        ];
    }

    /**
     * =============== UTILIDADES ===============
     */
    private function generateCacheKey(Request $request): string
    {
        $key = sprintf(
            'finance_dashboard_%s_%s_%s_%s_%s',
            $request->school_id,
            $request->get('season_id', 'current'),
            $request->get('start_date', ''),
            $request->get('end_date', ''),
            $request->get('optimization_level', 'fast')
        );

        return md5($key);
    }

    private function getSeasonDateRange(Request $request): array
    {
        // Mantener la lógica existente pero optimizada
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
        } else {
            // Usar cache para la consulta de temporada
            $cacheKey = "season_dates_{$request->school_id}";
            $seasonData = Cache::remember($cacheKey, 3600, function () use ($request) {
                return DB::table('seasons')
                    ->where('school_id', $request->school_id)
                    ->whereDate('start_date', '<=', now())
                    ->whereDate('end_date', '>=', now())
                    ->first(['start_date', 'end_date', 'name']);
            });

            if ($seasonData) {
                $startDate = $seasonData->start_date;
                $endDate = $seasonData->end_date;
                $seasonName = $seasonData->name;
            } else {
                $startDate = now()->subMonths(6)->format('Y-m-d');
                $endDate = now()->format('Y-m-d');
                $seasonName = 'Últimos 6 meses';
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => \Carbon\Carbon::parse($startDate)->diffInDays($endDate),
            'season_name' => $seasonName ?? 'Período personalizado'
        ];
    }

    /**
     * =============== ENDPOINT PARA LIMPIAR CACHE ===============
     */
    public function clearDashboardCache(Request $request): JsonResponse
    {
        $pattern = "finance_dashboard_{$request->school_id}_*";

        // En producción, usar Redis::del() con patrón
        Cache::flush(); // Para desarrollo

        return $this->sendResponse(['cleared' => true], 'Cache limpiado');
    }
}

/**
 * =============== ESTRATEGIAS ADICIONALES DE OPTIMIZACIÓN ===============
 *
 * 1. 🚀 ÍNDICES DE BASE DE DATOS REQUERIDOS:
 * CREATE INDEX idx_bookings_school_created ON bookings(school_id, created_at);
 * CREATE INDEX idx_booking_users_date ON booking_users(booking_id, date);
 * CREATE INDEX idx_clients_test_detection ON clients(email, name);
 * CREATE INDEX idx_payments_booking ON payments(booking_id, type, amount);
 *
 * 2. 🚀 CONFIGURACIÓN DE CACHE EN .env:
 * CACHE_DRIVER=redis
 * REDIS_HOST=127.0.0.1
 * REDIS_PORT=6379
 *
 * 3. 🚀 CONFIGURACIÓN DE OPTIMIZACIÓN EN config/database.php:
 * 'options' => [
 *     PDO::ATTR_PERSISTENT => true,
 *     PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
 * ]
 *
 * 4. 🚀 CRON JOB PARA PRE-CALCULAR DASHBOARDS:
 * // Ejecutar cada 5 minutos para schools activas
 * * /5 * * * * php artisan dashboard:precalculate
 *
 * 5. 🚀 QUEUE JOBS PARA ANÁLISIS PESADOS:
 * dispatch(new GenerateDetailedAnalytics($schoolId, $dateRange));
 */

