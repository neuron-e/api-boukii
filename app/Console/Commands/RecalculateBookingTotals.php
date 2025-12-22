<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class RecalculateBookingTotals extends Command
{
    protected $signature = 'bookings:recalculate-totals
                            {--school_id= : Only process bookings for this school}
                            {--booking_id= : Process a single booking id}
                            {--chunk=200 : Chunk size for batch processing}
                            {--only-inconsistent : Skip bookings that already match calculated totals}
                            {--dry-run : Report changes without saving}';

    protected $description = 'Recalculate booking totals and pending amounts using backend price logic';

    public function handle(): int
    {
        $schoolId = $this->option('school_id');
        $bookingId = $this->option('booking_id');
        $chunk = (int) $this->option('chunk');
        $onlyInconsistent = (bool) $this->option('only-inconsistent');
        $dryRun = (bool) $this->option('dry-run');

        $query = Booking::query();

        if ($bookingId) {
            $query->where('id', $bookingId);
        }

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $processed = 0;
        $updated = 0;
        $skipped = 0;

        $processBooking = function (Booking $booking) use (
            $onlyInconsistent,
            $dryRun,
            &$processed,
            &$updated,
            &$skipped
        ) {
            $booking->loadMissing([
                'bookingUsers.course',
                'bookingUsers.bookingUserExtras.courseExtra',
                'vouchersLogs.voucher',
                'payments'
            ]);

            $processed++;
            $consistency = $booking->checkPriceConsistency();

            if ($onlyInconsistent && ($consistency['is_consistent'] ?? false)) {
                $skipped++;
                return;
            }

            $calculated = $booking->calculateCurrentTotal();
            $newTotal = round((float) ($calculated['total_final'] ?? 0), 2);
            $oldTotal = round((float) ($booking->price_total ?? 0), 2);
            $pending = $booking->getPendingAmount();
            $shouldBePaid = $pending <= 0.01;

            $needsUpdate = abs($newTotal - $oldTotal) > 0.01 || $booking->paid !== $shouldBePaid;

            if ($dryRun) {
                $status = $needsUpdate ? 'UPDATE' : 'OK';
                $this->line("{$status} Booking {$booking->id}: total {$oldTotal} -> {$newTotal}, pending {$pending}");
                if ($needsUpdate) {
                    $updated++;
                }
                return;
            }

            if (!$needsUpdate) {
                $skipped++;
                return;
            }

            $booking->price_total = $newTotal;
            $booking->save();

            // Sync paid_total from payments, then adjust paid flag based on full balance (payments + vouchers).
            $booking->refreshPaymentTotalsFromPayments();
            $booking->paid = $shouldBePaid;
            $booking->save();

            // Refresh basket with accurate totals and pending amount.
            $booking->updateCart();

            $updated++;
        };

        if ($bookingId) {
            $booking = $query->first();
            if (!$booking) {
                $this->error("Booking {$bookingId} not found.");
                return Command::FAILURE;
            }
            $processBooking($booking);
        } else {
            $query->orderBy('id')->chunkById($chunk, function ($bookings) use ($processBooking) {
                foreach ($bookings as $booking) {
                    $processBooking($booking);
                }
            });
        }

        $this->info("Processed {$processed} bookings. Updated {$updated}. Skipped {$skipped}." . ($dryRun ? ' (dry run)' : ''));
        return Command::SUCCESS;
    }
}
