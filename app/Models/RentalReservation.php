<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalReservation extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'rental_reservations';

    public $fillable = [
        'school_id',
        'booking_id',
        'client_id',
        'pickup_point_id',
        'return_point_id',
        'warehouse_id',
        'reference',
        'status',
        'currency',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'notes',
        'meta',
        'cancelled_at',
        'cancellation_reason',
        'deposit_amount',
        'deposit_status',
        'deposit_payment_id',
        'damage_total',
        'payment_id',
        'payment_method',
    ];

    protected $casts = [
        'start_date'      => 'date',
        'end_date'        => 'date',
        'subtotal'        => 'decimal:2',
        'discount_total'  => 'decimal:2',
        'tax_total'       => 'decimal:2',
        'total'           => 'decimal:2',
        'deposit_amount'  => 'decimal:2',
        'damage_total'    => 'decimal:2',
        'cancelled_at'    => 'datetime',
        'meta'            => 'array',
    ];

    // ── Canonical status values ───────────────────────────────────────────────
    const STATUS_PENDING   = 'pending';
    const STATUS_ACTIVE    = 'active';
    const STATUS_OVERDUE   = 'overdue';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Backend status values that map to the UI 'active' canonical state
    const ACTIVE_STATUSES = ['active', 'assigned', 'checked_out', 'partial_return'];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function booking(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function pickupPoint(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalPickupPoint::class, 'pickup_point_id');
    }

    public function returnPoint(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalPickupPoint::class, 'return_point_id');
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalWarehouse::class, 'warehouse_id');
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalReservationLine::class, 'rental_reservation_id');
    }

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalReservationUnitAssignment::class, 'rental_reservation_id');
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalEvent::class, 'rental_reservation_id');
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function depositPayment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Payment::class, 'deposit_payment_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeActiveToday($query)
    {
        $today = now()->toDateString();
        return $query->whereIn('status', array_merge(self::ACTIVE_STATUSES, [self::STATUS_PENDING]))
                     ->where('start_date', '<=', $today)
                     ->where('end_date', '>=', $today);
    }

    // ── State helpers ─────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE;
    }

    public function allLinesReturned(): bool
    {
        return $this->lines->every(fn($line) => $line->isFullyReturned());
    }

    public function canonicalStatus(): string
    {
        if (in_array($this->status, self::ACTIVE_STATUSES)) {
            return self::STATUS_ACTIVE;
        }
        return $this->status;
    }
}
