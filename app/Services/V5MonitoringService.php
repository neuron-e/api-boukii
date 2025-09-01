<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\PerformanceMetric;
use App\Models\V5MigrationLog;
use Carbon\Carbon;

class V5MonitoringService
{
    private const CACHE_PREFIX = 'v5_monitoring';
    private const ALERT_THRESHOLD_MS = 5000; // 5 segundos
    private const ERROR_THRESHOLD_COUNT = 10; // 10 errores en ventana de tiempo
    private const WINDOW_MINUTES = 15; // Ventana de análisis

    /**
     * Registra métrica de performance
     */
    public function recordPerformance(
        ?int $schoolId,
        string $version,
        string $module,
        string $action,
        int $responseTimeMs,
        array $metadata = []
    ): void {
        try {
            PerformanceMetric::create([
                'school_id' => $schoolId,
                'version' => $version,
                'module' => $module,
                'action' => $action,
                'response_time_ms' => $responseTimeMs,
                'metadata' => $metadata,
                'measured_at' => now()
            ]);

            // Verificar si necesita alerta
            if ($responseTimeMs > self::ALERT_THRESHOLD_MS) {
                $this->triggerPerformanceAlert($schoolId, $version, $module, $action, $responseTimeMs);
            }

            // Actualizar estadísticas en cache
            $this->updateCachedStats($version, $module, $responseTimeMs);

        } catch (\Exception $e) {
            Log::error('Error recording performance metric', [
                'error' => $e->getMessage(),
                'school_id' => $schoolId,
                'version' => $version,
                'module' => $module,
                'action' => $action
            ]);
        }
    }

    /**
     * Registra error de migración
     */
    public function recordMigrationError(
        int $schoolId,
        string $migrationType,
        string $errorMessage,
        array $context = []
    ): void {
        Log::error('V5 Migration Error', [
            'school_id' => $schoolId,
            'migration_type' => $migrationType,
            'error' => $errorMessage,
            'context' => $context,
            'timestamp' => now()
        ]);

        // Verificar si necesita alerta crítica
        $recentErrors = $this->getRecentErrorCount($schoolId, $migrationType);
        if ($recentErrors >= self::ERROR_THRESHOLD_COUNT) {
            $this->triggerCriticalAlert($schoolId, $migrationType, $recentErrors);
        }
    }

    /**
     * Compara performance V4 vs V5
     */
    public function getPerformanceComparison(
        int $schoolId,
        string $module,
        int $hours = 24
    ): array {
        $since = now()->subHours($hours);

        $v4Stats = $this->getVersionStats($schoolId, 'v4', $module, $since);
        $v5Stats = $this->getVersionStats($schoolId, 'v5', $module, $since);

        $comparison = [
            'v4' => $v4Stats,
            'v5' => $v5Stats,
            'improvement' => [
                'response_time' => $this->calculateImprovement(
                    $v4Stats['avg_response_time'],
                    $v5Stats['avg_response_time']
                ),
                'error_rate' => $this->calculateImprovement(
                    $v4Stats['error_rate'],
                    $v5Stats['error_rate']
                )
            ],
            'period' => [
                'hours' => $hours,
                'from' => $since->toISOString(),
                'to' => now()->toISOString()
            ]
        ];

        return $comparison;
    }

    /**
     * Obtiene estadísticas de una versión específica
     */
    private function getVersionStats(
        int $schoolId,
        string $version,
        string $module,
        Carbon $since
    ): array {
        $metrics = PerformanceMetric::where('school_id', $schoolId)
            ->where('version', $version)
            ->where('module', $module)
            ->where('measured_at', '>=', $since)
            ->get();

        if ($metrics->isEmpty()) {
            return [
                'request_count' => 0,
                'avg_response_time' => 0,
                'min_response_time' => 0,
                'max_response_time' => 0,
                'p95_response_time' => 0,
                'error_rate' => 0,
                'errors_count' => 0
            ];
        }

        $responseTimes = $metrics->pluck('response_time_ms')->sort()->values();
        $errorCount = $metrics->where('response_time_ms', '>', self::ALERT_THRESHOLD_MS)->count();

        return [
            'request_count' => $metrics->count(),
            'avg_response_time' => $responseTimes->average(),
            'min_response_time' => $responseTimes->min(),
            'max_response_time' => $responseTimes->max(),
            'p95_response_time' => $this->calculatePercentile($responseTimes, 95),
            'error_rate' => $metrics->count() > 0 ? ($errorCount / $metrics->count()) * 100 : 0,
            'errors_count' => $errorCount
        ];
    }

    /**
     * Calcula percentil
     */
    private function calculatePercentile($values, int $percentile): float
    {
        if ($values->isEmpty()) return 0;

        $index = ($percentile / 100) * ($values->count() - 1);
        $lower = floor($index);
        $upper = ceil($index);

        if ($lower == $upper) {
            return $values[$lower];
        }

        return $values[$lower] * ($upper - $index) + $values[$upper] * ($index - $lower);
    }

