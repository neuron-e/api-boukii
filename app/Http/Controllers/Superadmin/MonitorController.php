<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\AppBaseController;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorController extends AppBaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Monitor::query()
            ->where('active', 1)
            ->whereNull('deleted_at');

        $schoolId = $request->input('school_id');
        if ($schoolId) {
            $query->whereHas('monitorsSchools', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId)->where('active_school', 1);
            });
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, (int) $request->input('perPage', 50));
        $items = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'total' => $items->total(),
            'per_page' => $items->perPage(),
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
        ]);
    }
}
