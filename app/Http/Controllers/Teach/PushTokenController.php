<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\AppBaseController;
use App\Models\MonitorPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends AppBaseController
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:2048',
            'platform' => 'nullable|string|max:32',
            'device_id' => 'nullable|string|max:128',
            'locale' => 'nullable|string|max:16',
            'monitor_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $monitorId = $this->resolveMonitorId($request, $user?->id);

        if (!$monitorId) {
            return $this->sendError('Monitor not found for user', [], 404);
        }

        $token = $request->input('token');
        $pushToken = MonitorPushToken::firstOrNew(['token' => $token]);
        $pushToken->monitor_id = $monitorId;
        $pushToken->platform = $request->input('platform');
        $pushToken->device_id = $request->input('device_id');
        $pushToken->locale = $request->input('locale');
        $pushToken->app = 'teach';
        $pushToken->last_seen_at = now();
        $pushToken->save();

        return $this->sendResponse([
            'id' => $pushToken->id,
            'monitor_id' => $pushToken->monitor_id,
        ], 'Push token registered');
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->sendError('Unauthorized', [], 401);
        }

        $monitorIds = $user->monitors()->pluck('id');
        $deleted = MonitorPushToken::where('token', $token)
            ->whereIn('monitor_id', $monitorIds)
            ->delete();

        return $this->sendResponse([
            'deleted' => $deleted,
        ], 'Push token removed');
    }

    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|string|max:50',
            'date' => 'nullable|date',
        ]);

        $user = $request->user();
        $monitorId = $this->resolveMonitorId($request, $user?->id);

        if (!$monitorId) {
            return $this->sendError('Monitor not found for user', [], 404);
        }

        $type = $request->input('type', 'booking_created');
        $payload = [
            'date' => $request->input('date', now()->toDateString()),
            'hour_start' => '09:00',
            'hour_end' => '10:00',
            'booking_id' => 0,
            'course_date_id' => 0,
        ];

        app(\App\Services\MonitorNotificationService::class)->notifyAssignment(
            $monitorId,
            $type,
            $payload,
            [],
            $user?->id
        );

        return $this->sendResponse([
            'monitor_id' => $monitorId,
            'type' => $type,
            'payload' => $payload,
        ], 'Test push sent');
    }

    private function resolveMonitorId(Request $request, ?int $userId): ?int
    {
        if (!$userId) {
            return null;
        }

        $monitorId = $request->input('monitor_id');
        $query = \App\Models\Monitor::where('user_id', $userId);

        if ($monitorId) {
            return $query->where('id', $monitorId)->value('id');
        }

        return $query->value('id');
    }
}
