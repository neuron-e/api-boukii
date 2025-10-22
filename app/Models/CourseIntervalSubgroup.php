<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CourseIntervalSubgroup
 *
 * Configuración de un subgrupo específico para un intervalo.
 * Permite override del max_participants del subgrupo base por intervalo.
 *
 * @property int $id
 * @property int $course_interval_group_id
 * @property int $course_subgroup_id
 * @property int|null $max_participants
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read CourseIntervalGroup $intervalGroup
 * @property-read CourseSubgroup $courseSubgroup
 */
class CourseIntervalSubgroup extends Model
{
    use HasFactory;

    protected $table = 'course_interval_subgroups';

    protected $fillable = [
        'course_interval_group_id',
        'course_subgroup_id',
        'max_participants',
        'active',
    ];

    protected $casts = [
        'course_interval_group_id' => 'integer',
        'course_subgroup_id' => 'integer',
        'max_participants' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Relación: pertenece a un interval group
     */
    public function intervalGroup(): BelongsTo
    {
        return $this->belongsTo(CourseIntervalGroup::class, 'course_interval_group_id');
    }

    /**
     * Relación: pertenece a un subgrupo base
     */
    public function courseSubgroup(): BelongsTo
    {
        return $this->belongsTo(CourseSubgroup::class, 'course_subgroup_id');
    }

    /**
     * Obtener max_participants efectivo (de este intervalo o del subgrupo base)
     */
    public function getEffectiveMaxParticipants(): ?int
    {
        if ($this->max_participants !== null) {
            return $this->max_participants;
        }

        // Fallback al grupo del intervalo
        if ($this->intervalGroup && $this->intervalGroup->max_participants !== null) {
            return $this->intervalGroup->max_participants;
        }

        // Fallback final al subgrupo base
        return $this->courseSubgroup->max_participants ?? null;
    }

    /**
     * Scope: solo configuraciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: para un subgrupo específico
     */
    public function scopeForSubgroup($query, int $subgroupId)
    {
        return $query->where('course_subgroup_id', $subgroupId);
    }

    /**
     * Scope: para un interval group específico
     */
    public function scopeForIntervalGroup($query, int $intervalGroupId)
    {
        return $query->where('course_interval_group_id', $intervalGroupId);
    }
}
