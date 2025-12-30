<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingPriceAudit extends Model
{
    use HasFactory;

    public $table = 'booking_price_audits';

    public $fillable = [
        'booking_id',
        'booking_price_snapshot_id',
        'event_type',
        'note',
        'diff',
        'created_by'
    ];

    protected $casts = [
        'diff' => 'array',
        'created_by' => 'integer'
    ];

    public function booking(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Booking::class, 'booking_id');
    }

    public function snapshot(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\BookingPriceSnapshot::class, 'booking_price_snapshot_id');
    }
}
