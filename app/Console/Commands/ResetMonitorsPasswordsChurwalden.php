<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ResetMonitorsPasswordsChurwalden extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitors:reset-passwords-churwalden {--password=Boukii2025!}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset passwords for all monitors of SSS Churwalden school';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schoolId = 15; // SSS Churwalden
        $defaultPassword = $this->option('password');

        $this->info("Resetting passwords for all monitors of SSS Churwalden (ID: {$schoolId})");
        $this->info("Default password: {$defaultPassword}");
        $this->newLine();

        // Get all monitors from Churwalden school
        $monitors = Monitor::whereHas('monitorsSchools', function ($query) use ($schoolId) {
            $query->where('school_id', $schoolId);
        })->with('user')->get();

        if ($monitors->isEmpty()) {
            $this->error('No monitors found for SSS Churwalden school.');
            return 1;
        }

        $this->info("Found {$monitors->count()} monitors.");
        $this->newLine();

        $updated = 0;
        $errors = 0;
        $results = [];

        foreach ($monitors as $monitor) {
            if (!$monitor->user) {
                $this->warn("Monitor {$monitor->first_name} {$monitor->last_name} (ID: {$monitor->id}) does not have a user account. Skipping...");
                $errors++;
                continue;
            }

            try {
                // Update password
                $monitor->user->password = Hash::make($defaultPassword);
                $monitor->user->save();

                $email = $monitor->user->email ?? $monitor->email ?? 'No email';

                $results[] = [
                    'name' => "{$monitor->first_name} {$monitor->last_name}",
                    'email' => $email,
                    'user_id' => $monitor->user->id,
                ];

                $this->info("✓ Updated: {$monitor->first_name} {$monitor->last_name} ({$email})");
                $updated++;
            } catch (\Exception $e) {
                $this->error("✗ Error updating {$monitor->first_name} {$monitor->last_name}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Total monitors found: {$monitors->count()}");
        $this->info("Successfully updated: {$updated}");
        $this->error("Errors: {$errors}");
        $this->newLine();

        // Display table with results
        if (!empty($results)) {
            $this->table(
                ['Name', 'Email', 'User ID'],
                array_map(function ($result) {
                    return [
                        $result['name'],
                        $result['email'],
                        $result['user_id'],
                    ];
                }, $results)
            );
        }

        $this->newLine();
        $this->info("Default password for all monitors: {$defaultPassword}");
        $this->warn("⚠ Please send this password to all monitors and ask them to change it on first login.");

        return 0;
    }
}
