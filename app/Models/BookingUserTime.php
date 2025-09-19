<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingUserTime extends Model
{
    protected $table = 'booking_user_times';

    protected $fillable = [
        'booking_user_id',
        'course_date_id',
        'lap_no',
        'time_ms',
        'status',
        'source',
        'device_id',
        'meta',
    ];

    protected $casts = [
        'lap_no' => 'integer',
        'time_ms' => 'integer',
        'meta' => 'array',
    ];

    /**
     * The booking user this time belongs to
     */
    public function bookingUser()
    {
        return $this->belongsTo(BookingUser::class, 'booking_user_id');
    }

    /**
     * The course date this time is for
     */
    public function courseDate()
    {
        return $this->belongsTo(\App\Models\CourseDate::class, 'course_date_id');
    }

    /**
     * Get the course through the course date
     */
    public function course()
    {
        return $this->hasOneThrough(
            Course::class,
            \App\Models\CourseDate::class,
            'id',
            'id',
            'course_date_id',
            'course_id'
        );
    }

    /**
     * Get the school through the booking user
     */
    public function school()
    {
        return $this->hasOneThrough(
            School::class,
            BookingUser::class,
            'id',
            'id',
            'booking_user_id',
            'school_id'
        );
    }

    /**
     * Format time in human readable format (mm:ss.SSS)
     */
    public function getFormattedTimeAttribute()
    {
        $totalMs = $this->time_ms;
        $minutes = intval($totalMs / 60000);
        $seconds = intval(($totalMs % 60000) / 1000);
        $milliseconds = $totalMs % 1000;

        return sprintf('%02d:%02d.%03d', $minutes, $seconds, $milliseconds);
    }

    /**
     * Scope to filter by course
     */
    public function scopeForCourse($query, $courseId)
    {
        return $query->whereHas('courseDate', function ($query) use ($courseId) {
            $query->where('course_id', $courseId);
        });
    }

    /**
     * Scope to filter by school
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->whereHas('bookingUser', function ($query) use ($schoolId) {
            $query->where('school_id', $schoolId);
        });
    }
}