    /**
     * Calcula mejora porcentual
     */
    private function calculateImprovement(float $oldValue, float $newValue): array
    {
        if ($oldValue == 0) {
            return ['percentage' => 0, 'direction' => 'neutral'];
        }

        $percentage = (($oldValue - $newValue) / $oldValue) * 100;
        $direction = $percentage > 0 ? 'improved' : ($percentage < 0 ? 'degraded' : 'neutral');

        return [
            'percentage' => abs($percentage),
            'direction' => $direction
        ];
    }

    /**
     * Actualiza estadísticas en cache
     */
    private function updateCachedStats(string $version, string $module, int $responseTime): void
    {
        $cacheKey = self::CACHE_PREFIX . ":stats:{$version}:{$module}";
        
        $stats = Cache::get($cacheKey, [
            'count' => 0,
            'total_time' => 0,
            'max_time' => 0,
            'min_time' => PHP_INT_MAX,
            'last_update' => now()
        ]);

        $stats['count']++;
        $stats['total_time'] += $responseTime;
        $stats['max_time'] = max($stats['max_time'], $responseTime);
        $stats['min_time'] = min($stats['min_time'], $responseTime);
        $stats['avg_time'] = $stats['total_time'] / $stats['count'];
        $stats['last_update'] = now();

        Cache::put($cacheKey, $stats, now()->addHours(2));
    }

