<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingConfirmationService;
use App\Services\CriticalErrorNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendBookingConfirmations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-confirmations 
        {--status= : Comma separated list of booking status codes to include (default: 1,3)}
        {--force : Resend even if a confirmation log already exists}
        {--dry-run : Only report the bookings that would be processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send confirmation emails to existing bookings that meet the selected criteria.';

    public function __construct(
        private readonly BookingConfirmationService $confirmationService,
        private readonly CriticalErrorNotifier $errorNotifier
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $statuses = $this->resolveStatuses();
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Scanning bookings (statuses: %s, force: %s, dry-run: %s)',
            implode(',', $statuses),
            $force ? 'yes' : 'no',
            $dryRun ? 'yes' : 'no'
        ));

        $query = Booking::query()
            ->with(['school', 'clientMain'])
            ->whereNull('deleted_at')
            ->whereIn('status', $statuses);

        if (!$force) {
            $query->whereDoesntHave('bookingLogs', function ($q) {
                $q->where('action', 'mail_booking_create_sent');
            });
        }

        $processed = 0;
        $sent = 0;

        $query->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$processed, &$sent, $dryRun) {
                foreach ($bookings as $booking) {
                    $processed++;
                    if ($dryRun) {
                        $this->line(sprintf(
                            '[DRY-RUN] Booking #%d (%s) would receive confirmation',
                            $booking->id,
                            $booking->clientMain->email ?? 'no-email'
                        ));
                        continue;
                    }

                    try {
                        $this->confirmationService->sendConfirmation($booking, (bool) $booking->paid);
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->error(sprintf(
                            'Failed sending confirmation for booking #%d: %s',
                            $booking->id,
                            $e->getMessage()
                        ));
                        Log::error('BOOKING_CONFIRMATION_COMMAND_FAILED', [
                            'booking_id' => $booking->id,
                            'error' => $e->getMessage(),
                        ]);
                        $this->errorNotifier->notify(
                            'Scheduled confirmation failed',
                            [
                                'booking_id' => $booking->id,
                                'client_email' => $booking->clientMain->email ?? null,
                            ],
                            $e
                        );
                    }
                }
            });

        $this->info(sprintf(
            'Processed %d bookings, confirmations sent: %d',
            $processed,
            $dryRun ? 0 : $sent
        ));

        return Command::SUCCESS;
    }

    private function resolveStatuses(): array
    {
        $option = $this->option('status');
        if (empty($option)) {
            return [1, 3];
        }

        return collect(explode(',', $option))
            ->map(fn ($value) => (int) trim($value))
            ->filter(fn ($value) => $value !== 0 || trim($value) === '0')
            ->unique()
            ->values()
            ->all() ?: [1, 3];
    }
}
