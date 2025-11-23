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
            ->groupBy('course_id', 'degree_id', 'course_group_id')
            ->get();

        $this->command->info("Found {$homonymGroups->count()} homonym groups to process...");

        $counter = 1;
        $progressBar = $this->command->getOutput()->createProgressBar($homonymGroups->count());

        foreach ($homonymGroups as $group) {
            // Generate unique ID for this homonym group
            $subgroupDatesId = 'SG-' . str_pad($counter, 6, '0', STR_PAD_LEFT);

            // Update all subgroups in this group
            DB::table('course_subgroups')
                ->where('course_id', $group->course_id)
                ->where('degree_id', $group->degree_id)
                ->where('course_group_id', $group->course_group_id)
                ->whereNull('subgroup_dates_id')
                ->update(['subgroup_dates_id' => $subgroupDatesId]);

            $counter++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info('✅ Backfill completed successfully!');
        $this->command->info("Total homonym groups processed: " . ($counter - 1));
    }
}
