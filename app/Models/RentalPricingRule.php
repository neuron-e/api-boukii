<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalPricingRule extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'rental_pricing_rules';

    public $fillable = [
        'school_id',
        'item_id',
        'variant_id',
        'period_type',
        'pricing_mode',
        'min_days',
        'max_days',
        'priority',
        'price',
        'currency',
        'active',
    ];

    protected $casts = [
        'price'  => 'decimal:2',
        'min_days' => 'integer',
        'max_days' => 'integer',
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function item(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalItem::class, 'item_id');
    }

    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalVariant::class, 'variant_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForPeriod($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }
}
