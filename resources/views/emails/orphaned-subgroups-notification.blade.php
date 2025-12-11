BOUKII - Course Data Integrity Alert
=====================================

Date: {{ $timestamp }}
@if($schoolId)
School ID: {{ $schoolId }}
@endif

AUTOMATED MAINTENANCE COMPLETED
-------------------------------

Step 1: Booking Users Migration
- Migrated: {{ $migrationStats['migrated'] }} booking_users
- Skipped (no active booking): {{ $migrationStats['skipped_no_booking'] }}
- Skipped (no target subgroup): {{ $migrationStats['skipped_no_target'] }}

Step 2: Orphaned Subgroups Detection
- Total orphaned subgroups: {{ $orphanedStats['total'] }}
- With booking_users: {{ $orphanedStats['with_bookings'] }}
- Without booking_users: {{ $orphanedStats['without_bookings'] }}

@if($orphanedStats['with_bookings'] > 0)
⚠️ WARNING: {{ $orphanedStats['with_bookings'] }} orphaned subgroups still have booking_users!

These booking_users likely have soft-deleted bookings and should be reviewed manually.

Details of subgroups with booking_users:
@foreach($orphanedStats['subgroups_with_bookings'] as $subgroup)
- Subgroup ID: {{ $subgroup['id'] }} | Course: {{ $subgroup['course_id'] }} | Date: {{ $subgroup['course_date_id'] }} | Degree: {{ $subgroup['degree_id'] }} | Booking Users: {{ $subgroup['booking_users_count'] }}
@endforeach
@endif

RECOMMENDED ACTIONS
-------------------

@if($orphanedStats['without_bookings'] > 0)
1. Run cleanup command to remove {{ $orphanedStats['without_bookings'] }} orphaned subgroups without booking_users:
   php artisan course:clean-orphaned-subgroups @if($schoolId)--school_id={{ $schoolId }}@endif

@endif
@if($orphanedStats['with_bookings'] > 0)
2. Review the {{ $orphanedStats['with_bookings'] }} subgroups with booking_users manually
3. Check if their booking_users have soft-deleted bookings
4. If safe, run cleanup command to remove them

@endif

This is an automated message from the Course Data Integrity Maintenance system.
