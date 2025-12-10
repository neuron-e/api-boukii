<?php

namespace App\Services;

use App\Mail\BookingCreateMailer;
use App\Models\Booking;
use App\Models\BookingLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingConfirmationService
{
    /**
     * Send booking confirmation email once per booking.
     */
    public function sendConfirmation(Booking $booking, bool $isPaid): void
    {
        $booking->loadMissing(['school', 'clientMain']);

        $school = $booking->school;
        $client = $booking->clientMain;

        if (!$school || !$client || empty($client->email)) {
            Log::warning('BOOKING_CONFIRMATION_SKIPPED_MISSING_DATA', [
                'booking_id' => $booking->id,
                'school_loaded' => (bool) $school,
                'client_loaded' => (bool) $client,
            ]);
            return;
        }

        $alreadySent = $booking->bookingLogs()
            ->where('action', 'mail_booking_create_sent')
            ->exists();

        if ($alreadySent) {
            return;
        }

        try {
            Mail::to($client->email)
                ->send(new BookingCreateMailer($school, $booking->fresh(), $client, $isPaid));

            BookingLog::create([
                'booking_id' => $booking->id,
                'action' => 'mail_booking_create_sent',
                'user_id' => optional(auth())->id(),
            ]);

            Log::info('BOOKING_CONFIRMATION_MAIL_SENT', [
                'booking_id' => $booking->id,
                'email' => $client->email,
                'paid' => $isPaid,
            ]);
        } catch (\Exception $e) {
            Log::error('BOOKING_CONFIRMATION_MAIL_FAILED', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
