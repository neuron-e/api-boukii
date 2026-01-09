<?php

namespace App\Console\Commands;

use App\Http\Services\BookingPriceCalculatorService;
use App\Models\Booking;
use App\Models\BookingLog;
use Illuminate\Console\Command;

class FixBookingPriceTotals extends Command
{
    protected $signature = 'bookings:fix-price-totals
                            {--booking-ids= : Comma-separated booking IDs to fix}
                            {--school-id= : Filter by school ID when no booking IDs provided}
                            {--start-date= : Start date (Y-m-d) when using school filter}
                            {--end-date= : End date (Y-m-d) when using school filter}
                            {--dry-run : Show changes without updating}';

    protected $description = 'Recalculate and fix booking price_total values using the pricing calculator.';

    public function handle(BookingPriceCalculatorService $calculator): int
    {
        $idsRaw = (string) $this->option('booking-ids');
        $ids = array_values(array_filter(array_map('intval', array_filter(array_map('trim', explode(',', $idsRaw))))));
        $schoolId = $this->option('school-id');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $dryRun = (bool) $this->option('dry-run');

        if (empty($ids) && !$schoolId) {
            $this->error('Provide --booking-ids or --school-id (with optional date range).');
            return Command::INVALID;
        }

        $query = Booking::query();
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->where('school_id', $schoolId);
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
        }

        $total = 0;
        $updated = 0;
        $tolerance = 0.5;

        $query->orderBy('id')->chunkById(200, function ($bookings) use (
            $calculator,
            $dryRun,
            $tolerance,
            &$total,
            &$updated
        ) {
            foreach ($bookings as $booking) {
                $total++;
                $booking->loadMissing([
                    'bookingUsers.course',
                    'bookingUsers.courseDate',
                    'bookingUsers.bookingUserExtras.courseExtra',
                    'vouchersLogs.voucher',
                    'payments',
                ]);

                $analysis = $calculator->analyzeFinancialReality($booking);
                $calculatedTotal = (float) ($analysis['calculated_total'] ?? 0);
                $storedTotal = (float) ($booking->price_total ?? 0);
                $diff = $calculatedTotal - $storedTotal;

                if (abs($diff) <= $tolerance) {
                    continue;
                }

                $this->line(sprintf(
                    'Booking %d: stored=%0.2f calculated=%0.2f diff=%0.2f',
                    $booking->id,
                    $storedTotal,
                    $calculatedTotal,
                    $diff
                ));

                if ($dryRun) {
                    continue;
                }

                $booking->price_total = round($calculatedTotal, 2);

                $paidTotal = (float) $booking->payments
                    ->whereIn('status', ['paid', 'completed'])
                    ->sum('amount');
                $booking->paid_total = round($paidTotal, 2);

                $pendingAmount = max(0, $booking->getPendingAmount());
                $booking->paid = $pendingAmount <= 0.01;

                if (is_string($booking->basket)) {
                    $basket = json_decode($booking->basket, true);
                    if (is_array($basket)) {
                        $basket['price_total'] = $booking->price_total;
                        $basket['paid_total'] = $booking->paid_total;
                        $basket['pending_amount'] = $pendingAmount;
                        $booking->basket = json_encode($basket);
                    }
                }

                $booking->save();

                BookingLog::create([
                    'booking_id' => $booking->id,
                    'action' => 'price_total_corrected',
                    'description' => sprintf(
                        'price_total %0.2f -> %0.2f',
                        $storedTotal,
                        $booking->price_total
                    ),
                ]);

                $updated++;
            }
        });

        $this->info("Processed {$total} bookings. Updated {$updated}.");
        return Command::SUCCESS;
    }
}
