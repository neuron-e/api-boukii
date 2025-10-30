<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;

class InspectOtherSettings extends Command
{
    protected $signature = 'courses:inspect-other';
    protected $description = 'Inspect courses in OTHER category';

    public function handle()
    {
        $courses = Course::whereNotNull('settings')->get();

        $otherCourses = [];

        foreach ($courses as $course) {
            $settings = $course->settings;

            if (is_array($settings)) {
                $keys = array_keys($settings);
                // Skip if it has numeric keys with single characters
                if (isset($keys[0]) && is_int($keys[0]) && isset($settings[0]) && is_string($settings[0]) && strlen($settings[0]) === 1) {
                    continue;
                }
                $otherCourses[] = $course;
            } elseif (is_string($settings)) {
                if (!str_starts_with($settings, '{')) {
                    $otherCourses[] = $course;
                }
            }
        }

        $this->info("Found " . count($otherCourses) . " courses in OTHER category\n");

        foreach (array_slice($otherCourses, 0, 10) as $course) {
            $settings = $course->settings;

            $this->info("=== Course ID {$course->id}: {$course->name} (Type: {$course->course_type}) ===");
            $this->line("Settings type: " . gettype($settings));

            if (is_array($settings)) {
                $this->line("Array count: " . count($settings));
                $keys = array_keys($settings);
                $this->line("Keys: " . implode(', ', array_slice($keys, 0, 20)));

                // Show full structure for small arrays
                if (count($settings) <= 10) {
                    $this->line("Full content:");
                    $this->line(print_r($settings, true));
                } else {
                    $this->line("First 5 items:");
                    foreach (array_slice($settings, 0, 5, true) as $key => $val) {
                        $this->line("  [{$key}] => " . (is_array($val) ? 'array(' . count($val) . ')' : gettype($val) . ': ' . (is_string($val) ? substr($val, 0, 50) : print_r($val, true))));
                    }
                }

                // Try to encode as JSON
                $json = json_encode($settings);
                if ($json !== false) {
                    $this->line("As JSON (first 200 chars): " . substr($json, 0, 200));
                }
            } elseif (is_string($settings)) {
                $this->line("String length: " . strlen($settings));
                $this->line("First 200 chars: " . substr($settings, 0, 200));
            }

            $this->newLine();
        }

        return Command::SUCCESS;
    }
}
