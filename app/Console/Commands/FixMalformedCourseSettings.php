<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;

class FixMalformedCourseSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'courses:fix-settings
                            {--dry-run : Preview changes without applying them}
                            {--id= : Fix specific course by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix malformed course settings (where settings is stored as array of characters)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $courseId = $this->option('id');

        $this->info('Starting to fix malformed course settings...');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }

        // Get courses to fix
        $query = Course::whereNotNull('settings');
        if ($courseId) {
            $query->where('id', $courseId);
        }

        $courses = $query->get();
        $this->info("Found {$courses->count()} courses with settings field");

        $fixedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($courses as $course) {
            try {
                $settings = $course->settings;

                // Check if settings is malformed
                $isMalformed = false;

                // Case 1: settings is directly an array with numeric keys (Laravel deserialized it)
                if (is_array($settings)) {
                    // Check if it has sequential numeric keys starting from 0
                    $keys = array_keys($settings);
                    if (isset($keys[0]) && is_int($keys[0]) && $keys[0] === 0) {
                        // Check if values are single characters (strings)
                        $firstValues = array_slice($settings, 0, 5);
                        $allSingleChars = true;
                        foreach ($firstValues as $val) {
                            if (!is_string($val) || strlen($val) > 1) {
                                $allSingleChars = false;
                                break;
                            }
                        }

                        if ($allSingleChars || count($settings) > 50) {
                            $isMalformed = true;
                            $this->warn("Course ID {$course->id} ({$course->name}): Settings is malformed (character array)");

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

                            ksort($numericParts); // Sort by numeric key to maintain order

                            // Reconstruct the original JSON string from numeric keys
                            $reconstructed = implode('', $numericParts);

                            $this->line("  Total keys: " . count($settings));
                            $this->line("  Numeric keys: " . count($numericParts));
                            $this->line("  Non-numeric keys: " . implode(', ', array_keys($nonNumericParts)));
                            $this->line("  Reconstructed length: " . strlen($reconstructed));
                            $this->line("  Reconstructed (first 100 chars): " . substr($reconstructed, 0, 100));

                            // Validate that reconstructed string is valid JSON
                            $parsedSettings = json_decode($reconstructed, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $this->info("  ✓ Reconstructed valid JSON from numeric keys");

                                // Merge with non-numeric keys (these were added by spread operator bug)
                                foreach ($nonNumericParts as $key => $value) {
                                    $parsedSettings[$key] = $value;
                                    $this->line("  + Added key: {$key}");
                                }

                                if (!$isDryRun) {
                                    // Save as ARRAY - Laravel's 'json' cast will convert to JSON string automatically
                                    $course->settings = $parsedSettings;
                                    $course->save();
                                    $this->info("  ✓ Saved fixed settings as array");

                                    // Verify
                                    $course->refresh();
                                    $this->line("  ✓ Verification - Type after save: " . gettype($course->settings));
                                }
                                $fixedCount++;
                            } else {
                                $this->error("  ✗ Reconstructed string is not valid JSON: " . json_last_error_msg());
                                $this->line("  Last 100 chars: " . substr($reconstructed, -100));
                                $errorCount++;
                            }
                        }
                    }
                }
                // Case 2: settings is a string with JSON that decodes to character array
                elseif (is_string($settings)) {
                    $decoded = json_decode($settings, true);

                    // Check if decoded settings has numeric keys (sign of malformation)
                    if (is_array($decoded) && isset($decoded[0]) && is_string($decoded[0])) {
                        $isMalformed = true;
                        $this->warn("Course ID {$course->id} ({$course->name}): Settings is malformed (string with character array JSON)");

                        // Try to reconstruct the original JSON string from the character array
                        $reconstructed = implode('', $decoded);

                        $this->line("  Original (first 100 chars): " . substr($settings, 0, 100));
                        $this->line("  Reconstructed (first 100 chars): " . substr($reconstructed, 0, 100));

                        // Validate that reconstructed string is valid JSON
                        $parsedSettings = json_decode($reconstructed, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $this->info("  ✓ Reconstructed valid JSON");

                            if (!$isDryRun) {
                                // Save as ARRAY - Laravel's 'json' cast will convert to JSON string automatically
                                $course->settings = $parsedSettings;
                                $course->save();
                                $this->info("  ✓ Saved fixed settings as array");

                                // Verify
                                $course->refresh();
                                $this->line("  ✓ Verification - Type after save: " . gettype($course->settings));
                            }
                            $fixedCount++;
                        } else {
                            $this->error("  ✗ Reconstructed string is not valid JSON: " . json_last_error_msg());
                            $errorCount++;
                        }
                    }
                }

                if (!$isMalformed) {
                    $skippedCount++;
                }

            } catch (\Exception $e) {
                $this->error("Error processing course ID {$course->id}: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Fixed: {$fixedCount}");
        $this->info("  Skipped (already correct): {$skippedCount}");
        $this->info("  Errors: {$errorCount}");

        if ($isDryRun && $fixedCount > 0) {
            $this->newLine();
            $this->warn("DRY RUN MODE - Run without --dry-run to apply changes");
        }

        return Command::SUCCESS;
    }
}
