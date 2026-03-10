<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalReservationLine extends Model
{
    use HasFactory;

    public $table = 'rental_reservation_lines';

    public $fillable = [
        'school_id',
        'rental_reservation_id',
        'item_id',
        'variant_id',
        'period_type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'quantity',
        'qty_assigned',
        'returned_quantity',
        'status',
        'unit_price',
        'line_total',
        'service_total',
        'discount_total',
        'damage_notes',
        'notes',
        'meta',
    ];

    protected $casts = [
        'unit_price'        => 'decimal:2',
        'line_total'        => 'decimal:2',
        'service_total'     => 'decimal:2',
        'discount_total'    => 'decimal:2',
        'quantity'          => 'integer',
        'qty_assigned'      => 'integer',
        'returned_quantity'  => 'integer',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'meta'              => 'array',
    ];

    public function reservation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalReservation::class, 'rental_reservation_id');
    }

    public function item(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalItem::class, 'item_id');
    }

    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalVariant::class, 'variant_id');
    }

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalReservationUnitAssignment::class, 'rental_reservation_line_id');
    }

    public function pendingReturnQuantity(): int
    {
        return max(0, $this->qty_assigned - $this->returned_quantity);
    }

    public function isFullyReturned(): bool
    {
        return $this->qty_assigned > 0 && $this->returned_quantity >= $this->qty_assigned;
    }

    public function scopeForReservation($query, int $reservationId)
    {
        return $query->where('rental_reservation_id', $reservationId);
    }
}