    /**
     * Obtiene conteo de errores recientes
     */
    private function getRecentErrorCount(int $schoolId, string $migrationType): int
    {
        $since = now()->subMinutes(self::WINDOW_MINUTES);
        
        return V5MigrationLog::where('school_id', $schoolId)
            ->where('migration_type', $migrationType)
            ->where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * Dispara alerta de performance
     */
    private function triggerPerformanceAlert(
        ?int $schoolId,
        string $version,
        string $module,
        string $action,
        int $responseTime
    ): void {
        $alertData = [
            'type' => 'performance_degradation',
            'school_id' => $schoolId,
            'version' => $version,
            'module' => $module,
            'action' => $action,
            'response_time_ms' => $responseTime,
            'threshold_ms' => self::ALERT_THRESHOLD_MS,
            'timestamp' => now(),
            'severity' => $responseTime > 10000 ? 'critical' : 'warning'
        ];

        // Log la alerta
        Log::warning('Performance Alert Triggered', $alertData);

        // Enviar notificación (implementar según necesidades)
        $this->sendAlert($alertData);

        // Guardar en cache para dashboard
        $this->cacheAlert($alertData);
    }

    /**
     * Dispara alerta crítica
     */
    private function triggerCriticalAlert(int $schoolId, string $migrationType, int $errorCount): void
    {
        $alertData = [
            'type' => 'critical_migration_errors',
            'school_id' => $schoolId,
            'migration_type' => $migrationType,
            'error_count' => $errorCount,
            'window_minutes' => self::WINDOW_MINUTES,
            'threshold' => self::ERROR_THRESHOLD_COUNT,
            'timestamp' => now(),
            'severity' => 'critical'
        ];

        Log::critical('Critical Migration Alert', $alertData);

        // Enviar notificación urgente
        $this->sendAlert($alertData);

        // Deshabilitar migración automática para esta escuela
        $this->disableAutoMigration($schoolId);
    }

    /**
     * Envía alerta por diferentes canales
     */
    private function sendAlert(array $alertData): void
    {
        try {
            // Slack/Discord webhook
            if ($webhookUrl = config('monitoring.alert_webhook_url')) {
                \Http::timeout(10)->post($webhookUrl, [
                    'text' => $this->formatAlertMessage($alertData),
                    'channel' => '#boukii-alerts',
                    'username' => 'Boukii V5 Monitor'
                ]);
            }

            // Email para administradores
            if ($alertData['severity'] === 'critical') {
                $admins = config('monitoring.alert_emails', []);
                foreach ($admins as $email) {
                    \Mail::raw(
                        $this->formatAlertMessage($alertData),
                        function ($message) use ($email, $alertData) {
                            $message->to($email)
                                ->subject('[CRITICAL] Boukii V5 Alert: ' . $alertData['type']);
                        }
                    );
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to send alert', [
                'error' => $e->getMessage(),
                'alert_data' => $alertData
            ]);
        }
    }

    /**
     * Formatea mensaje de alerta
     */
    private function formatAlertMessage(array $alertData): string
    {
        $emoji = match($alertData['severity']) {
            'critical' => '🚨',
            'warning' => '⚠️',
            default => 'ℹ️'
        };

        $message = "{$emoji} **Boukii V5 Alert**\n\n";
        $message .= "**Type:** {$alertData['type']}\n";
        $message .= "**Severity:** {$alertData['severity']}\n";
        $message .= "**Timestamp:** {$alertData['timestamp']}\n";

        if (isset($alertData['school_id'])) {
            $message .= "**School ID:** {$alertData['school_id']}\n";
        }

        if (isset($alertData['response_time_ms'])) {
            $message .= "**Response Time:** {$alertData['response_time_ms']}ms (threshold: {$alertData['threshold_ms']}ms)\n";
        }

        if (isset($alertData['error_count'])) {
            $message .= "**Error Count:** {$alertData['error_count']} in {$alertData['window_minutes']} minutes\n";
        }

        return $message;
    }

    /**
     * Guarda alerta en cache para dashboard
     */
    private function cacheAlert(array $alertData): void
    {
        $cacheKey = self::CACHE_PREFIX . ':alerts';
        $alerts = Cache::get($cacheKey, []);
        
        array_unshift($alerts, $alertData);
        
        // Mantener solo últimas 50 alertas
        $alerts = array_slice($alerts, 0, 50);
        
        Cache::put($cacheKey, $alerts, now()->addHours(24));
    }

    /**
     * Deshabilita migración automática para una escuela
     */
    private function disableAutoMigration(int $schoolId): void
    {
        try {
            $featureFlagService = app(FeatureFlagService::class);
            $flags = $featureFlagService->getFlagsForSchool($schoolId);
            
            // Deshabilitar todas las features V5
            foreach ($flags as $key => $value) {
                if (str_starts_with($key, 'useV5')) {
                    $flags[$key] = false;
                }
            }
            
            $featureFlagService->updateFlagsForSchool(
                $schoolId,
                $flags,
                1, // System user
                'Auto-disabled due to critical errors'
            );

            Log::info('Auto-migration disabled for school', ['school_id' => $schoolId]);

        } catch (\Exception $e) {
            Log::error('Failed to disable auto-migration', [
                'school_id' => $schoolId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtiene estadísticas del sistema
     */
    public function getSystemStats(): array
    {
        return [
            'migration_status' => $this->getMigrationStatus(),
            'performance_overview' => $this->getPerformanceOverview(),
            'recent_alerts' => $this->getRecentAlerts(),
            'version_distribution' => $this->getVersionDistribution(),
            'system_health' => $this->getSystemHealth()
        ];
    }

    /**
     * Obtiene estado de migraciones
     */
    private function getMigrationStatus(): array
    {
        $base = $this->activeSchoolsQuery();
        $total = (clone $base)->count();
        $completed = (clone $base)->whereNotNull('feature_flags')->count();

        return [
            'total_schools' => $total,
            'migrated_schools' => $completed,
            'pending_schools' => $total - $completed,
            'migration_percentage' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
        ];
    }

    /**
     * Obtiene overview de performance
     */
    private function getPerformanceOverview(): array
    {
        $since = now()->subHours(24);
        
        $v4Metrics = PerformanceMetric::where('version', 'v4')
            ->where('measured_at', '>=', $since)
            ->get();
            
        $v5Metrics = PerformanceMetric::where('version', 'v5')
            ->where('measured_at', '>=', $since)
            ->get();

        return [
            'v4' => [
                'request_count' => $v4Metrics->count(),
                'avg_response_time' => $v4Metrics->avg('response_time_ms') ?? 0
            ],
            'v5' => [
                'request_count' => $v5Metrics->count(),
                'avg_response_time' => $v5Metrics->avg('response_time_ms') ?? 0
            ]
        ];
    }

    /**
     * Obtiene alertas recientes
     */
    private function getRecentAlerts(): array
    {
        return Cache::get(self::CACHE_PREFIX . ':alerts', []);
    }

    /**
     * Obtiene distribución de versiones
     */
    private function getVersionDistribution(): array
    {
        // Implementación basada en feature flags activos
        $schools = $this->activeSchoolsQuery()->get();
        $distribution = ['legacy' => 0, 'mixed' => 0, 'v5' => 0];

        foreach ($schools as $school) {
            $flags = $school->feature_flags ?? [];
            $v5Count = collect($flags)->filter(fn($flag) => $flag === true)->count();
            
            if ($v5Count === 0) {
                $distribution['legacy']++;
            } elseif ($v5Count < count($flags)) {
                $distribution['mixed']++;
            } else {
                $distribution['v5']++;
            }
        }

        return $distribution;
    }

    /**
     * Obtiene salud del sistema
     */
    private function getSystemHealth(): array
    {
        $health = ['status' => 'healthy', 'checks' => []];
        
        // Check database
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = 'ok';
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = 'error';
        }
        
        // Check Redis
        try {
            if (class_exists(\Redis::class)) {
                \Redis::ping();
            } else {
                \Illuminate\Support\Facades\Redis::connection()->ping();
            }
            $health['checks']['redis'] = 'ok';
        } catch (\Exception $e) {
            $health['checks']['redis'] = 'warning';
        }
        
        // Check recent errors
        $recentErrors = V5MigrationLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subHour())
            ->count();
            
        if ($recentErrors > 5) {
            $health['status'] = 'degraded';
            $health['checks']['migration_errors'] = 'warning';
        } else {
            $health['checks']['migration_errors'] = 'ok';
        }

        return $health;
    }

    /**
     * Query helper para filtrar schools activas en esquemas con 'is_active' o 'active'
     */
    private function activeSchoolsQuery()
    {
        $q = \App\Models\School::query();
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('schools', 'is_active')) {
                $q->where('is_active', 1);
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('schools', 'active')) {
                $q->where('active', 1);
            }
        } catch (\Throwable $e) {
            // Si falla el schema (p. ej., durante instalación), no aplicar filtro
        }
        return $q;
    }
}
