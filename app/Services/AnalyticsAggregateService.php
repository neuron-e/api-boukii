<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsAggregateService
{
    private const EXCLUDED_COURSES = [
        260, 243,
        277, 276, 274, 273, 271, 269, 268, 266, 265
    ];

    public function buildActivityFacts(
        int $schoolId,
        ?int $seasonId,
        string $startDate,
        string $endDate
    ): void {
        $startDateTime = $startDate ? $startDate . ' 00:00:00' : $startDate;
        $endDateTime = $endDate ? $endDate . ' 23:59:59' : $endDate;

        $bookingIdsByCreated = DB::table('bookings as b')
            ->where('b.school_id', $schoolId)
            ->whereNull('b.deleted_at')
            ->whereBetween('b.created_at', [$startDateTime, $endDateTime])
            ->select('b.id');

        $bookingIdsByActivity = DB::table('booking_users as bu')
            ->join('bookings as b', 'bu.booking_id', '=', 'b.id')
            ->where('b.school_id', $schoolId)
            ->whereNull('b.deleted_at')
            ->whereBetween('bu.date', [$startDate, $endDate])
            ->select('b.id');

        $bookingIdsQuery = DB::query()
            ->fromSub($bookingIdsByCreated->union($bookingIdsByActivity), 'booking_ids')
            ->select('id')
            ->distinct();

        $bookingIdsQuery->orderBy('id')
            ->chunk(200, function ($rows) use ($schoolId, $seasonId) {
                $bookingIds = collect($rows)->pluck('id')->filter()->values();
                if ($bookingIds->isEmpty()) {
                    return;
                }

                $bookings = Booking::with([
                    'bookingUsers' => function ($q) {
                        $q->select(
                            'id',
                            'booking_id',
                            'course_id',
                            'course_date_id',
                            'client_id',
                            'group_id',
                            'status',
                            'date',
                            'hour_start',
                            'hour_end',
                            'monitor_id'
                        )
                            ->whereNull('deleted_at');
                    },
                    'bookingUsers.course:id,name,course_type,is_flexible,sport_id,price,price_range,settings,discounts',
                    'bookingUsers.course.sport:id,name',
                    'bookingUsers.courseDate:id,interval_id,course_interval_id',
                    'bookingUsers.bookingUserExtras.courseExtra:id,price',
                    'payments:id,booking_id,status,amount,notes,payrexx_reference',
                    'vouchersLogs:id,booking_id,voucher_id,amount',
                    'vouchersLogs.voucher:id,code,quantity,remaining_balance,payed',
                ])
                    ->whereIn('id', $bookingIds)
                    ->whereNull('deleted_at')
                    ->get();

                $rowsToUpsert = [];

                foreach ($bookings as $booking) {
                    $bookingUsers = $booking->bookingUsers
                        ->filter(function (BookingUser $bookingUser) {
                            return !in_array((int) $bookingUser->course_id, self::EXCLUDED_COURSES, true)
                                && $bookingUser->course !== null;
                        });

                    if ($bookingUsers->isEmpty()) {
                        continue;
                    }

                    $groupedActivities = $booking->buildGroupedActivitiesFromBookingUsers($bookingUsers);
                    if (empty($groupedActivities)) {
                        continue;
                    }

                    $activities = [];
                    $baseTotal = 0.0;

                    foreach ($groupedActivities as $activity) {
                        $course = $activity['course'] ?? null;
                        if (!$course || in_array((int) $course->id, self::EXCLUDED_COURSES, true)) {
                            continue;
                        }

                        $activityTotal = (float) ($activity['total'] ?? 0);
                        $activityDates = array_values(array_unique(array_filter(array_map(
                            fn ($date) => $date['date'] ?? null,
                            $activity['dates'] ?? []
                        ))));

                        $activities[] = [
                            'group_id' => $activity['group_id'] ?? null,
                            'course_id' => $course->id,
                            'course_type' => $course->course_type ?? null,
                            'sport_id' => $course->sport_id ?? null,
                            'total' => $activityTotal,
                            'dates' => $activityDates,
                        ];

                        $baseTotal += $activityTotal;
                    }

                    if (empty($activities)) {
                        continue;
                    }

                    $bookingDiscount = $this->getBookingLevelDiscountTotal($booking);
                    if ($bookingDiscount > $baseTotal) {
                        $bookingDiscount = $baseTotal;
                    }

                    $remainingDiscount = $bookingDiscount;
                    $activityCount = count($activities);
                    $adjustedTotal = 0.0;

                    foreach ($activities as $index => $activity) {
                        $allocated = 0.0;
                        if ($baseTotal > 0 && $bookingDiscount > 0) {
                            $allocated = ($activity['total'] / $baseTotal) * $bookingDiscount;
                            if ($index < $activityCount - 1) {
                                $allocated = round($allocated, 2);
                                $remainingDiscount -= $allocated;
                            } else {
                                $allocated = round($remainingDiscount, 2);
                            }
                        }

                        $activities[$index]['adjusted_total'] = max(0.0, $activity['total'] - $allocated);
                        $adjustedTotal += $activities[$index]['adjusted_total'];
                    }


                    $paidTotal = (float) $booking->payments->where('status', 'paid')->sum('amount');
                    $voucherInfo = $booking->getPriceCalculator()->analyzeVouchersForBalance($booking);
                    $voucherTotal = (float) ($voucherInfo['total_used'] ?? 0);
                    $receivedTotal = max(0.0, $paidTotal + $voucherTotal);

                    $nonActivityConcepts = (float) ($booking->price_cancellation_insurance ?? 0)
                        + (float) ($booking->price_boukii_care ?? 0)
                        + (float) ($booking->price_tva ?? 0);

                    $activityShare = ($adjustedTotal + max(0.0, $nonActivityConcepts)) > 0
                        ? $adjustedTotal / ($adjustedTotal + max(0.0, $nonActivityConcepts))
                        : 0.0;

                    $receivedTotal *= $activityShare;
                    $primaryPaymentMethod = $this->determinePrimaryPaymentMethod($booking);

                    foreach ($activities as $activity) {
                        $activityDates = $activity['dates'] ?? [];
                        $dates = $activityDates ?: [null];
                        $dateCount = count($dates);
                        $participants = $this->countParticipantsForGroup(
                            $bookingUsers->where('status', '!=', 2),
                            $activity['group_id']
                        );
                        $participantsShare = $dateCount > 0 ? ($participants / $dateCount) : $participants;
                        $expectedTotal = $activity['adjusted_total'];

                        foreach ($dates as $date) {
                            $expectedAmount = $dateCount > 0 ? $expectedTotal / $dateCount : $expectedTotal;
                            $receivedAmount = $adjustedTotal > 0
                                ? $receivedTotal * ($expectedAmount / $adjustedTotal)
                                : 0.0;

                            $rowsToUpsert[] = [
                                'school_id' => $schoolId,
                                'season_id' => $seasonId,
                                'booking_id' => $booking->id,
                                'client_id' => $booking->client_main_id,
                                'group_id' => $activity['group_id'],
                                'course_id' => $activity['course_id'],
                                'course_type' => $activity['course_type'],
                                'sport_id' => $activity['sport_id'],
                                'activity_date' => $date,
                                'booking_created_at' => optional($booking->created_at)->format('Y-m-d'),
                                'source' => $booking->source ?? 'unknown',
                                'payment_method' => $primaryPaymentMethod,
                                'is_cancelled' => (bool) ($booking->status == 2),
                                'is_test' => false,
                                'participants' => round($participantsShare, 2),
                                'expected_amount' => $booking->status == 2 ? 0 : round($expectedAmount, 2),
                                'received_amount' => $booking->status == 2 ? 0 : round($receivedAmount, 2),
                                'pending_amount' => $booking->status == 2 ? 0 : round(max(0.0, $expectedAmount - $receivedAmount), 2),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    if (count($rowsToUpsert) >= 1000) {
                        $this->upsertFacts($rowsToUpsert);
                        $rowsToUpsert = [];
                    }
                }

                if (!empty($rowsToUpsert)) {
                    $this->upsertFacts($rowsToUpsert);
                }
            });
    }

    public function aggregateForSchoolSeason(
        int $schoolId,
        ?int $seasonId,
        string $dateFilter = 'activity'
    ): void {
        $dateColumn = $dateFilter === 'activity' ? 'activity_date' : 'booking_created_at';

        DB::transaction(function () use ($schoolId, $seasonId, $dateColumn) {
            DB::table('analytics_course_stats')
                ->where('school_id', $schoolId)
                ->where('season_id', $seasonId)
                ->delete();

            $courseRows = DB::table('analytics_activity_facts')
                ->selectRaw("
                    school_id,
                    season_id,
                    course_id,
                    course_type,
                    sport_id,
                    DATE_FORMAT($dateColumn, '%Y-%m-01') as month,
                    source,
                    payment_method,
                    SUM(participants) as participants,
                    COUNT(DISTINCT booking_id) as bookings,
                    SUM(expected_amount) as revenue_expected,
                    SUM(received_amount) as revenue_received,
                    SUM(pending_amount) as revenue_pending
                ")
                ->where('school_id', $schoolId)
                ->where('season_id', $seasonId)
                ->groupBy(
                    'school_id',
                    'season_id',
                    'course_id',
                    'course_type',
                    'sport_id',
                    'month',
                    'source',
                    'payment_method'
                )
                ->get();

            $this->insertAggregates('analytics_course_stats', $courseRows);

            DB::table('analytics_kpis_monthly')
                ->where('school_id', $schoolId)
                ->where('season_id', $seasonId)
                ->delete();

            $kpiRows = DB::table('analytics_activity_facts')
                ->selectRaw("
                    school_id,
                    season_id,
                    DATE_FORMAT($dateColumn, '%Y-%m-01') as month,
                    COUNT(DISTINCT booking_id) as total_bookings,
                    COUNT(DISTINCT booking_id) as production_bookings,
                    SUM(CASE WHEN is_cancelled = 1 THEN 1 ELSE 0 END) as cancelled_bookings,
                    SUM(CASE WHEN is_test = 1 THEN 1 ELSE 0 END) as test_bookings,
                    COUNT(DISTINCT client_id) as total_clients,
                    SUM(participants) as total_participants,
                    SUM(expected_amount) as revenue_expected,
                    SUM(received_amount) as revenue_received,
                    SUM(pending_amount) as revenue_pending,
                    0 as overpayment_amount,
                    0 as unpaid_with_debt_amount
                ")
                ->where('school_id', $schoolId)
                ->where('season_id', $seasonId)
                ->groupBy('school_id', 'season_id', 'month')
                ->get();

            $this->insertAggregates('analytics_kpis_monthly', $kpiRows);

            DB::table('analytics_payment_methods')
                ->where('school_id', $schoolId)
                ->where('season_id', $seasonId)
                ->delete();

            $paymentRows = DB::table('analytics_activity_facts')
                ->selectRaw("
                    school_id,
                    season_id,
                    DATE_FORMAT($dateColumn, '%Y-%m-01') as month,
                    payment_method,
                    COUNT(DISTINCT booking_id) as payment_count,
                    SUM(received_amount) as revenue_received
                ")
                ->where('school_id', $schoolId)
                ->where('season_id', $seasonId)
                ->groupBy('school_id', 'season_id', 'month', 'payment_method')
                ->get();

            $this->insertAggregates('analytics_payment_methods', $paymentRows);

            DB::table('analytics_sources')
                ->where('school_id', $schoolId)
                ->where('season_id', $seasonId)
                ->delete();

            $sourceRows = DB::table('analytics_activity_facts')
                ->selectRaw("
                    school_id,
                    season_id,
                    DATE_FORMAT($dateColumn, '%Y-%m-01') as month,
                    source,
                    COUNT(DISTINCT booking_id) as bookings,
                    SUM(expected_amount) as revenue_expected
                ")
                ->where('school_id', $schoolId)
                ->where('season_id', $seasonId)
                ->groupBy('school_id', 'season_id', 'month', 'source')
                ->get();

            $this->insertAggregates('analytics_sources', $sourceRows);
        });
    }

    private function upsertFacts(array $rows): void
    {
        DB::table('analytics_activity_facts')->upsert(
            $rows,
            ['booking_id', 'group_id', 'activity_date'],
            [
                'school_id',
                'season_id',
                'client_id',
                'course_id',
                'course_type',
                'sport_id',
                'booking_created_at',
                'source',
                'payment_method',
                'is_cancelled',
                'is_test',
                'participants',
                'expected_amount',
                'received_amount',
                'pending_amount',
                'updated_at'
            ]
        );
    }

    private function insertAggregates(string $table, Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $payload = $rows->map(function ($row) {
            $data = (array) $row;
            $data['created_at'] = now();
            $data['updated_at'] = now();
            return $data;
        })->all();

        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function getBookingLevelDiscountTotal(Booking $booking): float
    {
        $manualReduction = ($booking->has_reduction && $booking->price_reduction > 0)
            ? (float) $booking->price_reduction
            : 0.0;
        $discountCode = !empty($booking->discount_code_value)
            ? (float) $booking->discount_code_value
            : 0.0;

        return max(0, $manualReduction + $discountCode);
    }

    private function countParticipantsForGroup(Collection $bookingUsers, $groupId): int
    {
        if ($groupId === null) {
            return $bookingUsers->groupBy('client_id')->count();
        }

        return $bookingUsers
            ->where('group_id', $groupId)
            ->groupBy('client_id')
            ->count();
    }

    private function determinePrimaryPaymentMethod(Booking $booking): string
    {
        $paid = $booking->payments->where('status', 'paid');
        if ($paid->isEmpty()) {
            return 'no_payment';
        }

        $totals = [];
        foreach ($paid as $payment) {
            $method = $this->determinePaymentMethodImproved($payment, $booking->payment_method_id);
            $totals[$method] = ($totals[$method] ?? 0) + (float) $payment->amount;
        }

        arsort($totals);
        $method = array_key_first($totals) ?? 'other';
        if (count($totals) > 1) {
            return 'mixed';
        }

        return $method;
    }

    private function determinePaymentMethodImproved($payment, ?int $bookingPaymentMethodId): string
    {
        $notes = strtolower($payment->notes ?? '');

        if ($payment->payrexx_reference) {
            if ($bookingPaymentMethodId == Booking::ID_BOUKIIPAY) {
                return 'boukii_direct';
            }
            if ($bookingPaymentMethodId == Booking::ID_INVOICE) {
                return 'invoice';
            }
            return 'online_link';
        }

        if (str_contains($notes, 'boukii pay') || str_contains($notes, 'boukiipay')) {
            return 'boukii_direct';
        }

        if (
            str_contains($notes, 'cash') ||
            str_contains($notes, 'efectivo') ||
            str_contains($notes, 'kasse') ||
            str_contains($notes, 'caja') ||
            str_contains($notes, 'bar') ||
            str_contains($notes, 'bargeld')
        ) {
            return 'cash';
        }

        if (
            str_contains($notes, 'card') ||
            str_contains($notes, 'tarjeta') ||
            str_contains($notes, 'tarjeta de credito') ||
            str_contains($notes, 'tarjeta de crédito') ||
            str_contains($notes, 'kreditkarte') ||
            str_contains($notes, 'kartenzahlung') ||
            str_contains($notes, 'girocard') ||
            str_contains($notes, 'ec') ||
            str_contains($notes, 'tpv') ||
            str_contains($notes, 'stripe') ||
            str_contains($notes, 'sumup') ||
            str_contains($notes, 'dataphone') ||
            str_contains($notes, 'terminal') ||
            str_contains($notes, 'visa') ||
            str_contains($notes, 'mastercard') ||
            str_contains($notes, 'maestro') ||
            str_contains($notes, 'amex') ||
            str_contains($notes, 'credit') ||
            str_contains($notes, 'debit') ||
            str_contains($notes, 'debito') ||
            str_contains($notes, 'karte') ||
            str_contains($notes, 'kredit')
        ) {
            return 'card_offline';
        }

        if (str_contains($notes, 'transfer') || str_contains($notes, 'transferencia')) {
            return 'transfer';
        }

        switch ($bookingPaymentMethodId) {
            case Booking::ID_CASH:
                return 'cash';
            case Booking::ID_BOUKIIPAY:
                return 'boukii_offline';
            case Booking::ID_ONLINE:
                return 'online_link';
            case Booking::ID_OTHER:
                return 'card_offline';
            case Booking::ID_INVOICE:
                return 'invoice';
            default:
                return 'other';
        }
    }
}
