<?php

namespace App\Events;

use App\Models\MarketStructure;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMarketStructureUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, string>  $changed  Keys the admin actually changed,
     *                                        e.g. ['support_2']. Empty = unknown
     *                                        (frontend falls back to all set levels).
     */
    public function __construct(
        public MarketStructure $structure,
        public array $changed = [],
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('team.dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'market-structure.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'market_structure',
            'title' => 'Market structure updated',
            'message' => 'Support and resistance levels were updated.',
            'structure' => [
                'support_1' => $this->structure->support_1,
                'support_2' => $this->structure->support_2,
                'support_3' => $this->structure->support_3,
                'resistance_1' => $this->structure->resistance_1,
                'resistance_2' => $this->structure->resistance_2,
                'resistance_3' => $this->structure->resistance_3,
                'updated_at' => optional($this->structure->updated_at)?->format('d M Y, h:i A'),
            ],
            // Only the fields that changed this update — the toast renders
            // just these instead of every level that happens to be set.
            'changed' => array_values($this->changed),
        ];
    }
}