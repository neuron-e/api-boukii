<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CourseDiscount
 *
 * Descuentos aplicables a nivel de curso (independiente del intervalo).
 * Permite configurar descuentos globales por porcentaje o cantidad fija con condiciones.
 *
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property string|null $description
 * @property string $discount_type
 * @property float $discount_value
 * @property int|null $min_days
 * @property string|null $valid_from
 * @property string|null $valid_to
 * @property int $priority
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Course $course
 */
class CourseDiscount extends Model
{
    use HasFactory;

    protected $table = 'course_discounts';

    protected $fillable = [
        'course_id',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'min_days',
        'valid_from',
        'valid_to',
        'priority',
        'active',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'discount_value' => 'decimal:2',
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
     * Verificar si el descuento es aplicable según condiciones
     *
     * @param int $days Número de días
     * @param string|null $date Fecha de la reserva
     * @return bool
     */
    public function isApplicable(int $days, ?string $date = null): bool
    {
        // Verificar activo
        if (!$this->active) {
            return false;
        }

        // Verificar mínimo de días
        if ($this->min_days && $days < $this->min_days) {
            return false;
        }

        // Verificar validez temporal
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
     * Scope: para un curso específico
     */
    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
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
