<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Course;
use App\Models\CourseSubgroup;
use App\Models\Payment;
use App\Models\School;
use App\Http\Services\BookingPriceCalculatorService;
use App\Support\IntervalDiscountHelper;
use App\Services\Admin\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends AppBaseController
{
    protected DashboardMetricsService $metricsService;

    public function __construct(DashboardMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    public function systemStats(Request $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        if (!$schoolId) {
            return $this->sendError('school_id is required', [], 400);
        }

        $selectedDate = $request->get('date') ?? now()->toDateString();
        $subgroupMaxColumn = $this->getSubgroupMaxParticipantsColumn();
        $overbookingGroupBy = ['sg.id', 'c.max_participants'];
        if ($subgroupMaxColumn !== 'NULL') {
            $overbookingGroupBy[] = 'sg.max_participants';
        }
        $cacheKey = "dashboard_system_stats_{$schoolId}_{$selectedDate}";
        $payload = Cache::remember($cacheKey, 60, function () use ($schoolId, $selectedDate, $subgroupMaxColumn, $overbookingGroupBy) {
            $overbookings = DB::table('booking_users as bu')
                ->join('course_subgroups as sg', 'sg.id', '=', 'bu.course_subgroup_id')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->join('course_dates as cd', 'cd.id', '=', 'sg.course_date_id')
                ->leftJoin('course_dates as cdbu', 'cdbu.id', '=', 'bu.course_date_id')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->where('bu.school_id', $schoolId)
                ->whereNull('bu.deleted_at')
                ->whereNull('sg.deleted_at')
                ->whereNull('cd.deleted_at')
                ->whereNull('c.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('bu.status', 1)
                ->where('b.status', '<>', 2)
                ->where(function ($query) use ($selectedDate) {
                    $query->whereDate('bu.date', $selectedDate)
                        ->orWhereDate('cdbu.date', $selectedDate)
                        ->orWhereDate('cd.date', $selectedDate)
                        ->orWhereExists(function ($subquery) use ($selectedDate) {
                            $subquery->select(DB::raw(1))
                                ->from('course_subgroup_dates as csd')
                                ->join('course_dates as cdsg', 'cdsg.id', '=', 'csd.course_date_id')
                                ->whereColumn('csd.course_subgroup_id', 'sg.id')
                                ->whereDate('cdsg.date', $selectedDate);
                        });
                })
                ->groupBy($overbookingGroupBy)
                ->select('sg.id')
                ->selectRaw("COALESCE({$subgroupMaxColumn}, c.max_participants, 0) as subgroup_capacity")
                ->havingRaw("COUNT(bu.id) > subgroup_capacity")
                ->get()
                ->count();

            $today = $selectedDate;

            $unassignedGroupSubgroups = DB::table('booking_users as bu')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->whereNull('bu.deleted_at')
                ->whereNull('c.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('bu.school_id', $schoolId)
                ->where('bu.status', 1)
                ->where('b.status', '<>', 2)
                ->whereDate('bu.date', $today)
                ->whereNull('bu.monitor_id')
                ->where('c.course_type', 1)
                ->whereNotNull('bu.course_subgroup_id')
                ->distinct('bu.course_subgroup_id')
                ->count('bu.course_subgroup_id');

            $unassignedPrivate = DB::table('booking_users as bu')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->leftJoin('course_dates as cd', 'cd.id', '=', 'bu.course_date_id')
                ->whereNull('bu.deleted_at')
                ->whereNull('c.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('bu.school_id', $schoolId)
                ->where('bu.status', 1)
                ->where('b.status', '<>', 2)
                ->where(function ($query) use ($today) {
                    $query->whereDate('bu.date', $today)
                        ->orWhereDate('cd.date', $today);
                })
                ->whereNull('bu.monitor_id')
                ->where('c.course_type', 2)
                ->selectRaw(
                    "COUNT(DISTINCT CONCAT(bu.booking_id, '_', IFNULL(bu.group_id,'no-group'), '_', IFNULL(bu.course_id,'no-course'), '_', IFNULL(bu.course_date_id,'no-date'), '_', DATE(COALESCE(bu.date, cd.date)), '_', IFNULL(bu.hour_start,''), '_', IFNULL(bu.hour_end,''))) as aggregate"
                )
                ->value('aggregate') ?? 0;

            $unassigned = $unassignedGroupSubgroups + $unassignedPrivate;

            return [                'overbookings' => $overbookings,
                'unassigned_courses' => $unassigned,
                'unassigned_group_subgroups' => $unassignedGroupSubgroups,
                'unassigned_private_courses' => $unassignedPrivate,
            ];
        });
        $payload['pending_payments'] = $this->countPendingPayments($schoolId, $selectedDate);

        return $this->sendResponse($payload, 'System statistics retrieved.');
    }

    public function systemDetails(Request $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        if (!$schoolId) {
            return $this->sendError('school_id is required', [], 400);
        }

        $type = $request->get('type');
        $date = $request->get('date') ?? now()->toDateString();
        $subgroupMaxColumn = $this->getSubgroupMaxParticipantsColumn();
        $overbookedGroupBy = ['sg.id', 'c.name', 'c.max_participants', 'cd.hour_start', 'cd.hour_end', 'd.name', 'd.degree_order', 'sg.course_group_id', 'sg.degree_id'];
        if ($subgroupMaxColumn !== 'NULL') {
            $overbookedGroupBy[] = 'sg.max_participants';
        }

        $allowed = ['pending_payments', 'overbooked_groups', 'unassigned_groups', 'unassigned_private', 'free_monitors'];
        if (!in_array($type, $allowed, true)) {
            return $this->sendError('Invalid type', [], 400);
        }

        switch ($type) {
            case 'pending_payments':
                $rows = DB::table('booking_users as bu')
                    ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                    ->leftJoin('clients as c', 'c.id', '=', 'b.client_main_id')
                    ->leftJoin('course_dates as cd', 'cd.id', '=', 'bu.course_date_id')
                    ->where('bu.school_id', $schoolId)
                    ->whereNull('bu.deleted_at')
                    ->whereNull('b.deleted_at')
                    ->where('bu.status', 1)
                    ->where('b.status', '<>', 2)
                    ->where(function ($query) use ($date) {
                        $query->whereDate('bu.date', $date)
                            ->orWhereDate('cd.date', $date);
                    })
                    ->where(function ($query) use ($date) {
                        $query->whereRaw(
                            '(SELECT COALESCE(SUM(bu2.price),0) FROM booking_users bu2 WHERE bu2.booking_id = b.id AND bu2.deleted_at IS NULL AND bu2.status = 1 AND DATE(bu2.date) = ?) - COALESCE(b.paid_total, 0) > 0.01',
                            [$date]
                        )->orWhereRaw(
                            '(SELECT COALESCE(SUM(bu2.price),0) FROM booking_users bu2 WHERE bu2.booking_id = b.id AND bu2.deleted_at IS NULL AND bu2.status = 1 AND DATE(bu2.date) = ?) = 0 AND (b.paid = 0 OR b.paid IS NULL)',
                            [$date]
                        );
                    })
                    ->groupBy('bu.booking_id', 'c.first_name', 'c.last_name')
                    ->select('bu.booking_id')
                    ->selectRaw('bu.booking_id as booking_id')
                    ->selectRaw("CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) as client_name")
                    ->selectRaw('COUNT(bu.id) as participants')
                    ->selectRaw('MAX(COALESCE(b.paid_total, 0)) as paid_total')
                    ->selectRaw('COALESCE(SUM(bu.price), 0) as total_due')
                    ->selectRaw('MIN(bu.hour_start) as start_time')
                    ->selectRaw('MAX(bu.hour_end) as end_time')
                    ->limit(200)
                    ->get()
                    ->map(function ($row) {
                        $priceTotal = (float) ($row->total_due ?? 0);
                        $paidTotal = (float) ($row->paid_total ?? 0);
                        $totalDue = max($priceTotal - $paidTotal, 0);

                        return [
                            'booking_id' => $row->booking_id,
                            'client_name' => trim($row->client_name) ?: null,
                            'participants' => (int) $row->participants,
                            'total_due' => round($totalDue, 2),
                            'time_label' => $this->makeTimeLabel($row->start_time, $row->end_time),
                        ];
                    });
                break;
            case 'overbooked_groups':
                $rows = DB::table('booking_users as bu')
                    ->join('course_subgroups as sg', 'sg.id', '=', 'bu.course_subgroup_id')
                    ->join('courses as c', 'c.id', '=', 'bu.course_id')
                    ->join('course_dates as cd', 'cd.id', '=', 'sg.course_date_id')
                    ->leftJoin('course_dates as cdbu', 'cdbu.id', '=', 'bu.course_date_id')
                    ->leftJoin('course_subgroup_dates as csd', 'csd.course_subgroup_id', '=', 'sg.id')
                    ->leftJoin('course_dates as cdsg', function ($join) use ($date) {
                        $join->on('cdsg.id', '=', 'csd.course_date_id')
                            ->whereDate('cdsg.date', $date);
                    })
                    ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                    ->leftJoin('degrees as d', 'd.id', '=', 'sg.degree_id')
                    ->where('bu.school_id', $schoolId)
                    ->whereNull('bu.deleted_at')
                    ->whereNull('c.deleted_at')
                    ->whereNull('sg.deleted_at')
                    ->whereNull('cd.deleted_at')
                    ->whereNull('b.deleted_at')
                    ->where('bu.status', 1)
                    ->where('b.status', '<>', 2)
                    ->where(function ($query) use ($date) {
                        $query->whereDate('bu.date', $date)
                            ->orWhereDate('cdbu.date', $date)
                            ->orWhereDate('cd.date', $date)
                            ->orWhereExists(function ($subquery) use ($date) {
                                $subquery->select(DB::raw(1))
                                    ->from('course_subgroup_dates as csd2')
                                    ->join('course_dates as cdsg2', 'cdsg2.id', '=', 'csd2.course_date_id')
                                    ->whereColumn('csd2.course_subgroup_id', 'sg.id')
                                    ->whereDate('cdsg2.date', $date);
                            });
                    })
                    ->groupBy($overbookedGroupBy)
                    ->havingRaw("COUNT(bu.id) > subgroup_capacity")
                    ->select('sg.id')
                    ->selectRaw('sg.id as subgroup_id')
                    ->selectRaw('c.name as course_name')
                    ->selectRaw('d.name as degree_name')
                    ->selectRaw('d.degree_order as degree_order')
                    ->selectRaw('(SELECT COUNT(*) FROM course_subgroups sg2 WHERE IFNULL(sg2.course_group_id,0) = IFNULL(sg.course_group_id,0) AND IFNULL(sg2.degree_id,0) = IFNULL(sg.degree_id,0) AND sg2.deleted_at IS NULL AND sg2.id <= sg.id) as subgroup_number')
                    ->selectRaw("COALESCE({$subgroupMaxColumn}, c.max_participants, 0) as subgroup_capacity")
                    ->selectRaw("COALESCE({$subgroupMaxColumn}, c.max_participants, 0) as max_participants")
                    ->selectRaw('COUNT(bu.id) as participants')
                    ->selectRaw('MIN(COALESCE(cd.hour_start, cdbu.hour_start, cdsg.hour_start)) as start_time')
                    ->selectRaw('MAX(COALESCE(cd.hour_end, cdbu.hour_end, cdsg.hour_end)) as end_time')
                    ->limit(200)
                    ->get()
                    ->map(function ($row) {
                        return [
                            'subgroup_id' => $row->subgroup_id,
                            'course_name' => $row->course_name,
                            'degree_name' => $row->degree_name,
                            'degree_order' => $row->degree_order,
                            'subgroup_number' => $row->subgroup_number ? (int) $row->subgroup_number : null,
                            'participants' => (int) $row->participants,
                            'max_participants' => (int) $row->max_participants,
                            'time_label' => $this->makeTimeLabel($row->start_time, $row->end_time),
                        ];
                    });
                break;
            case 'unassigned_groups':
                $rows = DB::table('booking_users as bu')
                    ->join('courses as c', 'c.id', '=', 'bu.course_id')
                    ->join('course_subgroups as sg', 'sg.id', '=', 'bu.course_subgroup_id')
                    ->join('course_dates as cd', 'cd.id', '=', 'sg.course_date_id')
                    ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                    ->leftJoin('degrees as d', 'd.id', '=', 'sg.degree_id')
                    ->where('bu.school_id', $schoolId)
                    ->whereNull('bu.deleted_at')
                    ->whereNull('c.deleted_at')
                    ->whereNull('sg.deleted_at')
                    ->whereNull('cd.deleted_at')
                    ->whereNull('b.deleted_at')
                    ->where('bu.status', 1)
                    ->where('b.status', '<>', 2)
                    ->where(function ($query) use ($date) {
                        $query->whereDate('bu.date', $date)
                            ->orWhereDate('cd.date', $date);
                    })
                    ->whereNull('bu.monitor_id')
                    ->where('c.course_type', 1)
                    ->groupBy('sg.id', 'c.name', 'cd.hour_start', 'cd.hour_end', 'd.name', 'd.degree_order', 'sg.course_group_id', 'sg.degree_id')
                                        ->select('sg.id')
                    ->selectRaw('sg.id as subgroup_id')
                    ->selectRaw('c.name as course_name')
                    ->selectRaw('d.name as degree_name')
                    ->selectRaw('d.degree_order as degree_order')
                    ->selectRaw('(SELECT COUNT(*) FROM course_subgroups sg2 WHERE IFNULL(sg2.course_group_id,0) = IFNULL(sg.course_group_id,0) AND IFNULL(sg2.degree_id,0) = IFNULL(sg.degree_id,0) AND sg2.deleted_at IS NULL AND sg2.id <= sg.id) as subgroup_number')
                    ->selectRaw('COUNT(bu.id) as participants')
                    ->selectRaw('MIN(cd.hour_start) as start_time')
                    ->selectRaw('MAX(cd.hour_end) as end_time')
                    ->limit(200)
                    ->get()
                    ->map(function ($row) {
                        return [
                            'subgroup_id' => $row->subgroup_id,
                            'course_name' => $row->course_name,
                            'degree_name' => $row->degree_name,
                            'degree_order' => $row->degree_order,
                            'subgroup_number' => $row->subgroup_number ? (int) $row->subgroup_number : null,
                            'participants' => (int) $row->participants,
                            'time_label' => $this->makeTimeLabel($row->start_time, $row->end_time),
                        ];
                    });
                break;
            case 'unassigned_private':
        $entries = BookingUser::with(['course:id,name,course_type', 'client:id,first_name,last_name'])
                    ->select([
                        'id',
                        'course_id',
                        'client_id',
                        'booking_id',
                        'group_id',
                        'hour_start',
                        'hour_end',
                        'date',
                        'status',
                        'monitor_id'
                    ])
                    ->where('school_id', $schoolId)
                    ->where(function ($query) use ($date) {
                        $query->whereDate('date', $date)
                            ->orWhereHas('courseDate', fn($q) => $q->whereDate('date', $date));
                    })
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->whereNull('monitor_id')
                    ->whereHas('course', fn($q) => $q->where('course_type', 2))
                    ->whereHas('booking', function ($query) {
                        $query->whereNull('deleted_at')->where('status', '<>', 2);
                    })
                    ->orderBy('hour_start')
                    ->limit(200)
                    ->get();

                $rows = $entries
                    ->groupBy(function ($bookingUser) {
                        $start = $bookingUser->hour_start ?? $bookingUser->courseDate?->hour_start ?? '';
                        $end = $bookingUser->hour_end ?? $bookingUser->courseDate?->hour_end ?? '';
                        $date = $bookingUser->date?->toDateString() ?? $bookingUser->courseDate?->date?->toDateString() ?? '';
                        $courseDateId = $bookingUser->course_date_id ?? 'no-date';
                        $groupId = $bookingUser->group_id ?? 'no-group';
                        $courseId = $bookingUser->course_id ?? 'no-course';

                        return 'b_' . $bookingUser->booking_id . '_' . $groupId . '_' . $courseId . '_' . $courseDateId . '_' . $date . '_' . $start . '_' . $end;
                    })
                    ->map(function ($group) {
                        $first = $group->first();
                        $courseName = $first?->course?->name ?? 'Privado';
                        $clientNames = $group->map(function ($item) {
                            if (!$item->client) {
                                return null;
                            }
                            return trim(($item->client->first_name ?? '') . ' ' . ($item->client->last_name ?? ''));
                        })->filter()->values();

                        $clientName = $clientNames->first();
                        if ($clientName && $clientNames->count() > 1) {
                            $clientName = $clientName . ' +' . ($clientNames->count() - 1);
                        }

                        $startTime = $group->pluck('hour_start')->filter()->sort()->first();
                        $endTime = $group->pluck('hour_end')->filter()->sort()->last();
                        if (!$startTime || !$endTime) {
                            $startTime = $group->pluck('courseDate.hour_start')->filter()->sort()->first() ?? $startTime;
                            $endTime = $group->pluck('courseDate.hour_end')->filter()->sort()->last() ?? $endTime;
                        }

                        return [
                            'course_name' => $courseName,
                            'client_name' => $clientName,
                            'participants' => $group->count(),
                            'time_label' => $this->makeTimeLabel($startTime, $endTime),
                        ];
                    })
                    ->values();
                break;
            case 'free_monitors':
                $freeData = $this->getFreeMonitorsData($schoolId, $date);
                $freeMonitorIds = $freeData['ids'];

                $rows = DB::table('monitors as m')
                    ->leftJoin('monitor_sports_degrees as msd', function ($join) use ($schoolId) {
                        $join->on('msd.monitor_id', '=', 'm.id')
                            ->where('msd.school_id', '=', $schoolId);
                    })
                    ->leftJoin('sports as s', 's.id', '=', 'msd.sport_id')
                    ->whereIn('m.id', $freeMonitorIds)
                    ->groupBy('m.id', 'm.first_name', 'm.last_name')
                    ->select('m.id')
                    ->selectRaw("CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,'')) as monitor_name")
                    ->selectRaw("GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as sports")
                    ->orderBy('m.last_name')
                    ->orderBy('m.first_name')
                    ->limit(300)
                    ->get()
                    ->map(function ($row) {
                        return [
                            'monitor_name' => trim($row->monitor_name) ?: null,
                            'course_name' => $row->sports ?: null,
                        ];
                    });
                break;
            default:
                $rows = collect();
        }

        return $this->sendResponse([
            'type' => $type,
            'date' => $date,
            'items' => $rows,
        ], 'System details retrieved.');
    }

    public function operations(Request $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        if (!$schoolId) {
            return $this->sendError('school_id is required', [], 400);
        }

        $today = Carbon::parse($request->get('date') ?? Carbon::today()->toDateString());
        $cacheKey = "dashboard_operations_v7_{$schoolId}_" . $today->toDateString();

        $payload = Cache::remember($cacheKey, 60, function () use ($schoolId, $today) {
            $coursesToday = Course::where('school_id', $schoolId)
                ->whereHas('courseDates', fn($q) => $q->whereDate('date', $today))
                ->count();

            $groupCourses = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->where('bu.school_id', $schoolId)
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->whereNull('c.deleted_at')
                ->where('bu.status', 1)
                ->where('b.status', '<>', 2)
                ->where('c.course_type', 1)
                ->whereDate('bu.date', $today)
                ->whereNotNull('bu.course_subgroup_id')
                ->distinct('bu.course_subgroup_id')
                ->count('bu.course_subgroup_id');

            $privateCourses = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->leftJoin('course_dates as cd', 'cd.id', '=', 'bu.course_date_id')
                ->where('bu.school_id', $schoolId)
                ->where(function ($query) use ($today) {
                    $query->whereDate('bu.date', $today)
                        ->orWhereDate('cd.date', $today);
                })
                ->where('bu.status', 1)
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->whereNull('c.deleted_at')
                ->where('c.course_type', 2)
                ->selectRaw(
                    "COUNT(DISTINCT CONCAT('b_', bu.booking_id, '_', IFNULL(bu.group_id,'no-group'), '_', bu.course_id, '_', IFNULL(bu.course_date_id,'no-date'), '_', DATE(COALESCE(bu.date, cd.date)), '_', IFNULL(bu.hour_start,''), '_', IFNULL(bu.hour_end,''))) as aggregate"
                )
                ->value('aggregate') ?? 0;

            $groupSports = DB::table('booking_users as bu')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->join('sports as s', 's.id', '=', 'c.sport_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNull('bu.deleted_at')
                ->where('c.course_type', 1)
                ->whereNotNull('bu.course_subgroup_id')
                ->groupBy('s.id', 's.name', 's.icon_collective', 's.icon_selected')
                ->select('s.id')
                ->selectRaw('s.id as sport_id, s.name as sport_name, s.icon_collective as icon_collective, s.icon_selected as icon_selected, COUNT(DISTINCT bu.course_subgroup_id) as total')
                ->orderByDesc('total')
                ->get()
                ->map(fn($row) => [
                    'sport_id' => (int) $row->sport_id,
                    'sport_name' => $row->sport_name,
                    'icon' => $row->icon_selected ?: $row->icon_collective,
                    'count' => (int) $row->total,
                ])
                ->values();

            $groupSportsCatalog = DB::table('courses as c')
                ->join('sports as s', 's.id', '=', 'c.sport_id')
                ->where('c.school_id', $schoolId)
                ->whereNull('c.deleted_at')
                ->where('c.course_type', 1)
                ->select('s.id', 's.name', 's.icon_collective', 's.icon_selected')
                ->distinct()
                ->get()
                ->map(fn($row) => [
                    'sport_id' => (int) $row->id,
                    'sport_name' => $row->name,
                    'icon' => $row->icon_selected ?: $row->icon_collective,
                ]);

            if ($groupSportsCatalog->isNotEmpty()) {
                $groupSportsById = $groupSports->keyBy('sport_id');
                $groupSports = $groupSportsCatalog
                    ->map(fn($row) => [
                        'sport_id' => $row['sport_id'],
                        'sport_name' => $row['sport_name'],
                        'icon' => $row['icon'],
                        'count' => (int) ($groupSportsById[$row['sport_id']]['count'] ?? 0),
                    ])
                    ->values();
            }

            $privateSports = DB::table('booking_users as bu')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->join('sports as s', 's.id', '=', 'c.sport_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNull('bu.deleted_at')
                ->where('c.course_type', 2)
                ->groupBy('s.id', 's.name', 's.icon_prive', 's.icon_selected')
                ->select('s.id')
                ->selectRaw("s.id as sport_id, s.name as sport_name, s.icon_prive as icon_prive, s.icon_selected as icon_selected, COUNT(DISTINCT CONCAT('b_', bu.booking_id, '_', IFNULL(bu.group_id,'no-group'), '_', bu.course_id, '_', DATE(bu.date), '_', IFNULL(bu.hour_start,''), '_', IFNULL(bu.hour_end,''))) as total")
                ->orderByDesc('total')
                ->get()
                ->map(fn($row) => [
                    'sport_id' => (int) $row->sport_id,
                    'sport_name' => $row->sport_name,
                    'icon' => $row->icon_selected ?: $row->icon_prive,
                    'count' => (int) $row->total,
                ])
                ->values();

            $privateSportsCatalog = DB::table('courses as c')
                ->join('sports as s', 's.id', '=', 'c.sport_id')
                ->where('c.school_id', $schoolId)
                ->whereNull('c.deleted_at')
                ->where('c.course_type', 2)
                ->select('s.id', 's.name', 's.icon_prive', 's.icon_selected')
                ->distinct()
                ->get()
                ->map(fn($row) => [
                    'sport_id' => (int) $row->id,
                    'sport_name' => $row->name,
                    'icon' => $row->icon_selected ?: $row->icon_prive,
                ]);

            if ($privateSportsCatalog->isNotEmpty()) {
                $privateSportsById = $privateSports->keyBy('sport_id');
                $privateSports = $privateSportsCatalog
                    ->map(fn($row) => [
                        'sport_id' => $row['sport_id'],
                        'sport_name' => $row['sport_name'],
                        'icon' => $row['icon'],
                        'count' => (int) ($privateSportsById[$row['sport_id']]['count'] ?? 0),
                    ])
                    ->values();
            }

            $assignedMonitors = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->where('bu.school_id', $schoolId)
                ->whereNotNull('bu.monitor_id')
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->distinct('bu.monitor_id')
                ->count('bu.monitor_id');

            $assignedMonitorsGroup = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNotNull('bu.monitor_id')
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->whereNull('c.deleted_at')
                ->where('c.course_type', 1)
                ->distinct('bu.monitor_id')
                ->count('bu.monitor_id');

            $assignedMonitorsPrivate = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNotNull('bu.monitor_id')
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->whereNull('c.deleted_at')
                ->where('c.course_type', 2)
                ->distinct('bu.monitor_id')
                ->count('bu.monitor_id');

            $assignedGroupCourses = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNotNull('bu.monitor_id')
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->whereNull('c.deleted_at')
                ->where('c.course_type', 1)
                ->whereNotNull('bu.course_subgroup_id')
                ->distinct('bu.course_subgroup_id')
                ->count('bu.course_subgroup_id');

            $assignedPrivateCourses = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNotNull('bu.monitor_id')
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->whereNull('c.deleted_at')
                ->where('c.course_type', 2)
                ->selectRaw(
                    "COUNT(DISTINCT CONCAT('b_', bu.booking_id, '_', IFNULL(bu.group_id,'no-group'), '_', bu.course_id, '_', DATE(bu.date), '_', IFNULL(bu.hour_start,''), '_', IFNULL(bu.hour_end,''))) as aggregate"
                )
                ->value('aggregate') ?? 0;

            $assignedCourses = $assignedGroupCourses + $assignedPrivateCourses;
            $coursesToday = $groupCourses + $privateCourses;
            $coursesToday = $groupCourses + $privateCourses;

            $freeData = $this->getFreeMonitorsData($schoolId, $today);
            $freeMonitorIds = $freeData['ids'];
            $freeMonitors = $freeData['count'];
            $freeMonitorsBySport = $freeData['sports'];
            $freeMonitorsHours = $freeData['hours'];

            $occupancy = $this->getDailyOccupancyPercent($schoolId, Carbon::parse($today));

            $uniqueParticipants = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->distinct('bu.client_id')
                ->count('bu.client_id');

            return [
                'courses_today' => $coursesToday,
                'group_courses' => $groupCourses,
                'private_courses' => $privateCourses,
                'assigned_courses' => $assignedCourses,
                'assigned_monitors' => $assignedMonitors,
                'assigned_monitors_group' => $assignedMonitorsGroup,
                'assigned_monitors_private' => $assignedMonitorsPrivate,
                'free_monitors' => $freeMonitors,
                'free_monitors_hours' => $freeMonitorsHours,
                'today_occupancy' => $occupancy,
                'unique_participants' => $uniqueParticipants,
                'group_courses_sports' => $groupSports,
                'private_courses_sports' => $privateSports,
                'free_monitors_sports' => $freeMonitorsBySport,
            ];
        });

        return $this->sendResponse($payload, 'Today operations retrieved.');
    }

    public function coursesCapacity(Request $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        if (!$schoolId) {
            return $this->sendError('school_id is required', [], 400);
        }

        $today = $request->get('date') ?? Carbon::today()->toDateString();
        $groupPage = max(1, (int)$request->get('group_page', 1));
        $privatePage = max(1, (int)$request->get('private_page', 1));
        $perPage = (int)$request->get('per_page', 6);
        $perPage = $perPage > 0 ? min($perPage, 20) : 6;

        $cacheKey = "dashboard_courses_capacity_v3_{$schoolId}_{$today}";
        $groupCourses = Cache::remember("{$cacheKey}_group", 60, fn () => $this->buildGroupCourseCollection($schoolId, $today));
        $privateCourses = Cache::remember("{$cacheKey}_private", 60, fn () => $this->buildPrivateCourseCollection($schoolId, $today));
        $groupSummary = Cache::remember("{$cacheKey}_group_summary", 60, fn () => $this->buildGroupSummary($schoolId, $today));
        $privateSummary = Cache::remember("{$cacheKey}_private_summary", 60, fn () => $this->buildPrivateSummary($schoolId, $today));

        return $this->sendResponse([
            'group_courses' => $this->paginateCollection($groupCourses, $groupPage, $perPage),
            'private_courses' => $this->paginateCollection($privateCourses, $privatePage, $perPage),
            'group_summary' => $groupSummary,
            'private_summary' => $privateSummary,
        ], 'Courses capacity retrieved.');
    }

    public function forecast(Request $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        if (!$schoolId) {
            return $this->sendError('school_id is required', [], 400);
        }

        $days = (int)$request->get('days', 7);
        $days = $days < 3 ? 3 : min($days, 14);

        $startDate = Carbon::today();
        $endDate = (clone $startDate)->addDays($days - 1);

        $forecastKey = "dashboard_forecast_v5_{$schoolId}_{$days}_{$startDate->toDateString()}";
        $forecastPayload = Cache::remember($forecastKey, 60, function () use ($schoolId, $startDate, $endDate, $days) {
            $forecast = collect();
            for ($i = 0; $i < $days; $i++) {
                $date = (clone $startDate)->addDays($i);
                $forecast[$date->toDateString()] = [
                    'date' => $date->toDateString(),
                    'label' => $date->format('D d'),
                    'bookings' => 0,
                    'participants' => 0,
                    'expected_revenue' => 0.0,
                    'pending_payments' => 0,
                    'assigned_monitors' => 0,
                    'private_courses' => 0,
                    'group_courses' => 0,
                    'courses' => 0,
                    'unassigned' => 0,
                    'free_monitors' => 0,
                    'unpaid' => 0,
                    'occupancy_percent' => 0,
                ];
            }

            $revenueByDate = $this->calculateForecastRevenueByDate($schoolId, $startDate, $endDate);

            $rows = DB::table('booking_users as bu')
                ->leftJoin('bookings as b', 'bu.booking_id', '=', 'b.id')
                ->leftJoin('courses as c', 'c.id', '=', 'bu.course_id')
                ->leftJoin('course_dates as cd', 'cd.id', '=', 'bu.course_date_id')
                ->where('bu.school_id', $schoolId)
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->whereNull('c.deleted_at')
                ->where('b.status', '<>', 2)
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('bu.date', [$startDate->toDateString(), $endDate->toDateString()])
                        ->orWhereBetween('cd.date', [$startDate->toDateString(), $endDate->toDateString()]);
                })
                ->where('bu.status', 1)
                ->groupBy('date_key')
                ->selectRaw('DATE(COALESCE(bu.date, cd.date)) as date_key')
                ->selectRaw('COUNT(DISTINCT bu.booking_id) as bookings')
                ->selectRaw('COUNT(DISTINCT bu.client_id) as participants')
                ->selectRaw('COALESCE(SUM(bu.price), 0) as expected_revenue')
                ->selectRaw('COUNT(DISTINCT CASE WHEN b.paid = 0 OR b.paid IS NULL THEN bu.booking_id END) as pending_payments')
                ->selectRaw('COUNT(DISTINCT bu.monitor_id) as assigned_monitors')
                ->selectRaw('COUNT(DISTINCT CASE WHEN c.course_type = 2 THEN CONCAT("b_", bu.booking_id, "_", IFNULL(bu.group_id,"no-group"), "_", bu.course_id, "_", IFNULL(bu.course_date_id,""), "_", DATE(COALESCE(bu.date, cd.date)), "_", IFNULL(bu.hour_start,""), "_", IFNULL(bu.hour_end,"")) END) as private_courses')
                ->selectRaw('COUNT(DISTINCT CASE WHEN c.course_type = 1 THEN bu.course_subgroup_id END) as group_courses')
                ->selectRaw('COUNT(DISTINCT CASE WHEN bu.monitor_id IS NULL THEN CONCAT("b_", bu.booking_id, "_", IFNULL(bu.group_id,"no-group"), "_", bu.course_id, "_", IFNULL(bu.course_date_id,""), "_", DATE(COALESCE(bu.date, cd.date)), "_", IFNULL(bu.hour_start,""), "_", IFNULL(bu.hour_end,"")) END) as unassigned')
                ->selectRaw('COUNT(DISTINCT CASE WHEN b.paid = 0 OR b.paid IS NULL THEN bu.booking_id END) as unpaid')
                ->get();

            foreach ($rows as $row) {
                $dateKey = Carbon::parse($row->date_key)->toDateString();
                if (!isset($forecast[$dateKey])) {
                    continue;
                }

                $privateCourses = (int) ($row->private_courses ?? 0);
                $groupCourses = (int) ($row->group_courses ?? 0);
                $totalCourses = $privateCourses + $groupCourses;

                $busyMonitorIds = DB::table('booking_users as bu2')
                    ->join('bookings as b2', 'b2.id', '=', 'bu2.booking_id')
                    ->where('bu2.school_id', $schoolId)
                    ->whereDate('bu2.date', $dateKey)
                    ->where('bu2.status', 1)
                    ->whereNotNull('bu2.monitor_id')
                    ->whereNull('bu2.deleted_at')
                    ->whereNull('b2.deleted_at')
                    ->where('b2.status', '<>', 2)
                    ->pluck('bu2.monitor_id')
                    ->unique()
                    ->values();

                $nwdMonitorIds = DB::table('monitor_nwd')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at')
                    ->whereDate('start_date', '<=', $dateKey)
                    ->whereDate('end_date', '>=', $dateKey)
                    ->pluck('monitor_id')
                    ->unique()
                    ->values();

                $freeMonitors = DB::table('monitors as m')
                    ->where('m.active_school', $schoolId)
                    ->where('m.active', 1)
                    ->whereNotIn('m.id', $busyMonitorIds)
                    ->whereNotIn('m.id', $nwdMonitorIds)
                    ->count();

                $pendingPayments = $this->countPendingPayments($schoolId, Carbon::parse($dateKey));
                $unassignedCounts = $this->getUnassignedCountsForDate($schoolId, Carbon::parse($dateKey));

                $occupancyPercent = $this->getDailyOccupancyPercent($schoolId, Carbon::parse($dateKey));
                $expectedRevenue = $revenueByDate[$dateKey] ?? (float) $row->expected_revenue;

                $forecast[$dateKey] = array_merge($forecast[$dateKey], [
                    'bookings' => (int) $row->bookings,
                    'participants' => (int) $row->participants,
                    'expected_revenue' => round((float) $expectedRevenue, 2),
                    'pending_payments' => (int) $pendingPayments,
                    'assigned_monitors' => (int) $row->assigned_monitors,
                    'private_courses' => $privateCourses,
                    'group_courses' => $groupCourses,
                    'courses' => $totalCourses,
                    'unassigned' => (int) ($unassignedCounts['total'] ?? 0),
                    'free_monitors' => $freeMonitors,
                    'unpaid' => (int) $pendingPayments,
                    'occupancy_percent' => $occupancyPercent,
                ]);
            }

            return [
                'data' => array_values($forecast->toArray()),
                'currency' => 'CHF',
            ];
        });

        $payload = $forecastPayload['data'];
        $currency = $forecastPayload['currency'];

        return $this->sendResponse([
            'forecast' => $payload,
            'currency' => $currency,
        ], '7-day forecast retrieved.');
    }

    public function commercialPerformance(Request $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        if (!$schoolId) {
            return $this->sendError('school_id is required', [], 400);
        }

        $today = Carbon::today()->toDateString();
        $cacheKey = "dashboard_commercial_{$schoolId}_{$today}";
        $payload = Cache::remember($cacheKey, 120, function () use ($schoolId, $today) {
            $metrics = $this->metricsService->getMetrics($schoolId, $today);
            $school = School::find($schoolId);

            $bookingsStat = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->selectRaw('COUNT(DISTINCT bu.booking_id) as total_bookings, COALESCE(SUM(bu.price), 0) as total_price')
                ->first();

            $expectedRevenue = round($bookingsStat?->total_price ?? 0, 2);
            $totalBookings = (int) ($bookingsStat?->total_bookings ?? 0);
            $boukiiCommission = $school ? ($school->bookings_comission_boukii_pay ?? $school->bookings_comission_other ?? 0) : 0;
            $boukiiRevenue = round($expectedRevenue * ($boukiiCommission / 100), 2);

            return [
                'net_income_today' => $metrics['revenue']['ingresosHoy'] ?? 0,
                'net_income_week' => $metrics['revenue']['ingresosSemana'] ?? 0,
                'net_income_month' => $metrics['revenue']['ingresosMes'] ?? 0,
                'trend' => $metrics['revenue']['tendencia'] ?? 'stable',
                'pending_payments' => $this->countPendingPayments($schoolId, $today),
                'expected_revenue_today' => $expectedRevenue,
                'total_bookings_today' => $totalBookings,
                'boukii_revenue_estimate' => $boukiiRevenue,
                'currency' => $metrics['revenue']['moneda'] ?? 'CHF',
            ];
        });

        return $this->sendResponse($payload, 'Commercial performance retrieved.');
    }

    private function getUnassignedCountsForDate(int $schoolId, Carbon $date): array
    {
        $unassignedGroupSubgroups = DB::table('booking_users as bu')
            ->join('courses as c', 'c.id', '=', 'bu.course_id')
            ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
            ->whereNull('bu.deleted_at')
            ->whereNull('b.deleted_at')
            ->whereNull('c.deleted_at')
            ->where('bu.school_id', $schoolId)
            ->where('bu.status', 1)
            ->whereDate('bu.date', $date)
            ->whereNull('bu.monitor_id')
            ->where('c.course_type', 1)
            ->whereNotNull('bu.course_subgroup_id')
            ->distinct('bu.course_subgroup_id')
            ->count('bu.course_subgroup_id');

        $unassignedPrivate = DB::table('booking_users as bu')
            ->join('courses as c', 'c.id', '=', 'bu.course_id')
            ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
            ->leftJoin('course_dates as cd', 'cd.id', '=', 'bu.course_date_id')
            ->whereNull('bu.deleted_at')
            ->whereNull('b.deleted_at')
            ->whereNull('c.deleted_at')
            ->where('bu.school_id', $schoolId)
            ->where('bu.status', 1)
            ->whereNull('bu.monitor_id')
            ->where(function ($query) use ($date) {
                $query->whereDate('bu.date', $date)
                    ->orWhereDate('cd.date', $date);
            })
            ->where('c.course_type', 2)
            ->selectRaw(
                "COUNT(DISTINCT CONCAT('b_', bu.booking_id, '_', IFNULL(bu.group_id,'no-group'), '_', IFNULL(bu.course_id,'no-course'), '_', IFNULL(bu.course_date_id,'no-date'), '_', DATE(COALESCE(bu.date, cd.date)), '_', IFNULL(bu.hour_start,''), '_', IFNULL(bu.hour_end,''))) as aggregate"
            )
            ->value('aggregate') ?? 0;

        return [
            'total' => $unassignedGroupSubgroups + $unassignedPrivate,
            'group' => $unassignedGroupSubgroups,
            'private' => $unassignedPrivate,
        ];
    }

    private function calculateForecastRevenueByDate(int $schoolId, Carbon $startDate, Carbon $endDate): array
    {
        $bookingUsers = BookingUser::query()
            ->with([
                'booking',
                'course.courseDates',
                'courseDate',
                'bookingUserExtras.courseExtra',
            ])
            ->where('school_id', $schoolId)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->whereHas('booking', function ($query) {
                $query->whereNull('deleted_at')
                    ->where('status', '<>', 2);
            })
            ->whereHas('course', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereHas('courseDate', function ($subQuery) use ($startDate, $endDate) {
                        $subQuery->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
                    });
            })
            ->get();

        if ($bookingUsers->isEmpty()) {
            return [];
        }

        $calculator = new BookingPriceCalculatorService();
        $usersByDate = [];
        foreach ($bookingUsers as $bookingUser) {
            $dateValue = $bookingUser->date?->format('Y-m-d')
                ?? $bookingUser->courseDate?->date?->format('Y-m-d');
            if (!$dateValue) {
                continue;
            }
            if (!isset($usersByDate[$dateValue])) {
                $usersByDate[$dateValue] = collect();
            }
            $usersByDate[$dateValue]->push($bookingUser);
        }

        $revenues = [];
        foreach ($usersByDate as $dateKey => $dayUsers) {
            $total = 0.0;
            foreach ($dayUsers->groupBy('course_id') as $courseUsers) {
                $course = $courseUsers->first()->course;
                if (!$course) {
                    continue;
                }

                $courseType = (int) ($course->course_type ?? 0);
                $isFlexible = !empty($course->is_flexible);

                if ($courseType === 2) {
                    if ($isFlexible) {
                        $total += $calculator->calculatePrivatePrice($courseUsers, $course);
                    } else {
                        $total += $courseUsers->sum(function ($bu) use ($course) {
                            return (float) ($bu->price ?? $course->price ?? 0);
                        });
                        $total += $courseUsers->sum(function ($bu) {
                            return $bu->bookingUserExtras?->sum('courseExtra.price') ?? 0;
                        });
                    }
                    continue;
                }

                if ($courseType === 1) {
                    if ($isFlexible) {
                        foreach ($courseUsers->groupBy('client_id') as $clientUsers) {
                            $totalPrice = IntervalDiscountHelper::calculateFlexibleCollectivePrice($course, $clientUsers);
                            $uniqueDates = $clientUsers->map(function ($bu) {
                                return $bu->date?->format('Y-m-d')
                                    ?? $bu->courseDate?->date?->format('Y-m-d');
                            })->filter()->unique()->count();
                            if ($uniqueDates > 0) {
                                $total += $totalPrice / $uniqueDates;
                            }
                        }
                        $total += $courseUsers->sum(function ($bu) {
                            return $bu->bookingUserExtras?->sum('courseExtra.price') ?? 0;
                        });
                    } else {
                        $datesCount = max(1, $course->courseDates?->count() ?? 0);
                        $perDay = (float) ($course->price ?? 0) / $datesCount;
                        $uniqueClients = $courseUsers->groupBy('client_id')->count();
                        $total += $perDay * $uniqueClients;
                        $total += $courseUsers->sum(function ($bu) {
                            return $bu->bookingUserExtras?->sum('courseExtra.price') ?? 0;
                        });
                    }
                    continue;
                }

                $total += $calculator->calculatePrivatePrice($courseUsers, $course);
            }

            $revenues[$dateKey] = round($total, 2);
        }

        return $revenues;
    }

    private function getDailyOccupancyPercent(int $schoolId, Carbon $date): float
    {
        $dateString = $date->toDateString();
        $groupSummary = $this->buildGroupSummary($schoolId, $dateString);
        $privateSummary = $this->buildPrivateSummary($schoolId, $dateString);

        $totalCapacity = (float) ($groupSummary['spots_available'] ?? 0)
            + (float) ($privateSummary['hours_available'] ?? 0);
        $usedCapacity = (float) ($groupSummary['spots_sold'] ?? 0)
            + (float) ($privateSummary['hours_sold'] ?? 0);

        if ($totalCapacity <= 0) {
            return 0.0;
        }

        return round(($usedCapacity / $totalCapacity) * 100, 2);
    }

    private function getDailyGroupCapacity(int $schoolId, Carbon $date): int
    {
        $subgroupMaxColumn = $this->getSubgroupMaxParticipantsColumn();
        if ($subgroupMaxColumn === 'NULL') {
            return 0;
        }

        return (int) DB::table('course_subgroups as sg')
            ->join('course_dates as cd', 'cd.id', '=', 'sg.course_date_id')
            ->join('courses as c', 'c.id', '=', 'sg.course_id')
            ->whereNull('sg.deleted_at')
            ->whereNull('cd.deleted_at')
            ->whereNull('c.deleted_at')
            ->where('c.school_id', $schoolId)
            ->whereDate('cd.date', $date)
            ->sum(DB::raw("COALESCE({$subgroupMaxColumn}, c.max_participants, 0)"));
    }

    private function getDailyUsedCapacity(int $schoolId, Carbon $date): int
    {
        return (int) BookingUser::whereHas('booking', fn($q) => $q->where('school_id', $schoolId))
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->whereDate('date', $date)
            ->count();
    }

    private function buildGroupCourseCollection(int $schoolId, string $date): Collection
    {
        $entries = BookingUser::with([
                'course:id,name,course_type,sport_id',
                'course.sport:id,icon_collective,icon_prive,icon_selected',
                'monitor:id,first_name,last_name',
                'booking:id,paid'
            ])
            ->select([
                'id',
                'course_id',
                'course_subgroup_id',
                'monitor_id',
                'booking_id',
                'hour_start',
                'hour_end',
                'price',
                'currency',
                'date',
                'status',
            ])
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($date) {
                $query->whereDate('date', $date)
                    ->orWhereHas('courseDate', fn($q) => $q->whereDate('date', $date));
            })
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereHas('course', fn($q) => $q->where('course_type', 1))
            ->whereHas('booking', function ($query) {
                $query->whereNull('deleted_at')->where('status', '<>', 2);
            })
            ->get();

        return $entries->groupBy('course_id')->map(function ($group) {
            $course = $group->first()?->course;
            $startTime = $group->pluck('hour_start')->filter()->sort()->first();
            $endTime = $group->pluck('hour_end')->filter()->sort()->last();

            $pendingPayments = $group->filter(fn($entry) => !($entry->booking?->paid ?? true))
                ->pluck('booking_id')
                ->unique()
                ->count();

            $courseIcon = null;
            if ($course?->sport) {
                $courseIcon = $course->sport->icon_selected
                    ?: ($course->course_type === 1
                        ? ($course->sport->icon_collective ?: $course->sport->icon_prive)
                        : ($course->sport->icon_prive ?: $course->sport->icon_collective));
            }

            return [
                'course_id' => $course?->id,
                'course_name' => $course?->name ?? 'Curso colectivo',
                'course_icon' => $courseIcon,
                'groups_count' => $group->pluck('course_subgroup_id')->filter()->unique()->count(),
                'assigned_monitors' => $group->pluck('monitor_id')->filter()->unique()->count(),
                'participants' => $group->count(),                'start_time' => $startTime,
                'end_time' => $endTime,
                'time_label' => $this->makeTimeLabel($startTime, $endTime),
                'monitors' => $group->pluck('monitor')
                    ->filter()
                    ->map(fn($monitor) => trim(($monitor->first_name ?? '') . ' ' . ($monitor->last_name ?? '')))
                    ->unique()
                    ->values(),
                'currency' => $group->first()?->currency ?? 'CHF',
            ];
        })->values()->take(40);
    }

    private function buildPrivateCourseCollection(int $schoolId, string $date): Collection
    {
        $entries = BookingUser::with([
                'course:id,name,course_type,sport_id',
                'course.sport:id,icon_collective,icon_prive,icon_selected',
                'client:id,first_name,last_name',
                'monitor:id,first_name,last_name',
                'booking:id,paid,price_total,paid_total',
                'courseDate:id,course_id,date,hour_start,hour_end'
            ])
            ->select([
                'id',
                'course_id',
                'client_id',
                'monitor_id',
                'booking_id',
                'group_id',
                'hour_start',
                'hour_end',
                'course_date_id',
                'price',
                'currency',
                'date',
                'status',
            ])
            ->where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereHas('course', fn($q) => $q->where('course_type', 2))
            ->whereHas('booking', function ($query) {
                $query->whereNull('deleted_at')->where('status', '<>', 2);
            })
            ->whereHas('booking', function ($query) {
                $query->whereNull('deleted_at')->where('status', '<>', 2);
            })
            ->orderBy('hour_start')
            ->limit(120)
            ->get();

        return $entries
            ->groupBy(function ($bookingUser) {
                $bookingId = $bookingUser->booking_id ?? 'no-booking';
                $groupId = $bookingUser->group_id ?? 'no-group';
                $date = $bookingUser->date?->toDateString() ?? $bookingUser->courseDate?->date?->toDateString() ?? 'no-day';
                $start = $bookingUser->hour_start ?? $bookingUser->courseDate?->hour_start ?? 'no-start';
                $end = $bookingUser->hour_end ?? $bookingUser->courseDate?->hour_end ?? 'no-end';

                return implode('|', [
                    $bookingId,
                    $groupId,
                    $date,
                    $start,
                    $end,
                ]);
            })
            ->map(function ($group) {
                $first = $group->first();
                $course = $first?->course;
                $courseName = $course?->name ?? 'Privado';
                $courseType = $course?->course_type;
                $monitorName = $first?->monitor ? trim(($first->monitor->first_name ?? '') . ' ' . ($first->monitor->last_name ?? '')) : null;

                $clientNames = $group->map(function ($item) {
                    if (!$item->client) {
                        return null;
                    }
                    return trim(($item->client->first_name ?? '') . ' ' . ($item->client->last_name ?? ''));
                })->filter()->values();

                $clientName = $clientNames->first();
                if ($clientName && $clientNames->count() > 1) {
                    $clientName = $clientName . ' +' . ($clientNames->count() - 1);
                }

                $bookingPaid = $group->every(function ($item) {
                    $booking = $item->booking;
                    if (!$booking) {
                        return false;
                    }
                    if ($booking->paid) {
                        return true;
                    }
                    $priceTotal = (float) ($booking->price_total ?? 0);
                    $paidTotal = (float) ($booking->paid_total ?? 0);
                    return $priceTotal > 0 && $paidTotal >= $priceTotal;
                });

                $startTime = $group->pluck('hour_start')->filter()->sort()->first();
                $endTime = $group->pluck('hour_end')->filter()->sort()->last();
                if (!$startTime || !$endTime) {
                    $startTime = $group->pluck('courseDate.hour_start')->filter()->sort()->first();
                    $endTime = $group->pluck('courseDate.hour_end')->filter()->sort()->last();
                }
                $timeLabel = $this->makeTimeLabel($startTime, $endTime);
                $dateValue = $first?->date?->toDateString() ?? $first?->courseDate?->date?->toDateString();

                $courseIcon = null;
            if ($course?->sport) {
                $courseIcon = $course->sport->icon_selected
                    ?: ($course->course_type === 1
                        ? ($course->sport->icon_collective ?: $course->sport->icon_prive)
                        : ($course->sport->icon_prive ?: $course->sport->icon_collective));
            }

                return [
                    'id' => $first?->group_id ?? $first?->id,
                    'course_name' => $courseName,
                    'course_icon' => $courseIcon,
                    'course_type' => $courseType,
                    'client_name' => $clientName,
                    'monitor_name' => $monitorName,
                    'is_paid' => $bookingPaid,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration' => $first?->formatted_duration,
                    'duration_hours' => $this->calculateDurationHours($startTime, $endTime),
                    'date' => $dateValue,
                    'status' => $group->contains(fn($item) => $item->monitor_id) ? 'assigned' : 'pending',
                    'price' => round((float) $group->sum('price'), 2),
                    'participants' => $group->pluck('client_id')->filter()->unique()->count(),
                    'currency' => $first?->currency ?? 'CHF',
                    'time_label' => $timeLabel,
                    'course_type' => 'private',
                ];
            })
            ->values()
            ->take(80);
    }

    private function buildGroupSummary(int $schoolId, string $date): array
    {
        $today = Carbon::parse($date)->toDateString();

        $totalDaysByCourse = DB::table('course_dates')
            ->whereNull('deleted_at')
            ->groupBy('course_id')
            ->selectRaw('course_id, COUNT(*) as total_days')
            ->pluck('total_days', 'course_id');

        $groupBookings = DB::table('booking_users as bu')
            ->join('courses as c', 'c.id', '=', 'bu.course_id')
            ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
            ->where('bu.school_id', $schoolId)
            ->whereDate('bu.date', $today)
            ->where('bu.status', 1)
            ->whereNull('bu.deleted_at')
            ->whereNull('b.deleted_at')
            ->where('b.status', '<>', 2)
            ->where('c.course_type', 1)
            ->select('bu.course_id', 'c.is_flexible', 'bu.course_subgroup_id')
            ->get();

        $spotsSold = 0.0;
        foreach ($groupBookings as $booking) {
            $days = max(1, (int) ($totalDaysByCourse[$booking->course_id] ?? 1));
            $weight = $booking->is_flexible ? 1 : (1 / $days);
            $spotsSold += $weight;
        }

        $groupParticipants = (int) $groupBookings->count();
        $groupCount = (int) $groupBookings->pluck('course_subgroup_id')->filter()->unique()->count();

        $subgroups = DB::table('course_subgroups as sg')
            ->join('course_dates as cd', 'cd.id', '=', 'sg.course_date_id')
            ->join('courses as c', 'c.id', '=', 'sg.course_id')
            ->whereNull('sg.deleted_at')
            ->whereNull('cd.deleted_at')
            ->whereNull('c.deleted_at')
            ->where('c.school_id', $schoolId)
            ->whereDate('cd.date', $today)
            ->select('sg.course_id', 'c.is_flexible')
            ->selectRaw($this->getSubgroupMaxParticipantsColumn() . ' as max_participants')
            ->get();

        $spotsAvailable = 0.0;
        foreach ($subgroups as $subgroup) {
            $days = max(1, (int) ($totalDaysByCourse[$subgroup->course_id] ?? 1));
            $capacity = max(0, (int) ($subgroup->max_participants ?? 0));
            $spotsAvailable += $subgroup->is_flexible ? $capacity : ($capacity / $days);
        }

        $spotsSold = round($spotsSold, 1);
        $spotsAvailable = round($spotsAvailable, 1);
        $spotsRemaining = max(0, round($spotsAvailable - $spotsSold, 1));
        $usagePercent = $spotsAvailable > 0 ? round(($spotsSold / $spotsAvailable) * 100, 0) : 0;

        return [
            'spots_sold' => $spotsSold,
            'spots_available' => $spotsAvailable,
            'spots_remaining' => $spotsRemaining,
            'usage_percent' => $usagePercent,
            'participants' => $groupParticipants,
            'groups' => $groupCount,
        ];
    }

    private function buildPrivateSummary(int $schoolId, string $date): array
    {
        $today = Carbon::parse($date)->toDateString();

        $entries = BookingUser::with('courseDate')
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($today) {
                $query->whereDate('date', $today)
                    ->orWhereHas('courseDate', fn($q) => $q->whereDate('date', $today));
            })
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereHas('course', fn($q) => $q->where('course_type', 2))
            ->whereHas('booking', function ($query) {
                $query->whereNull('deleted_at')->where('status', '<>', 2);
            })
            ->select('booking_id', 'group_id', 'hour_start', 'hour_end', 'monitor_id')
            ->get();

        $groups = [];
        foreach ($entries as $entry) {
            $start = $entry->hour_start ?? '';
            $end = $entry->hour_end ?? '';
            $dateKey = $entry->date?->format('Y-m-d') ?? $entry->courseDate?->date?->format('Y-m-d') ?? $today;
            $groupKey = 'b_' . $entry->booking_id . '_' . ($entry->group_id ?? 'no-group') . '_' . ($entry->course_id ?? 'no-course') . '_' . ($entry->course_date_id ?? 'no-date') . '_' . $dateKey . '_' . $start . '_' . $end;
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'start' => $start,
                    'end' => $end,
                    'count' => 0,
                ];
            }
            $groups[$groupKey]['count']++;
        }

        $hoursSold = 0.0;
        foreach ($groups as $group) {
            $hoursSold += $this->calculateDurationHours($group['start'], $group['end']);
        }

        $totalParticipants = (int) $entries->count();
        $totalCourses = (int) count($groups);

        $season = DB::table('seasons')
            ->where('school_id', $schoolId)
            ->where('is_active', 1)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('start_date')
            ->first();

        $seasonStart = $season?->hour_start ?: null;
        $seasonEnd = $season?->hour_end ?: null;

        $courseWindow = DB::table('course_dates as cd')
            ->join('courses as c', 'c.id', '=', 'cd.course_id')
            ->whereNull('cd.deleted_at')
            ->whereNull('c.deleted_at')
            ->where('c.school_id', $schoolId)
            ->where('c.course_type', 2)
            ->whereDate('cd.date', $today)
            ->selectRaw('MIN(cd.hour_start) as min_hour, MAX(cd.hour_end) as max_hour')
            ->first();

        $windowStart = $courseWindow?->min_hour ?: $seasonStart;
        $windowEnd = $courseWindow?->max_hour ?: $seasonEnd;

        if ($seasonStart && $seasonEnd && $windowStart && $windowEnd) {
            $windowStart = max($windowStart, $seasonStart);
            $windowEnd = min($windowEnd, $seasonEnd);
        }

        $totalWindowHours = $this->calculateDurationHours($windowStart, $windowEnd);
        if ($totalWindowHours <= 0) {
            $totalWindowHours = 8.0;
        }

        $monitorIds = DB::table('monitors')
            ->where('active_school', $schoolId)
            ->where('active', 1)
            ->pluck('id')
            ->values();

        $busyByMonitor = [];
        if ($monitorIds->isNotEmpty()) {
            $bookingRows = DB::table('booking_users as bu')
                ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNotNull('bu.monitor_id')
                ->whereNull('bu.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->select('bu.monitor_id', 'bu.hour_start', 'bu.hour_end')
                ->get();

            foreach ($bookingRows as $row) {
                $busyByMonitor[$row->monitor_id] = ($busyByMonitor[$row->monitor_id] ?? 0)
                    + $this->calculateDurationHours($row->hour_start, $row->hour_end);
            }

            $nwdRows = DB::table('monitor_nwd')
                ->where('school_id', $schoolId)
                ->whereNull('deleted_at')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->select('monitor_id', 'full_day', 'start_time', 'end_time')
                ->get();

            foreach ($nwdRows as $row) {
                $duration = $row->full_day ? $totalWindowHours : $this->calculateDurationHours($row->start_time ?: $windowStart, $row->end_time ?: $windowEnd);
                $busyByMonitor[$row->monitor_id] = ($busyByMonitor[$row->monitor_id] ?? 0) + $duration;
            }
        }

        $hoursAvailable = 0.0;
        foreach ($monitorIds as $monitorId) {
            $busy = $busyByMonitor[$monitorId] ?? 0;
            $hoursAvailable += max(0, $totalWindowHours - $busy);
        }

        $hoursSold = round($hoursSold, 1);
        $hoursAvailable = round($hoursAvailable, 1);
        $hoursRemaining = max(0, round($hoursAvailable - $hoursSold, 1));
        $usagePercent = $hoursAvailable > 0 ? round(($hoursSold / $hoursAvailable) * 100, 0) : 0;

        return [
            'hours_sold' => $hoursSold,
            'hours_available' => $hoursAvailable,
            'hours_remaining' => $hoursRemaining,
            'usage_percent' => $usagePercent,
            'participants' => $totalParticipants,
            'courses_today' => $totalCourses,
            'window_hours' => $totalWindowHours,
        ];
    }

    private function getFreeMonitorsData(int $schoolId, string $date): array
    {
        $busyMonitorIds = DB::table('booking_users as bu')
            ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
            ->leftJoin('course_dates as cd', 'cd.id', '=', 'bu.course_date_id')
            ->where('bu.school_id', $schoolId)
            ->where(function ($query) use ($date) {
                $query->whereDate('bu.date', $date)
                    ->orWhereDate('cd.date', $date);
            })
            ->where('bu.status', 1)
            ->whereNotNull('bu.monitor_id')
            ->whereNull('bu.deleted_at')
            ->whereNull('b.deleted_at')
            ->where('b.status', '<>', 2)
            ->pluck('bu.monitor_id')
            ->unique()
            ->values();

        $nwdMonitorIds = DB::table('monitor_nwd')
            ->where('school_id', $schoolId)
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->pluck('monitor_id')
            ->unique()
            ->values();

        $freeMonitorIds = DB::table('monitors_schools as ms')
            ->join('monitors as m', 'm.id', '=', 'ms.monitor_id')
            ->join('monitor_sports_degrees as msd', function ($join) use ($schoolId) {
                $join->on('msd.monitor_id', '=', 'm.id')
                    ->where('msd.school_id', '=', $schoolId);
            })
            ->where('ms.school_id', $schoolId)
            ->where('ms.active_school', 1)
            ->whereNotIn('m.id', $busyMonitorIds)
            ->whereNotIn('m.id', $nwdMonitorIds)
            ->pluck('m.id')
            ->unique()
            ->values();

        $freeMonitors = $freeMonitorIds->count();

        $freeMonitorsBySport = collect();
        if ($freeMonitors > 0) {
            $freeMonitorsBySport = DB::table('monitor_sports_degrees as msd')
                ->join('sports as s', 's.id', '=', 'msd.sport_id')
                ->where('msd.school_id', $schoolId)
                ->whereIn('msd.monitor_id', $freeMonitorIds)
                ->groupBy('s.id', 's.name', 's.icon_selected')
                ->select('s.id')
                ->selectRaw('s.id as sport_id, s.name as sport_name, s.icon_selected as icon_selected, COUNT(DISTINCT msd.monitor_id) as total')
                ->orderByDesc('total')
                ->get()
                ->map(fn($row) => [
                    'sport_id' => (int) $row->sport_id,
                    'sport_name' => $row->sport_name,
                    'icon' => $row->icon_selected,
                    'count' => (int) $row->total,
                ])
                ->values();
        }

        $freeMonitorSportsCatalog = DB::table('monitor_sports_degrees as msd')
            ->join('monitors as m', 'm.id', '=', 'msd.monitor_id')
            ->join('monitors_schools as ms', function ($join) use ($schoolId) {
                $join->on('ms.monitor_id', '=', 'm.id')
                    ->where('ms.school_id', '=', $schoolId)
                    ->where('ms.active_school', '=', 1);
            })
            ->join('sports as s', 's.id', '=', 'msd.sport_id')
            ->where('msd.school_id', $schoolId)
            ->select('s.id', 's.name', 's.icon_selected')
            ->distinct()
            ->get()
            ->map(fn($row) => [
                'sport_id' => (int) $row->id,
                'sport_name' => $row->name,
                'icon' => $row->icon_selected,
            ]);

        if ($freeMonitorSportsCatalog->isNotEmpty()) {
            $freeMonitorsById = $freeMonitorsBySport->keyBy('sport_id');
            $freeMonitorsBySport = $freeMonitorSportsCatalog
                ->map(fn($row) => [
                    'sport_id' => $row['sport_id'],
                    'sport_name' => $row['sport_name'],
                    'icon' => $row['icon'],
                    'count' => (int) ($freeMonitorsById[$row['sport_id']]['count'] ?? 0),
                ])
                ->values();
        }

        $dayHours = DB::table('courses')
            ->where('school_id', $schoolId)
            ->whereNotNull('hour_min')
            ->whereNotNull('hour_max')
            ->selectRaw('MIN(hour_min) as min_hour, MAX(hour_max) as max_hour')
            ->first();

        $hoursAvailable = 8.0;
        if ($dayHours && $dayHours->min_hour && $dayHours->max_hour) {
            try {
                $start = Carbon::createFromFormat('H:i', $dayHours->min_hour);
                $end = Carbon::createFromFormat('H:i', $dayHours->max_hour);
                $diff = $start->diffInMinutes($end) / 60;
                $hoursAvailable = $diff > 0 ? $diff : 8.0;
            } catch (\Exception $e) {
                $hoursAvailable = 8.0;
            }
        }

        $freeMonitorsHours = round($freeMonitors * $hoursAvailable, 1);

        return [
            'ids' => $freeMonitorIds,
            'count' => $freeMonitors,
            'sports' => $freeMonitorsBySport,
            'hours' => $freeMonitorsHours,
        ];
    }

    private function calculateDurationHours(?string $start, ?string $end): float
    {
        if (!$start || !$end) {
            return 0.0;
        }

        try {
            $startTime = Carbon::createFromFormat('H:i', substr($start, 0, 5));
            $endTime = Carbon::createFromFormat('H:i', substr($end, 0, 5));
            $minutes = $startTime->diffInMinutes($endTime, false);
            if ($minutes <= 0) {
                return 0.0;
            }
            return round($minutes / 60, 2);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    private function paginateCollection(Collection $collection, int $page, int $perPage): array
    {
        $total = $collection->count();
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => $collection->slice($offset, $perPage)->values(),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / max($perPage, 1)),
            ],
        ];
    }

    private function makeTimeLabel(?string $start, ?string $end): ?string
    {
        if ($start && $end) {
            return "{$start} - {$end}";
        }

        return $start ?? $end;
    }

    private function countPendingPayments(int $schoolId, ?string $date = null): int
    {
        $today = $date ?? Carbon::today()->toDateString();

        return DB::table('booking_users as bu')
            ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
            ->where('bu.school_id', $schoolId)
            ->whereNull('bu.deleted_at')
            ->whereNull('b.deleted_at')
            ->where('bu.status', 1)
            ->where('b.status', '<>', 2)
            ->whereDate('bu.date', $today)
            ->where(function ($query) use ($today) {
                $query->whereRaw(
                    '(SELECT COALESCE(SUM(bu2.price),0) FROM booking_users bu2 WHERE bu2.booking_id = b.id AND bu2.deleted_at IS NULL AND bu2.status = 1 AND DATE(bu2.date) = ?) - COALESCE(b.paid_total, 0) > 0.01',
                    [$today]
                )->orWhereRaw(
                    '(SELECT COALESCE(SUM(bu2.price),0) FROM booking_users bu2 WHERE bu2.booking_id = b.id AND bu2.deleted_at IS NULL AND bu2.status = 1 AND DATE(bu2.date) = ?) = 0 AND (b.paid = 0 OR b.paid IS NULL)',
                    [$today]
                );
            })
            ->distinct('bu.booking_id')
            ->count('bu.booking_id');
    }

    private function resolveSchoolId(Request $request): ?int
    {
        $school = $this->getSchool($request);
        if (!$school) {
            return null;
        }

        $request->merge(['school_id' => $school->id]);

        return (int) $school->id;
    }

    private function getSubgroupMaxParticipantsColumn(): string
    {
        return Schema::hasColumn('course_subgroups', 'max_participants') ? 'sg.max_participants' : 'NULL';
    }
}
