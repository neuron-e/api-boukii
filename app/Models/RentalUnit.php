<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalUnit extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'rental_units';

    public $fillable = [
        'school_id',
        'variant_id',
        'warehouse_id',
        'serial',
        'status',
        'condition',
        'notes',
        'blocked_until',
    ];

    protected $casts = [
        'blocked_until' => 'datetime',
    ];

    // Canonical status values
    const STATUS_AVAILABLE   = 'available';
    const STATUS_RESERVED    = 'reserved';
    const STATUS_RENTED      = 'rented';
    const STATUS_MAINTENANCE = 'maintenance';

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalVariant::class, 'variant_id');
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalWarehouse::class, 'warehouse_id');
    }

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalReservationUnitAssignment::class, 'rental_unit_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }
}
