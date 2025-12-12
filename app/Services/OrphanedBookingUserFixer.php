<?php

namespace App\Services;

use App\Models\BookingUser;
use App\Models\CourseSubgroup;
use Illuminate\Support\Facades\DB;

class OrphanedBookingUserFixer
{
    /**
     * Align booking_users to active course_subgroup/course_group/course_date chains.
     *
     * @param bool $dryRun
     * @param int|null $schoolId
     * @return array
     */
    public function migrate(bool $dryRun = false, ?int $schoolId = null): array
    {
        $stats = [
            'migrated' => 0,
            'skipped_no_target' => 0,
            'skipped_no_active_booking' => 0,
            'failed' => []
        ];

        $bookingUsers = $this->queryOrphanedBookingUsers($schoolId);

        foreach ($bookingUsers as $bookingUser) {
            if (!$bookingUser->booking || $bookingUser->booking->deleted_at) {
                $stats['skipped_no_active_booking']++;
                continue;
            }

            $targetSubgroup = $this->findTargetSubgroup($bookingUser);

            if (!$targetSubgroup) {
                $stats['skipped_no_target']++;
                $stats['failed'][] = [
                    'booking_user_id' => $bookingUser->id,
                    'booking_id' => $bookingUser->booking_id,
                    'course_id' => $bookingUser->course_id,
                    'course_date_id' => $bookingUser->course_date_id,
                    'course_group_id' => $bookingUser->course_group_id,
                    'course_subgroup_id' => $bookingUser->course_subgroup_id,
                    'date' => $bookingUser->date,
                    'degree_id' => $bookingUser->degree_id,
                ];

                continue;
            }

            if (!$dryRun) {
                DB::transaction(function () use ($bookingUser, $targetSubgroup) {
                    $bookingUser->course_subgroup_id = $targetSubgroup->id;
                    $bookingUser->course_group_id = $targetSubgroup->course_group_id;
                    $bookingUser->course_date_id = $targetSubgroup->course_date_id;
                    $bookingUser->course_id = $targetSubgroup->course_id;
                    $bookingUser->save();
                });
            }

            $stats['migrated']++;
        }

        $stats['skipped_no_booking'] = $stats['skipped_no_active_booking'];

        return $stats;
    }

    /**
     * @param int|null $schoolId
     * @return \Illuminate\Support\Collection
     */
    private function queryOrphanedBookingUsers(?int $schoolId)
    {
        return BookingUser::query()
            ->select('booking_users.*')
            ->leftJoin('course_subgroups', function ($join) {
                $join->on('booking_users.course_subgroup_id', '=', 'course_subgroups.id');
            })
            ->leftJoin('course_groups', function ($join) {
                $join->on('booking_users.course_group_id', '=', 'course_groups.id');
            })
            ->leftJoin('course_dates', function ($join) {
                $join->on('booking_users.course_date_id', '=', 'course_dates.id');
            })
            ->whereNull('booking_users.deleted_at')
            ->where(function ($query) {
                $query->whereNull('course_subgroups.id')
                    ->orWhereNotNull('course_subgroups.deleted_at')
                    ->orWhereNull('course_groups.id')
                    ->orWhereNotNull('course_groups.deleted_at')
                    ->orWhereNull('course_dates.id')
                    ->orWhereNotNull('course_dates.deleted_at');
            })
            ->when($schoolId, function ($query) use ($schoolId) {
                $query->where('booking_users.school_id', $schoolId);
            })
            ->whereHas('booking', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->with('booking')
            ->orderBy('booking_users.id')
            ->get();
    }

    private function findTargetSubgroup(BookingUser $bookingUser): ?CourseSubgroup
    {
        if (!$bookingUser->course_id) {
            return null;
        }

        $baseQuery = CourseSubgroup::query()
            ->whereNull('deleted_at')
            ->where('course_id', $bookingUser->course_id)
            ->when($bookingUser->degree_id, function ($query) use ($bookingUser) {
                $query->where('degree_id', $bookingUser->degree_id);
            })
            ->whereHas('courseGroup', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->whereHas('courseDate', function ($query) {
                $query->whereNull('deleted_at');
            });

        $target = null;

        if ($bookingUser->course_date_id) {
            $target = (clone $baseQuery)
                ->where('course_date_id', $bookingUser->course_date_id)
                ->orderByDesc('id')
                ->first();
        }

        if (!$target && $bookingUser->date) {
            $target = (clone $baseQuery)
                ->whereHas('courseDate', function ($query) use ($bookingUser) {
                    $query->whereDate('date', $bookingUser->date);
                })
                ->orderByDesc('id')
                ->first();
        }

        if (!$target) {
            $target = (clone $baseQuery)->orderByDesc('id')->first();
        }

        return $target;
    }
}
