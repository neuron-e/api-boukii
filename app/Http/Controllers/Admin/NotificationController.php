<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends AppBaseController
{
    public function index(Request $request): JsonResponse
    {
        $school = $this->getSchool($request);
        if (!$school) {
            return $this->sendError('School not found for user', [], 404);
        }

        $perPage = max(1, (int) $request->input('perPage', 20));
        $query = AppNotification::query()
            ->where('recipient_type', 'school')
            ->where('recipient_id', $school->id)
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

    public function unreadCount(Request $request): JsonResponse
    {
        $school = $this->getSchool($request);
        if (!$school) {
            return $this->sendError('School not found for user', [], 404);
        }

        $count = AppNotification::query()
            ->where('recipient_type', 'school')
            ->where('recipient_id', $school->id)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->whereNull('read_at')
            ->count();

        return $this->sendResponse(['count' => $count], 'Unread count');
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $school = $this->getSchool($request);
        if (!$school) {
            return $this->sendError('School not found for user', [], 404);
        }

        $notification = AppNotification::query()
            ->where('recipient_type', 'school')
            ->where('recipient_id', $school->id)
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
        $school = $this->getSchool($request);
        if (!$school) {
            return $this->sendError('School not found for user', [], 404);
        }

        $updated = AppNotification::query()
            ->where('recipient_type', 'school')
            ->where('recipient_id', $school->id)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->sendResponse(['updated' => $updated], 'Notifications marked as read');
    }
}
