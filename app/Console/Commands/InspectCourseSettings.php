<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;

class InspectCourseSettings extends Command
{
    protected $signature = 'courses:inspect-settings {--limit=5}';
    protected $description = 'Inspect course settings to understand the data structure';

    public function handle()
    {
        $limit = $this->option('limit');
        $courses = Course::whereNotNull('settings')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        $this->info("Inspecting {$courses->count()} courses with settings...\n");

        foreach ($courses as $course) {
            $this->info("=== Course ID: {$course->id} ===");
            $this->line("Name: {$course->name}");

            $settings = $course->settings;

            $this->line("Type: " . gettype($settings));
            $this->line("Is string: " . (is_string($settings) ? 'Yes' : 'No'));

            if (is_string($settings)) {
                $this->line("Length: " . strlen($settings));
                $this->line("First 200 chars: " . substr($settings, 0, 200));

                $decoded = json_decode($settings, true);
                $this->line("JSON decode error: " . (json_last_error() === JSON_ERROR_NONE ? 'None' : json_last_error_msg()));

                if (is_array($decoded)) {
                    $this->line("Decoded keys (first 20): " . implode(', ', array_slice(array_keys($decoded), 0, 20)));
                    $this->line("First key type: " . gettype(array_key_first($decoded)));
                    $this->line("First value type: " . gettype($decoded[array_key_first($decoded)]));

                    if (isset($decoded[0])) {
                        $this->warn("⚠ Has numeric key 0: " . (is_string($decoded[0]) ? "'{$decoded[0]}'" : gettype($decoded[0])));
                    }
                }
            } elseif (is_array($settings)) {
                $keys = array_keys($settings);
                $this->line("Total keys: " . count($keys));
                $this->line("Array keys (first 20): " . implode(', ', array_slice($keys, 0, 20)));

                // Check for non-numeric keys
                $nonNumericKeys = array_filter($keys, function($k) { return !is_int($k); });
                if (!empty($nonNumericKeys)) {
                    $this->warn("⚠ Non-numeric keys found: " . implode(', ', array_slice($nonNumericKeys, 0, 10)));

                    // Show these non-numeric key values
                    foreach (array_slice($nonNumericKeys, 0, 5) as $key) {
                        $val = $settings[$key];
                        $type = gettype($val);
                        if (is_array($val)) {
                            $this->line("  [{$key}] = array(" . count($val) . " items) - sample: " . json_encode(array_slice($val, 0, 2)));
                        } elseif (is_string($val)) {
                            $this->line("  [{$key}] = string('" . (strlen($val) > 100 ? substr($val, 0, 100) . '...' : $val) . "')");
                        } else {
                            $this->line("  [{$key}] = {$type}: " . print_r($val, true));
                        }
                    }
                }

                // Show type and sample of first few numeric values
                $this->line("\nFirst 10 numeric values:");
                for ($i = 0; $i < 10 && isset($settings[$i]); $i++) {
                    $val = $settings[$i];
                    $type = gettype($val);
                    if (is_string($val)) {
                        $this->line("  [{$i}] = string('" . $val . "')");
                    } else {
                        $this->line("  [{$i}] = {$type}");
                    }
                }

                // Try to serialize as JSON to see what it looks like
                $jsonEncoded = json_encode($settings);
                if ($jsonEncoded !== false) {
                    $this->line("\nJSON encoded (first 300 chars): " . substr($jsonEncoded, 0, 300));
                }
            }

            $this->newLine();
        }

        return Command::SUCCESS;
    }
}
