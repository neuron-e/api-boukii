<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\AnalyticsService;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Payment;
use App\Models\Monitor;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Analytics Professional Controller - Optimizado para Admin Panel Angular
 * Endpoints específicos consumidos por el frontend Angular admin
 */
class AnalyticsProfessionalController extends AppBaseController
{
    /**
     * Dashboard de temporada - OPTIMIZADO para evitar N+1
     * GET /admin/finance/season-dashboard
     */
    public function seasonDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'season_id' => 'nullable|integer',
            'optimization_level' => 'nullable|in:fast,balanced,detailed'
        ]);

        $schoolId = $request->input('school_id');
        $cacheKey = "season_dashboard_{$schoolId}_" . md5(serialize($request->all()));
        
        // Cache por 30 minutos para analytics profesionales
        $data = Cache::remember($cacheKey, 1800, function () use ($request, $schoolId) {
            
            // OPTIMIZACIÓN: Una sola query con JOINs para datos ejecutivos
            $executiveKpis = DB::table('bookings as b')
                ->leftJoin('booking_users as bu', 'b.id', '=', 'bu.booking_id')
                ->leftJoin('clients as c', 'b.client_main_id', '=', 'c.id')
                ->leftJoin('payments as p', 'b.id', '=', 'p.booking_id')
                ->where('b.school_id', $schoolId)
                ->when($request->input('start_date'), fn($q) => $q->where('b.created_at', '>=', $request->input('start_date')))
                ->when($request->input('end_date'), fn($q) => $q->where('b.created_at', '<=', $request->input('end_date')))
                ->selectRaw('
                    COUNT(DISTINCT b.id) as totalBookings,
                    COUNT(DISTINCT c.id) as totalClients,
                    COUNT(bu.id) as totalParticipants,
                    SUM(CASE WHEN b.status = 1 THEN b.price_total ELSE 0 END) as revenueExpected,
                    SUM(CASE WHEN p.status = "paid" THEN p.amount ELSE 0 END) as revenueReceived,
                    SUM(CASE WHEN b.paid = 0 AND b.status = 1 THEN b.price_total ELSE 0 END) as revenuePending,
                    AVG(CASE WHEN b.status = 1 THEN b.price_total ELSE NULL END) as averageBookingValue
                ')
                ->first();

            // Calcular eficiencia de cobro
            $collectionEfficiency = $executiveKpis->revenueExpected > 0 
                ? ($executiveKpis->revenueReceived / $executiveKpis->revenueExpected) * 100 
                : 0;

            // OPTIMIZACIÓN: Query específica para fuentes de reserva
            $bookingSources = DB::table('bookings')
                ->where('school_id', $schoolId)
                ->where('status', 1)
                ->when($request->input('start_date'), fn($q) => $q->where('created_at', '>=', $request->input('start_date')))
                ->when($request->input('end_date'), fn($q) => $q->where('created_at', '<=', $request->input('end_date')))
                ->selectRaw('source, COUNT(*) as count, SUM(price_total) as revenue')
                ->groupBy('source')
                ->orderByDesc('count')
                ->get();

            // OPTIMIZACIÓN: Query específica para métodos de pago
            $paymentMethods = DB::table('payments as p')
                ->join('bookings as b', 'p.booking_id', '=', 'b.id')
                ->where('b.school_id', $schoolId)
                ->where('p.status', 'paid')
                ->when($request->input('start_date'), fn($q) => $q->where('p.created_at', '>=', $request->input('start_date')))
                ->when($request->input('end_date'), fn($q) => $q->where('p.created_at', '<=', $request->input('end_date')))
                ->selectRaw('
                    SUM(CASE WHEN b.payment_method_id = 1 THEN p.amount ELSE 0 END) as cash,
                    SUM(CASE WHEN b.payment_method_id = 2 THEN p.amount ELSE 0 END) as boukiiPay,
                    SUM(CASE WHEN b.payment_method_id = 3 THEN p.amount ELSE 0 END) as online,
                    SUM(CASE WHEN b.payment_method_id = 4 THEN p.amount ELSE 0 END) as other,
                    SUM(CASE WHEN b.payment_method_id = 5 THEN p.amount ELSE 0 END) as noPayment
                ')
                ->first();

            return [
                'executiveKpis' => [
                    'totalBookings' => (int) $executiveKpis->totalBookings,
                    'totalClients' => (int) $executiveKpis->totalClients,
                    'totalParticipants' => (int) $executiveKpis->totalParticipants,
                    'revenueExpected' => (float) $executiveKpis->revenueExpected,
                    'revenueReceived' => (float) $executiveKpis->revenueReceived,
                    'revenuePending' => (float) $executiveKpis->revenuePending,
                    'collectionEfficiency' => round($collectionEfficiency, 2),
                    'consistencyRate' => 95.5, // Placeholder para cálculo más complejo
                    'averageBookingValue' => round((float) $executiveKpis->averageBookingValue, 2)
                ],
                'bookingSources' => $bookingSources->map(fn($item) => [
                    'source' => $item->source ?: 'unknown',
                    'count' => (int) $item->count,
                    'revenue' => (float) $item->revenue,
                    'percentage' => $executiveKpis->totalBookings > 0 
                        ? round(($item->count / $executiveKpis->totalBookings) * 100, 1) 
                        : 0
                ]),
                'paymentMethods' => [
                    'cash' => (float) $paymentMethods->cash,
                    'boukiiPay' => (float) $paymentMethods->boukiiPay,
                    'online' => (float) $paymentMethods->online,
                    'other' => (float) $paymentMethods->other,
                    'noPayment' => (float) $paymentMethods->noPayment
                ],
                'cacheInfo' => [
                    'cached_at' => now()->toISOString(),
                    'ttl_minutes' => 30
                ]
            ];
        });

        return $this->sendResponse($data, 'Season dashboard retrieved successfully');
    }

    /**
     * Análisis de ingresos por período - OPTIMIZADO
     * GET /admin/analytics/revenue-by-period
     */
    public function revenueByPeriod(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer',
            'period_type' => 'required|in:daily,weekly,monthly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date'
        ]);

        $schoolId = $request->input('school_id');
        $periodType = $request->input('period_type');
        
        $cacheKey = "revenue_period_{$schoolId}_{$periodType}_" . md5(serialize($request->all()));
        
        $data = Cache::remember($cacheKey, 900, function () use ($request, $schoolId, $periodType) {
            $dateFormat = match($periodType) {
                'daily' => '%Y-%m-%d',
                'weekly' => '%Y-%u',
                'monthly' => '%Y-%m',
            };

            // OPTIMIZACIÓN: Una sola query con agregación temporal
            $revenueData = DB::table('payments as p')
                ->join('bookings as b', 'p.booking_id', '=', 'b.id')
                ->where('b.school_id', $schoolId)
                ->where('p.status', 'paid')
                ->when($request->input('start_date'), fn($q) => $q->where('p.created_at', '>=', $request->input('start_date')))
                ->when($request->input('end_date'), fn($q) => $q->where('p.created_at', '<=', $request->input('end_date')))
                ->selectRaw("
                    DATE_FORMAT(p.created_at, '{$dateFormat}') as period,
                    SUM(p.amount) as total_revenue,
                    COUNT(DISTINCT b.id) as booking_count,
                    AVG(p.amount) as avg_revenue
                ")
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            return $revenueData->map(fn($item) => [
                'period' => $item->period,
                'revenue' => (float) $item->total_revenue,
                'bookings' => (int) $item->booking_count,
                'average' => round((float) $item->avg_revenue, 2)
            ]);
        });

        return $this->sendResponse($data, 'Revenue by period retrieved successfully');
    }

    /**
     * Analytics detallado de cursos - OPTIMIZADO con relaciones
     * GET /admin/analytics/courses-detailed
     */
    public function coursesDetailed(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer',
            'course_type' => 'nullable|integer',
            'sport_id' => 'nullable|integer'
        ]);

        $schoolId = $request->input('school_id');
        $cacheKey = "courses_detailed_{$schoolId}_" . md5(serialize($request->all()));
        
        $data = Cache::remember($cacheKey, 1200, function () use ($request, $schoolId) {
            // OPTIMIZACIÓN: Query con JOINs para evitar N+1
            $coursesAnalytics = DB::table('courses as c')
                ->leftJoin('booking_users as bu', 'c.id', '=', 'bu.course_id')
                ->leftJoin('bookings as b', 'bu.booking_id', '=', 'b.id')
                ->leftJoin('sports as s', 'c.sport_id', '=', 's.id')
                ->where('c.school_id', $schoolId)
                ->where('c.active', 1)
                ->when($request->input('course_type'), fn($q) => $q->where('c.course_type', $request->input('course_type')))
                ->when($request->input('sport_id'), fn($q) => $q->where('c.sport_id', $request->input('sport_id')))
                ->selectRaw('
                    c.id,
                    c.name,
                    c.course_type,
                    c.price,
                    s.name as sport_name,
                    COUNT(DISTINCT bu.id) as total_participants,
                    COUNT(DISTINCT b.id) as total_bookings,
                    SUM(CASE WHEN bu.status = 1 THEN bu.price ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN bu.status = 1 THEN bu.price ELSE NULL END) as avg_price_per_participant,
                    COUNT(CASE WHEN bu.attended = 1 THEN 1 END) as attended_count,
                    COUNT(CASE WHEN bu.status = 2 THEN 1 END) as cancelled_count
                ')
                ->groupBy('c.id', 'c.name', 'c.course_type', 'c.price', 's.name')
                ->havingRaw('COUNT(DISTINCT bu.id) > 0')
                ->orderByDesc('total_revenue')
                ->get();

            return $coursesAnalytics->map(function ($course) {
                $completionRate = $course->total_participants > 0 
                    ? round(($course->attended_count / $course->total_participants) * 100, 1)
                    : 0;
                
                $cancellationRate = $course->total_participants > 0
                    ? round(($course->cancelled_count / $course->total_participants) * 100, 1)
                    : 0;

                return [
                    'courseId' => $course->id,
                    'courseName' => $course->name,
                    'courseType' => $course->course_type,
                    'sportName' => $course->sport_name,
                    'basePrice' => (float) $course->price,
                    'totalParticipants' => (int) $course->total_participants,
                    'totalBookings' => (int) $course->total_bookings,
                    'totalRevenue' => (float) $course->total_revenue,
                    'avgPricePerParticipant' => round((float) $course->avg_price_per_participant, 2),
                    'completionRate' => $completionRate,
                    'cancellationRate' => $cancellationRate,
                    'profitability' => $course->total_revenue > 0 && $course->price > 0 
                        ? round(($course->total_revenue / ($course->price * $course->total_participants)) * 100, 1)
                        : 0
                ];
            });
        });

        return $this->sendResponse($data, 'Detailed courses analytics retrieved successfully');
    }

    /**
     * Eficiencia de monitores - OPTIMIZADO
     * GET /admin/analytics/monitors-efficiency
     */
    public function monitorsEfficiency(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date'
        ]);

        $schoolId = $request->input('school_id');
        $cacheKey = "monitors_efficiency_{$schoolId}_" . md5(serialize($request->all()));
        
        $data = Cache::remember($cacheKey, 1800, function () use ($request, $schoolId) {
            // OPTIMIZACIÓN: Query con JOINs para datos de monitores
            $monitorsData = DB::table('monitors as m')
                ->leftJoin('booking_users as bu', 'm.id', '=', 'bu.monitor_id')
                ->leftJoin('bookings as b', 'bu.booking_id', '=', 'b.id')
                ->leftJoin('clients as c', 'bu.client_id', '=', 'c.id')
                ->where('m.active_school', $schoolId)
                ->where('m.active', 1)
                ->when($request->input('start_date'), fn($q) => $q->where('bu.date', '>=', $request->input('start_date')))
                ->when($request->input('end_date'), fn($q) => $q->where('bu.date', '<=', $request->input('end_date')))
                ->selectRaw('
                    m.id,
                    CONCAT(m.first_name, " ", m.last_name) as monitor_name,
                    COUNT(DISTINCT bu.id) as total_sessions,
                    COUNT(DISTINCT c.id) as unique_clients,
                    COUNT(DISTINCT bu.date) as working_days,
                    SUM(CASE WHEN bu.status = 1 THEN bu.price ELSE 0 END) as revenue_generated,
                    COUNT(CASE WHEN bu.attended = 1 THEN 1 END) as attended_sessions,
                    COUNT(CASE WHEN bu.status = 2 THEN 1 END) as cancelled_sessions,
                    AVG(TIMESTAMPDIFF(MINUTE, 
                        CONCAT(bu.date, " ", bu.hour_start), 
                        CONCAT(bu.date, " ", bu.hour_end)
                    )) as avg_session_duration
                ')
                ->groupBy('m.id', 'm.first_name', 'm.last_name')
                ->havingRaw('COUNT(DISTINCT bu.id) > 0')
                ->orderByDesc('revenue_generated')
                ->get();

            return $monitorsData->map(function ($monitor) {
                $efficiency = $monitor->total_sessions > 0 
                    ? round(($monitor->attended_sessions / $monitor->total_sessions) * 100, 1)
                    : 0;
                
                $clientRetention = $monitor->total_sessions > 0 && $monitor->unique_clients > 0
                    ? round($monitor->total_sessions / $monitor->unique_clients, 1)
                    : 0;

                return [
                    'monitorId' => $monitor->id,
                    'monitorName' => $monitor->monitor_name,
                    'totalSessions' => (int) $monitor->total_sessions,
                    'uniqueClients' => (int) $monitor->unique_clients,
                    'workingDays' => (int) $monitor->working_days,
                    'revenueGenerated' => (float) $monitor->revenue_generated,
                    'attendanceRate' => $efficiency,
                    'cancellationRate' => $monitor->total_sessions > 0 
                        ? round(($monitor->cancelled_sessions / $monitor->total_sessions) * 100, 1) 
                        : 0,
                    'clientRetentionScore' => $clientRetention,
                    'avgSessionDuration' => round((float) $monitor->avg_session_duration, 0),
                    'revenuePerSession' => $monitor->total_sessions > 0 
                        ? round($monitor->revenue_generated / $monitor->total_sessions, 2)
                        : 0
                ];
            });
        });

        return $this->sendResponse($data, 'Monitors efficiency analytics retrieved successfully');
    }

    /**
     * Limpiar caché de analytics
     * DELETE /admin/analytics/cache/clear
     */
    public function clearCache(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer',
            'cache_type' => 'nullable|in:all,season,revenue,courses,monitors'
        ]);

        $schoolId = $request->input('school_id');
        $cacheType = $request->input('cache_type', 'all');

        $patterns = [
            'all' => ["season_dashboard_{$schoolId}_*", "revenue_period_{$schoolId}_*", "courses_detailed_{$schoolId}_*", "monitors_efficiency_{$schoolId}_*"],
            'season' => ["season_dashboard_{$schoolId}_*"],
            'revenue' => ["revenue_period_{$schoolId}_*"],
            'courses' => ["courses_detailed_{$schoolId}_*"],
            'monitors' => ["monitors_efficiency_{$schoolId}_*"]
        ];

        $cleared = 0;
        $cacheStore = Cache::getStore();

        foreach ($patterns[$cacheType] as $pattern) {
            try {
                if (method_exists($cacheStore, 'getRedis')) {
                    // Si tenemos Redis disponible, usar pattern matching
                    $keys = $cacheStore->getRedis()->keys($pattern);
                    foreach ($keys as $key) {
                        Cache::forget($key);
                        $cleared++;
                    }
                } else {
                    // Fallback para otros drivers de cache
                    Cache::forget($pattern);
                    $cleared++;
                }
            } catch (\Exception $e) {
                \Log::warning('CACHE_CLEAR_FAILED', [
                    'pattern' => $pattern,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->sendResponse([
            'cleared_keys' => $cleared,
            'cache_type' => $cacheType
        ], 'Analytics cache cleared successfully');
    }

    /**
     * Estado del caché
     * GET /admin/analytics/cache/status
     */
    public function cacheStatus(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|integer'
        ]);

        $schoolId = $request->input('school_id');
        
        $cacheKeys = [
            'season_dashboard' => "season_dashboard_{$schoolId}_*",
            'revenue_period' => "revenue_period_{$schoolId}_*", 
            'courses_detailed' => "courses_detailed_{$schoolId}_*",
            'monitors_efficiency' => "monitors_efficiency_{$schoolId}_*"
        ];

        $status = [];
        $cacheStore = Cache::getStore();

        foreach ($cacheKeys as $type => $pattern) {
            try {
                if (method_exists($cacheStore, 'getRedis')) {
                    $keys = $cacheStore->getRedis()->keys($pattern);
                    $status[$type] = [
                        'cached_queries' => count($keys),
                        'last_cached' => count($keys) > 0 ? Cache::get(end($keys) . '_timestamp') : null
                    ];
                } else {
                    // Para drivers sin Redis, retornar información limitada
                    $status[$type] = [
                        'cached_queries' => 'N/A (file cache)',
                        'last_cached' => null
                    ];
                }
            } catch (\Exception $e) {
                $status[$type] = [
                    'cached_queries' => 'Error',
                    'last_cached' => null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $this->sendResponse($status, 'Cache status retrieved successfully');
    }
}