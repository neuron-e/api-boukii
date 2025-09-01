<?php

namespace App\V5\Modules\Renting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *   schema="V5RentingItem",
 *   required={"school_id","category_id","name","base_daily_rate","currency","inventory_count"},
 *   @OA\Property(property="id", type="integer", format="int64"),
 *   @OA\Property(property="school_id", type="integer", format="int64"),
 *   @OA\Property(property="category_id", type="integer", format="int64"),
 *   @OA\Property(property="name", type="string"),
 *   @OA\Property(property="sku", type="string", nullable=true),
 *   @OA\Property(property="description", type="string", nullable=true),
 *   @OA\Property(property="base_daily_rate", type="number", format="float"),
 *   @OA\Property(property="deposit", type="number", format="float", nullable=true),
 *   @OA\Property(property="currency", type="string", maxLength=3),
 *   @OA\Property(property="inventory_count", type="integer"),
 *   @OA\Property(property="attributes", type="object", nullable=true),
 *   @OA\Property(property="active", type="boolean"),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class RentingItem extends Model
{
    protected $table = 'v5_renting_items';

    protected $fillable = [
        'school_id',
        'category_id',
        'name',
        'sku',
        'description',
        'base_daily_rate',
        'deposit',
        'currency',
        'inventory_count',
        'attributes',
        'active',
    ];

    protected $casts = [
        'base_daily_rate' => 'decimal:2',
        'deposit' => 'decimal:2',
        'inventory_count' => 'integer',
        'attributes' => 'array',
        'active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(RentingCategory::class, 'category_id');
    }
}
