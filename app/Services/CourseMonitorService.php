<?php

namespace App\Services;

use App\Models\CourseSubgroup;
use App\Models\CourseIntervalMonitor;
use App\Models\CourseInterval;
use App\Models\Monitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CourseMonitorService
 *
 * Servicio para gestionar la asignación de monitores a subgrupos por intervalo.
 *
 * Prioridad de asignación:
 * 1. CourseIntervalMonitor - Monitor específico para el intervalo
 * 2. CourseSubgroup.monitor_id - Monitor base del subgrupo
 * 3. null - Sin monitor asignado
 */
class CourseMonitorService
{
    const CACHE_TTL = 3600; // 1 hora (en segundos)

    /**
     * Obtener el monitor asignado a un subgrupo para una fecha específica
     *
     * @param CourseSubgroup $subgroup
     * @param string $date Fecha en formato Y-m-d
     * @return Monitor|null
     */
    public function getMonitorForDate(CourseSubgroup $subgroup, string $date): ?Monitor
    {
        $cacheKey = "monitor_for_subgroup_{$subgroup->id}_{$date}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($subgroup, $date) {
            // 1. Buscar asignación por intervalo
            $intervalMonitor = $this->getIntervalMonitorForDate($subgroup, $date);
            if ($intervalMonitor) {
                return $intervalMonitor->monitor;
            }

            // 2. Usar monitor base del subgrupo
            return $subgroup->monitor;
        });
    }

    /**
     * Obtener ID del monitor para una fecha (más eficiente)
     *
     * @param CourseSubgroup $subgroup
     * @param string $date
     * @return int|null
     */
    public function getMonitorIdForDate(CourseSubgroup $subgroup, string $date): ?int
    {
        $monitor = $this->getMonitorForDate($subgroup, $date);
        return $monitor ? $monitor->id : null;
    }

    /**
     * Obtener la asignación del intervalo para una fecha específica
     *
     * @param CourseSubgroup $subgroup
     * @param string $date
     * @return CourseIntervalMonitor|null
     */
    protected function getIntervalMonitorForDate(CourseSubgroup $subgroup, string $date): ?CourseIntervalMonitor
    {
        // Buscar intervalo activo para esta fecha
        $interval = CourseInterval::where('course_id', $subgroup->course_id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->orderBy('display_order', 'desc') // Priorizar intervalos con mayor orden
            ->first();

        if (!$interval) {
            return null;
        }

        // Buscar asignación de monitor para este intervalo y subgrupo
        return CourseIntervalMonitor::where('course_interval_id', $interval->id)
            ->where('course_subgroup_id', $subgroup->id)
            ->where('active', true)
            ->first();
    }

    /**
     * Asignar monitor a un subgrupo para un intervalo específico
     *
     * @param int $intervalId
     * @param int $subgroupId
     * @param int $monitorId
     * @param string|null $notes
     * @return CourseIntervalMonitor
     * @throws \Exception
     */
    public function assignMonitorToInterval(
        int $intervalId,
        int $subgroupId,
        int $monitorId,
        ?string $notes = null
    ): CourseIntervalMonitor {
        $interval = CourseInterval::findOrFail($intervalId);
        $subgroup = CourseSubgroup::findOrFail($subgroupId);
        $monitor = Monitor::findOrFail($monitorId);

        // Validar que el subgrupo pertenezca al mismo curso que el intervalo
        if ($subgroup->course_id !== $interval->course_id) {
            throw new \Exception('El subgrupo no pertenece al mismo curso que el intervalo');
        }

        // Validar disponibilidad del monitor en las fechas del intervalo
        $this->validateMonitorAvailability($monitor, $subgroup, $interval);

        // Crear o actualizar asignación
        $assignment = CourseIntervalMonitor::updateOrCreate(
            [
                'course_interval_id' => $intervalId,
                'course_subgroup_id' => $subgroupId,
            ],
            [
                'course_id' => $subgroup->course_id,
                'monitor_id' => $monitorId,
                'active' => true,
                'notes' => $notes,
            ]
        );

        // Invalidar cache
        $this->invalidateCache($subgroup);

        return $assignment;
    }

    /**
     * Validar que el monitor esté disponible para todas las fechas del intervalo
     *
     * @param Monitor $monitor
     * @param CourseSubgroup $subgroup
     * @param CourseInterval $interval
     * @throws \Exception
     */
    protected function validateMonitorAvailability(
        Monitor $monitor,
        CourseSubgroup $subgroup,
        CourseInterval $interval
    ): void {
        $courseDate = $subgroup->courseDate;

        if (!$courseDate) {
            throw new \Exception('El subgrupo no tiene fecha de curso asignada');
        }

        // Verificar solo si la fecha del curso está dentro del intervalo
        $date = $courseDate->date;
        if ($date < $interval->start_date || $date > $interval->end_date) {
            return; // Fecha fuera del intervalo, no validar
        }

        // Verificar disponibilidad
        $isBusy = Monitor::isMonitorBusy(
            $monitor->id,
            $date,
            $courseDate->hour_start,
            $courseDate->hour_end
        );

        if ($isBusy) {
            throw new \Exception(
                "El monitor {$monitor->full_name} no está disponible el día {$date} de {$courseDate->hour_start} a {$courseDate->hour_end}"
            );
        }
    }

    /**
     * Remover asignación de monitor de un intervalo
     *
     * @param int $intervalId
     * @param int $subgroupId
     * @return bool
     */
    public function removeMonitorFromInterval(int $intervalId, int $subgroupId): bool
    {
        $assignment = CourseIntervalMonitor::where('course_interval_id', $intervalId)
            ->where('course_subgroup_id', $subgroupId)
            ->first();

        if ($assignment) {
            $this->invalidateCache($assignment->courseSubgroup);
            return $assignment->delete();
        }

        return false;
    }

    /**
     * Desactivar asignación (soft disable)
     *
     * @param int $intervalId
     * @param int $subgroupId
     * @return bool
     */
    public function deactivateMonitorAssignment(int $intervalId, int $subgroupId): bool
    {
        $assignment = CourseIntervalMonitor::where('course_interval_id', $intervalId)
            ->where('course_subgroup_id', $subgroupId)
            ->first();

        if ($assignment) {
            $assignment->update(['active' => false]);
            $this->invalidateCache($assignment->courseSubgroup);
            return true;
        }

        return false;
    }

    /**
     * Listar todas las asignaciones de un intervalo
     *
     * @param int $intervalId
     * @return \Illuminate\Support\Collection
     */
    public function getMonitorAssignmentsForInterval(int $intervalId)
    {
        return CourseIntervalMonitor::where('course_interval_id', $intervalId)
            ->with(['monitor', 'courseSubgroup.courseDate', 'courseSubgroup.degree'])
            ->get();
    }

    /**
     * Listar todas las asignaciones de un subgrupo
     *
     * @param int $subgroupId
     * @return \Illuminate\Support\Collection
     */
    public function getMonitorAssignmentsForSubgroup(int $subgroupId)
    {
        return CourseIntervalMonitor::where('course_subgroup_id', $subgroupId)
            ->with(['monitor', 'courseInterval'])
            ->orderBy('course_interval_id', 'desc')
            ->get();
    }

    /**
     * Invalidar cache de asignaciones de un subgrupo
     *
     * @param CourseSubgroup $subgroup
     * @param string|null $date Fecha específica o null para todas
     */
    public function invalidateCache(CourseSubgroup $subgroup, ?string $date = null): void
    {
        if ($date) {
            Cache::forget("monitor_for_subgroup_{$subgroup->id}_{$date}");
        } else {
            // Invalidar cache para todas las fechas
            // TODO: Mejorar con tags de cache si se usa Redis/Memcached
            Cache::flush();
        }
    }

    /**
     * Obtener información completa del monitor para una fecha
     *
     * Retorna tanto el monitor como metadatos sobre de dónde viene la asignación
     *
     * @param CourseSubgroup $subgroup
     * @param string $date
     * @return array [
     *      'monitor' => Monitor|null,
     *      'source' => 'interval'|'base'|null,
     *      'interval_id' => int|null,
     *      'interval_name' => string|null,
     *      'assignment_id' => int|null,
     *      'notes' => string|null
     * ]
     */
    public function getMonitorDetailsForDate(CourseSubgroup $subgroup, string $date): array
    {
        $intervalMonitor = $this->getIntervalMonitorForDate($subgroup, $date);

        if ($intervalMonitor) {
            return [
                'monitor' => $intervalMonitor->monitor,
                'source' => 'interval',
                'interval_id' => $intervalMonitor->course_interval_id,
                'interval_name' => $intervalMonitor->courseInterval->name,
                'assignment_id' => $intervalMonitor->id,
                'notes' => $intervalMonitor->notes,
            ];
        }

        if ($subgroup->monitor) {
            return [
                'monitor' => $subgroup->monitor,
                'source' => 'base',
                'interval_id' => null,
                'interval_name' => null,
                'assignment_id' => null,
                'notes' => null,
            ];
        }

        return [
            'monitor' => null,
            'source' => null,
            'interval_id' => null,
            'interval_name' => null,
            'assignment_id' => null,
            'notes' => null,
        ];
    }

    /**
     * Obtener todas las asignaciones de monitores para un curso en un rango de fechas
     *
     * Útil para visualizar un calendario completo de asignaciones
     *
     * @param int $courseId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getMonitorSchedule(int $courseId, string $startDate, string $endDate): array
    {
        // Obtener todos los subgrupos del curso
        $subgroups = CourseSubgroup::where('course_id', $courseId)
            ->with(['courseDate', 'degree', 'monitor'])
            ->get();

        $schedule = [];

        foreach ($subgroups as $subgroup) {
            if (!$subgroup->courseDate) {
                continue;
            }

            $date = $subgroup->courseDate->date;

            if ($date < $startDate || $date > $endDate) {
                continue;
            }

            $details = $this->getMonitorDetailsForDate($subgroup, $date);

            $schedule[] = [
                'subgroup_id' => $subgroup->id,
                'date' => $date,
                'hour_start' => $subgroup->courseDate->hour_start,
                'hour_end' => $subgroup->courseDate->hour_end,
                'degree_name' => $subgroup->degree ? $subgroup->degree->name : null,
                ...$details
            ];
        }

        return $schedule;
    }
}
