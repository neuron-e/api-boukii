<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingUserTime;
use App\Models\BookingUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseTimingController extends Controller
{
    /**
     * Display a listing of timing records with filtering
     */
    public function index(Request $request)
    {
        try {
            // Ajustar para la estructura real de la tabla
            $query = BookingUserTime::with(['bookingUser.client']);

            // Apply filters usando las columnas que realmente existen
            if ($request->has('course_id')) {
                $query->where('booking_user_times.course_id', $request->course_id);
            }

            if ($request->has('course_date_id')) {
                // No podemos filtrar directamente por course_date_id ya que no existe
                // En su lugar, podemos usar la fecha si está disponible
                // Por ahora, ignoramos este filtro y filtramos en el frontend
            }

            // Pagination and ordering
            $perPage = $request->get('perPage', 15);
            $orderColumn = $request->get('orderColumn', 'created_at');
            $order = $request->get('order', 'desc');

            // Handle JOIN for student_id ordering BEFORE applying filters that could be ambiguous
            $useJoin = ($orderColumn === 'student_id');

            if ($useJoin) {
                $query->select('booking_user_times.*')
                      ->join('booking_users', 'booking_user_times.booking_user_id', '=', 'booking_users.id')
                      ->orderBy('booking_users.client_id', $order);

                // Apply filters with qualified column names for JOINed query
                if ($request->has('course_id')) {
                    $query->where('booking_user_times.course_id', $request->course_id);
                }

                if ($request->has('course_subgroup_id')) {
                    $query->where('booking_users.course_subgroup_id', $request->course_subgroup_id);
                }

                if ($request->has('student_id')) {
                    $query->where('booking_users.client_id', $request->student_id);
                }
            } else {
                $query->orderBy($orderColumn, $order);

                // Apply filters with relationship queries for non-JOINed query
                if ($request->has('course_subgroup_id')) {
                    $query->whereHas('bookingUser', function ($q) use ($request) {
                        $q->where('course_subgroup_id', $request->course_subgroup_id);
                    });
                }

                if ($request->has('student_id')) {
                    $query->whereHas('bookingUser', function ($q) use ($request) {
                        $q->where('client_id', $request->student_id);
                    });
                }
            }

            $times = $query->paginate($perPage);

            // Transform data using the real table structure
            $times->getCollection()->transform(function ($time) {
                return [
                    'id' => $time->id,
                    'student_id' => $time->client_id, // Usar client_id directamente
                    'course_id' => $time->course_id,  // Usar course_id directamente
                    'course_date_id' => null,         // No existe en la tabla real
                    'course_subgroup_id' => $time->bookingUser->course_subgroup_id ?? null,
                    'lap_no' => 1,                    // Valor por defecto
                    'time_ms' => $time->time_ms,
                    'status' => 'valid',              // Valor por defecto
                    'formatted_time' => $this->formatTime($time->time_ms),
                    'source' => $time->source,
                    'date' => $time->date,            // Incluir la fecha real
                    'created_at' => $time->created_at,
                    'updated_at' => $time->updated_at,
                    'student' => [
                        'id' => $time->client_id,
                        'first_name' => $time->bookingUser->client->first_name ?? null,
                        'last_name' => $time->bookingUser->client->last_name ?? null,
                    ]
                ];
            });

            return response()->json([
                'data' => $times->items(),
                'meta' => [
                    'current_page' => $times->currentPage(),
                    'last_page' => $times->lastPage(),
                    'per_page' => $times->perPage(),
                    'total' => $times->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching timing records: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error fetching timing records',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store multiple timing records
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'times' => 'required|array',
                'times.*.student_id' => 'required|integer',
                'times.*.course_id' => 'required|integer',
                'times.*.course_date_id' => 'required|integer',
                'times.*.course_subgroup_id' => 'required|integer',
                'times.*.time_ms' => 'required|integer|min:0',
                'times.*.lap_no' => 'integer|min:1',
                'times.*.status' => 'string|in:valid,invalid,dns,dnf',
            ]);

            $savedTimes = [];

            DB::beginTransaction();

            foreach ($request->times as $timeData) {
                // Find the booking user
                $bookingUser = BookingUser::where('client_id', $timeData['student_id'])
                    ->where('course_subgroup_id', $timeData['course_subgroup_id'])
                    ->where('course_date_id', $timeData['course_date_id'])
                    ->first();

                if (!$bookingUser) {
                    return response()->json([
                        'error' => 'Booking user not found',
                        'student_id' => $timeData['student_id'],
                        'course_subgroup_id' => $timeData['course_subgroup_id'],
                        'course_date_id' => $timeData['course_date_id']
                    ], 404);
                }

                // Create or update timing record
                $timingRecord = BookingUserTime::updateOrCreate(
                    [
                        'booking_user_id' => $bookingUser->id,
                        'course_date_id' => $timeData['course_date_id'],
                        'lap_no' => $timeData['lap_no'] ?? 1,
                    ],
                    [
                        'time_ms' => $timeData['time_ms'],
                        'status' => $timeData['status'] ?? 'valid',
                        'source' => 'admin_panel',
                    ]
                );

                $savedTimes[] = [
                    'id' => $timingRecord->id,
                    'student_id' => $timeData['student_id'],
                    'course_date_id' => $timeData['course_date_id'],
                    'lap_no' => $timingRecord->lap_no,
                    'time_ms' => $timingRecord->time_ms,
                    'status' => $timingRecord->status,
                    'formatted_time' => $timingRecord->formatted_time,
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Timing records saved successfully',
                'data' => $savedTimes
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving timing records: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error saving timing records',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific timing record
     */
    public function show(string $id)
    {
        try {
            $time = BookingUserTime::with(['bookingUser.client', 'courseDate'])
                ->findOrFail($id);

            return response()->json([
                'data' => [
                    'id' => $time->id,
                    'student_id' => $time->bookingUser->client_id,
                    'course_id' => $time->courseDate->course_id,
                    'course_date_id' => $time->course_date_id,
                    'course_subgroup_id' => $time->bookingUser->course_subgroup_id,
                    'lap_no' => $time->lap_no,
                    'time_ms' => $time->time_ms,
                    'status' => $time->status,
                    'formatted_time' => $time->formatted_time,
                    'source' => $time->source,
                    'created_at' => $time->created_at,
                    'updated_at' => $time->updated_at,
                    'student' => [
                        'id' => $time->bookingUser->client_id,
                        'first_name' => $time->bookingUser->client->first_name ?? null,
                        'last_name' => $time->bookingUser->client->last_name ?? null,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Timing record not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update a specific timing record
     */
    public function update(Request $request, string $id)
    {
        try {
            $request->validate([
                'time_ms' => 'integer|min:0',
                'lap_no' => 'integer|min:1',
                'status' => 'string|in:valid,invalid,dns,dnf',
            ]);

            $time = BookingUserTime::findOrFail($id);
            $time->update($request->only(['time_ms', 'lap_no', 'status']));

            return response()->json([
                'message' => 'Timing record updated successfully',
                'data' => [
                    'id' => $time->id,
                    'time_ms' => $time->time_ms,
                    'lap_no' => $time->lap_no,
                    'status' => $time->status,
                    'formatted_time' => $time->formatted_time,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error updating timing record',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a timing record
     */
    public function destroy(string $id)
    {
        try {
            $time = BookingUserTime::findOrFail($id);
            $time->delete();

            return response()->json([
                'message' => 'Timing record deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error deleting timing record',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format time in milliseconds to human readable format
     */
    private function formatTime($timeMs)
    {
        if (!$timeMs) {
            return '00:00:00';
        }

        $totalSeconds = intval($timeMs / 1000);
        $hours = intval($totalSeconds / 3600);
        $minutes = intval(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;
        $milliseconds = $timeMs % 1000;

        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $milliseconds);
    }
}