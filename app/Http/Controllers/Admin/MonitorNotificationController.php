<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorNotificationController extends AppBaseController
{
    public function index(Request $request, int $monitorId): JsonResponse
    {
        $perPage = max(1, (int) $request->input('perPage', 20));

        $query = AppNotification::query()
            ->where('recipient_type', 'monitor')
            ->where('recipient_id', $monitorId)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
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

    public function markRead(Request $request, int $monitorId, int $notificationId): JsonResponse
    {
        $notification = AppNotification::query()
            ->where('recipient_type', 'monitor')
            ->where('recipient_id', $monitorId)
            ->where('id', $notificationId)
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
}
