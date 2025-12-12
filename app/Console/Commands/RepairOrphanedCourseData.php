<?php

namespace App\Console\Commands;

use App\Services\OrphanedBookingUserFixer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class RepairOrphanedCourseData extends Command
{
    protected $signature = 'course:repair-orphaned-data {--dry-run : Perform a dry run} {--school_id= : Filter by specific school} {--notify-email= : Email to send a summary} {--skip-clean : Skip cleaning orphaned data}';
    protected $description = 'Align booking_users and clean orphaned course data in one go';

    private OrphanedBookingUserFixer $bookingUserFixer;

    public function __construct(OrphanedBookingUserFixer $bookingUserFixer)
    {
        parent::__construct();
        $this->bookingUserFixer = $bookingUserFixer;
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $schoolId = $this->option('school_id');
        $notifyEmail = $this->option('notify-email');
        $skipClean = $this->option('skip-clean');

        $this->info('==============================================');
        $this->info('Repairing orphaned course data');
        $this->info('==============================================');
        if ($schoolId) {
            $this->info('School Filter: ' . $schoolId);
        }
        $this->info('');

        $this->info('Step 1: Realigning booking_users');
        $migrationStats = $this->bookingUserFixer->migrate($dryRun, $schoolId);
        $this->info("Migrated: {$migrationStats['migrated']} booking_users");
        $this->info("Skipped (no active booking): {$migrationStats['skipped_no_booking']}");
        $this->info("Skipped (no target subgroup): {$migrationStats['skipped_no_target']}");
        $this->info('');

        $cleanOutput = '';
        if (!$skipClean) {
            $this->info('Step 2: Cleaning orphaned subgroups/groups/dates');
            Artisan::call('course:clean-all-orphaned', [
                '--dry-run' => $dryRun,
                '--school_id' => $schoolId,
            ]);
            $cleanOutput = trim(Artisan::output());
            if ($cleanOutput) {
                $this->line($cleanOutput);
            }
            $this->info('');
        }

        $this->info('Step 3: Diagnosing remaining orphaned booking_users');
        Artisan::call('course:diagnose-orphaned-booking-users', ['--school_id' => $schoolId]);
        $diagnoseOutput = trim(Artisan::output());
        if ($diagnoseOutput) {
            $this->line($diagnoseOutput);
        }
        $this->info('');

        if ($notifyEmail) {
            $this->sendSummaryEmail($notifyEmail, $migrationStats, $cleanOutput, $diagnoseOutput, $schoolId);
        }

        return 0;
    }

    private function sendSummaryEmail(string $email, array $stats, string $cleanOutput, string $diagnoseOutput, $schoolId): void
    {
        $subject = '[Boukii] Repair orphaned course data report';
        $lines = [
            "School: " . ($schoolId ?? 'All'),
            "Migrated: {$stats['migrated']}",
            "Skipped (no active booking): {$stats['skipped_no_booking']}",
            "Skipped (no target): {$stats['skipped_no_target']}",
            '',
            'Clean command output:',
            $cleanOutput ?: 'N/A',
            '',
            'Diagnosis output:',
            $diagnoseOutput ?: 'N/A',
        ];

        try {
            Mail::raw(implode("\n", $lines), function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });
            $this->info('Summary email sent to ' . $email);
        } catch (\Exception $e) {
            $this->error('Failed to send summary email: ' . $e->getMessage());
        }
    }
}

