<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalItem extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'rental_items';

    public $fillable = [
        'school_id',
        'category_id',
        'name',
        'brand',
        'model',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalCategory::class, 'category_id');
    }

    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalVariant::class, 'item_id');
    }

    public function pricingRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalPricingRule::class, 'item_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}
