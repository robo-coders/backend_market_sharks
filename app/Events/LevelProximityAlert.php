<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LevelProximityAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public float $price,
        public array $levels, // [['label' => 'S2', 'value' => 4064.0], ...]
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        // Same channel the dashboard already subscribes to.
        return new PrivateChannel('team.dashboard');
    }

    public function broadcastAs(): string
    {
        return 'level.alert';
    }

    public function broadcastWith(): array
    {
        return [
            'price' => $this->price,
            'levels' => $this->levels,
        ];
    }
}