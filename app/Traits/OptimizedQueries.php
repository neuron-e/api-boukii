<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * MEJORA CRÍTICA: Trait para optimización de queries y caching
 *
 * Funcionalidades:
 * - Cache de queries frecuentes con invalidación inteligente
 * - Eager loading optimizado para relaciones
 * - Queries batch para reducir N+1 problems
 * - Indexing hints para MySQL
 * - Métricas de performance
 */
trait OptimizedQueries
{
    /**
     * Cache de query con key automática y TTL configurable
     */
    protected function cacheQuery(string $key, \Closure $query, int $ttl = 300): mixed
    {
        $cacheKey = $this->generateCacheKey($key);

        return Cache::remember($cacheKey, $ttl, function() use ($query) {
            $startTime = microtime(true);
            $result = $query();
            $executionTime = microtime(true) - $startTime;

            // Log performance para queries lentas
            if ($executionTime > 0.1) {
                \Log::warning('SLOW_QUERY_DETECTED', [
                    'cache_key' => $cacheKey,
                    'execution_time' => $executionTime,
                    'model' => get_class($this)
                ]);
            }

            return $result;
        });
    }

    /**
     * MEJORA CRÍTICA: Eager loading optimizado con selects específicos
     */
    protected function optimizedEagerLoad(Builder $query, array $relations): Builder
    {
        $optimizedRelations = [];

        foreach ($relations as $relation => $columns) {
            if (is_string($columns)) {
                // Relación simple sin columnas específicas
                $optimizedRelations[] = $columns;
            } else {
                // Relación con columnas específicas para reducir memory usage
                $optimizedRelations[$relation] = function($q) use ($columns) {
                    return $q->select($columns);
                };
            }
        }

        return $query->with($optimizedRelations);
    }

