<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MeetingPoint extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public $table = 'meeting_points';

    public $fillable = [
        'school_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'instructions',
        'active'
    ];

    protected $casts = [
        'school_id' => 'integer',
        'name' => 'string',
        'address' => 'string',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'instructions' => 'string',
        'active' => 'boolean'
    ];

    public static array $rules = [
        'school_id' => 'required|integer|exists:schools,id',
        'name' => 'required|string|max:255',
        'address' => 'nullable|string|max:255',
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'instructions' => 'nullable|string',
        'active' => 'nullable|boolean'
    ];

    /**
     * Relationship with School
     */
    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    /**
     * Scope to get only active meeting points
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
