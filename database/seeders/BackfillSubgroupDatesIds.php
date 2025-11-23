<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackfillSubgroupDatesIds extends Seeder
{
    /**
     * Run the seeder to backfill subgroup_dates_id for all existing subgroups.
     *
     * This groups subgroups by (course_id, degree_id) and assigns
     * a unique ID to each homonym group (e.g., all instances of "Black Prince" get SG-000001).
     *
     * IMPORTANTE: En legacy, cada fecha genera un course_group_id diferente,
     * por lo que NO se debe usar course_group_id para agrupar homónimos.
     * Los homónimos se identifican por (course_id, degree_id) porque:
     * - Un subgrupo de un grado específico siempre está en TODAS las fechas del curso
     * - Para cursos con intervalos, el grado siempre está en TODAS las fechas del mismo intervalo
     * - El nombre (e.g., "Black Prince/Princess") viene de degree.name
     */
    public function run(): void
    {
        // Get all unique combinations of course_id, degree_id
        // These represent homonym groups across all dates
        // Example: "course 1" + "degree 5" (Black Prince/Princess) is ONE homonym group
        //          regardless of how many dates it appears on
        $homonymGroups = DB::table('course_subgroups')
            ->select('course_id', 'degree_id')
            ->whereNull('subgroup_dates_id')
            ->distinct()
            ->get();

        $this->command->info("Found {$homonymGroups->count()} homonym groups to process...");

        $progressBar = $this->command->getOutput()->createProgressBar($homonymGroups->count());
        $updatedCount = 0;

        foreach ($homonymGroups as $group) {
            // Generate deterministic ID based on homonym group composition
            // This ensures the same (course_id, degree_id) always gets same ID
            $hash = md5("{$group->course_id}_{$group->degree_id}");
            $numericHash = abs(crc32($hash)) % 999999; // Get a number between 0-999999
            $subgroupDatesId = 'SG-' . str_pad($numericHash + 1, 6, '0', STR_PAD_LEFT);

            // Update ALL subgroups that are instances of this homonym
            // (same course and degree, but potentially different dates via different course_group_ids)
            $updated = DB::table('course_subgroups')
                ->where('course_id', $group->course_id)
                ->where('degree_id', $group->degree_id)
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
