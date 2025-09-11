<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission Model
 * 
 * Represents specific permissions in the system
 */
class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'module'
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'description' => 'string',
        'module' => 'string'
    ];

    /**
     * Get the roles associated with this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}