<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseInterval;
use App\Models\CourseIntervalGroup;
use App\Models\CourseIntervalSubgroup;
use App\Models\CourseSubgroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SERVICIO CENTRAL: Gestión de disponibilidad de cursos considerando intervalos
 *
 * Este servicio centraliza TODA la lógica de cálculo de disponibilidad
 * para evitar inconsistencias entre diferentes partes del sistema.
 *
 * Principios:
 * - Backward compatible: funciona sin intervalos configurados
 * - Thread-safe: usa conteos directos desde DB
 * - Cacheable: pero con TTL corto para datos críticos
 * - Auditable: registra decisiones importantes
 */
class CourseAvailabilityService
{
    /**
     * Cache TTL en segundos para datos de disponibilidad
     * Valor bajo para evitar mostrar plazas que ya no existen
     */
    const CACHE_TTL = 30;

    /**
     * MÉTODO PRINCIPAL: Obtener max_participants para un subgrupo en una fecha
     *
     * Orden de prioridad:
     * 1. CourseIntervalSubgroup (más específico)
     * 2. CourseIntervalGroup (grupo del intervalo)
     * 3. CourseSubgroup.max_participants (valor base)
     *
     * @param CourseSubgroup $subgroup
     * @param string $date Fecha en formato Y-m-d
     * @return int|null Número máximo de participantes, null = sin límite
     */
    public function getMaxParticipants(CourseSubgroup $subgroup, string $date): ?int
    {
        // 1. Buscar intervalo activo para la fecha
        $interval = $this->getIntervalForDate($subgroup->course_id, $date);

        if (!$interval) {
            // No hay intervalo configurado, usar valor base del subgrupo
            return $subgroup->max_participants;
        }

        // 2. Verificar si el curso usa configuración independiente por intervalo
        $course = Course::find($subgroup->course_id);
        if ($course && $course->intervals_config_mode !== 'independent') {
            // Modo 'unified' - usar valor base del subgrupo
            return $subgroup->max_participants;
        }

        // 3. Buscar configuración de grupo para este intervalo
        $intervalGroup = CourseIntervalGroup::where('course_interval_id', $interval->id)
            ->where('course_group_id', $subgroup->course_group_id)
            ->where('active', true)
            ->first();

        if (!$intervalGroup) {
            // No hay config de grupo para este intervalo, usar valor base
            return $subgroup->max_participants;
        }

        // 4. Buscar configuración de subgrupo para este intervalo
        $intervalSubgroup = CourseIntervalSubgroup::where('course_interval_group_id', $intervalGroup->id)
            ->where('course_subgroup_id', $subgroup->id)
            ->where('active', true)
            ->first();

        if ($intervalSubgroup && $intervalSubgroup->max_participants !== null) {
            // Usar max_participants específico del subgrupo en este intervalo
            Log::channel('availability')->debug("Using interval subgroup max_participants", [
                'subgroup_id' => $subgroup->id,
                'date' => $date,
                'interval_id' => $interval->id,
                'max_participants' => $intervalSubgroup->max_participants
            ]);
            return $intervalSubgroup->max_participants;
        }

        // 5. Si hay config de grupo pero no de subgrupo, usar del grupo
        if ($intervalGroup->max_participants !== null) {
            Log::channel('availability')->debug("Using interval group max_participants", [
                'subgroup_id' => $subgroup->id,
                'date' => $date,
                'interval_id' => $interval->id,
                'max_participants' => $intervalGroup->max_participants
            ]);
            return $intervalGroup->max_participants;
        }

        // 6. Fallback final al valor base
        return $subgroup->max_participants;
    }

