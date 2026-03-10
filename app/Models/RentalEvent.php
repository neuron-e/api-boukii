<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalEvent extends Model
{
    public $table = 'rental_events';

    // rental_events has no updated_at column
    const UPDATED_AT = null;

    public $fillable = [
        'school_id',
        'rental_reservation_id',
        'event_type',
        'payload',
        'user_id',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function reservation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalReservation::class, 'rental_reservation_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function scopeForReservation($query, int $reservationId)
    {
        return $query->where('rental_reservation_id', $reservationId);
    }

    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Check whether an event of a given type already exists for a reservation.
     * Used for idempotency (e.g. prevent sending the same email twice).
     */
    public static function exists(int $reservationId, string $eventType): bool
    {
        return static::where('rental_reservation_id', $reservationId)
            ->where('event_type', $eventType)
            ->exists();
    }

    /**
     * Log a new event. Safe to call even if rental_events table doesn't exist yet.
     */
    public static function log(int $reservationId, int $schoolId, string $eventType, array $payload = [], ?int $userId = null): void
    {
        try {
            static::create([
                'rental_reservation_id' => $reservationId,
                'school_id'             => $schoolId,
                'event_type'            => $eventType,
                'payload'               => $payload,
                'user_id'               => $userId ?? auth()->id(),
            ]);
        } catch (\Throwable) {
            // Table may not exist yet — fail silently
        }
    }
}
