<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'key_hash',
        'school_id',
        'scopes',
        'active',
        'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'key_hash',
    ];

    /**
     * The school this API key belongs to
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Generate a new API key and return both the key and hash
     */
    public static function generateKey(): array
    {
        $key = 'bk_' . Str::random(32);
        $hash = hash('sha256', $key);

        return [
            'key' => $key,
            'hash' => $hash,
        ];
    }

    /**
     * Create a new API key
     */
    public static function createKey(string $name, int $schoolId, array $scopes = ['timing:write']): array
    {
        $keyData = self::generateKey();

        $apiKey = self::create([
            'name' => $name,
            'key_hash' => $keyData['hash'],
            'school_id' => $schoolId,
            'scopes' => $scopes,
            'active' => true,
        ]);

        return [
            'api_key' => $apiKey,
            'key' => $keyData['key'], // Only returned once!
        ];
    }

    /**
     * Validate an API key and return the associated model
     */
    public static function validateKey(string $key): ?self
    {
        $hash = hash('sha256', $key);

        return self::where('key_hash', $hash)
            ->where('active', true)
            ->first();
    }

    /**
     * Check if the key has a specific scope
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Scope to filter active keys
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to filter by school
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}