<?php

namespace App\Services;

use App\Events\MonitorAssigned;
use App\Events\MonitorRemoved;
use App\Models\Monitor;
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
        $eventPayload = [
            'type' => $type,
            'monitor_id' => $monitorId,
            'payload' => $normalizedPayload,
        ];

        $hasBroadcastDriver = $this->hasBroadcastDriver();
        $this->emitEvent($type, $monitorId, $eventPayload);

        Log::info('Monitor notification', $eventPayload);

        $shouldSendEmail = app()->environment('production');

        if ($shouldSendEmail || !$hasBroadcastDriver || empty($monitor->user_id)) {
            $this->sendEmailFallback($monitor, $type, $normalizedPayload);
        }
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
            Log::warning('Monitor notification skipped: pusher library missing', [
                'monitor_id' => $monitorId,
                'type' => $type,
            ]);
            return;
        }

        $event = str_contains($type, 'removed')
            ? new MonitorRemoved($monitorId, $payload)
            : new MonitorAssigned($monitorId, $payload);

        try {
            event($event);
        } catch (\Throwable $exception) {
            Log::warning('Monitor notification dispatch failed', [
                'monitor_id' => $monitorId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendEmailFallback(Monitor $monitor, string $type, array $payload): void
    {
        if (empty($monitor->email)) {
            Log::info('Monitor notification email skipped: missing email', [
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
            Log::warning('Monitor notification email failed', [
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
}
