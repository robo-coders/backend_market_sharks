<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReadReceipt implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $readAt,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('chat.room');
    }

    public function broadcastAs(): string
    {
        return 'read.receipt';
    }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId, 'read_at' => $this->readAt];
    }
}
