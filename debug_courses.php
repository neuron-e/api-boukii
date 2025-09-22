<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUGGING COURSES ===\n";

// Test our collective courses
$testCourses = [437, 438];
foreach ($testCourses as $courseId) {
    $course = App\Models\Course::find($courseId);
    echo "\nCourse $courseId: " . $course->name . "\n";
    echo "  Type: " . $course->course_type . " | Active: " . $course->active . " | Online: " . $course->online . "\n";
    echo "  Age: " . $course->age_min . "-" . $course->age_max . "\n";

    // Check course dates
    $courseDates = $course->courseDates;
    echo "  Course dates: " . $courseDates->count() . "\n";

    if ($courseDates->count() > 0) {
        $firstDate = $courseDates->first();
        echo "  First date: " . $firstDate->date . "\n";

        // Check subgroups
        $subgroups = $firstDate->courseSubgroups;
        echo "  Subgroups: " . ($subgroups ? $subgroups->count() : 'NULL') . "\n";

        if ($subgroups && $subgroups->count() > 0) {
            $firstSubgroup = $subgroups->first();
            echo "  First subgroup max_participants: " . $firstSubgroup->max_participants . "\n";
            echo "  First subgroup ID: " . $firstSubgroup->id . "\n";

            // Check if there are any bookings for this subgroup
            $bookings = DB::table('booking_users')
                ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
                ->where('booking_users.course_subgroup_id', $firstSubgroup->id)
                ->where('booking_users.status', 1)
                ->whereNull('booking_users.deleted_at')
                ->whereNull('bookings.deleted_at')
                ->count();

            echo "  Bookings for this subgroup: " . $bookings . "\n";
            echo "  Available capacity: " . ($firstSubgroup->max_participants - $bookings) . "\n";
        }
    }
}

// Test a working course for comparison
echo "\n=== WORKING COURSE FOR COMPARISON ===\n";
$workingCourse = App\Models\Course::find(435);
if ($workingCourse) {
    echo "Course 435: " . $workingCourse->name . "\n";
    $workingDates = $workingCourse->courseDates;
    if ($workingDates->count() > 0) {
        $firstWorkingDate = $workingDates->first();
        $workingSubgroups = $firstWorkingDate->courseSubgroups;
        echo "  Subgroups: " . ($workingSubgroups ? $workingSubgroups->count() : 'NULL') . "\n";

        if ($workingSubgroups && $workingSubgroups->count() > 0) {
            $firstWorkingSubgroup = $workingSubgroups->first();
            echo "  First subgroup max_participants: " . $firstWorkingSubgroup->max_participants . "\n";
            echo "  First subgroup ID: " . $firstWorkingSubgroup->id . "\n";

            $workingBookings = DB::table('booking_users')
                ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
                ->where('booking_users.course_subgroup_id', $firstWorkingSubgroup->id)
                ->where('booking_users.status', 1)
                ->whereNull('booking_users.deleted_at')
                ->whereNull('bookings.deleted_at')
                ->count();

            echo "  Bookings for this subgroup: " . $workingBookings . "\n";
            echo "  Available capacity: " . ($firstWorkingSubgroup->max_participants - $workingBookings) . "\n";
        }
    }
}