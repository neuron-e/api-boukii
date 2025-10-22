<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CourseIntervalGroup
 *
 * Configuración de un grupo específico para un intervalo.
 * Permite override del max_participants del grupo base por intervalo.
 *
 * @property int $id
 * @property int $course_id
 * @property int $course_interval_id
 * @property int $course_group_id
 * @property int|null $max_participants
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Course $course
 * @property-read CourseInterval $courseInterval
 * @property-read CourseGroup $courseGroup
 * @property-read \Illuminate\Database\Eloquent\Collection|CourseIntervalSubgroup[] $intervalSubgroups
 */
class CourseIntervalGroup extends Model
{
    use HasFactory;

    protected $table = 'course_interval_groups';

    protected $fillable = [
        'course_id',
        'course_interval_id',
        'course_group_id',
        'max_participants',
        'active',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'course_interval_id' => 'integer',
        'course_group_id' => 'integer',
        'max_participants' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Relación: pertenece a un curso
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Relación: pertenece a un intervalo
     */
    public function courseInterval(): BelongsTo
    {
        return $this->belongsTo(CourseInterval::class, 'course_interval_id');
    }

    /**
     * Relación: pertenece a un grupo base
     */
    public function courseGroup(): BelongsTo
    {
        return $this->belongsTo(CourseGroup::class, 'course_group_id');
    }

    /**
     * Relación: tiene muchos subgrupos de intervalo
     */
    public function intervalSubgroups(): HasMany
    {
        return $this->hasMany(CourseIntervalSubgroup::class, 'course_interval_group_id');
    }

    /**
     * Obtener max_participants efectivo (de este intervalo o del grupo base)
     */
    public function getEffectiveMaxParticipants(): ?int
    {
        return $this->max_participants ?? $this->courseGroup->max_participants ?? null;
    }

    /**
     * Scope: solo configuraciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: para un intervalo específico
     */
    public function scopeForInterval($query, int $intervalId)
    {
        return $query->where('course_interval_id', $intervalId);
    }

    /**
     * Scope: para un grupo específico
     */
    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('course_group_id', $groupId);
    }
}
