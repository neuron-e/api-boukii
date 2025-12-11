<?php

namespace App\Console\Commands;

use App\Models\BookingUser;
use App\Models\CourseSubgroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseOrphanedBookingUsers extends Command
{
    protected $signature = 'course:diagnose-orphaned-booking-users {--school_id= : Filter by specific school}';
    protected $description = 'Diagnose why booking_users cannot be migrated';

    public function handle()
    {
        $schoolId = $this->option('school_id');

        $this->info('==============================================');
        $this->info('Diagnosing Orphaned Booking Users');
        $this->info('==============================================');
        if ($schoolId) {
            $this->info('School Filter: ' . $schoolId);
        }
        $this->info('');

        // Find orphaned subgroups with booking_users
        $query = CourseSubgroup::query()
            ->select('course_subgroups.*')
            ->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
            ->whereNotNull('course_groups.deleted_at')
            ->whereNull('course_subgroups.deleted_at');

        if ($schoolId) {
            $query->join('courses', 'course_subgroups.course_id', '=', 'courses.id')
                ->where('courses.school_id', $schoolId);
        }

        $orphanedSubgroups = $query->get();

        $problemCases = [];

        foreach ($orphanedSubgroups as $subgroup) {
            $bookingUsers = DB::table('booking_users')
                ->leftJoin('bookings', 'booking_users.booking_id', '=', 'bookings.id')
                ->where('booking_users.course_subgroup_id', $subgroup->id)
                ->whereNull('booking_users.deleted_at')
                ->whereNull('bookings.deleted_at')
                ->select('booking_users.*', 'bookings.id as booking_exists')
                ->get();

            if ($bookingUsers->isEmpty()) {
                continue;
            }

            foreach ($bookingUsers as $bu) {
                // Try to find target subgroup
                $targetSubgroup = CourseSubgroup::whereNull('deleted_at')
                    ->where('course_id', $subgroup->course_id)
                    ->where('course_date_id', $subgroup->course_date_id)
                    ->where('degree_id', $subgroup->degree_id)
                    ->whereHas('courseGroup', function($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->first();

                if (!$targetSubgroup) {
                    $problemCases[] = [
                        'booking_user_id' => $bu->id,
                        'booking_id' => $bu->booking_id,
                        'orphaned_subgroup_id' => $subgroup->id,
                        'course_id' => $subgroup->course_id,
                        'course_date_id' => $subgroup->course_date_id,
                        'degree_id' => $subgroup->degree_id,
                        'reason' => 'NO_TARGET_SUBGROUP',
                    ];
                }
            }
        }

        if (empty($problemCases)) {
            $this->info('✓ No problematic booking_users found!');
            return 0;
        }

        $this->warn('Found ' . count($problemCases) . ' booking_users that cannot be migrated:');
        $this->info('');

        $this->table(
            ['BookingUser ID', 'Booking ID', 'Orphaned Subgroup', 'Course', 'Date', 'Degree', 'Reason'],
            array_map(function($case) {
                return [
                    $case['booking_user_id'],
                    $case['booking_id'],
                    $case['orphaned_subgroup_id'],
                    $case['course_id'],
                    $case['course_date_id'],
                    $case['degree_id'],
                    $case['reason'],
                ];
            }, $problemCases)
        );

        $this->info('');
        $this->warn('These booking_users have active bookings but no valid target subgroup exists.');
        $this->warn('This usually means the course_date or degree no longer has active subgroups.');
        $this->info('');
        $this->info('Recommendation: Review these cases manually to determine if:');
        $this->info('1. The bookings should be cancelled/refunded');
        $this->info('2. New subgroups need to be created for these dates/degrees');
        $this->info('3. The booking_users can be safely orphaned (and cleaned up later)');

        return 0;
    }
}
