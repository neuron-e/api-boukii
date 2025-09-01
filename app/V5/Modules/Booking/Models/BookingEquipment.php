<?php

namespace App\V5\Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * V5 Booking Equipment Model
 *
 * @OA\Schema(
 *   schema="V5BookingEquipment",
 *   required={"booking_id","equipment_type","name","daily_rate","rental_days","currency","condition_out"},
 *   @OA\Property(property="id", type="integer", format="int64"),
 *   @OA\Property(property="booking_id", type="integer", format="int64"),
 *   @OA\Property(property="equipment_type", type="string", enum={"skis","boots","poles","helmet","goggles","snowboard","bindings","clothing","protection","other"}),
 *   @OA\Property(property="name", type="string"),
 *   @OA\Property(property="brand", type="string", nullable=true),
 *   @OA\Property(property="model", type="string", nullable=true),
 *   @OA\Property(property="size", type="string", nullable=true),
 *   @OA\Property(property="participant_name", type="string", nullable=true),
 *   @OA\Property(property="participant_index", type="integer", nullable=true),
 *   @OA\Property(property="daily_rate", type="number", format="float"),
 *   @OA\Property(property="rental_days", type="integer"),
 *   @OA\Property(property="total_price", type="number", format="float"),
 *   @OA\Property(property="currency", type="string"),
 *   @OA\Property(property="deposit", type="number", format="float", nullable=true),
 *   @OA\Property(property="condition_out", type="string"),
 *   @OA\Property(property="condition_in", type="string", nullable=true),
 *   @OA\Property(property="equipment_data", type="object", nullable=true),
 *   @OA\Property(property="serial_number", type="string", nullable=true),
 *   @OA\Property(property="rented_at", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="returned_at", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="notes", type="string", nullable=true),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class BookingEquipment extends Model
{
    protected $table = 'v5_booking_equipment';

    protected $fillable = [
        'booking_id',
        'equipment_type',
        'name',
        'brand',
        'model',
        'size',
        'participant_name',
        'participant_index',
        'daily_rate',
        'rental_days',
        'total_price',
        'currency',
        'deposit',
        'condition_out',
        'condition_in',
        'equipment_data',
        'serial_number',
        'rented_at',
        'returned_at',
        'notes',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'total_price' => 'decimal:2',
        'deposit' => 'decimal:2',
        'rental_days' => 'integer',
        'participant_index' => 'integer',
        'equipment_data' => 'array',
        'rented_at' => 'datetime',
        'returned_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Equipment types
    public const TYPE_SKIS = 'skis';
    public const TYPE_BOOTS = 'boots';
    public const TYPE_POLES = 'poles';
    public const TYPE_HELMET = 'helmet';
    public const TYPE_GOGGLES = 'goggles';
    public const TYPE_SNOWBOARD = 'snowboard';
    public const TYPE_BINDINGS = 'bindings';
    public const TYPE_CLOTHING = 'clothing';
    public const TYPE_PROTECTION = 'protection';
    public const TYPE_OTHER = 'other';

    // Equipment conditions
    public const CONDITION_EXCELLENT = 'excellent';
    public const CONDITION_GOOD = 'good';
    public const CONDITION_FAIR = 'fair';
    public const CONDITION_POOR = 'poor';
    public const CONDITION_DAMAGED = 'damaged';

    /**
     * Get all valid equipment types
     */
    public static function getValidTypes(): array
    {
        return [
            self::TYPE_SKIS,
            self::TYPE_BOOTS,
            self::TYPE_POLES,
            self::TYPE_HELMET,
            self::TYPE_GOGGLES,
            self::TYPE_SNOWBOARD,
            self::TYPE_BINDINGS,
            self::TYPE_CLOTHING,
            self::TYPE_PROTECTION,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Get all valid equipment conditions
     */
    public static function getValidConditions(): array
    {
        return [
            self::CONDITION_EXCELLENT,
            self::CONDITION_GOOD,
            self::CONDITION_FAIR,
            self::CONDITION_POOR,
            self::CONDITION_DAMAGED,
        ];
    }

    /**
     * Boot method to auto-calculate total price
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($equipment) {
            $equipment->total_price = $equipment->daily_rate * $equipment->rental_days;
        });
    }

    /**
     * Relationship: Parent booking
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scope: Filter by equipment type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('equipment_type', $type);
    }

    /**
     * Scope: Filter by participant
     */
    public function scopeForParticipant($query, string $participantName)
    {
        return $query->where('participant_name', $participantName);
    }

    /**
     * Scope: Rented equipment (taken out)
     */
    public function scopeRented($query)
    {
        return $query->whereNotNull('rented_at');
    }

    /**
     * Scope: Returned equipment
     */
    public function scopeReturned($query)
    {
        return $query->whereNotNull('returned_at');
    }

    /**
     * Scope: Outstanding equipment (rented but not returned)
     */
    public function scopeOutstanding($query)
    {
        return $query->whereNotNull('rented_at')
                    ->whereNull('returned_at');
    }

    /**
     * Check if equipment is rented out
     */
    public function isRented(): bool
    {
        return !is_null($this->rented_at) && is_null($this->returned_at);
    }

    /**
     * Check if equipment is returned
     */
    public function isReturned(): bool
    {
        return !is_null($this->returned_at);
    }

    /**
     * Check if equipment is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->isRented() || !$this->booking) {
            return false;
        }

        $expectedReturnDate = $this->booking->end_date;
        if (!$expectedReturnDate) {
            return false;
        }

        return now()->isAfter($expectedReturnDate->addDay());
    }

    /**
     * Get equipment full description
     */
    public function getFullDescriptionAttribute(): string
    {
        $parts = array_filter([
            $this->name,
            $this->brand,
            $this->model,
            $this->size ? "Size: {$this->size}" : null,
        ]);

        return implode(' - ', $parts);
    }

    /**
     * Mark equipment as rented
     */
    public function markAsRented(string $condition = self::CONDITION_GOOD): self
    {
        $this->update([
            'rented_at' => now(),
            'condition_out' => $condition,
        ]);

        return $this;
    }

    /**
     * Mark equipment as returned
     */
    public function markAsReturned(string $condition = self::CONDITION_GOOD, ?string $notes = null): self
    {
        $this->update([
            'returned_at' => now(),
            'condition_in' => $condition,
            'notes' => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $this->notes,
        ]);

        return $this;
    }

    /**
     * Calculate damage fee based on condition change
     */
    public function calculateDamageFee(): float
    {
        if (!$this->isReturned()) {
            return 0.0;
        }

        $outCondition = $this->condition_out;
        $inCondition = $this->condition_in;

        // Define damage fee multipliers
        $conditionScores = [
            self::CONDITION_EXCELLENT => 5,
            self::CONDITION_GOOD => 4,
            self::CONDITION_FAIR => 3,
            self::CONDITION_POOR => 2,
            self::CONDITION_DAMAGED => 1,
        ];

        $outScore = $conditionScores[$outCondition] ?? 4;
        $inScore = $conditionScores[$inCondition] ?? 4;

        if ($inScore >= $outScore) {
            return 0.0; // No damage or improvement
        }

        // Calculate damage fee as percentage of total rental price
        $conditionDrops = $outScore - $inScore;
        $damageFeePercentage = [
            1 => 0.10, // 10% for one condition level drop
            2 => 0.25, // 25% for two condition levels drop
            3 => 0.50, // 50% for three condition levels drop
            4 => 1.00, // 100% for four condition levels drop (excellent to damaged)
        ];

        $percentage = $damageFeePercentage[$conditionDrops] ?? 1.00;
        
        return $this->total_price * $percentage;
    }

    /**
     * Format equipment for frontend response
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'type' => $this->equipment_type,
            'details' => [
                'name' => $this->name,
                'brand' => $this->brand,
                'model' => $this->model,
                'size' => $this->size,
                'serial_number' => $this->serial_number,
                'full_description' => $this->getFullDescriptionAttribute(),
            ],
            'participant' => [
                'name' => $this->participant_name,
                'index' => $this->participant_index,
            ],
            'pricing' => [
                'daily_rate' => $this->daily_rate,
                'rental_days' => $this->rental_days,
                'total_price' => $this->total_price,
                'currency' => $this->currency,
                'deposit' => $this->deposit,
                'damage_fee' => $this->calculateDamageFee(),
            ],
            'rental_status' => [
                'is_rented' => $this->isRented(),
                'is_returned' => $this->isReturned(),
                'is_overdue' => $this->isOverdue(),
                'rented_at' => $this->rented_at?->toISOString(),
                'returned_at' => $this->returned_at?->toISOString(),
            ],
            'condition' => [
                'condition_out' => $this->condition_out,
                'condition_in' => $this->condition_in,
            ],
            'equipment_data' => $this->equipment_data,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
