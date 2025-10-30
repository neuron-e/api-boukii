<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;

class AnalyzeCourseSettings extends Command
{
    protected $signature = 'courses:analyze-settings';
    protected $description = 'Analyze all course settings to categorize their format';

    public function handle()
    {
        $courses = Course::whereNotNull('settings')->get();

        $this->info("Analyzing {$courses->count()} courses...\n");

        $categories = [
            'correct' => [],
            'double_escaped' => [],
            'array_nested' => [],
            'array_chars' => [],
            'other' => []
        ];

        foreach ($courses as $course) {
            $settings = $course->settings;
            $category = 'other';

            if (is_string($settings)) {
                // Check if it starts with escaped quote (double escaped)
                if (str_starts_with($settings, '"{') || str_starts_with($settings, '\"{')) {
                    $category = 'double_escaped';
                }
                // Check if it starts with array notation
                elseif (str_starts_with($settings, '"[') || str_starts_with($settings, '[')) {
                    $category = 'array_nested';
                }
                // Check if it looks like valid JSON
                elseif (str_starts_with($settings, '{')) {
                    $decoded = json_decode($settings, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $category = 'correct';
                    }
                }
            } elseif (is_array($settings)) {
                // Check if it has numeric keys with single characters
                $keys = array_keys($settings);
                if (isset($keys[0]) && is_int($keys[0]) && isset($settings[0]) && is_string($settings[0]) && strlen($settings[0]) === 1) {
                    $category = 'array_chars';
                }
            }

            $categories[$category][] = [
                'id' => $course->id,
                'name' => $course->name,
                'preview' => is_string($settings) ? substr($settings, 0, 80) : 'array(' . count($settings) . ')'
            ];
        }

        // Display results
        foreach ($categories as $cat => $items) {
            if (empty($items)) continue;

            $this->newLine();
            $this->info("=== " . strtoupper(str_replace('_', ' ', $cat)) . " ({" . count($items) . "}) ===");

            foreach (array_slice($items, 0, 5) as $item) {
                $this->line("  [{$item['id']}] {$item['name']}");
                $this->line("      " . $item['preview']);
            }

            if (count($items) > 5) {
                $this->line("  ... and " . (count($items) - 5) . " more");
            }
        }

        $this->newLine();
        $this->info("Summary:");
        foreach ($categories as $cat => $items) {
            $this->line("  " . str_pad($cat, 20) . ": " . count($items));
        }

        return Command::SUCCESS;
    }
}
