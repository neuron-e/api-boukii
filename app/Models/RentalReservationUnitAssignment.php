<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalReservationUnitAssignment extends Model
{
    use HasFactory;

    public $table = 'rental_reservation_unit_assignments';

    public $fillable = [
        'school_id',
        'rental_reservation_id',
        'rental_reservation_line_id',
        'rental_unit_id',
        'assignment_type',
        'assigned_at',
        'returned_at',
        'condition_out',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function reservation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalReservation::class, 'rental_reservation_id');
    }

    public function line(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalReservationLine::class, 'rental_reservation_line_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class, 'rental_unit_id');
    }

    public function scopeReturned($query)
    {
        return $query->whereNotNull('returned_at');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('returned_at');
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }
}
