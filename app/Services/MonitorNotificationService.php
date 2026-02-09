<?php

namespace App\Services;

use App\Events\MonitorAssigned;
use App\Events\MonitorRemoved;
use App\Models\AppNotification;
use App\Models\Monitor;
use App\Models\MonitorPushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorNotificationService
{
    public function notifyAssignment(
        ?int $monitorId,
        string $type,
        array $payload,
        array $schoolSettings = [],
        ?int $actorId = null
    ): void {
        $settings = $this->normalizeSettings($schoolSettings);
        if (!$monitorId || !$this->notificationsEnabled($settings)) {
            return;
        }

        $monitor = Monitor::find($monitorId);
        if (!$monitor) {
            return;
        }

        $normalizedPayload = $this->buildPayload($payload, $monitorId, $monitor->active_school, $actorId);
        $notification = $this->resolveNotificationCopy($type, $normalizedPayload, config('app.locale', 'es'));
        $eventPayload = [
            'type' => $type,
            'monitor_id' => $monitorId,
            'payload' => $normalizedPayload,
            'notification' => $notification,
        ];

        $notificationId = $this->storeNotification($monitorId, $type, $normalizedPayload, $notification, $actorId);
        if ($notificationId) {
            $eventPayload['notification_id'] = $notificationId;
        }

        $hasBroadcastDriver = $this->hasBroadcastDriver();
        $this->emitEvent($type, $monitorId, $eventPayload);
        $this->sendPushNotification($type, $monitorId, $eventPayload);

        Log::channel('notifications')->info('Monitor notification', $eventPayload);

        // Email fallback disabled for now (too noisy).
    }

    public function sendCustom(
        int $monitorId,
        array $notification,
        array $payload = [],
        ?int $actorId = null,
        ?int $notificationId = null
    ): void {
        if (!$monitorId) {
            return;
        }

        $monitor = Monitor::find($monitorId);
        if (!$monitor) {
            return;
        }

        $normalizedPayload = $this->buildPayload($payload, $monitorId, $monitor->active_school, $actorId);
        $eventPayload = [
            'type' => 'custom_message',
            'monitor_id' => $monitorId,
            'payload' => $normalizedPayload,
            'notification' => $notification,
        ];

        if ($notificationId) {
            $eventPayload['notification_id'] = $notificationId;
        }

        $this->sendPushNotification('custom_message', $monitorId, $eventPayload);
    }

    private function normalizeSettings(array|string $settings): array
    {
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $settings : [];
    }

    private function notificationsEnabled(array $settings): bool
    {
        $bookingSettings = $settings['booking'] ?? [];
        $toggleEnabled = $bookingSettings['monitor_notifications_enabled'] ?? true;
        $permission = $bookingSettings['monitor_app_client_bookings_permission'] ?? true;

        return (bool) ($toggleEnabled && $permission);
    }

    private function hasBroadcastDriver(): bool
    {
        $default = config('broadcasting.default');
        if (empty($default) || $default === 'log' || $default === 'null') {
            return false;
        }

        $connection = config("broadcasting.connections.{$default}");
        return !empty($connection);
    }

    private function emitEvent(string $type, int $monitorId, array $payload): void
    {
        $defaultDriver = config('broadcasting.default');
        if ($defaultDriver === 'pusher' && !class_exists(\Pusher\Pusher::class)) {
            Log::channel('notifications')->warning('Monitor notification skipped: pusher library missing', [
                'monitor_id' => $monitorId,
                'type' => $type,
            ]);
            return;
        }

        $event = (str_contains($type, 'removed') || str_contains($type, 'cancelled'))
            ? new MonitorRemoved($monitorId, $payload)
            : new MonitorAssigned($monitorId, $payload);

        try {
            event($event);
        } catch (\Throwable $exception) {
            Log::channel('notifications')->warning('Monitor notification dispatch failed', [
                'monitor_id' => $monitorId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendPushNotification(string $type, int $monitorId, array $payload): void
    {
        $this->sendPusherPush($type, $monitorId, $payload);
        $this->sendFcmPush($type, $monitorId, $payload);
    }

    private function sendPusherPush(string $type, int $monitorId, array $payload): void
    {
        $instanceId = config('services.pusher_beams.instance_id');
        $secretKey = config('services.pusher_beams.secret_key');
        if (empty($instanceId) || empty($secretKey)) {
            Log::channel('notifications')->info('Monitor push skipped: missing Beams config', [
                'monitor_id' => $monitorId,
                'type' => $type,
            ]);
            return;
        }

        $notification = $payload['notification'] ?? $this->resolveNotificationCopy($type, $payload['payload'] ?? [], config('app.locale', 'es'));
        $notificationId = $payload['notification_id'] ?? null;
        $url = "https://{$instanceId}.pushnotifications.pusher.com/publish_api/v1/instances/{$instanceId}/publishes/interests";

        try {
            $response = Http::withToken($secretKey)
                ->post($url, [
                    'interests' => ["monitor.{$monitorId}"],
                    'web' => [
                        'notification' => [
                            'title' => $notification['title'] ?? 'Boukii',
                            'body' => $notification['body'] ?? '',
                        ],
                        'data' => array_merge($payload, ['notification_id' => $notificationId]),
                    ],
                ]);

            if (!$response->successful()) {
                Log::channel('notifications')->warning('Monitor push failed', [
                    'monitor_id' => $monitorId,
                    'type' => $type,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::channel('notifications')->warning('Monitor push error', [
                'monitor_id' => $monitorId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendFcmPush(string $type, int $monitorId, array $payload): void
    {
        $useV1 = (bool) config('services.fcm.use_v1', true);
        $projectId = config('services.fcm.project_id');
        if ($useV1 && $projectId) {
            $this->sendFcmPushV1($type, $monitorId, $payload, $projectId);
            return;
        }

        $tokens = MonitorPushToken::query()
            ->where('monitor_id', $monitorId)
            ->get(['token', 'locale']);

        if ($tokens->isEmpty()) {
            return;
        }

        $tokensByLocale = $tokens->groupBy(function ($item) {
            return $this->normalizeLocale($item->locale ?? null);
        });

        foreach ($tokensByLocale as $locale => $tokenList) {
            $notification = $this->resolveNotificationCopy($type, $payload['payload'] ?? [], $locale);
            $data = [
                'type' => (string) $type,
                'payload' => json_encode($payload['payload'] ?? []),
                'notification_id' => (string) ($payload['notification_id'] ?? ''),
            ];

            $tokenValues = $tokenList->pluck('token')->filter()->values()->all();
            foreach (array_chunk($tokenValues, 500) as $chunk) {
                try {
                    $serverKey = config('services.fcm.server_key');
                    $sendUrl = config('services.fcm.send_url');
                    if (empty($serverKey) || empty($sendUrl)) {
                        Log::channel('notifications')->info('Monitor push skipped: missing FCM config', [
                            'monitor_id' => $monitorId,
                            'type' => $type,
                        ]);
                        return;
                    }
                    $response = Http::withHeaders([
                        'Authorization' => 'key=' . $serverKey,
                        'Content-Type' => 'application/json',
                    ])->post($sendUrl, [
                        'registration_ids' => $chunk,
                        'notification' => [
                            'title' => $notification['title'] ?? 'Boukii',
                            'body' => $notification['body'] ?? '',
                        ],
                        'data' => $data,
                        'priority' => 'high',
                    ]);

                    if (!$response->successful()) {
                        Log::channel('notifications')->warning('Monitor FCM push failed', [
                            'monitor_id' => $monitorId,
                            'type' => $type,
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                        continue;
                    }

                    $this->cleanupInvalidTokens($chunk, $response->json());
                } catch (\Throwable $exception) {
                    Log::channel('notifications')->warning('Monitor FCM push error', [
                        'monitor_id' => $monitorId,
                        'type' => $type,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }

    private function sendFcmPushV1(string $type, int $monitorId, array $payload, string $projectId): void
    {
        $accessToken = $this->getFcmAccessToken();
        if (!$accessToken) {
            return;
        }

        $tokens = MonitorPushToken::query()
            ->where('monitor_id', $monitorId)
            ->get(['token', 'locale']);

        if ($tokens->isEmpty()) {
            return;
        }

        $tokensByLocale = $tokens->groupBy(function ($item) {
            return $this->normalizeLocale($item->locale ?? null);
        });

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        foreach ($tokensByLocale as $locale => $tokenList) {
            $notification = $this->resolveNotificationCopy($type, $payload['payload'] ?? [], $locale);
                    $data = [
                        'type' => (string) $type,
                        'payload' => json_encode($payload['payload'] ?? []),
                        'notification_id' => (string) ($payload['notification_id'] ?? ''),
                    ];

            foreach ($tokenList as $tokenItem) {
                $token = $tokenItem->token ?? null;
                if (!$token) {
                    continue;
                }

                try {
                    $response = Http::withToken($accessToken)
                        ->post($url, [
                            'message' => [
                                'token' => $token,
                                'notification' => [
                                    'title' => $notification['title'] ?? 'Boukii',
                                    'body' => $notification['body'] ?? '',
                                ],
                                'data' => $data,
                                'android' => [
                                    'priority' => 'HIGH',
                                ],
                                'apns' => [
                                    'headers' => [
                                        'apns-priority' => '10',
                                    ],
                                    'payload' => [
                                        'aps' => [
                                            'sound' => 'default',
                                        ],
                                    ],
                                ],
                            ],
                        ]);

                    if ($response->successful()) {
                        continue;
                    }

                    $status = $response->status();
                    $body = $response->json();
                    Log::channel('notifications')->warning('Monitor FCM v1 push failed', [
                        'monitor_id' => $monitorId,
                        'type' => $type,
                        'status' => $status,
                        'body' => $body,
                    ]);

                    if (in_array($status, [404, 410], true) || $this->isFcmTokenInvalid($body)) {
                        MonitorPushToken::where('token', $token)->delete();
                    }
                } catch (\Throwable $exception) {
                    Log::channel('notifications')->warning('Monitor FCM v1 push error', [
                        'monitor_id' => $monitorId,
                        'type' => $type,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }

    private function getFcmAccessToken(): ?string
    {
        $cacheKey = 'fcm.access_token';
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $serviceAccount = $this->loadServiceAccount();
        if (!$serviceAccount) {
            Log::channel('notifications')->warning('FCM v1 skipped: service account missing or invalid');
            return null;
        }

        $clientEmail = $serviceAccount['client_email'] ?? null;
        $privateKey = $serviceAccount['private_key'] ?? null;
        if (!$clientEmail || !$privateKey) {
            Log::channel('notifications')->warning('FCM v1 skipped: service account missing keys');
            return null;
        }

        $now = time();
        $jwt = $this->buildJwt($clientEmail, $privateKey, $now);
        if (!$jwt) {
            Log::channel('notifications')->warning('FCM v1 skipped: JWT build failed');
            return null;
        }

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$response->successful()) {
                Log::channel('notifications')->warning('FCM v1 token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;
            $expiresIn = (int) ($data['expires_in'] ?? 0);
            if ($token) {
                $ttl = max(300, $expiresIn - 60);
                Cache::put($cacheKey, $token, $ttl);
            }

            return $token;
        } catch (\Throwable $exception) {
            Log::channel('notifications')->warning('FCM v1 token request error', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    private function buildJwt(string $clientEmail, string $privateKey, int $now): ?string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signatureInput = "{$header}.{$claims}";
        $signature = '';
        $success = openssl_sign($signatureInput, $signature, $privateKey, 'sha256');
        if (!$success) {
            return null;
        }

        return "{$signatureInput}." . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function loadServiceAccount(): ?array
    {
        $raw = config('services.fcm.service_account_json');
        if (!$raw) {
            return null;
        }

        $json = null;
        if (is_string($raw) && file_exists($raw)) {
            $json = file_get_contents($raw);
        } elseif (is_string($raw) && str_starts_with(trim($raw), '{')) {
            $json = $raw;
        }

        if (!$json) {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function isFcmTokenInvalid(?array $body): bool
    {
        $status = $body['error']['status'] ?? null;
        $message = $body['error']['message'] ?? null;
        if (in_array($status, ['NOT_FOUND', 'UNREGISTERED'], true)) {
            return true;
        }
        if (is_string($message) && stripos($message, 'not registered') !== false) {
            return true;
        }
        return false;
    }

    private function cleanupInvalidTokens(array $tokens, ?array $response): void
    {
        if (!is_array($response) || empty($response['results'])) {
            return;
        }

        $invalidTokens = [];
        foreach ($response['results'] as $index => $result) {
            $error = $result['error'] ?? null;
            if (!$error) {
                continue;
            }
            if (in_array($error, ['InvalidRegistration', 'NotRegistered', 'MismatchSenderId'], true)) {
                $invalidTokens[] = $tokens[$index] ?? null;
            }
        }

        $invalidTokens = array_filter($invalidTokens);
        if (!empty($invalidTokens)) {
            MonitorPushToken::whereIn('token', $invalidTokens)->delete();
        }
    }

    private function resolveNotificationCopy(string $type, array $payload, ?string $locale): array
    {
        $locale = $this->normalizeLocale($locale);
        $when = $this->formatWhen($payload);
        $bookingLabel = $payload['booking_id'] ?? $payload['course_date_id'] ?? null;
        $nwdType = $this->resolveNwdTypeLabel($payload, $locale);

        $copy = [
            'es' => [
                'booking_created' => ['title' => 'Nueva reserva', 'body' => $when ? "Nueva reserva para {$when}." : 'Tienes una nueva reserva.'],
                'booking_updated' => ['title' => 'Reserva actualizada', 'body' => $when ? "Reserva actualizada para {$when}." : 'Se actualizo una reserva.'],
                'booking_cancelled' => ['title' => 'Reserva cancelada', 'body' => $when ? "Reserva cancelada para {$when}." : 'Se cancelo una reserva.'],
                'group_assigned' => ['title' => 'Reserva asignada', 'body' => $when ? "Nueva reserva asignada para {$when}." : 'Tienes una nueva reserva asignada.'],
                'private_assigned' => ['title' => 'Reserva asignada', 'body' => $when ? "Nueva reserva asignada para {$when}." : 'Tienes una nueva reserva asignada.'],
                'group_removed' => ['title' => 'Reserva desasignada', 'body' => $bookingLabel ? "Te han quitado de la reserva {$bookingLabel}." : 'Te han quitado una reserva.'],
                'private_removed' => ['title' => 'Reserva desasignada', 'body' => $bookingLabel ? "Te han quitado de la reserva {$bookingLabel}." : 'Te han quitado una reserva.'],
                'subgroup_changed' => ['title' => 'Subgrupo actualizado', 'body' => $when ? "Se actualizo tu subgrupo para {$when}." : 'Se actualizo tu subgrupo.'],
                'nwd_created' => ['title' => 'Bloqueo creado', 'body' => $when ? "Se ha creado {$nwdType} para {$when}." : 'Se ha creado un bloqueo.'],
                'nwd_updated' => ['title' => 'Bloqueo actualizado', 'body' => $when ? "Se ha actualizado {$nwdType} para {$when}." : 'Se ha actualizado un bloqueo.'],
                'nwd_deleted' => ['title' => 'Bloqueo eliminado', 'body' => $when ? "Se ha eliminado {$nwdType} para {$when}." : 'Se ha eliminado un bloqueo.'],
            ],
            'fr' => [
                'booking_created' => ['title' => 'Nouvelle reservation', 'body' => $when ? "Nouvelle reservation pour {$when}." : 'Vous avez une nouvelle reservation.'],
                'booking_updated' => ['title' => 'Reservation mise a jour', 'body' => $when ? "Reservation mise a jour pour {$when}." : 'Une reservation a ete mise a jour.'],
                'booking_cancelled' => ['title' => 'Reservation annulee', 'body' => $when ? "Reservation annulee pour {$when}." : 'Une reservation a ete annulee.'],
                'group_assigned' => ['title' => 'Reservation assignee', 'body' => $when ? "Nouvelle reservation assignee pour {$when}." : 'Vous avez une nouvelle reservation.'],
                'private_assigned' => ['title' => 'Reservation assignee', 'body' => $when ? "Nouvelle reservation assignee pour {$when}." : 'Vous avez une nouvelle reservation.'],
                'group_removed' => ['title' => 'Reservation retiree', 'body' => $bookingLabel ? "Vous avez ete retire de la reservation {$bookingLabel}." : 'Une reservation a ete retiree.'],
                'private_removed' => ['title' => 'Reservation retiree', 'body' => $bookingLabel ? "Vous avez ete retire de la reservation {$bookingLabel}." : 'Une reservation a ete retiree.'],
                'subgroup_changed' => ['title' => 'Sous-groupe mis a jour', 'body' => $when ? "Votre sous-groupe a ete mis a jour pour {$when}." : 'Votre sous-groupe a ete mis a jour.'],
                'nwd_created' => ['title' => 'Blocage cree', 'body' => $when ? "Un {$nwdType} a ete cree pour {$when}." : 'Un blocage a ete cree.'],
                'nwd_updated' => ['title' => 'Blocage mis a jour', 'body' => $when ? "Un {$nwdType} a ete mis a jour pour {$when}." : 'Un blocage a ete mis a jour.'],
                'nwd_deleted' => ['title' => 'Blocage supprime', 'body' => $when ? "Un {$nwdType} a ete supprime pour {$when}." : 'Un blocage a ete supprime.'],
            ],
            'en' => [
                'booking_created' => ['title' => 'New booking', 'body' => $when ? "New booking for {$when}." : 'You have a new booking.'],
                'booking_updated' => ['title' => 'Booking updated', 'body' => $when ? "Booking updated for {$when}." : 'A booking was updated.'],
                'booking_cancelled' => ['title' => 'Booking cancelled', 'body' => $when ? "Booking cancelled for {$when}." : 'A booking was cancelled.'],
                'group_assigned' => ['title' => 'Booking assigned', 'body' => $when ? "New booking assigned for {$when}." : 'You have a new booking assigned.'],
                'private_assigned' => ['title' => 'Booking assigned', 'body' => $when ? "New booking assigned for {$when}." : 'You have a new booking assigned.'],
                'group_removed' => ['title' => 'Booking removed', 'body' => $bookingLabel ? "You were removed from booking {$bookingLabel}." : 'You were removed from a booking.'],
                'private_removed' => ['title' => 'Booking removed', 'body' => $bookingLabel ? "You were removed from booking {$bookingLabel}." : 'You were removed from a booking.'],
                'subgroup_changed' => ['title' => 'Subgroup updated', 'body' => $when ? "Your subgroup was updated for {$when}." : 'Your subgroup was updated.'],
                'nwd_created' => ['title' => 'Block created', 'body' => $when ? "A {$nwdType} was created for {$when}." : 'A block was created.'],
                'nwd_updated' => ['title' => 'Block updated', 'body' => $when ? "A {$nwdType} was updated for {$when}." : 'A block was updated.'],
                'nwd_deleted' => ['title' => 'Block removed', 'body' => $when ? "A {$nwdType} was removed for {$when}." : 'A block was removed.'],
            ],
            'de' => [
                'booking_created' => ['title' => 'Neue Buchung', 'body' => $when ? "Neue Buchung fuer {$when}." : 'Du hast eine neue Buchung.'],
                'booking_updated' => ['title' => 'Buchung aktualisiert', 'body' => $when ? "Buchung aktualisiert fuer {$when}." : 'Eine Buchung wurde aktualisiert.'],
                'booking_cancelled' => ['title' => 'Buchung storniert', 'body' => $when ? "Buchung storniert fuer {$when}." : 'Eine Buchung wurde storniert.'],
                'group_assigned' => ['title' => 'Buchung zugewiesen', 'body' => $when ? "Neue Buchung zugewiesen fuer {$when}." : 'Dir wurde eine neue Buchung zugewiesen.'],
                'private_assigned' => ['title' => 'Buchung zugewiesen', 'body' => $when ? "Neue Buchung zugewiesen fuer {$when}." : 'Dir wurde eine neue Buchung zugewiesen.'],
                'group_removed' => ['title' => 'Buchung entfernt', 'body' => $bookingLabel ? "Du wurdest von Buchung {$bookingLabel} entfernt." : 'Du wurdest von einer Buchung entfernt.'],
                'private_removed' => ['title' => 'Buchung entfernt', 'body' => $bookingLabel ? "Du wurdest von Buchung {$bookingLabel} entfernt." : 'Du wurdest von einer Buchung entfernt.'],
                'subgroup_changed' => ['title' => 'Untergruppe aktualisiert', 'body' => $when ? "Deine Untergruppe wurde fuer {$when} aktualisiert." : 'Deine Untergruppe wurde aktualisiert.'],
                'nwd_created' => ['title' => 'Block erstellt', 'body' => $when ? "Ein {$nwdType} wurde fuer {$when} erstellt." : 'Ein Block wurde erstellt.'],
                'nwd_updated' => ['title' => 'Block aktualisiert', 'body' => $when ? "Ein {$nwdType} wurde fuer {$when} aktualisiert." : 'Ein Block wurde aktualisiert.'],
                'nwd_deleted' => ['title' => 'Block entfernt', 'body' => $when ? "Ein {$nwdType} wurde fuer {$when} entfernt." : 'Ein Block wurde entfernt.'],
            ],
            'it' => [
                'booking_created' => ['title' => 'Nuova prenotazione', 'body' => $when ? "Nuova prenotazione per {$when}." : 'Hai una nuova prenotazione.'],
                'booking_updated' => ['title' => 'Prenotazione aggiornata', 'body' => $when ? "Prenotazione aggiornata per {$when}." : 'Una prenotazione e stata aggiornata.'],
                'booking_cancelled' => ['title' => 'Prenotazione annullata', 'body' => $when ? "Prenotazione annullata per {$when}." : 'Una prenotazione e stata annullata.'],
                'group_assigned' => ['title' => 'Prenotazione assegnata', 'body' => $when ? "Nuova prenotazione assegnata per {$when}." : 'Hai una nuova prenotazione assegnata.'],
                'private_assigned' => ['title' => 'Prenotazione assegnata', 'body' => $when ? "Nuova prenotazione assegnata per {$when}." : 'Hai una nuova prenotazione assegnata.'],
                'group_removed' => ['title' => 'Prenotazione rimossa', 'body' => $bookingLabel ? "Sei stato rimosso dalla prenotazione {$bookingLabel}." : 'Sei stato rimosso da una prenotazione.'],
                'private_removed' => ['title' => 'Prenotazione rimossa', 'body' => $bookingLabel ? "Sei stato rimosso dalla prenotazione {$bookingLabel}." : 'Sei stato rimosso da una prenotazione.'],
                'subgroup_changed' => ['title' => 'Sottogruppo aggiornato', 'body' => $when ? "Il tuo sottogruppo e stato aggiornato per {$when}." : 'Il tuo sottogruppo e stato aggiornato.'],
                'nwd_created' => ['title' => 'Blocco creato', 'body' => $when ? "Un {$nwdType} e stato creato per {$when}." : 'Un blocco e stato creato.'],
                'nwd_updated' => ['title' => 'Blocco aggiornato', 'body' => $when ? "Un {$nwdType} e stato aggiornato per {$when}." : 'Un blocco e stato aggiornato.'],
                'nwd_deleted' => ['title' => 'Blocco rimosso', 'body' => $when ? "Un {$nwdType} e stato rimosso per {$when}." : 'Un blocco e stato rimosso.'],
            ],
        ];

        $resolved = $copy[$locale][$type] ?? null;
        if ($resolved) {
            return $resolved;
        }

        return [
            'title' => $copy[$locale]['group_assigned']['title'] ?? 'Boukii',
            'body' => $copy[$locale]['group_assigned']['body'] ?? '',
        ];
    }

    private function formatWhen(array $payload): ?string
    {
        $parts = [];
        if (!empty($payload['date'])) {
            $parts[] = $payload['date'];
        }
        if (!empty($payload['hour_start']) && !empty($payload['hour_end'])) {
            $parts[] = "{$payload['hour_start']}-{$payload['hour_end']}";
        }

        return empty($parts) ? null : implode(' ', $parts);
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));
        if ($locale === '') {
            return 'es';
        }
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }

        return in_array($locale, ['es', 'fr', 'en', 'de', 'it'], true) ? $locale : 'es';
    }

    private function resolveNwdTypeLabel(array $payload, string $locale): string
    {
        $typeKey = $payload['nwd_type'] ?? null;
        $subtypeId = $payload['nwd_subtype_id'] ?? $payload['user_nwd_subtype_id'] ?? null;
        if (!$typeKey) {
            $typeKey = ((int) $subtypeId === 2) ? 'paid' : (((int) $subtypeId === 1) ? 'unpaid' : 'absence');
        }
        $labels = [
            'es' => [
                'paid' => 'bloqueo pagado',
                'unpaid' => 'bloqueo no pagado',
                'absence' => 'ausencia',
            ],
            'fr' => [
                'paid' => 'blocage paye',
                'unpaid' => 'blocage non paye',
                'absence' => 'absence',
            ],
            'en' => [
                'paid' => 'paid block',
                'unpaid' => 'unpaid block',
                'absence' => 'absence',
            ],
            'de' => [
                'paid' => 'bezahlter Block',
                'unpaid' => 'unbezahlter Block',
                'absence' => 'Abwesenheit',
            ],
            'it' => [
                'paid' => 'blocco pagato',
                'unpaid' => 'blocco non pagato',
                'absence' => 'assenza',
            ],
        ];

        return $labels[$locale][$typeKey] ?? $labels[$locale]['unpaid'] ?? '';
    }
    private function sendEmailFallback(Monitor $monitor, string $type, array $payload): void
    {
        if (empty($monitor->email)) {
            Log::channel('notifications')->info('Monitor notification email skipped: missing email', [
                'monitor_id' => $monitor->id,
                'type' => $type,
            ]);
            return;
        }

        $lines = [
            'Tipo: ' . $type,
            'Curso: ' . ($payload['course_id'] ?? 'n/a'),
            'Fecha de curso: ' . ($payload['course_date_id'] ?? 'n/a'),
            'Fecha: ' . ($payload['date'] ?? 'n/a'),
            'Horario: ' . (($payload['hour_start'] ?? '??') . ' - ' . ($payload['hour_end'] ?? '??')),
        ];

        if (!empty($payload['client_ids'])) {
            $lines[] = 'Clients: ' . implode(',', (array) $payload['client_ids']);
        } elseif (!empty($payload['client_id'])) {
            $lines[] = 'Client: ' . $payload['client_id'];
        }

        $subject = '[Boukii] Notificacion de monitor';
        $body = implode("\n", $lines);

        try {
            Mail::raw($body, function ($message) use ($monitor, $subject) {
                $message->to($monitor->email)->subject($subject);
            });
        } catch (\Throwable $exception) {
            Log::channel('notifications')->warning('Monitor notification email failed', [
                'monitor_id' => $monitor->id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildPayload(
        array $payload,
        int $monitorId,
        ?int $monitorSchoolId,
        ?int $actorId = null
    ): array {
        $normalized = $payload;
        $normalized['monitor_id'] = $monitorId;

        if (!isset($normalized['school_id']) && $monitorSchoolId) {
            $normalized['school_id'] = $monitorSchoolId;
        }

        if ($actorId !== null && !isset($normalized['actor_id'])) {
            $normalized['actor_id'] = $actorId;
        }

        if (isset($normalized['client_id']) && !isset($normalized['client_ids'])) {
            $normalized['client_ids'] = [$normalized['client_id']];
        }

        return $normalized;
    }

    private function storeNotification(
        int $monitorId,
        string $type,
        array $payload,
        array $notification,
        ?int $actorId
    ): ?int {
        try {
            $record = AppNotification::create([
                'recipient_type' => 'monitor',
                'recipient_id' => $monitorId,
                'actor_id' => $actorId,
                'school_id' => $payload['school_id'] ?? null,
                'type' => $type,
                'title' => $notification['title'] ?? 'Boukii',
                'body' => $notification['body'] ?? '',
                'payload' => $payload,
                'event_date' => $payload['date'] ?? null,
                'read_at' => null,
            ]);
            return $record->id;
        } catch (\Throwable $exception) {
            Log::channel('notifications')->warning('Monitor notification db save failed', [
                'monitor_id' => $monitorId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
        return null;
    }
}
