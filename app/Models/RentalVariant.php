<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalVariant extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'rental_variants';

    public $fillable = [
        'school_id',
        'item_id',
        'subcategory_id',
        'name',
        'size_group',
        'size_label',
        'sku',
        'barcode',
        'active',
    ];

    protected $casts = [
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

    public function subcategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalSubcategory::class, 'subcategory_id');
    }

    public function units(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalUnit::class, 'variant_id');
    }

    public function pricingRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalPricingRule::class, 'variant_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function availableUnitsCount(): int
    {
        return $this->units()->where('status', 'available')->whereNull('deleted_at')->count();
    }
}
