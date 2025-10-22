<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * CourseIntervalMonitor
 *
 * Sistema de asignación de monitores por intervalo.
 * Permite asignar diferentes monitores a un subgrupo según el intervalo de fechas.
 *
 * Prioridad de asignación:
 * 1. CourseIntervalMonitor (esta tabla) - Monitor específico para el intervalo
 * 2. CourseSubgroup.monitor_id - Monitor base del subgrupo
 *
 * @property int $id
 * @property int $course_id
 * @property int $course_interval_id
 * @property int $course_subgroup_id
 * @property int $monitor_id
 * @property bool $active
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Course $course
 * @property-read CourseInterval $courseInterval
 * @property-read CourseSubgroup $courseSubgroup
 * @property-read Monitor $monitor
 */
class CourseIntervalMonitor extends Model
{
    use HasFactory;

    protected $table = 'course_interval_monitors';

    protected $fillable = [
        'course_id',
        'course_interval_id',
        'course_subgroup_id',
        'monitor_id',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // ==================================================================================
    // RELACIONES
    // ==================================================================================

    /**
     * Curso al que pertenece esta asignación
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Intervalo de fechas para esta asignación
     */
    public function courseInterval(): BelongsTo
    {
        return $this->belongsTo(CourseInterval::class);
    }

    /**
     * Subgrupo al que se asigna el monitor
     */
    public function courseSubgroup(): BelongsTo
    {
        return $this->belongsTo(CourseSubgroup::class);
    }

    /**
     * Monitor asignado
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    // ==================================================================================
    // SCOPES
    // ==================================================================================

    /**
     * Scope para filtrar asignaciones activas
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para filtrar por intervalo
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $intervalId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForInterval($query, int $intervalId)
    {
        return $query->where('course_interval_id', $intervalId);
    }

    /**
     * Scope para filtrar por subgrupo
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $subgroupId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSubgroup($query, int $subgroupId)
    {
        return $query->where('course_subgroup_id', $subgroupId);
    }

    /**
     * Scope para filtrar por monitor
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $monitorId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForMonitor($query, int $monitorId)
    {
        return $query->where('monitor_id', $monitorId);
    }

    // ==================================================================================
    // MÉTODOS HELPER
    // ==================================================================================

    /**
     * Verificar si la asignación está activa y vigente para una fecha
     *
     * @param string $date Fecha en formato Y-m-d
     * @return bool
     */
    public function isValidForDate(string $date): bool
    {
        if (!$this->active) {
            return false;
        }

        $interval = $this->courseInterval;
        if (!$interval) {
            return false;
        }

        return $date >= $interval->start_date && $date <= $interval->end_date;
    }

    /**
     * Obtener información resumida de la asignación
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'course_subgroup_id' => $this->course_subgroup_id,
            'monitor_id' => $this->monitor_id,
            'monitor_name' => $this->monitor ? $this->monitor->full_name : null,
            'interval_name' => $this->courseInterval ? $this->courseInterval->name : null,
            'start_date' => $this->courseInterval ? $this->courseInterval->start_date : null,
            'end_date' => $this->courseInterval ? $this->courseInterval->end_date : null,
            'active' => $this->active,
            'notes' => $this->notes,
        ];
    }

    /**
     * Activar la asignación
     *
     * @return bool
     */
    public function activate(): bool
    {
        return $this->update(['active' => true]);
    }

    /**
     * Desactivar la asignación
     *
     * @return bool
     */
    public function deactivate(): bool
    {
        return $this->update(['active' => false]);
    }
}
