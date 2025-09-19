<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingUserTime;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\BookingUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimingController extends Controller
{
    /**
     * Endpoint de ingesta de eventos de cronometraje
     * POST /api/v4/timing/ingest
     */
    public function ingest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
            'course_id' => 'required|integer|exists:courses,id',
            'events' => 'required|array|min:1',
            'events.*.booking_user_id' => 'required|integer|exists:booking_users,id',
            'events.*.course_date_id' => 'required|integer|exists:course_dates,id',
            'events.*.lap_no' => 'required|integer|min:1',
            'events.*.time_ms' => 'required|integer|min:0',
            'events.*.status' => 'required|in:valid,invalid,dns,dnf',
            'events.*.meta' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 400);
        }

        $deviceId = $request->device_id;
        $courseId = $request->course_id;
        $events = $request->events;

        // Verificar que el curso pertenece a la escuela del usuario autenticado
        $course = Course::find($courseId);
        if (!$course || $course->school_id !== auth()->user()->school_id) {
            return response()->json(['error' => 'Course not found or not accessible'], 404);
        }

        $processedEvents = [];
        
        DB::transaction(function () use ($events, $deviceId, $courseId, &$processedEvents) {
            foreach ($events as $event) {
                // Verificar que la fecha del curso pertenece al curso
                $courseDate = CourseDate::where('id', $event['course_date_id'])
                    ->where('course_id', $courseId)
                    ->first();
                    
                if (!$courseDate) {
                    Log::warning("Course date {$event['course_date_id']} does not belong to course {$courseId}");
                    continue;
                }

                // Verificar que el booking_user pertenece a la fecha del curso
                $bookingUser = BookingUser::where('id', $event['booking_user_id'])
                    ->where('course_date_id', $event['course_date_id'])
                    ->first();
                    
                if (!$bookingUser) {
                    Log::warning("Booking user {$event['booking_user_id']} does not belong to course date {$event['course_date_id']}");
                    continue;
                }

                // Upsert del tiempo (idempotencia por booking_user_id, course_date_id, lap_no)
                $time = BookingUserTime::updateOrCreate(
                    [
                        'booking_user_id' => $event['booking_user_id'],
                        'course_date_id' => $event['course_date_id'],
                        'lap_no' => $event['lap_no']
                    ],
                    [
                        'time_ms' => $event['time_ms'],
                        'status' => $event['status'],
                        'source' => 'microgate',
                        'device_id' => $deviceId,
                        'meta' => $event['meta'] ?? null
                    ]
                );

                $processedEvents[] = [
                    'id' => $time->id,
                    'booking_user_id' => $time->booking_user_id,
                    'course_date_id' => $time->course_date_id,
                    'lap_no' => $time->lap_no,
                    'time_ms' => $time->time_ms,
                    'status' => $time->status,
                    'student_name' => $bookingUser->client->first_name . ' ' . $bookingUser->client->last_name,
                    'meta' => $time->meta
                ];
            }
        });

        // Broadcast en tiempo real (implementaremos SSE)
        if (!empty($processedEvents)) {
            $this->broadcastTimingEvents($courseId, $processedEvents);
        }

        return response()->json([
            'success' => true,
            'processed' => count($processedEvents),
            'events' => $processedEvents
        ]);
    }

    /**
     * Resumen/estado inicial para una fecha de curso
     * GET /api/v4/timing/summary
     */
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer|exists:courses,id',
            'course_date_id' => 'required|integer|exists:course_dates,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 400);
        }

        $courseId = $request->course_id;
        $courseDateId = $request->course_date_id;

        // Verificar permisos
        $course = Course::find($courseId);
        if (!$course || $course->school_id !== auth()->user()->school_id) {
            return response()->json(['error' => 'Course not found or not accessible'], 404);
        }

        // Obtener estudiantes y sus tiempos
        $bookingUsers = BookingUser::with(['client', 'times'])
            ->where('course_date_id', $courseDateId)
            ->get();

        $students = [];
        $ranking = [];

        foreach ($bookingUsers as $bookingUser) {
            $times = $bookingUser->times;
            $validTimes = $times->where('status', 'valid');
            
            $bestTimeMs = $validTimes->isNotEmpty() ? $validTimes->min('time_ms') : null;
            $lastTime = $times->sortByDesc('created_at')->first();
            
            $studentData = [
                'booking_user_id' => $bookingUser->id,
                'name' => $bookingUser->client->first_name . ' ' . $bookingUser->client->last_name,
                'bib' => $bookingUser->meta['bib'] ?? null,
                'last_time_ms' => $lastTime ? $lastTime->time_ms : null,
                'best_time_ms' => $bestTimeMs,
                'laps' => $times->count(),
                'status' => $lastTime ? $lastTime->status : 'pending',
                'times' => $times->map(function ($time) {
                    return [
                        'id' => $time->id,
                        'lap_no' => $time->lap_no,
                        'time_ms' => $time->time_ms,
                        'status' => $time->status,
                        'created_at' => $time->created_at
                    ];
                })
            ];

            $students[] = $studentData;

            if ($bestTimeMs) {
                $ranking[] = [
                    'booking_user_id' => $bookingUser->id,
                    'name' => $studentData['name'],
                    'best_time_ms' => $bestTimeMs
                ];
            }
        }

        // Ordenar ranking por mejor tiempo
        usort($ranking, function ($a, $b) {
            return $a['best_time_ms'] <=> $b['best_time_ms'];
        });

        return response()->json([
            'course_id' => $courseId,
            'course_date_id' => $courseDateId,
            'students' => $students,
            'ranking' => $ranking
        ]);
    }

    /**
     * Stream de eventos en tiempo real (SSE)
     * GET /api/v4/timing/stream
     */
    public function stream(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer|exists:courses,id',
            'course_date_id' => 'required|integer|exists:course_dates,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 400);
        }

        $courseId = $request->course_id;
        $courseDateId = $request->course_date_id;

        // Verificar permisos
        $course = Course::find($courseId);
        if (!$course || $course->school_id !== auth()->user()->school_id) {
            return response()->json(['error' => 'Course not found or not accessible'], 404);
        }

        return new StreamedResponse(function () use ($courseId, $courseDateId) {
            $this->sendSSEHeaders();
            
            // Enviar ping inicial
            $this->sendSSEEvent('ping', ['timestamp' => time()]);
            
            $lastEventId = 0;
            
            while (true) {
                // Buscar nuevos eventos desde el último ID
                $newEvents = $this->getNewTimingEvents($courseId, $courseDateId, $lastEventId);
                
                foreach ($newEvents as $event) {
                    $this->sendSSEEvent('timing', $event);
                    $lastEventId = max($lastEventId, $event['id'] ?? 0);
                }
                
                // Keep-alive ping cada 25 segundos
                if (time() % 25 === 0) {
                    $this->sendSSEEvent('ping', ['timestamp' => time()]);
                }
                
                // Verificar si la conexión sigue activa
                if (connection_aborted()) {
                    break;
                }
                
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no'
        ]);
    }

    /**
     * Actualizar un tiempo manualmente
     * PUT /api/v4/timing/times/{id}
     */
    public function updateTime(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'time_ms' => 'required|integer|min:0',
            'status' => 'required|in:valid,invalid,dns,dnf'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 400);
        }

        $time = BookingUserTime::find($id);
        if (!$time) {
            return response()->json(['error' => 'Time not found'], 404);
        }

        // Verificar permisos a través del curso
        $bookingUser = BookingUser::with(['courseDate.course'])->find($time->booking_user_id);
        if (!$bookingUser || $bookingUser->courseDate->course->school_id !== auth()->user()->school_id) {
            return response()->json(['error' => 'Time not accessible'], 403);
        }

        $time->update([
            'time_ms' => $request->time_ms,
            'status' => $request->status,
            'source' => 'manual'
        ]);

        // Broadcast del cambio
        $this->broadcastTimingEvents($bookingUser->courseDate->course_id, [[
            'id' => $time->id,
            'booking_user_id' => $time->booking_user_id,
            'course_date_id' => $time->course_date_id,
            'lap_no' => $time->lap_no,
            'time_ms' => $time->time_ms,
            'status' => $time->status,
            'student_name' => $bookingUser->client->first_name . ' ' . $bookingUser->client->last_name,
            'updated' => true
        ]]);

        return response()->json([
            'success' => true,
            'time' => $time
        ]);
    }

    /**
     * Eliminar un tiempo
     * DELETE /api/v4/timing/times/{id}
     */
    public function deleteTime($id)
    {
        $time = BookingUserTime::find($id);
        if (!$time) {
            return response()->json(['error' => 'Time not found'], 404);
        }

        // Verificar permisos
        $bookingUser = BookingUser::with(['courseDate.course'])->find($time->booking_user_id);
        if (!$bookingUser || $bookingUser->courseDate->course->school_id !== auth()->user()->school_id) {
            return response()->json(['error' => 'Time not accessible'], 403);
        }

        $time->delete();

        return response()->json(['success' => true]);
    }

    private function sendSSEHeaders()
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        if (ob_get_level()) {
            ob_end_flush();
        }
    }

    private function sendSSEEvent($type, $data)
    {
        echo "event: {$type}\n";
        echo "data: " . json_encode($data) . "\n\n";
        flush();
    }

    private function getNewTimingEvents($courseId, $courseDateId, $lastEventId)
    {
        // Esta implementación es simple - en producción podrías usar Redis o una cola
        $times = BookingUserTime::with(['bookingUser.client'])
            ->whereHas('bookingUser', function ($query) use ($courseDateId) {
                $query->where('course_date_id', $courseDateId);
            })
            ->where('id', '>', $lastEventId)
            ->orderBy('id')
            ->get();

        return $times->map(function ($time) {
            return [
                'id' => $time->id,
                'booking_user_id' => $time->booking_user_id,
                'course_date_id' => $time->course_date_id,
                'lap_no' => $time->lap_no,
                'time_ms' => $time->time_ms,
                'status' => $time->status,
                'student_name' => $time->bookingUser->client->first_name . ' ' . $time->bookingUser->client->last_name,
                'meta' => $time->meta,
                'created_at' => $time->created_at
            ];
        })->toArray();
    }

    private function broadcastTimingEvents($courseId, $events)
    {
        // En una implementación más robusta, esto podría usar Laravel Echo/WebSockets
        // Por simplicidad, guardamos en cache para que SSE los recoja
        cache()->put("timing_events_{$courseId}", $events, 60);
    }
}