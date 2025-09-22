<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUGGING FULL QUERY STEP BY STEP ===\n";

$startDate = '2025-09-20';
$endDate = '2125-01-01';
$min_age = 1;
$max_age = 99;
$school_id = 1;

// Step 1: Basic filters
echo "Step 1: Basic filters (course_type=1, school_id=$school_id, online=1, active=1)\n";
$step1 = App\Models\Course::where('course_type', 1)
    ->where('school_id', $school_id)
    ->where('online', 1)
    ->where('active', 1)
    ->get(['id', 'name']);
echo "Courses found: " . $step1->count() . "\n";
foreach ($step1 as $course) {
    echo "  {$course->id}: {$course->name}\n";
}

// Step 2: Add courseDate filter
echo "\nStep 2: Add courseDates filter (date >= $startDate AND <= $endDate)\n";
$step2 = App\Models\Course::where('course_type', 1)
    ->where('school_id', $school_id)
    ->where('online', 1)
    ->where('active', 1)
    ->whereHas('courseDates', function($query) use ($startDate, $endDate) {
        $query->where('date', '>=', $startDate)
              ->where('date', '<=', $endDate);
    })
    ->get(['id', 'name']);
echo "Courses found: " . $step2->count() . "\n";
foreach ($step2 as $course) {
    echo "  {$course->id}: {$course->name}\n";
}

// Step 3: Add courseSubgroups filter
echo "\nStep 3: Add courseSubgroups filter\n";
$step3 = App\Models\Course::where('course_type', 1)
    ->where('school_id', $school_id)
    ->where('online', 1)
    ->where('active', 1)
    ->whereHas('courseDates', function($query) use ($startDate, $endDate) {
        $query->where('date', '>=', $startDate)
              ->where('date', '<=', $endDate)
              ->whereHas('courseSubgroups', function($subQuery) {
                  // Just basic existence
                  $subQuery->whereNotNull('id');
              });
    })
    ->get(['id', 'name']);
echo "Courses found: " . $step3->count() . "\n";
foreach ($step3 as $course) {
    echo "  {$course->id}: {$course->name}\n";
}

// Step 4: Add capacity check
echo "\nStep 4: Add capacity check\n";
$step4 = App\Models\Course::where('course_type', 1)
    ->where('school_id', $school_id)
    ->where('online', 1)
    ->where('active', 1)
    ->whereHas('courseDates', function($query) use ($startDate, $endDate) {
        $query->where('date', '>=', $startDate)
              ->where('date', '<=', $endDate)
              ->whereHas('courseSubgroups', function($subQuery) {
                  $subQuery->whereRaw('max_participants > (
                    SELECT COUNT(*)
                    FROM booking_users
                    JOIN bookings ON booking_users.booking_id = bookings.id
                    WHERE booking_users.course_subgroup_id = course_subgroups.id
                        AND booking_users.status = 1
                        AND booking_users.deleted_at IS NULL
                        AND bookings.deleted_at IS NULL
                  )');
              });
    })
    ->get(['id', 'name']);
echo "Courses found: " . $step4->count() . "\n";
foreach ($step4 as $course) {
    echo "  {$course->id}: {$course->name}\n";
}

// Step 5: Add courseGroup filter
echo "\nStep 5: Add courseGroup filter\n";
$step5 = App\Models\Course::where('course_type', 1)
    ->where('school_id', $school_id)
    ->where('online', 1)
    ->where('active', 1)
    ->whereHas('courseDates', function($query) use ($startDate, $endDate, $max_age, $min_age) {
        $query->where('date', '>=', $startDate)
              ->where('date', '<=', $endDate)
              ->whereHas('courseSubgroups', function($subQuery) use ($max_age, $min_age) {
                  $subQuery->whereRaw('max_participants > (
                    SELECT COUNT(*)
                    FROM booking_users
                    JOIN bookings ON booking_users.booking_id = bookings.id
                    WHERE booking_users.course_subgroup_id = course_subgroups.id
                        AND booking_users.status = 1
                        AND booking_users.deleted_at IS NULL
                        AND bookings.deleted_at IS NULL
                  )')
                  ->whereHas('courseGroup', function($groupQuery) use ($max_age, $min_age) {
                      if ($max_age !== null) {
                          $groupQuery->where('age_min', '<=', $max_age);
                      }
                      if ($min_age !== null) {
                          $groupQuery->where('age_max', '>=', $min_age);
                      }
                  });
              });
    })
    ->get(['id', 'name']);
echo "Courses found: " . $step5->count() . "\n";
foreach ($step5 as $course) {
    echo "  {$course->id}: {$course->name}\n";
}