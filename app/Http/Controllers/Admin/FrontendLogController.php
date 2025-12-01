<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FrontendLogController extends Controller
{
    /**
     * Store debug logs sent from the frontend (Angular) into a local file.
     * Intended for local/dev troubleshooting; uses auth:sanctum with admin ability.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'logs' => 'required|array|min:1',
            'meta' => 'nullable|array',
        ]);

        $user = Auth::user();
        $meta = $data['meta'] ?? [];

        $entries = [];
        foreach ($data['logs'] as $log) {
            if (!is_array($log)) {
                continue;
            }
            $entries[] = [
                'ts' => $log['ts'] ?? now()->toISOString(),
                'event' => $log['event'] ?? 'frontend-log',
                'payload' => $log['payload'] ?? [],
                'meta' => $meta,
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'route' => $request->path(),
                'ip' => $request->ip(),
            ];
        }

        if (empty($entries)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid log entries provided.',
            ], 422);
        }

        $path = 'logs/frontend-debug.log';
        if (!Storage::disk('local')->exists('logs')) {
            Storage::disk('local')->makeDirectory('logs');
        }
        foreach ($entries as $entry) {
            Storage::disk('local')->append($path, json_encode($entry));
        }

        return response()->json([
            'success' => true,
            'message' => 'Frontend logs stored',
            'stored' => count($entries),
        ]);
    }
}
