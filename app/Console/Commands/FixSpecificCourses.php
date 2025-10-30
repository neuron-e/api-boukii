<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;

class FixSpecificCourses extends Command
{
    protected $signature = 'courses:fix-specific {ids*}';
    protected $description = 'Fix specific courses by ID';

    public function handle()
    {
        $ids = $this->argument('ids');

        foreach ($ids as $id) {
            $course = Course::find($id);

            if (!$course) {
                $this->error("Course {$id} not found");
                continue;
            }

            $this->info("\n=== Course ID {$course->id}: {$course->name} ===");
            $settings = $course->settings;

            $this->line("Current type: " . gettype($settings));

            if (is_array($settings)) {
                $this->line("Array count: " . count($settings));

                // Separate numeric and non-numeric keys
                $numericParts = [];
                $nonNumericParts = [];

                foreach ($settings as $key => $value) {
                    if (is_int($key)) {
                        $numericParts[$key] = $value;
                    } else {
                        $nonNumericParts[$key] = $value;
                    }
                }

                if (!empty($numericParts)) {
                    ksort($numericParts);

                    // Reconstruct JSON from numeric keys
                    $reconstructed = implode('', $numericParts);
                    $this->line("Reconstructed from " . count($numericParts) . " numeric keys");
                    $this->line("Reconstructed (first 150 chars): " . substr($reconstructed, 0, 150));

                    // Parse the reconstructed JSON
                    $parsedSettings = json_decode($reconstructed, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->info("✓ Valid JSON parsed");

                        // Merge with non-numeric keys
                        if (!empty($nonNumericParts)) {
                            $this->line("Non-numeric keys found: " . implode(', ', array_keys($nonNumericParts)));
                            foreach ($nonNumericParts as $key => $value) {
                                $parsedSettings[$key] = $value;
                            }
                        }

                        // Show preview
                        $previewJson = json_encode($parsedSettings);
                        $this->line("Final array (first 200 chars as JSON): " . substr($previewJson, 0, 200));

                        // Save as ARRAY - Laravel will convert to JSON string automatically due to 'json' cast
                        $course->settings = $parsedSettings;
                        $course->save();

                        $this->info("✓ Course {$id} fixed and saved as array");

                        // Verify
                        $course->refresh();
                        $this->line("Verification - Type after save: " . gettype($course->settings));
                    } else {
                        $this->error("✗ Invalid JSON: " . json_last_error_msg());
                    }
                } else {
                    // No numeric keys, save array directly
                    $this->line("No numeric keys, saving array as-is");
                    $previewJson = json_encode($settings);
                    $this->line("Array preview (first 200 chars): " . substr($previewJson, 0, 200));

                    // Save as ARRAY - Laravel will convert to JSON string automatically
                    $course->settings = $settings;
                    $course->save();

                    $this->info("✓ Course {$id} saved as array");

                    // Verify
                    $course->refresh();
                    $this->line("Verification - Type after save: " . gettype($course->settings));
                }
            } elseif (is_string($settings)) {
                $this->line("Currently string, length: " . strlen($settings));
                $this->line("First 200 chars: " . substr($settings, 0, 200));

                // Parse and save as array
                $decoded = json_decode($settings, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->info("✓ Valid JSON string, converting to array");

                    // Save as ARRAY - Laravel will convert to JSON string automatically
                    $course->settings = $decoded;
                    $course->save();

                    $this->info("✓ Course {$id} converted from string to array");

                    // Verify
                    $course->refresh();
                    $this->line("Verification - Type after save: " . gettype($course->settings));
                } else {
                    $this->error("✗ String but not valid JSON: " . json_last_error_msg());
                }
            }
        }

        return Command::SUCCESS;
    }
}
