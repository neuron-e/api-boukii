<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\AppBaseController;
use App\Models\BookingUser;
use App\Services\TeachScanTokenService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends AppBaseController
{
    public function resolve(Request $request): JsonResponse
    {
        $monitor = $this->getMonitor($request);
        if (!$monitor) {
            return $this->sendError('Monitor not found for this user', [], 404);
        }

        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            return $this->sendError('Token is required', [], 422);
        }

        $decoded = TeachScanTokenService::decodeToken($token);
        if (empty($decoded['valid'])) {
            return $this->sendError('Invalid token', ['reason' => $decoded['reason'] ?? 'unknown'], 422);
        }

        $payload = $decoded['payload'];
        $bookingUser = BookingUser::with([
            'client.language1',
            'course.sport',
            'degree',
            'monitor',
            'booking',
        ])->find($payload['booking_user_id']);

        if (!$bookingUser) {
            return $this->sendError('Booking user not found', [], 404);
        }

        $activeSchoolId = (int) ($monitor->active_school ?? 0);
        $isSameSchool = $activeSchoolId > 0 && (int) $bookingUser->school_id === $activeSchoolId;
        if (!$isSameSchool) {
            return $this->sendError('Booking does not belong to this school', [], 403);
        }

        $isAssignedToMonitor = (int) $bookingUser->monitor_id === (int) $monitor->id;
        $booking = $bookingUser->booking;
        $course = $bookingUser->course;
        $meetingPointName = $booking->meeting_point ?? $course->meeting_point ?? null;
        $meetingPointAddress = $booking->meeting_point_address ?? $course->meeting_point_address ?? null;
        $meetingPointInstructions = $booking->meeting_point_instructions ?? $course->meeting_point_instructions ?? null;

        $response = [
            'booking_user' => [
                'id' => $bookingUser->id,
                'date' => $bookingUser->date,
                'hour_start' => $bookingUser->hour_start,
                'hour_end' => $bookingUser->hour_end,
                'status' => $bookingUser->status,
                'monitor_id' => $bookingUser->monitor_id,
                'course_id' => $bookingUser->course_id,
                'client_id' => $bookingUser->client_id,
            ],
            'booking' => $booking ? [
                'id' => $booking->id,
                'status' => $booking->status,
                'paid' => $booking->paid,
                'payment_method_status' => $booking->payment_method_status,
            ] : null,
            'client' => [
                'id' => $bookingUser->client_id,
                'full_name' => $bookingUser->client->full_name ?? null,
                'birth_date' => $bookingUser->client->birth_date ?? null,
                'language' => $bookingUser->client->language1->code ?? null,
            ],
            'course' => $course ? [
                'id' => $course->id,
                'name' => $course->name,
                'course_type' => $course->course_type,
                'sport' => $course->sport ? [
                    'id' => $course->sport->id,
                    'name' => $course->sport->name,
                ] : null,
            ] : null,
            'meeting_point' => [
                'name' => $meetingPointName,
                'address' => $meetingPointAddress,
                'instructions' => $meetingPointInstructions,
            ],
            'degree' => $bookingUser->degree ? [
                'id' => $bookingUser->degree->id,
                'name' => $bookingUser->degree->name,
            ] : null,
            'monitor' => $bookingUser->monitor ? [
                'id' => $bookingUser->monitor->id,
                'full_name' => $bookingUser->monitor->full_name,
            ] : null,
            'is_assigned_to_monitor' => $isAssignedToMonitor,
            'is_today' => $bookingUser->date === Carbon::today()->toDateString(),
        ];

        return $this->sendResponse($response, 'Scan booking resolved successfully');
    }
}