    /**
     * MEJORA CRÍTICA: Scope para filtros de disponibilidad optimizado
     */
    public function scopeAvailableWithCapacity(Builder $query, array $filters = []): Builder
    {
        // Usar raw queries optimizadas para conteos complejos
        $subquery = DB::table('booking_users as bu')
            ->select('course_subgroup_id', DB::raw('COUNT(*) as participant_count'))
            ->where('bu.status', 1)
            ->whereExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('bookings as b')
                      ->whereColumn('b.id', 'bu.booking_id')
                      ->where('b.status', '!=', 2);
            })
            ->groupBy('course_subgroup_id');

        return $query->leftJoinSub($subquery, 'participant_counts', function($join) {
                $join->on('course_subgroups.id', '=', 'participant_counts.course_subgroup_id');
            })
            ->whereRaw('COALESCE(participant_counts.participant_count, 0) < course_subgroups.max_participants OR course_subgroups.max_participants IS NULL')
            ->select('course_subgroups.*',
                    DB::raw('COALESCE(participant_counts.participant_count, 0) as current_participants'),
                    DB::raw('COALESCE(course_subgroups.max_participants - participant_counts.participant_count, 999) as available_slots')
            );
    }

    /**
     * MEJORA CRÍTICA: Query batch para múltiples IDs
     */
    protected function batchQuery(array $ids, string $column = 'id', array $columns = ['*']): \Illuminate\Support\Collection
    {
        if (empty($ids)) {
            return collect();
        }

        // Optimizar query para grandes listas de IDs
        $chunks = array_chunk($ids, 100); // Procesar en chunks de 100
        $results = collect();

        foreach ($chunks as $chunk) {
            $chunkResults = $this->select($columns)
                ->whereIn($column, $chunk)
                ->get();

            $results = $results->merge($chunkResults);
        }

        return $results;
    }

    /**
     * MEJORA CRÍTICA: Scope para queries con índices optimizados
     */
    public function scopeWithOptimizedIndexes(Builder $query): Builder
    {
        // Forzar uso de índices específicos en MySQL
        if (config('database.default') === 'mysql') {
            $table = $this->getTable();

            // Hints de índices para queries comunes
            switch ($table) {
                case 'course_subgroups':
                    return $query->from(DB::raw("{$table} USE INDEX (idx_course_date_degree)"));

                case 'booking_users':
                    return $query->from(DB::raw("{$table} USE INDEX (idx_booking_subgroup_status)"));

                case 'bookings':
                    return $query->from(DB::raw("{$table} USE INDEX (idx_school_client_status)"));

                default:
                    return $query;
            }
        }

        return $query;
    }

    /**
     * MEJORA CRÍTICA: Invalidación de cache inteligente
     */
    protected function invalidateRelatedCache(array $patterns = []): void
    {
        $defaultPatterns = [
            get_class($this),
            $this->getTable(),
            'course_availability',
            'booking_summary'
        ];

        $allPatterns = array_merge($defaultPatterns, $patterns);

        // Verificar si estamos usando Redis antes de intentar usar métodos específicos de Redis
        $cacheStore = Cache::getStore();

        if (method_exists($cacheStore, 'getRedis')) {
            // Si tenemos Redis disponible, usar el método optimizado
            foreach ($allPatterns as $pattern) {
                try {
                    $keys = $cacheStore->getRedis()->keys("*{$pattern}*");
                    if (!empty($keys)) {
                        $cacheStore->getRedis()->del($keys);
                        \Log::info('CACHE_INVALIDATED_REDIS', [
                            'pattern' => $pattern,
                            'keys_count' => count($keys)
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('REDIS_CACHE_INVALIDATION_FAILED', [
                        'pattern' => $pattern,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } else {
            // Fallback: invalidar tags o limpiar cache completo dependiendo del driver
            foreach ($allPatterns as $pattern) {
                try {
                    // Para drivers como file, array, etc., simplemente limpiar por tags si está disponible
                    if (method_exists(Cache::class, 'tags')) {
                        Cache::tags([$pattern])->flush();
                    } else {
                        // Como último recurso, limpiar cache específicos conocidos
                        Cache::forget($pattern);
                        Cache::forget($pattern . '_*');
                    }

                    \Log::info('CACHE_INVALIDATED_FALLBACK', [
                        'pattern' => $pattern,
                        'driver' => config('cache.default')
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('CACHE_INVALIDATION_FAILED', [
                        'pattern' => $pattern,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * MEJORA CRÍTICA: Query con estadísticas integradas
     */
    protected function queryWithStats(Builder $query, string $operation = 'SELECT'): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Habilitar query log temporalmente para esta query
        DB::enableQueryLog();

        $result = $query->get();

        $queries = DB::getQueryLog();
        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage() - $startMemory;

        DB::disableQueryLog();

        // Log estadísticas para queries complejas
        if ($executionTime > 0.05 || $memoryUsed > 1024 * 1024) { // 50ms o 1MB
            \Log::info('QUERY_PERFORMANCE_STATS', [
                'model' => get_class($this),
                'operation' => $operation,
                'execution_time' => round($executionTime, 4),
                'memory_used' => $this->formatBytes($memoryUsed),
                'query_count' => count($queries),
                'result_count' => $result->count(),
                'queries' => $queries
            ]);
        }

        return $result;
    }

    /**
     * MEJORA CRÍTICA: Preload de relaciones críticas en background
     */
    protected function preloadCriticalRelations(array $ids, array $relations = []): void
    {
        // Ejecutar en background job si está disponible
        if (class_exists(\App\Jobs\PreloadRelationsJob::class)) {
            \App\Jobs\PreloadRelationsJob::dispatch(
                get_class($this),
                $ids,
                $relations
            )->onQueue('low-priority');
        } else {
            // Fallback: preload inmediato
            $this->whereIn('id', $ids)->with($relations)->get();
        }
    }

    /**
     * MEJORA CRÍTICA: Scope para paginación optimizada
     */
    public function scopeOptimizedPaginate(Builder $query, int $page = 1, int $perPage = 20): Builder
    {
        // Para paginación de grandes datasets, usar cursor-based pagination
        if ($page > 100) {
            $offset = ($page - 1) * $perPage;

            // Usar cursor basado en ID para mejor performance
            $lastId = $this->skip($offset)->first()?->id ?? 0;

            return $query->where('id', '>', $lastId)->limit($perPage);
        }

        return $query->skip(($page - 1) * $perPage)->take($perPage);
    }

    // Métodos auxiliares

    protected function generateCacheKey(string $key): string
    {
        $class = str_replace('\\', '_', get_class($this));
        $school = request()->header('X-School-ID', 'default');

        return "query_cache:{$class}:{$school}:{$key}";
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Hook para invalidar cache automáticamente en saves
     */
    protected static function bootOptimizedQueries(): void
    {
        static::saved(function ($model) {
            $model->invalidateRelatedCache();
        });

        static::deleted(function ($model) {
            $model->invalidateRelatedCache();
        });
    }
}