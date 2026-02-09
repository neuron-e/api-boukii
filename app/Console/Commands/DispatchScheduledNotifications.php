<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Services\MonitorNotificationService;
use Illuminate\Console\Command;

class DispatchScheduledNotifications extends Command
{
    protected $signature = 'notifications:dispatch-scheduled {--limit=200}';
    protected $description = 'Dispatch scheduled app notifications (monitors)';

    public function handle(MonitorNotificationService $notificationService): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $due = AppNotification::query()
            ->where('recipient_type', 'monitor')
            ->whereNotNull('scheduled_at')
            ->whereNull('sent_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled notifications to dispatch.');
            return Command::SUCCESS;
        }

        foreach ($due as $notification) {
            $notificationService->sendCustom(
                (int) $notification->recipient_id,
                ['title' => $notification->title, 'body' => $notification->body ?? ''],
                $notification->payload ?? [],
                $notification->actor_id,
                $notification->id
            );
            $notification->sent_at = now();
            $notification->save();
        }

        $this->info("Dispatched {$due->count()} notifications.");
        return Command::SUCCESS;
    }
}
