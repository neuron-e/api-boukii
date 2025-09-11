<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class SchoolModuleSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'module_id',
        'status',
        'subscription_type',
        'activated_at',
        'expires_at',
        'trial_ends_at',
        'settings',
        'limits',
        'monthly_cost',
        'activated_by',
        'notes',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'settings' => 'array',
        'limits' => 'array',
        'monthly_cost' => 'decimal:2',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
                    ->where('expires_at', '>', now());
    }

    public function scopeInTrial(Builder $query): Builder
    {
        return $query->where('status', 'trial')
                    ->where('trial_ends_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && 
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isInTrial(): bool
    {
        return $this->status === 'trial' && 
               $this->trial_ends_at !== null && 
               $this->trial_ends_at->isFuture();
    }

    public function canAccess(): bool
    {
        return $this->isActive() || $this->isInTrial();
    }

    public function activate(?User $activatedBy = null): void
    {
        $this->update([
            'status' => 'active',
            'activated_at' => now(),
            'activated_by' => $activatedBy?->id,
        ]);
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
    }

    public function startTrial(int $trialDays = 30): void
    {
        $this->update([
            'status' => 'trial',
            'trial_ends_at' => now()->addDays($trialDays),
            'activated_at' => now(),
        ]);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    public function getLimit(string $key, mixed $default = null): mixed
    {
        return data_get($this->limits, $key, $default);
    }

    public function hasReachedLimit(string $key, int $currentUsage): bool
    {
        $limit = $this->getLimit($key);
        return $limit !== null && $currentUsage >= $limit;
    }
}
