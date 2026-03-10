<?php

namespace App\Services;

use App\Models\RentalEvent;
use App\Models\RentalPickupPoint;
use App\Support\LocaleHelper;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Handles email notifications for rental reservation lifecycle events.
 * Follows the pattern of BookingConfirmationService.
 *
 * All subjects and email body strings use Laravel translations (emails.rental.*)
 * and respect the client's language preference via LocaleHelper.
 */
class RentalNotificationService
{
    private const VIEW = 'mailsv2.rental.rental_notification';

    // ── Public API ────────────────────────────────────────────────────────────

    /** rental.confirmed — on reservation creation (status=pending) */
    public function sendConfirmation(int $reservationId): void
    {
        $this->send($reservationId, 'confirmation', 'confirmation_sent');
    }

    /** rental.reminder — X hours before pickup */
    public function sendReminder(int $reservationId, int $hoursBeforePickup = 24): void
    {
        // Only send for pending/assigned reservations
        $reservation = $this->loadReservation($reservationId);
        if (!$reservation) return;
        if (!in_array($reservation->status, ['pending', 'assigned'], true)) return;

        $this->send($reservationId, 'reminder', 'reminder_sent', [
            'hours_before' => $hoursBeforePickup,
            'start_date'   => $reservation->start_date,
        ]);
    }

    /** rental.overdue — when reservation passes end_date without return */
    public function sendOverdue(int $reservationId): void
    {
        $this->send($reservationId, 'overdue', 'overdue_sent');
    }

    /** rental.returned — on full return / completed */
    public function sendReturned(int $reservationId): void
    {
        $this->send($reservationId, 'returned', 'returned_sent');
    }

    /**
     * rental.damage — damage registered.
     * Sent to the school admin (not the client) as an internal alert.
     */
    public function sendDamage(int $reservationId, float $damageCost, string $description = ''): void
    {
        $reservation = $this->loadReservation($reservationId);
        if (!$reservation) return;

        $school = $this->getSchool($reservation->school_id);
        if (!$school || empty($school->contact_email ?? $school->email ?? null)) return;

        $schoolEmail = $school->contact_email ?? $school->email;
        $client      = $this->getClient($reservation->client_id);
        $lines       = $this->getLines($reservationId);
        $pickupPoint = $this->getPickupPoint($reservation->rental_pickup_point_id ?? null);

        // Use school locale (or fallback) for internal alert
        $locale = $this->resolveLocale(null, $school);
        $oldLocale = App::getLocale();
        App::setLocale($locale);

        $subject = __('emails.rental.subject_damage', [
            'id'       => $reservation->id,
            'amount'   => number_format($damageCost, 2),
            'currency' => $reservation->currency ?? 'CHF',
        ]);

        $notificationType = 'damage';
        $damageContext    = ['damage_cost' => $damageCost, 'description' => $description];

        try {
            Mail::send(
                self::VIEW,
                compact('reservation', 'client', 'school', 'lines', 'pickupPoint', 'notificationType', 'damageContext'),
                static function ($message) use ($schoolEmail, $school, $subject) {
                    $message->to($schoolEmail, $school->name ?? '')->subject($subject);
                }
            );

            $this->logEvent($reservationId, 'damage_notification_sent', [
                'damage_cost' => $damageCost,
                'description' => $description,
            ]);
        } catch (\Exception $e) {
            Log::channel('emails')->error('RENTAL_DAMAGE_MAIL_FAILED', [
                'reservation_id' => $reservationId,
                'error'          => $e->getMessage(),
            ]);
        } finally {
            App::setLocale($oldLocale);
        }
    }

    /** rental.cancelled — on explicit cancellation */
    public function sendCancellation(int $reservationId, string $reason = ''): void
    {
        $this->send($reservationId, 'cancelled', 'cancellation_sent', [
            'cancellation_reason' => $reason,
        ]);
    }

    // ── Core dispatcher ───────────────────────────────────────────────────────

