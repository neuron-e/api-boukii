<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\AppBaseController;
use App\Models\AppNotification;
use App\Models\Monitor;
use App\Models\School;
use App\Services\MonitorNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends AppBaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('perPage', 20);
        $perPage = max(1, min($perPage, 200));
        $query = AppNotification::query()->orderByDesc('id');

        $recipientType = $request->input('recipient_type');
        if ($recipientType) {
            $query->where('recipient_type', $recipientType);
        }

        $schoolId = $request->input('school_id');
        if ($schoolId) {
            $query->where('recipient_type', 'school')
                ->where('recipient_id', $schoolId);
        }

        $monitorId = $request->input('monitor_id');
        if ($monitorId) {
            $query->where('recipient_type', 'monitor')
                ->where('recipient_id', $monitorId);
        }

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        if ($request->boolean('scheduled_only')) {
            $query->whereNotNull('scheduled_at')->whereNull('sent_at');
        }

        $items = $query->paginate($perPage);
        $pageItems = $items->items();

        $schoolIds = [];
        $monitorIds = [];
        foreach ($pageItems as $item) {
            if ($item->recipient_type === 'school') {
                $schoolIds[] = $item->recipient_id;
            } elseif ($item->recipient_type === 'monitor') {
                $monitorIds[] = $item->recipient_id;
                if ($item->school_id) {
                    $schoolIds[] = $item->school_id;
                }
            }
        }
        $schoolNames = !empty($schoolIds)
            ? School::query()->whereIn('id', $schoolIds)->pluck('name', 'id')->all()
            : [];
        $monitorNames = !empty($monitorIds)
            ? Monitor::query()->whereIn('id', $monitorIds)->get()->mapWithKeys(function ($monitor) {
                return [$monitor->id => trim($monitor->first_name . ' ' . $monitor->last_name)];
            })->all()
            : [];

        $pageItems = array_map(function ($item) use ($schoolNames, $monitorNames) {
            $payload = $item->payload ?? [];
            if (!empty($payload['recipient_name'])) {
                return $item;
            }

            if ($item->recipient_type === 'school') {
                $payload['recipient_name'] = $schoolNames[$item->recipient_id] ?? null;
            } elseif ($item->recipient_type === 'monitor') {
                $payload['recipient_name'] = $monitorNames[$item->recipient_id] ?? null;
                if ($item->school_id) {
                    $payload['school_name'] = $schoolNames[$item->school_id] ?? null;
                }
            }

            $item->payload = $payload;
            return $item;
        }, $pageItems);

        return response()->json([
            'success' => true,
            'data' => $pageItems,
            'total' => $items->total(),
            'per_page' => $items->perPage(),
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $base = AppNotification::query()->whereNotNull('sent_at');
        $totalSent = (clone $base)->count();
        $dashboardSent = (clone $base)->where('recipient_type', 'school')->count();
        $appSent = (clone $base)->where('recipient_type', 'monitor')->count();
        $totalRead = (clone $base)->whereNotNull('read_at')->count();
        $readRate = $totalSent > 0 ? round(($totalRead / $totalSent) * 100, 2) : 0;

        return $this->sendResponse([
            'total_sent' => $totalSent,
            'dashboard_sent' => $dashboardSent,
            'app_sent' => $appSent,
            'read_rate' => $readRate,
        ], 'Notification stats');
    }

    public function store(Request $request, MonitorNotificationService $notificationService): JsonResponse
    {
        $payload = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'nullable|string',
            'recipient_type' => 'required|string|in:schools,monitors,both',
            'priority' => 'nullable|string|in:low,medium,high',
            'school_ids' => 'array',
            'school_ids.*' => 'integer',
            'monitor_ids' => 'array',
            'monitor_ids.*' => 'integer',
            'all_schools' => 'boolean',
            'all_monitors' => 'boolean',
            'schedule_at' => 'nullable|date',
            'send_push' => 'boolean',
        ]);

        $actorId = $request->user()?->id;
        $scheduleAt = isset($payload['schedule_at']) ? Carbon::parse($payload['schedule_at']) : null;
        $sendNow = !$scheduleAt || $scheduleAt->isPast();
        $sendPush = $payload['send_push'] ?? true;
        $batchId = (string) Str::uuid();

        $title = trim($payload['title']);
        $bodyHtml = (string) ($payload['body'] ?? '');
        $bodyText = trim(strip_tags($bodyHtml));

        $allSchoolsFlag = (bool) ($payload['all_schools'] ?? false);
        $allMonitorsFlag = (bool) ($payload['all_monitors'] ?? false);

        $basePayload = [
            'source' => 'superadmin',
            'body_html' => $bodyHtml,
            'priority' => $payload['priority'] ?? 'medium',
            'batch_id' => $batchId,
            'recipient_type' => $payload['recipient_type'],
            'all_schools' => $allSchoolsFlag,
            'all_monitors' => $allMonitorsFlag,
        ];

        $notificationsCreated = 0;

        $recipientType = $payload['recipient_type'];
        if ($recipientType === 'schools' || $recipientType === 'both') {
            $schoolIds = $payload['school_ids'] ?? [];
            if ($allSchoolsFlag || empty($schoolIds)) {
                $schoolIds = School::query()->pluck('id')->all();
            }
            $schoolNames = School::query()->whereIn('id', $schoolIds)->pluck('name', 'id')->all();

            foreach ($schoolIds as $schoolId) {
                $schoolName = $schoolNames[$schoolId] ?? null;
                AppNotification::create([
                    'recipient_type' => 'school',
                    'recipient_id' => $schoolId,
                    'actor_id' => $actorId,
                    'school_id' => $schoolId,
                    'type' => 'custom_message',
                    'title' => $title,
                    'body' => $bodyText,
                    'payload' => array_merge($basePayload, [
                        'school_id' => $schoolId,
                        'recipient_name' => $schoolName,
                    ]),
                    'event_date' => $scheduleAt?->toDateString(),
                    'scheduled_at' => $scheduleAt,
                    'sent_at' => $sendNow ? now() : null,
                ]);
                $notificationsCreated++;
            }
        }

        if ($recipientType === 'monitors' || $recipientType === 'both') {
            $monitorIds = $payload['monitor_ids'] ?? [];
            if ($allMonitorsFlag || empty($monitorIds)) {
                $monitorIds = Monitor::query()
                    ->where('active', 1)
                    ->pluck('id')
                    ->all();
            }

            foreach ($monitorIds as $monitorId) {
                $monitor = Monitor::find($monitorId);
                if (!$monitor) {
                    continue;
                }
                $monitorName = trim($monitor->first_name . ' ' . $monitor->last_name);

                $record = AppNotification::create([
                    'recipient_type' => 'monitor',
                    'recipient_id' => $monitorId,
                    'actor_id' => $actorId,
                    'school_id' => $monitor->active_school,
                    'type' => 'custom_message',
                    'title' => $title,
                    'body' => $bodyText,
                    'payload' => array_merge($basePayload, [
                        'monitor_id' => $monitorId,
                        'school_id' => $monitor->active_school,
                        'recipient_name' => $monitorName,
                    ]),
                    'event_date' => $scheduleAt?->toDateString(),
                    'scheduled_at' => $scheduleAt,
                    'sent_at' => $sendNow ? now() : null,
                ]);

                if ($sendNow && $sendPush) {
                    $notificationService->sendCustom(
                        $monitorId,
                        ['title' => $title, 'body' => $bodyText],
                        array_merge($basePayload, ['monitor_id' => $monitorId, 'school_id' => $monitor->active_school]),
                        $actorId,
                        $record->id
                    );
                }

                $notificationsCreated++;
            }
        }

        return $this->sendResponse(['created' => $notificationsCreated], 'Notifications created');
    }
}
