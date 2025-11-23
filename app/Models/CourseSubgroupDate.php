<?php

namespace App\Models;

use App\Models\CourseSubgroup;
use App\Models\CourseDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseSubgroupDate extends Model
{
    protected $table = 'course_subgroup_dates';

    protected $fillable = [
        'course_subgroup_id',
        'course_date_id',
        'order',
    ];

    public function courseSubgroup(): BelongsTo
    {
        return $this->belongsTo(CourseSubgroup::class, 'course_subgroup_id');
    }

    public function courseDate(): BelongsTo
    {
        return $this->belongsTo(CourseDate::class, 'course_date_id');
    }
}
