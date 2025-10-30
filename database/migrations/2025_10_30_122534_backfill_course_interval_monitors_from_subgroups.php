<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FIXED BUG #3: Backfill course_interval_monitors table from existing course_subgroups.monitor_id
     * This migration syncs historical teacher assignments that were created before the auto-sync fix
     */
    public function up(): void
    {
        // Get all subgroups that have a monitor assigned
        $subgroups = DB::table('course_subgroups')
            ->whereNotNull('monitor_id')
            ->whereNotNull('course_date_id')
            ->whereNull('deleted_at')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($subgroups as $subgroup) {
            // Get the course date to find the interval
            $courseDate = DB::table('course_dates')
                ->where('id', $subgroup->course_date_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$courseDate || !$courseDate->course_interval_id) {
                $skipped++;
                continue;
            }

            // Get the course to verify it uses intervals
            $course = DB::table('courses')
                ->where('id', $subgroup->course_id)
                ->first();

            if (!$course || $course->intervals_config_mode !== 'intervals') {
                $skipped++;
                continue;
            }

            // Check if assignment already exists
            $exists = DB::table('course_interval_monitors')
                ->where('course_interval_id', $courseDate->course_interval_id)
                ->where('course_subgroup_id', $subgroup->id)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Create the interval monitor assignment
            DB::table('course_interval_monitors')->insert([
                'course_id' => $course->id,
                'course_interval_id' => $courseDate->course_interval_id,
                'course_subgroup_id' => $subgroup->id,
                'monitor_id' => $subgroup->monitor_id,
                'active' => true,
                'notes' => 'Backfilled from course_subgroups by migration 2025_10_30_122534',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $created++;
        }

        echo "\n✅ Backfill completed:\n";
        echo "   - Created: {$created} interval monitor assignments\n";
        echo "   - Skipped: {$skipped} subgroups (no interval or already exists)\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove only the records created by this migration
        DB::table('course_interval_monitors')
            ->where('notes', 'LIKE', '%Backfilled from course_subgroups by migration 2025_10_30_122534%')
            ->delete();

        echo "\n✅ Backfill rollback completed\n";
    }
};
