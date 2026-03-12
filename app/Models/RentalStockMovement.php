<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalStockMovement extends Model
{
    public $table = 'rental_stock_movements';

    const UPDATED_AT = null;

    protected $fillable = [
        'school_id',
        'rental_reservation_id',
        'rental_reservation_line_id',
        'rental_unit_id',
        'variant_id',
        'item_id',
        'warehouse_id_from',
        'warehouse_id_to',
        'movement_type',
        'quantity',
        'reason',
        'payload',
        'user_id',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}

