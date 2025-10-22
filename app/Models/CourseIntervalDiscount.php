<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CourseIntervalDiscount
 *
 * Descuentos aplicables a un intervalo específico.
 * Permite configurar descuentos por porcentaje o cantidad fija con condiciones.
 *
 * @property int $id
 * @property int $course_id
 * @property int $course_interval_id
 * @property string $name
 * @property string|null $description
 * @property string $discount_type
 * @property float $discount_value
 * @property int|null $min_participants
 * @property int|null $min_days
 * @property string|null $valid_from
 * @property string|null $valid_to
 * @property int $priority
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Course $course
 * @property-read CourseInterval $courseInterval
 */
class CourseIntervalDiscount extends Model
{
    use HasFactory;

    protected $table = 'course_interval_discounts';

    protected $fillable = [
        'course_id',
        'course_interval_id',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'min_participants',
        'min_days',
        'valid_from',
        'valid_to',
        'priority',
        'active',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'course_interval_id' => 'integer',
        'discount_value' => 'decimal:2',
        'min_participants' => 'integer',
        'min_days' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'priority' => 'integer',
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
     * Verificar si el descuento es aplicable según condiciones
     *
     * @param int $participants Número de participantes
     * @param int $days Número de días
     * @param string|null $date Fecha de la reserva
     * @return bool
     */
    public function isApplicable(int $participants, int $days, ?string $date = null): bool
    {
        // Verificar activo
        if (!$this->active) {
            return false;
        }

        // Verificar mínimo de participantes
        if ($this->min_participants && $participants < $this->min_participants) {
            return false;
        }

        // Verificar mínimo de días
        if ($this->min_days && $days < $this->min_days) {
            return false;
        }

        // Verificar validez temporal (adicional al intervalo)
        if ($date) {
            if ($this->valid_from && $date < $this->valid_from->format('Y-m-d')) {
                return false;
            }
            if ($this->valid_to && $date > $this->valid_to->format('Y-m-d')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcular monto del descuento para un precio base
     *
     * @param float $basePrice
     * @return float
     */
    public function calculateDiscount(float $basePrice): float
    {
        if ($this->discount_type === 'percentage') {
            return $basePrice * ($this->discount_value / 100);
        }

        // fixed_amount
        return min($this->discount_value, $basePrice); // No puede descontar más que el precio base
    }

    /**
     * Calcular precio final después del descuento
     *
     * @param float $basePrice
     * @return float
     */
    public function applyDiscount(float $basePrice): float
    {
        return max(0, $basePrice - $this->calculateDiscount($basePrice));
    }

    /**
     * Scope: solo descuentos activos
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
     * Scope: ordenar por prioridad (mayor primero)
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Scope: válidos en una fecha específica
     */
    public function scopeValidOnDate($query, string $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->whereNull('valid_from')
              ->orWhere('valid_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', $date);
        });
    }
}
