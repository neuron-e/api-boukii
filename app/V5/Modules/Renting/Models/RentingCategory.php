<?php

namespace App\V5\Modules\Renting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *   schema="V5RentingCategory",
 *   required={"school_id","name"},
 *   @OA\Property(property="id", type="integer", format="int64"),
 *   @OA\Property(property="school_id", type="integer", format="int64"),
 *   @OA\Property(property="parent_id", type="integer", format="int64", nullable=true),
 *   @OA\Property(property="name", type="string"),
 *   @OA\Property(property="slug", type="string", nullable=true),
 *   @OA\Property(property="description", type="string", nullable=true),
 *   @OA\Property(property="position", type="integer"),
 *   @OA\Property(property="active", type="boolean"),
 *   @OA\Property(property="metadata", type="object", nullable=true),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class RentingCategory extends Model
{
    protected $table = 'v5_renting_categories';

    protected $fillable = [
        'school_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'position',
        'active',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'position' => 'integer',
        'metadata' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }
}
