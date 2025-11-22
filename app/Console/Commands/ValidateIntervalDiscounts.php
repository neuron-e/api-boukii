<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;

class ValidateIntervalDiscounts extends Command
{
    protected $signature = 'courses:validate-interval-discounts';
    protected $description = 'Validate that collective flexible courses have interval discount configuration';

    public function handle()
    {
        $this->info("Validating interval discount configuration...\n");

        // Find all collective flexible courses
        $courses = Course::where('course_type', 'collective')
            ->where('flexible_dates', true)
            ->get();

        if ($courses->isEmpty()) {
            $this->info("No collective flexible courses found.");
            return;
        }

        $this->info("Found " . $courses->count() . " collective flexible courses.\n");

        $withDiscounts = 0;
        $withoutDiscounts = 0;
        $problematic = [];

        foreach ($courses as $course) {
            // Check if course has intervals configured
            $hasIntervalConfig = false;

            // Option 1: Check course_intervals table
            if ($course->course_intervals && $course->course_intervals->count() > 0) {
                // Check if any interval has discounts
                foreach ($course->course_intervals as $interval) {
                    if ($interval->discounts && is_array($interval->discounts) && count($interval->discounts) > 0) {
                        $hasIntervalConfig = true;
                        break;
                    }
                }
            }

            // Option 2: Check settings.intervals
            if (!$hasIntervalConfig) {
                $settings = $course->settings;
                if (is_string($settings)) {
                    $settings = json_decode($settings, true);
                }

                if (is_array($settings) && isset($settings['intervals']) && is_array($settings['intervals'])) {
                    foreach ($settings['intervals'] as $interval) {
                        if (isset($interval['discounts']) && is_array($interval['discounts']) && count($interval['discounts']) > 0) {
                            $hasIntervalConfig = true;
                            break;
                        }
                    }
                }
            }

            if ($hasIntervalConfig) {
                $withDiscounts++;
                $this->line("✓ Course ID {$course->id}: {$course->name} - HAS interval discounts");
            } else {
                $withoutDiscounts++;
                $this->line("✗ Course ID {$course->id}: {$course->name} - NO interval discounts");
                $problematic[] = [
                    'id' => $course->id,
                    'name' => $course->name,
                    'type' => 'missing_interval_discounts'
                ];
            }
        }

        $this->line("\n");
        $this->info("Summary:");
        $this->line("  ✓ With interval discounts: {$withDiscounts}");
        $this->line("  ✗ Without interval discounts: {$withoutDiscounts}");

        if (count($problematic) > 0) {
            $this->line("\n");
            $this->error("ISSUES FOUND:");
            foreach ($problematic as $item) {
                $this->error("  - Course ID {$item['id']}: {$item['name']}");
            }
            $this->line("\nThese courses may have incorrect discount calculations in the booking page fallback.");
            $this->line("Please configure interval discounts or ensure course_intervals are populated.\n");
            return 1;
        }

        $this->info("All collective flexible courses have proper interval discount configuration.");
        return 0;
    }
}
