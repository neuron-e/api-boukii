<?php

namespace App\Console\Commands;

use App\Http\Services\BookingPriceSnapshotService;
use App\Models\Booking;
use Illuminate\Console\Command;

class BackfillBookingPriceSnapshots extends Command
{
    protected $signature = 'booking:price-snapshot-backfill
        {--from= : Start date (YYYY-MM-DD) based on booking created_at}
        {--to= : End date (YYYY-MM-DD) based on booking created_at}
        {--school_id= : Filter by school_id}
        {--limit= : Max number of bookings to process}
        {--chunk=200 : Chunk size}
        {--mode=auto : auto|basket|reprice}
        {--dry-run : Only report, do not write}';

    protected $description = 'Create pricing snapshots for bookings missing them.';

    public function __construct(
        private BookingPriceSnapshotService $snapshotService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Booking::query()->whereDoesntHave('priceSnapshots');

        if ($from = $this->option('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($schoolId = $this->option('school_id')) {
            $query->where('school_id', $schoolId);
        }

        $limit = $this->option('limit');
        if ($limit) {
            $query->limit((int)$limit);
        }

        $mode = $this->option('mode') ?: 'auto';
        $chunk = (int)($this->option('chunk') ?: 200);
        $dryRun = (bool)$this->option('dry-run');

        $total = 0;
        $created = 0;
        $skipped = 0;

        $this->info('Starting backfill of booking price snapshots...');

        $query->orderBy('id')->chunkById($chunk, function ($bookings) use (
            &$total,
            &$created,
            &$skipped,
            $mode,
            $dryRun
        ) {
            foreach ($bookings as $booking) {
                $total++;

                if ($dryRun) {
                    $skipped++;
                    $this->line("DRY-RUN: booking {$booking->id} would be processed.");
                    continue;
                }

                if ($mode === 'basket') {
                    $this->snapshotService->createSnapshotFromBasket($booking, null, 'basket_import', 'Backfill from basket');
                    $created++;
                    continue;
                }

                if ($mode === 'reprice') {
                    $this->snapshotService->createSnapshotFromCalculator($booking, null, 'reprice', 'Backfill from calculator');
                    $created++;
                    continue;
                }

                if ($booking->basket) {
                    $this->snapshotService->createSnapshotFromBasket($booking, null, 'basket_import', 'Backfill from basket');
                    $created++;
                } else {
                    $this->snapshotService->createSnapshotFromCalculator($booking, null, 'reprice', 'Backfill from calculator');
                    $created++;
                }
            }
        });

        $this->info("Backfill complete. Total: {$total}, created: {$created}, skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
