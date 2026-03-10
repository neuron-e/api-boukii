<?php

namespace App\Console\Commands;

use App\Models\RentalEvent;
use App\Models\RentalReservation;
use App\Services\RentalNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Detects rental reservations that have passed their end_date without being returned
 * and marks them as "overdue". Optionally sends overdue notification emails.
 *
 * Run: php artisan rentals:detect-overdue
 */
class DetectOverdueRentals extends Command
{
    protected $signature = 'rentals:detect-overdue
                            {--notify : Send overdue notification emails to clients}
                            {--dry-run : Log what would change without actually writing}';

    protected $description = 'Mark rental reservations as overdue when end_date has passed and they are still active/pending';

    public function __construct(private RentalNotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!Schema::hasTable('rental_reservations')) {
            $this->warn('Table rental_reservations does not exist. Skipping.');
            return self::SUCCESS;
        }

        $today = Carbon::today()->toDateString();
        $isDryRun = $this->option('dry-run');
        $shouldNotify = $this->option('notify');

        // Find reservations that are past their end_date and still in an active/pending state
        $overdueStatuses = ['pending', 'active', 'assigned', 'checked_out', 'partial_return'];
        $query = DB::table('rental_reservations')
            ->whereIn('status', $overdueStatuses)
            ->where('end_date', '<', $today)
            ->whereNull('deleted_at');

        if (Schema::hasColumn('rental_reservations', 'cancelled_at')) {
            $query->whereNull('cancelled_at');
        }

        $overdueReservations = $query->select('id', 'school_id', 'status', 'end_date', 'client_id')->get();

        if ($overdueReservations->isEmpty()) {
            $this->info('No overdue rentals found.');
            return self::SUCCESS;
        }

        $this->info("Found {$overdueReservations->count()} overdue rental(s).");

        foreach ($overdueReservations as $reservation) {
            $this->line("  → Reservation #{$reservation->id} (status={$reservation->status}, end_date={$reservation->end_date})");

            if ($isDryRun) {
                continue;
            }

            RentalReservation::where('id', $reservation->id)->update(['status' => 'overdue']);

            RentalEvent::log($reservation->id, $reservation->school_id, 'overdue_detected', [
                'previous_status' => $reservation->status,
                'end_date'        => $reservation->end_date,
                'detected_at'     => now()->toIso8601String(),
            ]);

            // Optionally send notification email
            if ($shouldNotify) {
                try {
                    $this->notificationService->sendOverdue($reservation->id);
                } catch (\Exception $e) {
                    Log::error('DETECT_OVERDUE_NOTIFY_FAILED', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($isDryRun) {
            $this->warn('Dry-run mode — no changes written.');
        } else {
            $this->info("Marked {$overdueReservations->count()} reservation(s) as overdue.");
        }

        return self::SUCCESS;
    }
}
