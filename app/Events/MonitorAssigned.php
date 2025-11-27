<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonitorAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $monitorId;

    public array $data;

    public function __construct(int $monitorId, array $data)
    {
        $this->monitorId = $monitorId;
        $this->data = $data;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('monitor.' . $this->monitorId);
    }

    public function broadcastAs(): string
    {
        return 'monitor.assigned';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
