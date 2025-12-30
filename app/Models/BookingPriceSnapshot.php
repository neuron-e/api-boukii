<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingPriceSnapshot extends Model
{
    use HasFactory;

    public $table = 'booking_price_snapshots';

    public $fillable = [
        'booking_id',
        'version',
        'source',
        'snapshot',
        'created_by'
    ];

    protected $casts = [
        'version' => 'integer',
        'snapshot' => 'array',
        'created_by' => 'integer'
    ];

    public function booking(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Booking::class, 'booking_id');
    }
}
