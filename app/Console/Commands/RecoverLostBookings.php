<?php

namespace App\Console\Commands;

use App\Http\Controllers\PayrexxHelpers;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\Payment;
use App\Models\School;
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
                              {--dry-run : Preview}';

    protected $description = 'Recover soft-deleted bookings paid in Payrexx';

    private $dryRun = false;
    private $recovered = 0;
    private $failed = 0;
    private $skipped = 0;

    public function handle()
    {
        $schoolId = $this->argument('school_id');
        $fromDate = $this->option('from-date') ?? now()->subDays(7)->format('Y-m-d');
        $toDate = $this->option('to-date') ?? now()->format('Y-m-d');
        $this->dryRun = $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE');
        }

        $this->info("Looking for deleted bookings for school #{$schoolId}");
        $this->info("Date range: {$fromDate} to {$toDate}");
        $this->newLine();

        $school = School::find($schoolId);
        if (!$school || !$school->getPayrexxInstance() || !$school->getPayrexxKey()) {
            $this->error("School #{$schoolId} not found or missing Payrexx credentials");
            return 1;
        }

        $deletedBookings = Booking::onlyTrashed()
            ->where('school_id', $schoolId)
            ->where('payment_method_id', 2)
            ->whereBetween('deleted_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->with(['bookingUsers' => fn($q) => $q->withTrashed()])
            ->get();

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
        $this->info("SUMMARY: Recovered: {$this->recovered} | Failed: {$this->failed} | Skipped: {$this->skipped}");
        return 0;
    }

    private function recoverBooking($booking, $school)
    {
        $this->newLine();
        $this->line("Booking #{$booking->id} - CHF {$booking->price_total}");

        try {
            $payment = $booking->payments()->whereNotNull('payrexx_transaction')->first();

            if ($payment) {
                $txData = json_decode($payment->payrexx_transaction, true);
                $txId = $txData['id'] ?? null;

                if (!$txId) {
                    $this->skipped++;
                    return;
                }

                $tx = PayrexxHelpers::retrieveTransaction($school->getPayrexxInstance(), $school->getPayrexxKey(), $txId);

                if (!$tx || $tx->getStatus() !== TransactionResponse::CONFIRMED) {
                    $this->warn("  Not confirmed");
                    $this->skipped++;
                    return;
                }

                $this->info("  CONFIRMED!");

                if (!$this->dryRun) {
                    DB::transaction(function() use ($booking, $txId) {
                        $booking->restore();
                        foreach ($booking->bookingUsers()->onlyTrashed()->get() as $bu) $bu->restore();

                        if ($booking->payments()->where('status', 'paid')->sum('amount') >= $booking->price_total) {
                            $booking->update(['payed' => 1, 'payment_status' => 'paid']);
                        }

                        BookingLog::create([
                            'booking_id' => $booking->id,
                            'action' => 'recovered',
                            'description' => 'Recovered after Payrexx confirmation',
                            'metadata' => json_encode(['transaction_id' => $txId, 'recovered_by' => 'artisan'])
                        ]);
                    });

                    $this->recovered++;
                    $this->info("  RECOVERED!");
                } else {
                    $this->recovered++;
                }
            } else {
                $this->warn("  No payment, searching...");

                $date = $booking->created_at ?? $booking->deleted_at;
                $transactions = PayrexxHelpers::getTransactionsByDateRange(
                    $school,
                    $date->copy()->subDays(1)->format('Y-m-d'),
                    $date->copy()->addDays(2)->format('Y-m-d')
                );

                $match = collect($transactions)->first(fn($t) => ($t['referenceId'] ?? null) == $booking->id);

                if (!$match || $match['status'] !== TransactionResponse::CONFIRMED) {
                    $this->warn("  Not found");
                    $this->skipped++;
                    return;
                }

                $this->info("  Found #{$match['id']}!");

                if (!$this->dryRun) {
                    DB::transaction(function() use ($booking, $match) {
                        $booking->restore();
                        foreach ($booking->bookingUsers()->onlyTrashed()->get() as $bu) $bu->restore();

                        Payment::create([
                            'booking_id' => $booking->id,
                            'amount' => $match['amount'] / 100,
                            'status' => 'paid',
                            'payment_method_id' => 2,
                            'payrexx_transaction' => json_encode($match),
                            'paid_at' => $match['time'] ?? now(),
                        ]);

                        if ($booking->payments()->where('status', 'paid')->sum('amount') >= $booking->price_total) {
                            $booking->update(['payed' => 1, 'payment_status' => 'paid']);
                        }

                        BookingLog::create([
                            'booking_id' => $booking->id,
                            'action' => 'recovered',
                            'description' => 'Recovered from Payrexx',
                            'metadata' => json_encode(['transaction_id' => $match['id'], 'payment_created' => true])
                        ]);
                    });

                    $this->recovered++;
                    $this->info("  RECOVERED!");
                } else {
                    $this->recovered++;
                }
            }
        } catch (\Exception $e) {
            $this->error("  Error: " . $e->getMessage());
            $this->failed++;
        }
    }
}
