<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackfillSubgroupDatesIds extends Seeder
{
    /**
     * Run the seeder to backfill subgroup_dates_id for all existing subgroups.
     *
     * This groups subgroups by (course_id, degree_id, course_group_id) and assigns
     * a unique ID to each homonym group (e.g., all instances of "A1" get SG-000001).
     */
    public function run(): void
    {
        // Get all unique combinations of course_id, degree_id, course_group_id
        // These represent homonym groups
        $homonymGroups = DB::table('course_subgroups')
            ->select('course_id', 'degree_id', 'course_group_id')
            ->whereNull('subgroup_dates_id')
            ->distinct()
            ->get();

        $this->command->info("Found {$homonymGroups->count()} homonym groups to process...");

        $progressBar = $this->command->getOutput()->createProgressBar($homonymGroups->count());
        $updatedCount = 0;

        foreach ($homonymGroups as $group) {
            // Generate deterministic ID based on group composition
            // This ensures the same (course_id, degree_id, course_group_id) always gets same ID
            $hash = md5("{$group->course_id}_{$group->degree_id}_{$group->course_group_id}");
            $numericHash = abs(crc32($hash)) % 999999; // Get a number between 0-999999
            $subgroupDatesId = 'SG-' . str_pad($numericHash + 1, 6, '0', STR_PAD_LEFT);

            // Update all subgroups in this group (they all share same course_id, degree_id, course_group_id)
            $updated = DB::table('course_subgroups')
                ->where('course_id', $group->course_id)
                ->where('degree_id', $group->degree_id)
                ->where('course_group_id', $group->course_group_id)
                ->whereNull('subgroup_dates_id')
                ->update(['subgroup_dates_id' => $subgroupDatesId]);

            $updatedCount += $updated;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info("✅ Backfill completed successfully!");
        $this->command->info("Total homonym groups processed: {$homonymGroups->count()}");
        $this->command->info("Total subgroups updated: {$updatedCount}");
    }
}
