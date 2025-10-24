<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseInterval extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'name',
        'start_date',
        'end_date',
        'display_order',
        'config_mode',
        'date_generation_method',
        'consecutive_days_count',
        'weekly_pattern',
        'booking_mode',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'display_order' => 'integer',
        'consecutive_days_count' => 'integer',
        'weekly_pattern' => 'array',
    ];

    /**
     * Get the course that owns the interval.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the course dates that belong to this interval.
     */
    public function courseDates()
    {
        return $this->hasMany(CourseDate::class, 'course_interval_id');
    }

    /**
     * NUEVO: Get the monitor assignments for this interval.
     */
    public function monitorAssignments()
    {
        return $this->hasMany(CourseIntervalMonitor::class, 'course_interval_id');
    }

    /**
     * NUEVO: Get the group configurations for this interval.
     */
    public function intervalGroups()
    {
        return $this->hasMany(CourseIntervalGroup::class, 'course_interval_id');
    }

    /**
     * Interval-specific discount rules.
     */
    public function discounts()
    {
        return $this->hasMany(CourseIntervalDiscount::class, 'course_interval_id');
    }

    /**
     * Scope to get intervals for a specific course.
     */
    public function scopeForCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * Scope to get intervals ordered by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Check if this interval has custom configuration.
     */
    public function hasCustomConfig()
    {
        return $this->config_mode === 'custom';
    }

    /**
     * Check if this interval inherits configuration from course.
     */
    public function inheritsConfig()
    {
        return $this->config_mode === 'inherit';
    }

    /**
     * Check if booking mode is package (must book all dates).
     */
    public function isPackageMode()
    {
        return $this->booking_mode === 'package';
    }

    /**
     * Check if booking mode is flexible (can choose dates).
     */
    public function isFlexibleMode()
    {
        return $this->booking_mode === 'flexible';
    }
}
