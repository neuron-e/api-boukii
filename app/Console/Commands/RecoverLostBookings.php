<?php

namespace App\Console\Commands;

use App\Http\Controllers\PayrexxHelpers;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\BookingUser;
use App\Models\Payment;
use App\Models\School;
use App\Models\Extra;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Payrexx\Models\Response\Transaction as TransactionResponse;

class RecoverLostBookings extends Command
{
    protected $signature = 'bookings:recover-lost
                              {school_id : The school ID}
                              {--from-date= : Start date}
                              {--to-date= : End date}
                              {--dry-run : Preview}
                              {--booking-id= : Specific booking ID to recover}
                              {--verbose-details : Show detailed transaction info}';

    protected $description = 'Recover soft-deleted bookings paid in Payrexx';

    private $dryRun = false;
    private $verbose = false;
    private $recovered = 0;
    private $failed = 0;
    private $skipped = 0;

    public function handle()
    {
        $schoolId = $this->argument('school_id');
        $fromDate = $this->option('from-date') ?? now()->subDays(7)->format('Y-m-d');
        $toDate = $this->option('to-date') ?? now()->format('Y-m-d');
        $this->dryRun = $this->option('dry-run');
        $this->verbose = $this->option('verbose-details');
        $specificBookingId = $this->option('booking-id');

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info("Looking for deleted bookings for school #{$schoolId}");
        $this->info("Date range: {$fromDate} to {$toDate}");
        if ($specificBookingId) {
            $this->info("Targeting specific booking: #{$specificBookingId}");
        }
        $this->newLine();

        $school = School::find($schoolId);
        if (!$school || !$school->getPayrexxInstance() || !$school->getPayrexxKey()) {
            $this->error("School #{$schoolId} not found or missing Payrexx credentials");
            return 1;
        }

        $query = Booking::onlyTrashed()
            ->where('school_id', $schoolId)
            ->where('payment_method_id', 2)
            ->whereBetween('deleted_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->with(['bookingUsers' => fn($q) => $q->withTrashed()]);

        if ($specificBookingId) {
            $query->where('id', $specificBookingId);
        }

        $deletedBookings = $query->get();

        if ($deletedBookings->isEmpty()) {
            $this->info("No deleted bookings found");
            return 0;
        }

        $this->info("Found {$deletedBookings->count()} deleted booking(s)");
        $bar = $this->output->createProgressBar($deletedBookings->count());
        $bar->start();

        foreach ($deletedBookings as $booking) {
            $this->recoverBooking($booking, $school);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("SUMMARY:");
        $this->info("  Recovered: {$this->recovered}");
        $this->info("  Failed: {$this->failed}");
        $this->info("  Skipped: {$this->skipped}");
        return 0;
    }

    private function recoverBooking($booking, $school)
    {
        $this->newLine();
        $this->line("================================");
        $this->line("Booking #{$booking->id}");

        try {
            // Calculate CORRECT price from booking_users (without duplicates) + extras
            $correctPrice = $this->calculateCorrectPrice($booking);

            $this->line("  DB price_total: CHF {$booking->price_total}");
            $this->line("  Calculated correct price: CHF {$correctPrice}");

            $payment = $booking->payments()->whereNotNull('payrexx_transaction')->first();

            if ($payment) {
                $this->line("  Found existing payment record");
                $txData = json_decode($payment->payrexx_transaction, true);
                $txId = $txData['id'] ?? null;

                if (!$txId) {
                    $this->warn("  Payment record has no transaction ID");
                    $this->skipped++;
                    return;
                }

                $this->line("  Checking Payrexx transaction #{$txId}...");
                $tx = PayrexxHelpers::retrieveTransaction($school->getPayrexxInstance(), $school->getPayrexxKey(), $txId);

                if (!$tx) {
                    $this->warn("  Transaction not found in Payrexx");
                    $this->skipped++;
                    return;
                }

                if ($tx->getStatus() !== TransactionResponse::CONFIRMED) {
                    $this->warn("  Transaction status: " . $tx->getStatus() . " (not confirmed)");
                    $this->skipped++;
                    return;
                }

                $txAmount = $tx->getAmount() / 100;
                $this->info("  CONFIRMED! Payrexx amount: CHF {$txAmount}");

                $diff = abs($txAmount - $correctPrice);
                if ($diff > 1) {
                    $this->warn("  Price difference: CHF {$diff}");
                }

                $this->performRecovery($booking, $txId, null, $correctPrice, $txAmount);

            } else {
                $this->warn("  No payment record found, searching Payrexx...");

                $date = $booking->created_at ?? $booking->deleted_at;
                $searchStart = $date->copy()->subDays(2)->format('Y-m-d');
                $searchEnd = $date->copy()->addDays(2)->format('Y-m-d');

                $this->line("  Searching transactions from {$searchStart} to {$searchEnd}");

                $transactions = PayrexxHelpers::getTransactionsByDateRange(
                    $school,
                    $searchStart,
                    $searchEnd
                );

                if ($this->verbose) {
                    $this->newLine();
                    $this->line("  Available transactions in date range:");
                    foreach ($transactions as $t) {
                        $amount = ($t['amount'] ?? 0) / 100;
                        $refId = $t['referenceId'] ?? 'N/A';
                        $status = $t['status'] ?? 'unknown';
                        $this->line("      - Tx #{$t['id']}: CHF {$amount}, Ref: {$refId}, Status: {$status}");
                    }
                    $this->newLine();
                }

                // Try multiple matching strategies
                $match = $this->findMatchingTransaction($transactions, $booking, $correctPrice);

                if (!$match) {
                    $this->warn("  No matching transaction found");
                    $this->warn("    Booking amount: CHF {$correctPrice}");
                    $this->line("    Available amounts: " . implode(', ', array_map(fn($t) => 'CHF ' . (($t['amount'] ?? 0) / 100), array_slice($transactions, 0, 5))));
                    $this->skipped++;
                    return;
                }

                if ($match['status'] !== TransactionResponse::CONFIRMED) {
                    $this->warn("  Transaction status: {$match['status']} (not confirmed)");
                    $this->skipped++;
                    return;
                }

                $txAmount = ($match['amount'] ?? 0) / 100;
                $this->info("  Found transaction #{$match['id']}! Amount: CHF {$txAmount}");

                $diff = abs($txAmount - $correctPrice);
                if ($diff > 1) {
                    $this->warn("  Price difference: CHF {$diff}");
                    if (!$this->dryRun && !$this->confirm("  Continue with recovery?", true)) {
                        $this->skipped++;
                        return;
                    }
                }

                $this->performRecovery($booking, $match['id'], $match, $correctPrice, $txAmount);
            }
        } catch (\Exception $e) {
            $this->error("  Error: " . $e->getMessage());
            Log::channel('cron')->error("RecoverLostBookings error for booking #{$booking->id}: " . $e->getMessage());
            $this->failed++;
        }
    }

    private function calculateCorrectPrice($booking)
    {
        // Get all booking users
        $bookingUsers = BookingUser::withTrashed()->where('booking_id', $booking->id)->get();

        // Detect and remove duplicates
        $uniqueUsers = collect();
        $duplicates = collect();

        foreach ($bookingUsers as $bu) {
            $key = $bu->client_id . '_' . $bu->price . '_' . $bu->course_id;

            if ($uniqueUsers->has($key)) {
                $duplicates->push($bu);
            } else {
                $uniqueUsers->put($key, $bu);
            }
        }

        if ($duplicates->isNotEmpty()) {
            $this->warn("  Found {$duplicates->count()} duplicate booking_users");
            if ($this->fixDuplicates && !$this->dryRun) {
                foreach ($duplicates as $dup) {
                    $this->line("    Deleting duplicate BookingUser #{$dup->id}");
                    $dup->forceDelete();
                }
            }
        }

        // Calculate sum from unique booking_users
        $usersPrice = $uniqueUsers->sum('price');

        // Get extras from booking_user_extras
        $extrasPrice = 0;
        $bookingUserIds = $uniqueUsers->pluck('id')->toArray();
        if (!empty($bookingUserIds)) {
            $extras = DB::table('booking_user_extras')->whereIn('booking_user_id', $bookingUserIds)->get();
            foreach ($extras as $e) {
                $extra = Extra::find($e->extra_id);
                if ($extra) {
                    $extrasPrice += ($extra->price * $e->quantity);
                }
            }
        }

        if ($this->verbose) {
            $this->line("  Price calculation:");
            $this->line("    Booking users ({$uniqueUsers->count()} unique): CHF {$usersPrice}");
            $this->line("    Extras: CHF {$extrasPrice}");
        }

        return $usersPrice + $extrasPrice;
    }

    private function findMatchingTransaction($transactions, $booking, $correctPrice)
    {
        // Try exact referenceId match first
        $match = collect($transactions)->first(fn($t) => ($t['referenceId'] ?? null) == $booking->id);
        if ($match) {
            $this->line("  Found by exact reference ID");
            return $match;
        }

        // Try partial match (e.g., "Booking #5761")
        $match = collect($transactions)->first(fn($t) =>
            stripos($t['referenceId'] ?? '', "Booking #{$booking->id}") !== false ||
            stripos($t['referenceId'] ?? '', "#{$booking->id}") !== false
        );
        if ($match) {
            $this->line("  Found by partial reference match");
            return $match;
        }

        // Try amount match within 15% tolerance (to handle price differences)
        $match = collect($transactions)->first(function($t) use ($correctPrice) {
            $txAmount = ($t['amount'] ?? 0) / 100;
            $tolerance = max($correctPrice * 0.15, 50); // 15% or CHF 50, whichever is larger
            return abs($txAmount - $correctPrice) <= $tolerance;
        });
        if ($match) {
            $this->line("  Found by amount match (within tolerance)");
            return $match;
        }

        // Try match with HALF of the booking price_total (for duplicated bug cases)
        $halfPrice = $booking->price_total / 2;
        $match = collect($transactions)->first(function($t) use ($halfPrice) {
            $txAmount = ($t['amount'] ?? 0) / 100;
            $tolerance = $halfPrice * 0.10;
            return abs($txAmount - $halfPrice) <= $tolerance;
        });
        if ($match) {
            $this->line("  Found by half price match (duplicate bug detected)");
            return $match;
        }

        return null;
    }

    private function performRecovery($booking, $txId, $txData, $correctPrice, $payrexxAmount)
    {
        if (!$this->dryRun) {
            DB::transaction(function() use ($booking, $txId, $txData, $correctPrice, $payrexxAmount) {
                // Restore booking
                $booking->restore();

                // Restore all unique booking users
                $bookingUsers = BookingUser::onlyTrashed()->where('booking_id', $booking->id)->get();

                // Detect duplicates
                $restored = collect();
                foreach ($bookingUsers as $bu) {
                    $key = $bu->client_id . '_' . $bu->price . '_' . $bu->course_id;

                    if (!$restored->has($key)) {
                        $bu->restore();
                        $restored->put($key, $bu);
                    } else if ($this->fixDuplicates) {
                        // Don't restore, will be deleted
                        $bu->forceDelete();
                    } else {
                        $bu->restore(); // Restore even duplicates if not fixing
                    }
                }

                // Create payment if provided
                if ($txData) {
                    Payment::create([
                        'booking_id' => $booking->id,
                        'amount' => $payrexxAmount, // Use actual Payrexx amount
                        'status' => 'paid',
                        'payment_method_id' => 2,
                        'payrexx_transaction' => json_encode($txData),
                        'paid_at' => $txData['time'] ?? now(),
                    ]);
                }

                // Update booking with CORRECT price
                $updates = [];
                if ($booking->price_total != $correctPrice) {
                    $updates['price_total'] = $correctPrice;
                }

                // Update payment status
                $totalPaid = $booking->payments()->where('status', 'paid')->sum('amount');
                if ($totalPaid >= $correctPrice) {
                    $updates['payed'] = 1;
                    $updates['payment_status'] = 'paid';
                }

                if (!empty($updates)) {
                    $booking->update($updates);
                }

                // Create log entry
                BookingLog::create([
                    'booking_id' => $booking->id,
                    'action' => 'recovered',
                    'description' => 'Recovered after Payrexx confirmation',
                    'metadata' => json_encode([
                        'transaction_id' => $txId,
                        'recovered_by' => 'artisan:bookings:recover-lost',
                        'payment_created' => $txData !== null,
                        'price_corrected_from' => $booking->getOriginal('price_total'),
                        'price_corrected_to' => $correctPrice,
                        'payrexx_amount' => $payrexxAmount,
                        'duplicates_fixed' => $this->fixDuplicates
                    ])
                ]);
            });

            $this->recovered++;
            $this->info("  RECOVERED!");
        } else {
            $this->recovered++;
            $this->line("  Would recover (dry run)");
        }
    }
}
