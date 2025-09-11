<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role Model
 * 
 * Represents roles in the system (admin, manager, monitor, client, etc.)
 */
class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'name',
        'slug',
        'description'
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'description' => 'string'
    ];

    /**
     * Get the user-school-role assignments for this role
     */
    public function userSchoolRoles(): HasMany
    {
        return $this->hasMany(UserSchoolRole::class);
    }

    /**
     * Get the permissions associated with this role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Check if role has specific permission
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions()->where('slug', $permissionSlug)->exists();
    }
}