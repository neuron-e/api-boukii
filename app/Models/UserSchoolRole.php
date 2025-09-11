<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserSchoolRole Model
 * 
 * Represents the relationship between users, schools, and roles.
 * This is used for the multi-context authentication system where
 * users can have different roles in different schools.
 */
class UserSchoolRole extends Model
{
    protected $table = 'user_school_roles';

    protected $fillable = [
        'user_id',
        'school_id',
        'role_id'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'school_id' => 'integer', 
        'role_id' => 'integer'
    ];

    /**
     * Get the user that owns this role assignment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the school for this role assignment
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the role for this assignment
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}