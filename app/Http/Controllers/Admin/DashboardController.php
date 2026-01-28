<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Course;
use App\Models\CourseSubgroup;
use App\Models\Payment;
use App\Models\School;
use App\Services\Admin\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        $cacheKey = "dashboard_system_stats_{$schoolId}_{$selectedDate}";
        $payload = Cache::remember($cacheKey, 60, function () use ($schoolId, $selectedDate) {
            $pendingPayments = $this->countPendingPayments($schoolId, $selectedDate);

            $overbookings = CourseSubgroup::whereHas('course', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
                ->whereRaw(
                    '(SELECT COUNT(*) FROM booking_users bu JOIN bookings b ON b.id = bu.booking_id WHERE bu.course_subgroup_id = course_subgroups.id AND bu.status = 1 AND bu.deleted_at IS NULL AND b.deleted_at IS NULL AND b.status <> 2 AND date(bu.date) = ?) > course_subgroups.max_participants',
                    [$selectedDate]
                )
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
                ->whereNull('bu.deleted_at')
                ->whereNull('c.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('bu.school_id', $schoolId)
                ->where('bu.status', 1)
                ->where('b.status', '<>', 2)
                ->whereDate('bu.date', $today)
                ->whereNull('bu.monitor_id')
                ->where('c.course_type', 2)
                ->selectRaw(
                    "COUNT(DISTINCT COALESCE(bu.group_id, CONCAT(bu.booking_id, '_', DATE(bu.date), '_', IFNULL(bu.hour_start,''), '_', IFNULL(bu.hour_end,'')))) as aggregate"
                )
                ->value('aggregate') ?? 0;

            $unassigned = $unassignedGroupSubgroups + $unassignedPrivate;

            return [
                'pending_payments' => $pendingPayments,
                'overbookings' => $overbookings,
                'unassigned_courses' => $unassigned,
                'unassigned_group_subgroups' => $unassignedGroupSubgroups,
                'unassigned_private_courses' => $unassignedPrivate,
            ];
        });

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

        $allowed = ['pending_payments', 'overbooked_groups', 'unassigned_groups', 'unassigned_private'];
        if (!in_array($type, $allowed, true)) {
            return $this->sendError('Invalid type', [], 400);
        }

        switch ($type) {
            case 'pending_payments':
                $rows = DB::table('booking_users as bu')
                    ->join('bookings as b', 'b.id', '=', 'bu.booking_id')
                    ->leftJoin('clients as c', 'c.id', '=', 'b.client_main_id')
                    ->where('bu.school_id', $schoolId)
                    ->whereNull('bu.deleted_at')
                    ->whereNull('b.deleted_at')
                    ->where('bu.status', 1)
                    ->where('b.status', '<>', 2)
                    ->whereDate('bu.date', $date)
                    ->where(function ($query) {
                        $query->where('b.paid', 0)->orWhereNull('b.paid');
                    })
                    ->groupBy('bu.booking_id', 'c.first_name', 'c.last_name')
                    ->selectRaw('bu.booking_id as booking_id')
                    ->selectRaw("CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) as client_name")
                    ->selectRaw('COUNT(bu.id) as participants')
                    ->selectRaw('COALESCE(SUM(bu.price), 0) as total_due')
                    ->selectRaw('MIN(bu.hour_start) as start_time')
                    ->selectRaw('MAX(bu.hour_end) as end_time')
                    ->limit(200)
                    ->get()
                    ->map(function ($row) {
                        return [
                            'booking_id' => $row->booking_id,
                            'client_name' => trim($row->client_name) ?: null,
                            'participants' => (int) $row->participants,
                            'total_due' => round((float) $row->total_due, 2),
                            'time_label' => $this->makeTimeLabel($row->start_time, $row->end_time),
                        ];
                    });
                break;
            case 'overbooked_groups':
                $rows = DB::table('booking_users as bu')
                    ->join('course_subgroups as sg', 'sg.id', '=', 'bu.course_subgroup_id')
                    ->join('courses as c', 'c.id', '=', 'bu.course_id')
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
                    ->whereDate('bu.date', $date)
                    ->groupBy('sg.id', 'c.name', 'sg.max_participants', 'cd.hour_start', 'cd.hour_end', 'd.name', 'd.degree_order', 'sg.course_group_id', 'sg.degree_id')
                    ->havingRaw('COUNT(bu.id) > sg.max_participants')
                    ->selectRaw('sg.id as subgroup_id')
                    ->selectRaw('c.name as course_name')
                    ->selectRaw('d.name as degree_name')
                    ->selectRaw('d.degree_order as degree_order')
                    ->selectRaw('(SELECT COUNT(*) FROM course_subgroups sg2 WHERE IFNULL(sg2.course_group_id,0) = IFNULL(sg.course_group_id,0) AND IFNULL(sg2.degree_id,0) = IFNULL(sg.degree_id,0) AND sg2.deleted_at IS NULL AND sg2.id <= sg.id) as subgroup_number')
                    ->selectRaw('sg.max_participants as max_participants')
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
                    ->whereDate('bu.date', $date)
                    ->whereNull('bu.monitor_id')
                    ->where('c.course_type', 1)
                    ->groupBy('sg.id', 'c.name', 'cd.hour_start', 'cd.hour_end', 'd.name', 'd.degree_order', 'sg.course_group_id', 'sg.degree_id')
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
                    ->whereDate('date', $date)
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
                        if ($bookingUser->group_id) {
                            return 'g_' . $bookingUser->group_id;
                        }

                        $start = $bookingUser->hour_start ?? '';
                        $end = $bookingUser->hour_end ?? '';
                        $date = $bookingUser->date?->toDateString() ?? '';

                        return 'b_' . $bookingUser->booking_id . '_' . $date . '_' . $start . '_' . $end;
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

                        return [
                            'course_name' => $courseName,
                            'client_name' => $clientName,
                            'participants' => $group->count(),
                            'time_label' => $this->makeTimeLabel($startTime, $endTime),
                        ];
                    })
                    ->values();
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
        $cacheKey = "dashboard_operations_{$schoolId}_" . $today->toDateString();

        $payload = Cache::remember($cacheKey, 60, function () use ($schoolId, $today) {
            $coursesToday = Course::where('school_id', $schoolId)
                ->whereHas('courseDates', fn($q) => $q->whereDate('date', $today))
                ->count();

            $groupCourses = BookingUser::where('school_id', $schoolId)
                ->whereHas('course', fn($q) => $q->where('course_type', 1))
                ->whereDate('date', $today)
                ->where('status', 1)
                ->whereNotNull('course_subgroup_id')
                ->distinct('course_subgroup_id')
                ->count('course_subgroup_id');

            $privateCourses = DB::table('booking_users as bu')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNull('bu.deleted_at')
                ->where('c.course_type', 2)
                ->selectRaw(
                    "COUNT(DISTINCT COALESCE(bu.group_id, CONCAT(bu.booking_id, '_', DATE(bu.date), '_', IFNULL(bu.hour_start,''), '_', IFNULL(bu.hour_end,'')))) as aggregate"
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
                ->selectRaw('s.id as sport_id, s.name as sport_name, s.icon_collective as icon_collective, s.icon_selected as icon_selected, COUNT(DISTINCT bu.course_subgroup_id) as total')
                ->orderByDesc('total')
                ->get()
                ->map(fn($row) => [
                    'sport_id' => (int) $row->sport_id,
                    'sport_name' => $row->sport_name,
                    'icon' => $row->icon_collective ?: $row->icon_selected,
                    'count' => (int) $row->total,
                ])
                ->values();

            $privateSports = DB::table('booking_users as bu')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->join('sports as s', 's.id', '=', 'c.sport_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNull('bu.deleted_at')
                ->where('c.course_type', 2)
                ->groupBy('s.id', 's.name', 's.icon_prive', 's.icon_selected')
                ->selectRaw("s.id as sport_id, s.name as sport_name, s.icon_prive as icon_prive, s.icon_selected as icon_selected, COUNT(DISTINCT COALESCE(bu.group_id, CONCAT(bu.booking_id, '_', DATE(bu.date), '_', IFNULL(bu.hour_start,''), '_', IFNULL(bu.hour_end,'')))) as total")
                ->orderByDesc('total')
                ->get()
                ->map(fn($row) => [
                    'sport_id' => (int) $row->sport_id,
                    'sport_name' => $row->sport_name,
                    'icon' => $row->icon_prive ?: $row->icon_selected,
                    'count' => (int) $row->total,
                ])
                ->values();

            $assignedMonitors = BookingUser::where('school_id', $schoolId)
                ->whereNotNull('monitor_id')
                ->whereDate('date', $today)
                ->where('status', 1)
                ->distinct('monitor_id')
                ->count('monitor_id');

            $assignedMonitorsGroup = DB::table('booking_users as bu')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNotNull('bu.monitor_id')
                ->where('c.course_type', 1)
                ->distinct('bu.monitor_id')
                ->count('bu.monitor_id');

            $assignedMonitorsPrivate = DB::table('booking_users as bu')
                ->join('courses as c', 'c.id', '=', 'bu.course_id')
                ->where('bu.school_id', $schoolId)
                ->whereDate('bu.date', $today)
                ->where('bu.status', 1)
                ->whereNotNull('bu.monitor_id')
                ->where('c.course_type', 2)
                ->distinct('bu.monitor_id')
                ->count('bu.monitor_id');

            $busyMonitorIds = BookingUser::where('school_id', $schoolId)
                ->whereDate('date', $today)
                ->where('status', 1)
                ->whereNotNull('monitor_id')
                ->pluck('monitor_id')
                ->unique()
                ->values();

            $nwdMonitorIds = DB::table('monitor_nwd')
                ->where('school_id', $schoolId)
                ->whereNull('deleted_at')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->pluck('monitor_id')
                ->unique()
                ->values();

            $freeMonitorIds = DB::table('monitors as m')
                ->where('m.active_school', $schoolId)
                ->where('m.active', 1)
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

            $totalCapacity = CourseSubgroup::whereHas('course', fn($q) => $q->where('school_id', $schoolId))->sum('max_participants');

            $usedCapacity = BookingUser::whereHas('booking', fn($q) => $q->where('school_id', $schoolId))
                ->where('school_id', $schoolId)
                ->where('status', 1)
                ->whereDate('date', $today)
                ->count();

            $occupancy = $totalCapacity > 0 ? round(($usedCapacity / $totalCapacity) * 100, 2) : 0;

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

        $cacheKey = "dashboard_courses_capacity_{$schoolId}_{$today}";
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

        $forecastKey = "dashboard_forecast_{$schoolId}_{$days}_{$startDate->toDateString()}";
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
                ];
            }

            $rows = DB::table('booking_users as bu')
                ->leftJoin('bookings as b', 'bu.booking_id', '=', 'b.id')
                ->leftJoin('courses as c', 'c.id', '=', 'bu.course_id')
                ->where('bu.school_id', $schoolId)
                ->whereNull('b.deleted_at')
                ->where('b.status', '<>', 2)
                ->whereBetween('bu.date', [$startDate->toDateString(), $endDate->toDateString()])
                ->where('bu.status', 1)
                ->groupBy('bu.date')
                ->selectRaw('bu.date as date')
                ->selectRaw('COUNT(*) as bookings')
                ->selectRaw('COUNT(*) as participants')
                ->selectRaw('COALESCE(SUM(bu.price), 0) as expected_revenue')
                ->selectRaw('COUNT(DISTINCT CASE WHEN b.paid = 0 OR b.paid IS NULL THEN bu.booking_id END) as pending_payments')
                ->selectRaw('COUNT(DISTINCT bu.monitor_id) as assigned_monitors')
                ->selectRaw('COUNT(DISTINCT CASE WHEN c.course_type = 2 THEN COALESCE(bu.group_id, CONCAT(bu.booking_id, "_", DATE(bu.date), "_", IFNULL(bu.hour_start,""), "_", IFNULL(bu.hour_end,""))) END) as private_courses')
                ->selectRaw('COUNT(DISTINCT CASE WHEN c.course_type = 1 THEN bu.course_subgroup_id END) as group_courses')
                ->selectRaw('COUNT(DISTINCT CASE WHEN bu.monitor_id IS NULL THEN COALESCE(bu.group_id, CONCAT(bu.booking_id, "_", DATE(bu.date), "_", IFNULL(bu.hour_start,""), "_", IFNULL(bu.hour_end,""))) END) as unassigned')
                ->selectRaw('COUNT(DISTINCT CASE WHEN b.paid = 0 OR b.paid IS NULL THEN bu.booking_id END) as unpaid')
                ->get();

            foreach ($rows as $row) {
                $dateKey = Carbon::parse($row->date)->toDateString();
                if (!isset($forecast[$dateKey])) {
                    continue;
                }

                $privateCourses = (int) ($row->private_courses ?? 0);
                $groupCourses = (int) ($row->group_courses ?? 0);
                $totalCourses = $privateCourses + $groupCourses;

                $busyMonitorIds = DB::table('booking_users')
                    ->where('school_id', $schoolId)
                    ->whereDate('date', $dateKey)
                    ->where('status', 1)
                    ->whereNotNull('monitor_id')
                    ->pluck('monitor_id')
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

                $forecast[$dateKey] = array_merge($forecast[$dateKey], [
                    'bookings' => (int) $row->bookings,
                    'participants' => (int) $row->participants,
                    'expected_revenue' => round((float) $row->expected_revenue, 2),
                    'pending_payments' => (int) $row->pending_payments,
                    'assigned_monitors' => (int) $row->assigned_monitors,
                    'private_courses' => $privateCourses,
                    'group_courses' => $groupCourses,
                    'courses' => $totalCourses,
                    'unassigned' => (int) ($row->unassigned ?? 0),
                    'free_monitors' => $freeMonitors,
                    'unpaid' => (int) ($row->unpaid ?? 0),
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

            $bookingsStat = BookingUser::where('school_id', $schoolId)
                ->whereDate('date', $today)
                ->where('status', 1)
                ->selectRaw('COUNT(*) as total_bookings, COALESCE(SUM(price), 0) as total_price')
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

    private function buildGroupCourseCollection(int $schoolId, string $date): Collection
    {
        $entries = BookingUser::with(['course:id,name,course_type', 'monitor:id,first_name,last_name', 'booking:id,paid'])
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
            ->whereDate('date', $date)
            ->where('status', 1)
            ->whereHas('course', fn($q) => $q->where('course_type', 1))
            ->get();

        return $entries->groupBy('course_id')->map(function ($group) {
            $course = $group->first()?->course;
            $startTime = $group->pluck('hour_start')->filter()->sort()->first();
            $endTime = $group->pluck('hour_end')->filter()->sort()->last();

            $pendingPayments = $group->filter(fn($entry) => !($entry->booking?->paid ?? true))
                ->pluck('booking_id')
                ->unique()
                ->count();

            return [
                'course_id' => $course?->id,
                'course_name' => $course?->name ?? 'Curso colectivo',
                'groups_count' => $group->pluck('course_subgroup_id')->filter()->unique()->count(),
                'assigned_monitors' => $group->pluck('monitor_id')->filter()->unique()->count(),
                'participants' => $group->count(),
                'pending_payments' => $pendingPayments,
                'start_time' => $startTime,
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
        $entries = BookingUser::with(['course:id,name,course_type', 'client:id,first_name,last_name', 'monitor:id,first_name,last_name', 'booking:id,paid'])
            ->select([
                'id',
                'course_id',
                'client_id',
                'monitor_id',
                'booking_id',
                'group_id',
                'hour_start',
                'hour_end',
                'price',
                'currency',
                'date',
                'status',
            ])
            ->where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->where('status', 1)
            ->whereHas('course', fn($q) => $q->where('course_type', 2))
            ->orderBy('hour_start')
            ->limit(120)
            ->get();

        return $entries
            ->groupBy(function ($bookingUser) {
                if ($bookingUser->group_id) {
                    return 'g_' . $bookingUser->group_id;
                }

                $start = $bookingUser->hour_start ?? '';
                $end = $bookingUser->hour_end ?? '';
                $date = $bookingUser->date?->toDateString() ?? '';

                return 'b_' . $bookingUser->booking_id . '_' . $date . '_' . $start . '_' . $end;
            })
            ->map(function ($group) {
                $first = $group->first();
                $courseName = $first?->course?->name ?? 'Privado';
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
                    return (bool) ($item->booking?->paid ?? false);
                });

                $startTime = $group->pluck('hour_start')->filter()->sort()->first();
                $endTime = $group->pluck('hour_end')->filter()->sort()->last();
                $timeLabel = $this->makeTimeLabel($startTime, $endTime);

                return [
                    'id' => $first?->group_id ?? $first?->id,
                    'course_name' => $courseName,
                    'client_name' => $clientName,
                    'monitor_name' => $monitorName,
                    'is_paid' => $bookingPaid,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration' => $first?->formatted_duration,
                    'duration_hours' => $this->calculateDurationHours($startTime, $endTime),
                    'date' => $first?->date?->toDateString(),
                    'status' => $group->contains(fn($item) => $item->monitor_id) ? 'assigned' : 'pending',
                    'price' => round((float) $group->sum('price'), 2),
                    'participants' => $group->count(),
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
            ->where('bu.school_id', $schoolId)
            ->whereDate('bu.date', $today)
            ->where('bu.status', 1)
            ->whereNull('bu.deleted_at')
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
            ->select('sg.course_id', 'sg.max_participants', 'c.is_flexible')
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

        $entries = BookingUser::where('school_id', $schoolId)
            ->whereDate('date', $today)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereHas('course', fn($q) => $q->where('course_type', 2))
            ->select('booking_id', 'group_id', 'hour_start', 'hour_end', 'monitor_id')
            ->get();

        $groups = [];
        foreach ($entries as $entry) {
            $start = $entry->hour_start ?? '';
            $end = $entry->hour_end ?? '';
            $groupKey = $entry->group_id ? 'g_' . $entry->group_id : ('b_' . $entry->booking_id . '_' . $start . '_' . $end);
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
            $bookingRows = DB::table('booking_users')
                ->where('school_id', $schoolId)
                ->whereDate('date', $today)
                ->where('status', 1)
                ->whereNotNull('monitor_id')
                ->select('monitor_id', 'hour_start', 'hour_end')
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
            ->where(function ($query) {
                $query->where('b.paid', 0)->orWhereNull('b.paid');
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
}