    /**
     * Generic send method for client-facing notifications.
     * Resolves locale, builds subject, sends email, logs event.
     */
    private function send(
        int    $reservationId,
        string $type,
        string $eventKey,
        array  $extraPayload = []
    ): void {
        $reservation = $this->loadReservation($reservationId);
        if (!$reservation) return;

        $client = $this->getClient($reservation->client_id);
        if (!$client || empty($client->email)) return;

        $school = $this->getSchool($reservation->school_id);
        if (!$school) return;

        // Idempotency: only send once per event type
        if ($this->eventExists($reservationId, $eventKey)) return;

        $locale    = $this->resolveLocale($client);
        $oldLocale = App::getLocale();
        App::setLocale($locale);

        $subject = $this->buildSubject($type, $reservation, $school);

        $lines            = $this->getLines($reservationId);
        $pickupPoint      = $this->getPickupPoint($reservation->rental_pickup_point_id ?? null);
        $notificationType = $type;

        try {
            Mail::send(
                self::VIEW,
                compact('reservation', 'client', 'school', 'lines', 'pickupPoint', 'notificationType'),
                static function ($message) use ($client, $school, $subject) {
                    $name = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
                    $message->to($client->email, $name)->subject($subject);
                    $replyEmail = $school->contact_email ?? $school->email ?? null;
                    if (!empty($replyEmail)) {
                        $message->replyTo($replyEmail, $school->name ?? '');
                    }
                }
            );

            $this->logEvent($reservationId, $eventKey, $extraPayload);

            Log::channel('emails')->info('RENTAL_MAIL_SENT', [
                'type'           => $type,
                'reservation_id' => $reservationId,
                'email'          => $client->email,
                'locale'         => $locale,
            ]);
        } catch (\Exception $e) {
            Log::channel('emails')->error('RENTAL_MAIL_FAILED', [
                'type'           => $type,
                'reservation_id' => $reservationId,
                'error'          => $e->getMessage(),
            ]);
        } finally {
            App::setLocale($oldLocale);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildSubject(string $type, object $reservation, object $school): string
    {
        $key = 'emails.rental.subject_' . $type;

        return __($key, [
            'id'       => $reservation->id,
            'school'   => $school->name ?? '',
            'amount'   => number_format((float) ($reservation->total ?? 0), 2),
            'currency' => $reservation->currency ?? 'CHF',
        ]);
    }

    private function resolveLocale(?object $client, ?object $school = null): string
    {
        // Try client language_id → Language model code
        if ($client && !empty($client->language1_id)) {
            $lang = DB::table('languages')->where('id', $client->language1_id)->value('code');
            if ($lang) {
                $supported = ['de', 'en', 'es', 'fr', 'it'];
                $code = strtolower(substr($lang, 0, 2));
                if (in_array($code, $supported, true)) {
                    return $code;
                }
            }
        }

        return config('app.fallback_locale', 'en');
    }

    private function loadReservation(int $id): ?object
    {
        if (!Schema::hasTable('rental_reservations')) return null;
        return DB::table('rental_reservations')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
    }

    private function getClient(?int $clientId): ?object
    {
        if (!$clientId || !Schema::hasTable('clients')) return null;
        return DB::table('clients')->where('id', $clientId)->first();
    }

    private function getSchool(?int $schoolId): ?object
    {
        if (!$schoolId) return null;
        return DB::table('schools')->where('id', $schoolId)->first();
    }

    private function getLines(int $reservationId): array
    {
        if (!Schema::hasTable('rental_reservation_lines')) return [];
        return DB::table('rental_reservation_lines as rrl')
            ->leftJoin('rental_items as ri', 'ri.id', '=', 'rrl.item_id')
            ->where('rrl.rental_reservation_id', $reservationId)
            ->select('rrl.*', 'ri.name as item_name')
            ->get()
            ->toArray();
    }

    private function getPickupPoint(?int $pickupPointId): ?object
    {
        if (!$pickupPointId) return null;
        return RentalPickupPoint::find($pickupPointId);
    }

    private function eventExists(int $reservationId, string $eventType): bool
    {
        return RentalEvent::exists($reservationId, $eventType);
    }

    private function logEvent(int $reservationId, string $eventType, array $extra = []): void
    {
        $schoolId = (int) DB::table('rental_reservations')->where('id', $reservationId)->value('school_id');
        RentalEvent::log($reservationId, $schoolId, $eventType, array_merge(['sent_at' => now()->toIso8601String()], $extra), null);
    }
}
