<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @OA\Schema(
 *      schema="DiscountCode",
 *      required={"code","school_id"},
 *      @OA\Property(
 *          property="code",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="quantity",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="percentage",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="number",
 *          format="number"
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="updated_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="deleted_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      )
 * )
 */

class DiscountCode extends Model
{

    use LogsActivity, SoftDeletes, HasFactory;

    public $table = 'discounts_codes';

    public $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'quantity', // DEPRECATED
        'percentage', // DEPRECATED
        'school_id',
        'total',
        'remaining',
        'max_uses_per_user',
        'valid_from',
        'valid_to',
        'sport_ids',
        'course_ids',
        'client_ids',
        'degree_ids',
        'min_purchase_amount',
        'max_discount_amount',
        'applicable_to',
        'active',
        'created_by',
        'notes'
    ];

    protected $casts = [
        'code' => 'string',
        'name' => 'string',
        'description' => 'string',
        'discount_type' => 'string',
        'discount_value' => 'decimal:2',
        'quantity' => 'float', // DEPRECATED
        'percentage' => 'float', // DEPRECATED
        'total' => 'integer',
        'remaining' => 'integer',
        'max_uses_per_user' => 'integer',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'sport_ids' => 'array',
        'course_ids' => 'array',
        'client_ids' => 'array',
        'degree_ids' => 'array',
        'min_purchase_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'applicable_to' => 'string',
        'active' => 'boolean'
    ];

    public static array $rules = [
        'code' => 'required|string|max:50',
        'name' => 'nullable|string|max:255',
        'description' => 'nullable|string|max:255',
        'discount_type' => 'required|in:percentage,fixed_amount',
        'discount_value' => 'required|numeric|min:0',
        'quantity' => 'nullable|numeric', // DEPRECATED
        'percentage' => 'nullable|numeric', // DEPRECATED
        'school_id' => 'nullable|exists:schools,id',
        'total' => 'nullable|integer|min:1',
        'remaining' => 'nullable|integer|min:0',
        'max_uses_per_user' => 'nullable|integer|min:1',
        'valid_from' => 'nullable|date',
        'valid_to' => 'nullable|date|after_or_equal:valid_from',
        'sport_ids' => 'nullable|array',
        'sport_ids.*' => 'integer|exists:sports,id',
        'course_ids' => 'nullable|array',
        'course_ids.*' => 'integer|exists:courses,id',
        'client_ids' => 'nullable|array',
        'client_ids.*' => 'integer|exists:clients,id',
        'degree_ids' => 'nullable|array',
        'degree_ids.*' => 'integer|exists:degrees,id',
        'min_purchase_amount' => 'nullable|numeric|min:0',
        'max_discount_amount' => 'nullable|numeric|min:0',
        'applicable_to' => 'required|in:all,specific_courses,specific_clients,specific_sports,specific_degrees',
        'active' => 'boolean',
        'created_by' => 'nullable|string|max:255',
        'notes' => 'nullable|string',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
    }

    /**
     * Verifica si el código está activo y disponible para usar
     */
    public function isActive(): bool
    {
        return $this->active && !$this->trashed();
    }

    /**
     * Verifica si el código está dentro del periodo de validez
     */
    public function isValidForDate(?string $date = null): bool
    {
        $checkDate = $date ? new \DateTime($date) : new \DateTime();

        if ($this->valid_from && $checkDate < $this->valid_from) {
            return false;
        }

        if ($this->valid_to && $checkDate > $this->valid_to) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si el código tiene usos disponibles
     */
    public function hasUsesAvailable(): bool
    {
        // Si total es null = ilimitado
        if ($this->total === null) {
            return true;
        }

        // Si remaining es null pero total no, calcular remaining
        if ($this->remaining === null) {
            return $this->total > 0;
        }

        return $this->remaining > 0;
    }

    /**
     * Verifica si el código es aplicable a una escuela específica
     */
    public function isValidForSchool(?int $schoolId): bool
    {
        // Si school_id es null, aplica a todas las escuelas
        if ($this->school_id === null) {
            return true;
        }

        return $this->school_id === $schoolId;
    }

    /**
     * Verifica si el código es aplicable a un deporte específico
     */
    public function isValidForSport(?int $sportId): bool
    {
        // Si sport_ids es null, aplica a todos los deportes
        if ($this->sport_ids === null || empty($this->sport_ids)) {
            return true;
        }

        return in_array($sportId, $this->sport_ids);
    }

    /**
     * Verifica si el código es aplicable a un curso específico
     */
    public function isValidForCourse(?int $courseId): bool
    {
        // Si course_ids es null, aplica a todos los cursos
        if ($this->course_ids === null || empty($this->course_ids)) {
            return true;
        }

        return in_array($courseId, $this->course_ids);
    }

    /**
     * Verifica si el código es aplicable a un nivel/grado específico
     */
    public function isValidForDegree(?int $degreeId): bool
    {
        // Si degree_ids es null, aplica a todos los niveles
        if ($this->degree_ids === null || empty($this->degree_ids)) {
            return true;
        }

        return in_array($degreeId, $this->degree_ids);
    }

    /**
     * Verifica si el monto de compra cumple con el mínimo requerido
     */
    public function meetsMinimumPurchase(float $amount): bool
    {
        if ($this->min_purchase_amount === null) {
            return true;
        }

        return $amount >= $this->min_purchase_amount;
    }

    /**
     * Calcula el monto del descuento según el tipo y valor
     *
     * @param float $purchaseAmount Monto de la compra
     * @return float Monto del descuento calculado
     */
    public function calculateDiscountAmount(float $purchaseAmount): float
    {
        if ($this->discount_type === 'percentage') {
            $discount = $purchaseAmount * ($this->discount_value / 100);
        } else {
            $discount = $this->discount_value;
        }

        // Aplicar descuento máximo si está definido
        if ($this->max_discount_amount !== null) {
            $discount = min($discount, $this->max_discount_amount);
        }

        // No puede ser mayor que el monto de compra
        $discount = min($discount, $purchaseAmount);

        return round($discount, 2);
    }

    /**
     * Decrementar los usos restantes
     */
    public function decrementRemaining(): bool
    {
        if ($this->remaining !== null && $this->remaining > 0) {
            $this->remaining--;
            return $this->save();
        }

        return false;
    }

    /**
     * Incrementar los usos restantes (útil para rollback)
     */
    public function incrementRemaining(): bool
    {
        if ($this->remaining !== null) {
            $this->remaining++;
            return $this->save();
        }

        return false;
    }

    /**
     * Obtiene un resumen del estado del código
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'is_active' => $this->isActive(),
            'is_valid_now' => $this->isValidForDate(),
            'has_uses_available' => $this->hasUsesAvailable(),
            'uses_remaining' => $this->remaining,
            'uses_total' => $this->total,
            'valid_from' => $this->valid_from?->format('Y-m-d H:i:s'),
            'valid_to' => $this->valid_to?->format('Y-m-d H:i:s'),
            'restrictions' => [
                'school_id' => $this->school_id,
                'sport_ids' => $this->sport_ids,
                'course_ids' => $this->course_ids,
                'degree_ids' => $this->degree_ids,
                'min_purchase_amount' => $this->min_purchase_amount,
                'max_discount_amount' => $this->max_discount_amount,
            ]
        ];
    }
}

