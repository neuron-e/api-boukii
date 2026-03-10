<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalPolicy extends Model
{
    use HasFactory;

    public $table = 'rental_policies';

    public $fillable = [
        'school_id',
        'enabled',
        'default_deposit_mode',
        'default_deposit_value',
        'auto_assign_on_create',
        'allow_overbooking',
        'grace_minutes',
        'terms',
        'settings',
    ];

    protected $casts = [
        'enabled'               => 'boolean',
        'default_deposit_value' => 'decimal:2',
        'auto_assign_on_create' => 'boolean',
        'allow_overbooking'     => 'boolean',
        'grace_minutes'         => 'integer',
        'settings'              => 'array',
    ];

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Return the policy for a school, or a disabled default if none exists.
     */
    public static function forSchool(int $schoolId): self
    {
        return static::where('school_id', $schoolId)->first()
            ?? new self(['school_id' => $schoolId, 'enabled' => false]);
    }
}
