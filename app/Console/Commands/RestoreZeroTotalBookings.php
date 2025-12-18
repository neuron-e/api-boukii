<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\BookingUser;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreZeroTotalBookings extends Command
{
    protected $signature = 'bookings:restore-zero-total
                            {--school-id= : Filter bookings by school}
                            {--course-id= : Filter by course_id inside the booking}
                            {--source=web : Booking source token (set to "any" to skip)}
                            {--from-date= : Earliest deleted_at date (YYYY-MM-DD)}
                            {--to-date= : Latest deleted_at date (YYYY-MM-DD)}
                            {--limit= : Max number of bookings to touch}
                            {--dry-run : Print candidates without modifying data}
                            {--details : Show extra context per booking}';

    protected $description = 'Restore zero-total slug bookings that remained soft deleted before payment.';

    public function handle(): int
    {
        $schoolId = $this->option('school-id');
        $courseId = $this->option('course-id');
        $source = $this->option('source');
        $fromDate = $this->option('from-date');
        $toDate = $this->option('to-date');
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');
        $details = $this->option('details');

        $query = Booking::onlyTrashed()
            ->where('status', 1)
            ->where(function ($builder) {
                $builder->where('price_total', '<=', 0)
                    ->orWhereRaw("(JSON_EXTRACT(basket, '$.price_total') IS NOT NULL AND COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(basket, '$.price_total')) AS DECIMAL(12,2)), 0) <= 0)");
            });

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if ($source && $source !== 'any') {
            $sources = array_filter(array_map('trim', explode(',', $source)));
            if (!empty($sources)) {
                $query->whereIn('source', $sources);
            }
        }

        if ($courseId) {
            $query->whereHas('bookingUsers', function ($builder) use ($courseId) {
                $builder->where('course_id', $courseId);
            });
        }

        if ($fromDate) {
            $query->where('deleted_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate) {
            $query->where('deleted_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $bookings = $query
            ->with(['bookingUsers' => function ($builder) {
                $builder->withTrashed();
            }])
            ->withCount('vouchersLogs')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No matching zero-total bookings found.');
            return 0;
        }

        $restored = 0;

        foreach ($bookings as $booking) {
            $line = $this->formatBookingLine($booking);
            if ($dryRun) {
                $this->info("[DRY RUN] {$line}");
                continue;
            }

            DB::beginTransaction();
            try {
                $booking->deleted_at = null;
                $booking->paid = true;
                $booking->paid_total = 0;
                $booking->save();

                BookingUser::withTrashed()
                    ->where('booking_id', $booking->id)
                    ->whereNotNull('deleted_at')
                    ->update(['deleted_at' => null]);

                BookingLog::create([
                    'booking_id' => $booking->id,
                    'action' => 'restored_zero_total',
                    'user_id' => null,
                    'description' => 'Restored by bookings:restore-zero-total command'
                ]);

                DB::commit();

                $restored++;
                $this->info("Restored {$line}");
                if ($details) {
                    $this->line("    vouchers: {$booking->vouchers_logs_count}, discount: " . ($booking->discount_code_id ?? 'none'));
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to restore booking #{$booking->id}: {$e->getMessage()}");
            }
        }

        if (!$dryRun) {
            $this->info("Restored {$restored} booking(s).");
        }

        return 0;
    }

    private function formatBookingLine(Booking $booking): string
    {
        $courses = $booking->bookingUsers->pluck('course_id')->unique()->implode(',') ?: 'none';
        $discount = $booking->discount_code_id ? $booking->discount_code_id : 'none';
        $basketPrice = $this->extractBasketPrice($booking->basket);
        $basketSuffix = $basketPrice !== null ? ", basket_price {$basketPrice}" : '';
        return "Booking #{$booking->id} (school {$booking->school_id}, client {$booking->client_main_id}, courses {$courses}, discount {$discount}{$basketSuffix})";
    }

    private function extractBasketPrice(?string $basket): ?float
    {
        if (!$basket) {
            return null;
        }

        $decoded = json_decode($basket, true);
        if (!is_array($decoded)) {
            return null;
        }

        $price = $decoded['price_total'] ?? $decoded['total_price'] ?? null;
        return is_numeric($price) ? (float) $price : null;
    }
}
