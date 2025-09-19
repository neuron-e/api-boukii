<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\BookingUser;
use App\Models\BookingUserTime;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimingController extends AppBaseController
{
    /**
     * POST /api/v4/timing/ingest
     */
    public function ingest(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-Api-Key');
        $expected = config('services.timing.api_key') ?? env('TIMING_API_KEY');
        if (!$expected || $apiKey !== $expected) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key',
                'code' => 'invalid_api_key',
            ], 401);
        }

        $data = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'items' => ['required', 'array'],
            'items.*.booking_user_id' => ['required', 'integer'],
            'items.*.course_id' => ['nullable', 'integer'],
            'items.*.client_id' => ['nullable', 'integer'],
            'items.*.date' => ['required', 'date'],
            'items.*.time_ms' => ['required', 'integer', 'min:0'],
            'items.*.source' => ['nullable', 'string'],
            'items.*.external_id' => ['nullable', 'string'],
            'items.*.meta' => ['nullable', 'array'],
        ]);

        $created = 0; $updated = 0; $skipped = 0;
        foreach ($data['items'] as $item) {
            $attrs = [
                'booking_user_id' => $item['booking_user_id'],
                'date' => Carbon::parse($item['date']),
                'time_ms' => $item['time_ms'],
                'source' => $item['source'] ?? null,
                'external_id' => $item['external_id'] ?? null,
            ];

            $values = [
                'school_id' => $data['school_id'] ?? null,
                'course_id' => $item['course_id'] ?? null,
                'client_id' => $item['client_id'] ?? null,
                'meta' => $item['meta'] ?? null,
            ];

            // Idempotent upsert
            $existing = BookingUserTime::where($attrs)->first();
            if ($existing) {
                $existing->fill($values)->save();
                $updated++;
            } else {
                BookingUserTime::create($attrs + $values);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Timing data ingested',
            'data' => compact('created', 'updated', 'skipped'),
        ]);
    }

    /**
     * GET /api/v4/courses/{course}/timing/summary
     */
    public function courseSummary(Course $course): JsonResponse
    {
        // Aggregate times
        $rows = BookingUserTime::select(
            DB::raw('COUNT(*) as total_records'),
            DB::raw('AVG(time_ms) as avg_time_ms'),
            DB::raw('COUNT(DISTINCT booking_user_id) as unique_booking_users')
        )->where('course_id', $course->id)->first();

        // Attendance % based on attended booking_users with past date
        $now = Carbon::now()->toDateString();
        $total = BookingUser::where('course_id', $course->id)
            ->whereDate('date', '<=', $now)
            ->count();
        $attended = BookingUser::where('course_id', $course->id)
            ->where('attended', true)
            ->whereDate('date', '<=', $now)
            ->count();
        $attendance_pct = $total > 0 ? round(($attended / $total) * 100, 1) : 0.0;

        return response()->json([
            'success' => true,
            'message' => 'Course timing summary',
            'data' => [
                'total_records' => (int) ($rows->total_records ?? 0),
                'avg_time_ms' => $rows->avg_time_ms !== null ? (int) $rows->avg_time_ms : null,
                'unique_booking_users' => (int) ($rows->unique_booking_users ?? 0),
                'attendance_pct' => $attendance_pct,
            ],
        ]);
    }

    /**
     * GET /api/v4/courses/{course}/timing/export.csv
     */
    public function courseExportCsv(Course $course): StreamedResponse
    {
        $filename = 'course_'.$course->id.'_timing.csv';
        $callback = function () use ($course) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['client_id', 'booking_user_id', 'date', 'time_ms', 'source', 'external_id']);
            BookingUserTime::where('course_id', $course->id)
                ->orderBy('date')
                ->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $row) {
                        fputcsv($out, [
                            $row->client_id,
                            $row->booking_user_id,
                            optional($row->date)->toDateTimeString(),
                            $row->time_ms,
                            $row->source,
                            $row->external_id,
                        ]);
                    }
                });
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}

