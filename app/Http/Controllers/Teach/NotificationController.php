<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\AppBaseController;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends AppBaseController
{
    public function index(Request $request): JsonResponse
    {
        $monitorId = $this->resolveMonitorId($request);
        if (!$monitorId) {
            return $this->sendError('Monitor not found for user', [], 404);
        }

        $perPage = max(1, (int) $request->input('perPage', 20));
        $query = AppNotification::query()
            ->where('recipient_type', 'monitor')
            ->where('recipient_id', $monitorId)
            ->orderByDesc('id');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'total' => $items->total(),
            'per_page' => $items->perPage(),
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $monitorId = $this->resolveMonitorId($request);
        if (!$monitorId) {
            return $this->sendError('Monitor not found for user', [], 404);
        }

        $count = AppNotification::query()
            ->where('recipient_type', 'monitor')
            ->where('recipient_id', $monitorId)
            ->whereNull('read_at')
            ->count();

        return $this->sendResponse(['count' => $count], 'Unread count');
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $monitorId = $this->resolveMonitorId($request);
        if (!$monitorId) {
            return $this->sendError('Monitor not found for user', [], 404);
        }

        $notification = AppNotification::query()
            ->where('recipient_type', 'monitor')
            ->where('recipient_id', $monitorId)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->sendError('Notification not found', [], 404);
        }

        if (!$notification->read_at) {
            $notification->read_at = now();
            $notification->save();
        }

        return $this->sendResponse(['id' => $notification->id], 'Notification read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $monitorId = $this->resolveMonitorId($request);
        if (!$monitorId) {
            return $this->sendError('Monitor not found for user', [], 404);
        }

        $updated = AppNotification::query()
            ->where('recipient_type', 'monitor')
            ->where('recipient_id', $monitorId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->sendResponse(['updated' => $updated], 'Notifications marked as read');
    }

    private function resolveMonitorId(Request $request): ?int
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        $monitorId = $request->input('monitor_id');
        $query = \App\Models\Monitor::where('user_id', $user->id);

        if ($monitorId) {
            return $query->where('id', $monitorId)->value('id');
        }

        return $query->value('id');
    }
}
