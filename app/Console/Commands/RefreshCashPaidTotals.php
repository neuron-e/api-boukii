<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class RefreshCashPaidTotals extends Command
{
    protected $signature = 'bookings:refresh-cash-paid-totals {school_id?} {--methods=1,4} {--chunk=200} {--dry-run}';
    protected $description = 'Recalculate paid_total/pending_amount for offline bookings and repair zero-price cases';

    public function handle(): int
    {
        $schoolId = $this->argument('school_id');
        $methods = array_filter(array_map('trim', explode(',', $this->option('methods'))));
        $chunk = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');

        $query = Booking::with(['payments'])
            ->whereIn('payment_method_id', $methods)
            ->where('paid', true)
            ->where('price_total', '<=', 0);

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $total = $query->count();
        $this->info("Found {$total} bookings to examine (methods: " . implode(',', $methods) . ")");

        if ($total === 0) {
            return 0;
        }

        $processed = 0;

        $query->chunkById($chunk, function ($bookings) use (&$processed, $dryRun) {
            foreach ($bookings as $booking) {
                $basketTotal = $this->inferBasketTotal($booking);
                if ($basketTotal <= 0) {
                    $this->line("Skip booking {$booking->id}: cannot infer price from basket");
                    $processed++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("DRY: Booking {$booking->id} would be updated to price {$basketTotal}");
                    $processed++;
                    continue;
                }

                $booking->price_total = $basketTotal;

                $paidPayment = $booking->payments->firstWhere('status', 'paid')
                    ?? $booking->payments()->where('status', 'paid')->first();

                if ($paidPayment && (float) $paidPayment->amount <= 0) {
                    $paidPayment->amount = $basketTotal;
                    $paidPayment->save();
                    $this->line("Adjusted payment {$paidPayment->id} for booking {$booking->id} to {$basketTotal}");
                }

                $booking->refreshPaymentTotalsFromPayments();

                $this->line("Updated booking {$booking->id}");
                $processed++;
            }
        });

        $this->info("Processed {$processed} bookings" . ($dryRun ? ' (dry run)' : ''));
        return 0;
    }

    private function inferBasketTotal(Booking $booking): float
    {
        if (empty($booking->basket)) {
            return 0;
        }

        $decoded = json_decode($booking->basket, true);
        if (!is_array($decoded)) {
            return 0;
        }

        $sum = 0;
        foreach ($decoded as $item) {
            $amount = $item['amount'] ?? 0;
            $sum += (float) $amount;
        }

        return round($sum / 100, 2);
    }
}
