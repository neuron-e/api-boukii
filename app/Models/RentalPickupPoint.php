<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalPickupPoint extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'rental_pickup_points';

    public $fillable = [
        'school_id',
        'name',
        'code',
        'address',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalReservation::class, 'pickup_point_id');
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
