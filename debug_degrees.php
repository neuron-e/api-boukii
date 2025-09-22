<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUGGING COURSE GROUPS AND DEGREES ===\n";

// Check our course 437
$course437 = App\Models\Course::find(437);
$firstDate437 = $course437->courseDates->first();
$firstSubgroup437 = $firstDate437->courseSubgroups->first();

echo "Course 437:\n";
echo "  Subgroup ID: " . $firstSubgroup437->id . "\n";
echo "  Course Group ID: " . $firstSubgroup437->course_group_id . "\n";
echo "  Degree ID: " . $firstSubgroup437->degree_id . "\n";

// Check if courseGroup exists
if ($firstSubgroup437->course_group_id) {
    $courseGroup = App\Models\CourseGroup::find($firstSubgroup437->course_group_id);
    if ($courseGroup) {
        echo "  Course Group exists: YES\n";
        echo "  Course Group degree_id: " . $courseGroup->degree_id . "\n";

        // Check if degree exists
        $degree = App\Models\Degree::find($courseGroup->degree_id);
        if ($degree) {
            echo "  Degree exists: YES\n";
            echo "  Degree name: " . $degree->name . "\n";
            echo "  Degree order: " . ($degree->degree_order ?? 'NULL') . "\n";
        } else {
            echo "  Degree exists: NO\n";
        }
    } else {
        echo "  Course Group exists: NO\n";
    }
} else {
    echo "  Course Group ID: NULL\n";
}

// Compare with working course 435
echo "\n=== WORKING COURSE 435 FOR COMPARISON ===\n";
$course435 = App\Models\Course::find(435);
$firstDate435 = $course435->courseDates->first();
$firstSubgroup435 = $firstDate435->courseSubgroups->first();

echo "Course 435:\n";
echo "  Subgroup ID: " . $firstSubgroup435->id . "\n";
echo "  Course Group ID: " . $firstSubgroup435->course_group_id . "\n";
echo "  Degree ID: " . $firstSubgroup435->degree_id . "\n";

if ($firstSubgroup435->course_group_id) {
    $courseGroup435 = App\Models\CourseGroup::find($firstSubgroup435->course_group_id);
    if ($courseGroup435) {
        echo "  Course Group exists: YES\n";
        echo "  Course Group degree_id: " . $courseGroup435->degree_id . "\n";

        $degree435 = App\Models\Degree::find($courseGroup435->degree_id);
        if ($degree435) {
            echo "  Degree exists: YES\n";
            echo "  Degree name: " . $degree435->name . "\n";
            echo "  Degree order: " . ($degree435->degree_order ?? 'NULL') . "\n";
        } else {
            echo "  Degree exists: NO\n";
        }
    } else {
        echo "  Course Group exists: NO\n";
    }
}