    /**
     * Obtener plazas disponibles reales considerando intervalos
     *
     * @param CourseSubgroup $subgroup
     * @param string $date Fecha en formato Y-m-d
     * @return int Número de plazas disponibles (0 si no hay)
     */
    public function getAvailableSlots(CourseSubgroup $subgroup, string $date): int
    {
        // Cache key único por subgrupo y fecha
        $cacheKey = "available_slots_{$subgroup->id}_{$date}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($subgroup, $date) {
            $maxParticipants = $this->getMaxParticipants($subgroup, $date);

            if ($maxParticipants === null) {
                return 999; // Sin límite = mostrar 999
            }

            if ($maxParticipants <= 0) {
                return 0;
            }

            // Contar reservas activas de forma thread-safe
            $currentBookings = $this->getActiveBookingsCount($subgroup, $date);

            $available = max(0, $maxParticipants - $currentBookings);

            Log::channel('availability')->debug("Calculated available slots", [
                'subgroup_id' => $subgroup->id,
                'date' => $date,
                'max_participants' => $maxParticipants,
                'current_bookings' => $currentBookings,
                'available' => $available
            ]);

            return $available;
        });
    }

    /**
     * Verificar si hay disponibilidad suficiente
     *
     * @param CourseSubgroup $subgroup
     * @param string $date
     * @param int $needed Número de plazas necesarias
     * @return bool True si hay disponibilidad
     */
    public function hasAvailability(CourseSubgroup $subgroup, string $date, int $needed = 1): bool
    {
        $available = $this->getAvailableSlots($subgroup, $date);
        return $available >= $needed;
    }

    /**
     * Obtener disponibilidad para múltiples fechas de forma eficiente
     *
     * @param CourseSubgroup $subgroup
     * @param array $dates Array de fechas en formato Y-m-d
     * @return array [date => available_slots]
     */
    public function getAvailabilityForDates(CourseSubgroup $subgroup, array $dates): array
    {
        $result = [];

        foreach ($dates as $date) {
            $result[$date] = $this->getAvailableSlots($subgroup, $date);
        }

        return $result;
    }

    /**
     * Verificar disponibilidad para un carrito completo (validación pre-reserva)
     *
     * @param array $cartItems Array de items: ['subgroup_id' => int, 'date' => string]
     * @return array ['is_available' => bool, 'details' => array]
     */
    public function validateCartAvailability(array $cartItems): array
    {
        $results = [];
        $allAvailable = true;

        foreach ($cartItems as $item) {
            $subgroup = CourseSubgroup::find($item['subgroup_id']);
            $date = $item['date'];

            if (!$subgroup) {
                $results[] = [
                    'subgroup_id' => $item['subgroup_id'],
                    'date' => $date,
                    'available' => false,
                    'reason' => 'Subgrupo no encontrado'
                ];
                $allAvailable = false;
                continue;
            }

            $available = $this->hasAvailability($subgroup, $date);
            $slots = $this->getAvailableSlots($subgroup, $date);

            $results[] = [
                'subgroup_id' => $subgroup->id,
                'date' => $date,
                'available' => $available,
                'available_slots' => $slots,
                'max_participants' => $this->getMaxParticipants($subgroup, $date),
                'reason' => $available ? 'Disponible' : 'Sin plazas disponibles'
            ];

            if (!$available) {
                $allAvailable = false;
            }
        }

        return [
            'is_available' => $allAvailable,
            'details' => $results,
            'validated_at' => now()->toISOString()
        ];
    }

    /**
     * Invalidar cache de disponibilidad para un subgrupo
     *
     * Útil cuando se crea/cancela una reserva o se cambia configuración
     *
     * @param CourseSubgroup $subgroup
     * @param string|null $date Si es null, invalida todas las fechas
     */
    public function invalidateCache(CourseSubgroup $subgroup, ?string $date = null): void
    {
        if ($date) {
            // Invalidar cache de una fecha específica
            Cache::forget("available_slots_{$subgroup->id}_{$date}");
        } else {
            // Invalidar cache de todas las fechas (usar patrón si es posible)
            // Nota: Esto es aproximado, en producción usar Redis con scan
            for ($i = 0; $i < 365; $i++) {
                $dateStr = now()->addDays($i)->format('Y-m-d');
                Cache::forget("available_slots_{$subgroup->id}_{$dateStr}");
            }
        }

        Log::channel('availability')->info("Cache invalidated for subgroup", [
            'subgroup_id' => $subgroup->id,
            'date' => $date ?? 'all'
        ]);
    }

    /**
     * MÉTODO PRIVADO: Obtener intervalo activo para una fecha
     *
     * @param int $courseId
     * @param string $date
     * @return CourseInterval|null
     */
    private function getIntervalForDate(int $courseId, string $date): ?CourseInterval
    {
        $cacheKey = "course_interval_{$courseId}_{$date}";

        return Cache::remember($cacheKey, 3600, function() use ($courseId, $date) {
            return CourseInterval::where('course_id', $courseId)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->first();
        });
    }

    /**
     * MÉTODO PRIVADO: Contar reservas activas de forma thread-safe
     *
     * @param CourseSubgroup $subgroup
     * @param string $date
     * @return int
     */
    private function getActiveBookingsCount(CourseSubgroup $subgroup, string $date): int
    {
        // Query directo a DB para evitar problemas de cache/concurrencia
        return DB::table('booking_users')
            ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
            ->where('booking_users.course_subgroup_id', $subgroup->id)
            ->where('booking_users.status', 1) // Status activo
            ->where('bookings.status', '!=', 2) // Booking no cancelada
            ->whereNull('booking_users.deleted_at')
            ->whereNull('bookings.deleted_at')
            // Opcional: filtrar solo reservas para esta fecha específica si hay campo date
            // ->where('booking_users.date', $date)
            ->count();
    }

    /**
     * Obtener estadísticas de disponibilidad para un intervalo completo
     *
     * @param CourseInterval $interval
     * @return array
     */
    public function getIntervalStatistics(CourseInterval $interval): array
    {
        $dates = $this->getDateRange($interval->start_date, $interval->end_date);
        $subgroups = CourseSubgroup::where('course_id', $interval->course_id)->get();

        $totalSlots = 0;
        $occupiedSlots = 0;

        foreach ($subgroups as $subgroup) {
            foreach ($dates as $date) {
                $maxParticipants = $this->getMaxParticipants($subgroup, $date);
                $available = $this->getAvailableSlots($subgroup, $date);

                $totalSlots += $maxParticipants ?? 0;
                $occupiedSlots += ($maxParticipants ?? 0) - $available;
            }
        }

        $occupancyRate = $totalSlots > 0 ? ($occupiedSlots / $totalSlots) * 100 : 0;

        return [
            'interval_id' => $interval->id,
            'interval_name' => $interval->name,
            'start_date' => $interval->start_date,
            'end_date' => $interval->end_date,
            'total_slots' => $totalSlots,
            'occupied_slots' => $occupiedSlots,
            'available_slots' => $totalSlots - $occupiedSlots,
            'occupancy_rate' => round($occupancyRate, 2),
        ];
    }

    /**
     * Helper: Generar array de fechas entre dos fechas
     */
    private function getDateRange(string $startDate, string $endDate): array
    {
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($current->lte($end)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }
}
