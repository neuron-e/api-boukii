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

        if (!$bookingId) {
            $query->where('status', '!=', 2);
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

            $basketInfo = $this->extractBasketTotals($booking->basket);
            $basketTotal = $basketInfo['total'];
            $basketReduction = $basketInfo['reduction'];

            $canonicalTotal = $booking->getBookingUsersCanonicalTotal();
            $calculated = $booking->calculateCurrentTotal();
            $calculatedTotal = (float) ($calculated['total_final'] ?? 0);
            $fallbackTotal = $calculatedTotal;

            $basketMismatch = $basketTotal !== null
                && $calculatedTotal > 0
                && abs($basketTotal - $calculatedTotal) > 0.01;
            $usedCalculatedTotal = false;

            if ($basketMismatch) {
                $newTotal = $calculatedTotal;
                $usedCalculatedTotal = true;
            } else {
                if ($basketTotal !== null) {
                    $newTotal = $basketTotal;
                } elseif ($canonicalTotal > 0) {
                    $newTotal = $canonicalTotal;
                } else {
                    $newTotal = $fallbackTotal;
                    $usedCalculatedTotal = true;
                }
            }

            if (($basketTotal === null || $basketMismatch) && !$usedCalculatedTotal) {
                $bookingLevelDiscount = (float) ($basketReduction ?? $booking->price_reduction ?? 0);
                $discountCodeValue = (float) ($booking->discount_code_value ?? 0);
                $additional = (float) ($booking->price_cancellation_insurance ?? 0)
                    + (float) ($booking->price_tva ?? 0)
                    + (float) ($booking->price_boukii_care ?? 0);
                $newTotal = max(0, $newTotal + $additional - $bookingLevelDiscount - $discountCodeValue);
            }

            $newTotal = round((float) $newTotal, 2);
            if ($newTotal < 0) {
                $newTotal = 0.0;
            }
            $oldTotal = round((float) ($booking->price_total ?? 0), 2);

            $updatedReduction = $basketReduction !== null
                ? round((float) $basketReduction, 2)
                : round((float) ($booking->price_reduction ?? 0), 2);
            $hasReduction = $updatedReduction > 0;

            $balance = $booking->getCurrentBalance();
            $received = (float) ($balance['received'] ?? 0);

            $looksLikeFreeBooking = $calculatedTotal > 0.01
                && (float) ($booking->price_total ?? 0) <= 0.01
                && $received <= 0.01;

            if ($looksLikeFreeBooking) {
                $updatedReduction = round(max($updatedReduction, $calculatedTotal), 2);
                $hasReduction = true;
                $newTotal = 0.0;
            }

            $pending = max(0, $newTotal - ($balance['current_balance'] ?? 0));
            $shouldBePaid = $pending <= 0.01;

            $needsUpdate = abs($newTotal - $oldTotal) > 0.01
                || (bool) $booking->paid !== $shouldBePaid
                || ($booking->price_reduction ?? 0) != $updatedReduction
                || (bool) $booking->has_reduction !== $hasReduction;

            if ($onlyInconsistent && !$needsUpdate) {
                $skipped++;
                return;
            }

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
            $booking->price_reduction = $updatedReduction;
            $booking->has_reduction = $hasReduction;
            $booking->save();

            // Sync paid_total from payments, then adjust paid flag based on full balance (payments + vouchers).
            $booking->refreshPaymentTotalsFromPayments();
            $booking->paid = $shouldBePaid;
            $booking->save();

            // Refresh basket when missing or inconsistent with calculated totals.
            if ($basketTotal === null || $basketMismatch) {
                $booking->updateCart();
            }

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

    private function extractBasketTotals($rawBasket): array
    {
        if (!$rawBasket) {
            return ['total' => null, 'reduction' => null];
        }

        $basket = $rawBasket;
        if (is_string($rawBasket)) {
            $decoded = json_decode($rawBasket, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $basket = $decoded;
            }
        }

        if (!is_array($basket)) {
            return ['total' => null, 'reduction' => null];
        }

        if (array_key_exists('price_total', $basket)) {
            $base = $this->parseNumber($basket['price_base']['price'] ?? null);
            $extras = $this->sumExtras($basket['extras']['extras'] ?? []);
            $reduction = $this->parseNumber($basket['reduction']['price'] ?? null);
            $intervalDiscounts = $this->sumIntervalDiscounts($basket['interval_discounts']['discounts'] ?? []);
            $tva = $this->parseNumber($basket['tva']['price'] ?? null);
            $insurance = $this->parseNumber($basket['cancellation_insurance']['price'] ?? null);
            $boukiiCare = $this->parseNumber($basket['boukii_care']['price'] ?? null);

            $computed = null;
            if ($base !== null) {
                $computed = ($base ?? 0)
                    + ($extras ?? 0)
                    + ($reduction ?? 0)
                    + ($intervalDiscounts ?? 0)
                    + ($tva ?? 0)
                    + ($insurance ?? 0)
                    + ($boukiiCare ?? 0);
            }

            $stored = $this->parseNumber($basket['price_total'] ?? null);
            $total = $computed !== null && abs($computed) > 0.0001 ? $computed : $stored;

            return [
                'total' => $total !== null ? round((float) $total, 2) : null,
                'reduction' => $reduction !== null ? abs((float) $reduction) : null,
            ];
        }

        $totalCents = 0.0;
        $reductionCents = 0.0;

        foreach ($basket as $item) {
            if (!is_array($item)) {
                continue;
            }
            $amount = $this->parseNumber($item['amount'] ?? null);
            if ($amount === null) {
                continue;
            }
            $totalCents += $amount;

            $name = $item['name'] ?? null;
            $label = '';
            if (is_array($name)) {
                $label = (string) ($name[1] ?? $name['1'] ?? '');
            } elseif (is_string($name)) {
                $label = $name;
            }
            if (stripos($label, 'reduction') !== false) {
                $reductionCents += $amount;
            }
        }

        if ($totalCents === 0.0) {
            return ['total' => null, 'reduction' => null];
        }

        $reduction = $reductionCents !== 0.0 ? abs($reductionCents / 100) : null;

        return [
            'total' => round($totalCents / 100, 2),
            'reduction' => $reduction,
        ];
    }

    private function parseNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', $value);
            return is_numeric($normalized) ? (float) $normalized : null;
        }

        return null;
    }

    private function sumExtras(array $extras): float
    {
        $total = 0.0;
        foreach ($extras as $extra) {
            if (!is_array($extra)) {
                continue;
            }
            $price = $this->parseNumber($extra['price'] ?? null) ?? 0.0;
            $quantity = (int) ($extra['quantity'] ?? 1);
            $total += $price * max(1, $quantity);
        }

        return $total;
    }

    private function sumIntervalDiscounts(array $discounts): float
    {
        $total = 0.0;
        foreach ($discounts as $discount) {
            if (!is_array($discount)) {
                continue;
            }
            $price = $this->parseNumber($discount['price'] ?? null);
            if ($price === null) {
                continue;
            }
            $quantity = (int) ($discount['quantity'] ?? 1);
            $total += $price * max(1, $quantity);
        }

        return $total;
    }
}
