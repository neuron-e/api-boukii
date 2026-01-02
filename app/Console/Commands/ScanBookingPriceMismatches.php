<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class ScanBookingPriceMismatches extends Command
{
    protected $signature = 'bookings:scan-price-mismatch
                            {--school-id= : Only scan bookings for this school}
                            {--booking-id= : Scan a single booking id}
                            {--min-diff=0.01 : Minimum absolute difference to report}
                            {--tolerance=0.01 : Tolerance for payment match checks}
                            {--include-cancelled : Include cancelled bookings}
                            {--similar : Only report cases where payments match calculated total}
                            {--free-mismatch : Only report bookings where calculated total is ~0 but pending is > 0}
                            {--fix : Update stored totals and paid flag for reported bookings}
                            {--dry-run : Show what would be fixed without saving}
                            {--chunk=200 : Chunk size for batch processing}
                            {--limit=0 : Limit number of results (0 = no limit)}
                            {--json : Output JSON instead of lines}';

    protected $description = 'Report bookings where stored totals differ from calculated totals';

    public function handle(): int
    {
        $schoolId = $this->option('school-id');
        $bookingId = $this->option('booking-id');
        $minDiff = (float) $this->option('min-diff');
        $tolerance = (float) $this->option('tolerance');
        $includeCancelled = (bool) $this->option('include-cancelled');
        $similar = (bool) $this->option('similar');
        $freeMismatch = (bool) $this->option('free-mismatch');
        $fix = (bool) $this->option('fix');
        $dryRun = (bool) $this->option('dry-run');
        $chunk = (int) $this->option('chunk');
        $limit = (int) $this->option('limit');
        $asJson = (bool) $this->option('json');

        $query = Booking::query();

        if ($bookingId) {
            $query->where('id', $bookingId);
        }

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if (!$includeCancelled && !$bookingId) {
            $query->where('status', '!=', 2);
        }

        $reported = 0;
        $scanned = 0;
        $results = [];

        $processBooking = function (Booking $booking) use (
            $minDiff,
            $tolerance,
            $similar,
            $freeMismatch,
            $fix,
            $dryRun,
            $asJson,
            $limit,
            &$reported,
            &$scanned,
            &$results
        ) {
            if ($limit > 0 && $reported >= $limit) {
                return false;
            }

            $booking->loadMissing([
                'bookingUsers.course',
                'bookingUsers.bookingUserExtras.courseExtra',
                'vouchersLogs.voucher',
                'payments',
            ]);

            $scanned++;

            $calculated = $booking->calculateCurrentTotal();
            $calculatedTotal = round((float) ($calculated['total_final'] ?? 0), 2);
            $storedTotal = round((float) ($booking->price_total ?? 0), 2);
            $diff = round($storedTotal - $calculatedTotal, 2);

            if (abs($diff) < $minDiff) {
                return true;
            }

            $balance = $booking->getCurrentBalance();
            $received = round((float) ($balance['received'] ?? 0), 2);
            $currentBalance = round((float) ($balance['current_balance'] ?? 0), 2);
            $pending = round((float) $booking->getPendingAmount(), 2);

            $paymentMatches = $received > 0 && abs($received - $calculatedTotal) <= $tolerance;
            $isFreeMismatch = $calculatedTotal <= $tolerance && $pending > $tolerance;
            if ($similar && !$paymentMatches) {
                return true;
            }
            if ($freeMismatch && !$isFreeMismatch) {
                return true;
            }

            $fixApplied = false;
            if ($fix) {
                $shouldBePaid = $pending <= $tolerance;
                if ($dryRun) {
                    $fixApplied = true;
                } else {
                    $booking->recalculateAndUpdatePrice();
                    $booking->refreshPaymentTotalsFromPayments();
                    $booking->paid = $shouldBePaid;
                    $booking->save();
                    $booking->updateCart();
                    $fixApplied = true;
                }
            }

            $row = [
                'booking_id' => $booking->id,
                'school_id' => $booking->school_id,
                'status' => $booking->status,
                'paid' => (bool) $booking->paid,
                'stored_total' => $storedTotal,
                'calculated_total' => $calculatedTotal,
                'difference' => $diff,
                'received' => $received,
                'current_balance' => $currentBalance,
                'pending' => $pending,
                'free_mismatch' => $isFreeMismatch,
                'payment_matches_calculated' => $paymentMatches,
                'fix_applied' => $fixApplied,
            ];

            if ($asJson) {
                $results[] = $row;
            } else {
                $this->line(
                    "Booking {$row['booking_id']} | school {$row['school_id']} | " .
                    "stored {$row['stored_total']} | calculated {$row['calculated_total']} | " .
                    "diff {$row['difference']} | received {$row['received']} | pending {$row['pending']} | " .
                    "paid " . ($row['paid'] ? '1' : '0') .
                    ($fixApplied ? ($dryRun ? ' | FIX (dry-run)' : ' | FIXED') : '')
                );
            }

            $reported++;
            return true;
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
                    $continue = $processBooking($booking);
                    if ($continue === false) {
                        return false;
                    }
                }
            });
        }

        if ($asJson) {
            $this->line(json_encode([
                'scanned' => $scanned,
                'reported' => $reported,
                'results' => $results,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info("Scanned {$scanned} bookings. Reported {$reported}.");
        }

        return Command::SUCCESS;
    }
}
