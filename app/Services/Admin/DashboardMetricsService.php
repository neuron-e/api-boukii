<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardMetricsService
{
    private const CACHE_TTL = 300; // 5 minutos

    /**
     * Obtiene todas las métricas del dashboard de forma optimizada
     * Una sola llamada, queries SQL directas, con cache
     */
    public function getMetrics(int $schoolId, ?string $date = null): array
    {
        $date = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();
        $cacheKey = "dashboard_metrics_{$schoolId}_{$date}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($schoolId, $date) {
            $metrics = [
                'alertas' => $this->getCriticalAlerts($schoolId, $date),
                'revenue' => $this->getRevenueMetrics($schoolId, $date),
                'ocupacion' => $this->getOccupancyMetrics($schoolId, $date),
                'proximasActividades' => $this->getUpcomingActivities($schoolId, $date),
                'tendencias' => $this->getTrendData($schoolId, $date),
                'quickStats' => $this->getQuickStats($schoolId, $date),
                'lastUpdated' => now()->toISOString()
            ];

            return $metrics;
        });
    }

    /**
     * Alertas críticas - queries optimizadas con aggregates
     */
    private function getCriticalAlerts(int $schoolId, string $date): array
    {
        // Una sola query con subqueries para todas las alertas
        $alerts = DB::selectOne("
            SELECT
                -- Cursos privados sin monitor (JOIN con courses para obtener course_type)
                (SELECT COUNT(*)
                 FROM booking_users bu
                 INNER JOIN courses c ON c.id = bu.course_id AND c.deleted_at IS NULL
                 WHERE bu.school_id = ?
                   AND bu.date = ?
                   AND bu.monitor_id IS NULL
                   AND c.course_type = 2
                   AND bu.status = 1
                   AND bu.deleted_at IS NULL
                ) as cursos_sin_monitor,

                -- Pagos pendientes
                (SELECT COUNT(*)
                 FROM bookings b
                 WHERE b.school_id = ?
                   AND b.paid = 0
                   AND b.status = 1
                   AND b.deleted_at IS NULL
                ) as pagos_pendientes
        ", [$schoolId, $date, $schoolId]);

        return [
            'reservasHuerfanas' => 0,
            'cursosSinMonitor' => (int) $alerts->cursos_sin_monitor,
            'pagosPendientes' => (int) $alerts->pagos_pendientes,
            'conflictosHorarios' => 0,
            'capacidadCritica' => 0
        ];
    }

    /**
     * Métricas de ingresos - optimizado
     */
    private function getRevenueMetrics(int $schoolId, string $date): array
    {
        $weekStart = Carbon::parse($date)->startOf('week')->toDateString();
        $monthStart = Carbon::parse($date)->startOf('month')->toDateString();

        $revenue = DB::selectOne("
            SELECT
                COALESCE(SUM(CASE WHEN DATE(p.created_at) = ? THEN p.amount ELSE 0 END), 0) as ingresos_hoy,
                COALESCE(SUM(CASE WHEN DATE(p.created_at) BETWEEN ? AND ? THEN p.amount ELSE 0 END), 0) as ingresos_semana,
                COALESCE(SUM(CASE WHEN DATE(p.created_at) BETWEEN ? AND ? THEN p.amount ELSE 0 END), 0) as ingresos_mes
            FROM payments p
            WHERE p.school_id = ?
              AND p.status = 'paid'
              AND p.deleted_at IS NULL
        ", [$date, $weekStart, $date, $monthStart, $date, $schoolId]);

        $hoy = (float) $revenue->ingresos_hoy;
        $semana = (float) $revenue->ingresos_semana;
        $mes = (float) $revenue->ingresos_mes;

        // Calcular tendencia
        $dailyAverage = $semana > 0 ? $semana / 7 : 0;
        $diff = $hoy - $dailyAverage;
        $threshold = $dailyAverage * 0.1;

        $tendencia = 'stable';
        if ($diff > $threshold) {
            $tendencia = 'up';
        } elseif ($diff < -$threshold) {
            $tendencia = 'down';
        }

        return [
            'ingresosHoy' => round($hoy, 2),
            'ingresosSemana' => round($semana, 2),
            'ingresosMes' => round($mes, 2),
            'tendencia' => $tendencia,
            'comparacionPeriodoAnterior' => 0,
            'moneda' => 'CHF'
        ];
    }

    /**
     * Ocupación de cursos - optimizado
     */
    private function getOccupancyMetrics(int $schoolId, string $date): array
    {
        // Ocupación por tipo de curso en una sola query
        $occupancy = DB::select("
            SELECT
                c.course_type,
                COUNT(DISTINCT bu.id) as ocupados,
                COALESCE(SUM(cs.max_participants), 0) - COUNT(DISTINCT bu.id) as disponibles
            FROM courses c
            INNER JOIN course_dates cd ON cd.course_id = c.id AND cd.deleted_at IS NULL
            INNER JOIN course_groups cg ON cg.course_date_id = cd.id AND cg.deleted_at IS NULL
            INNER JOIN course_subgroups cs ON cs.course_group_id = cg.id AND cs.deleted_at IS NULL
            LEFT JOIN booking_users bu ON bu.course_subgroup_id = cs.id
                AND bu.date = cd.date
                AND bu.status = 1
                AND bu.deleted_at IS NULL
            WHERE c.school_id = ?
              AND cd.date = ?
              AND c.active = 1
              AND c.deleted_at IS NULL
            GROUP BY c.course_type
        ", [$schoolId, $date]);

        $privados = ['ocupados' => 0, 'disponibles' => 0, 'porcentaje' => 0];
        $colectivos = ['ocupados' => 0, 'disponibles' => 0, 'porcentaje' => 0];

        foreach ($occupancy as $occ) {
            $data = [
                'ocupados' => (int) $occ->ocupados,
                'disponibles' => (int) $occ->disponibles,
                'porcentaje' => 0
            ];

            $total = $data['ocupados'] + $data['disponibles'];
            if ($total > 0) {
                $data['porcentaje'] = round(($data['ocupados'] / $total) * 100);
            }

            if ($occ->course_type == 2) {
                $privados = $data;
            } else {
                $colectivos = $data;
            }
        }

        $total = [
            'ocupados' => $privados['ocupados'] + $colectivos['ocupados'],
            'disponibles' => $privados['disponibles'] + $colectivos['disponibles'],
            'porcentaje' => 0
        ];

        $totalPlaces = $total['ocupados'] + $total['disponibles'];
        if ($totalPlaces > 0) {
            $total['porcentaje'] = round(($total['ocupados'] / $totalPlaces) * 100);
        }

        return [
            'cursosPrivados' => $privados,
            'cursosColectivos' => $colectivos,
            'total' => $total
        ];
    }

    /**
     * Próximas actividades del día
     */
    private function getUpcomingActivities(int $schoolId, string $date): array
    {
        $activities = DB::select("
            SELECT
                bu.id,
                bu.hour_start as time,
                c.name as course_name,
                c.course_type,
                cl.first_name as client_name,
                m.first_name as monitor_name,
                bu.monitor_id
            FROM booking_users bu
            INNER JOIN bookings b ON b.id = bu.booking_id AND b.deleted_at IS NULL
            INNER JOIN courses c ON c.id = bu.course_id AND c.deleted_at IS NULL
            LEFT JOIN clients cl ON cl.id = bu.client_id AND cl.deleted_at IS NULL
            LEFT JOIN monitors m ON m.id = bu.monitor_id AND m.deleted_at IS NULL
            WHERE bu.school_id = ?
              AND bu.date = ?
              AND bu.status = 1
              AND bu.deleted_at IS NULL
            ORDER BY bu.hour_start ASC
            LIMIT 20
        ", [$schoolId, $date]);

        $proximasHoras = [];
        $monitorPendiente = [];

        foreach ($activities as $activity) {
            $item = [
                'id' => $activity->id,
                'clientName' => $activity->client_name ?? 'Cliente',
                'courseName' => $activity->course_name ?? 'Curso',
                'time' => $activity->time ?? '00:00',
                'type' => $activity->course_type == 2 ? 'private' : 'collective',
                'status' => $activity->monitor_id ? 'confirmed' : 'warning',
                'monitor' => $activity->monitor_name
            ];

            $proximasHoras[] = $item;

            if (!$activity->monitor_id) {
                $monitorPendiente[] = $item;
            }
        }

        return [
            'proximasHoras' => array_slice($proximasHoras, 0, 8),
            'alertasCapacidad' => [],
            'monitorPendiente' => $monitorPendiente
        ];
    }

    /**
     * Tendencias de los últimos 7 días - optimizado
     */
    private function getTrendData(int $schoolId, string $date): array
    {
        $startDate = Carbon::parse($date)->subDays(6)->toDateString();

        $trends = DB::select("
            SELECT
                DATE(bu.date) as fecha,
                COUNT(*) as reservas
            FROM booking_users bu
            WHERE bu.school_id = ?
              AND bu.date BETWEEN ? AND ?
              AND bu.status = 1
              AND bu.deleted_at IS NULL
            GROUP BY DATE(bu.date)
            ORDER BY fecha ASC
        ", [$schoolId, $startDate, $date]);

        // Crear array con todos los 7 días (rellenar con 0 si no hay datos)
        $reservasMap = [];
        foreach ($trends as $trend) {
            $reservasMap[$trend->fecha] = (int) $trend->reservas;
        }

        $reservasUltimos7Dias = [];
        $fechas = [];

        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::parse($date)->subDays($i);
            $dateStr = $d->toDateString();
            $fechas[] = $d->format('d/m');
            $reservasUltimos7Dias[] = $reservasMap[$dateStr] ?? 0;
        }

        return [
            'reservasUltimos30Dias' => $reservasUltimos7Dias, // Nombre legacy del frontend
            'fechas' => $fechas,
            'comparacionPeriodoAnterior' => [
                'reservas' => 0,
                'ingresos' => 0
            ]
        ];
    }

    /**
     * Quick stats
     */
    private function getQuickStats(int $schoolId, string $date): array
    {
        $stats = DB::selectOne("
            SELECT
                COUNT(DISTINCT bu.id) as reservas_hoy,
                COALESCE(SUM(p.amount), 0) as ingresos_hoy
            FROM booking_users bu
            LEFT JOIN bookings b ON b.id = bu.booking_id AND b.deleted_at IS NULL
            LEFT JOIN payments p ON p.booking_id = b.id
                AND DATE(p.created_at) = ?
                AND p.status = 'paid'
                AND p.deleted_at IS NULL
            WHERE bu.school_id = ?
              AND bu.date = ?
              AND bu.status = 1
              AND bu.deleted_at IS NULL
        ", [$date, $schoolId, $date]);

        return [
            'reservasHoy' => (int) $stats->reservas_hoy,
            'ingresosHoy' => round((float) $stats->ingresos_hoy, 2),
            'ocupacionActual' => 0,
            'alertasCriticas' => 0
        ];
    }
}
