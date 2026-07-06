<?php

namespace App\Events;

use App\Models\MarketTrend;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMarketTrendUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MarketTrend $trend
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
        return 'market-trend.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'market_trend',
            'title' => 'Market trend updated',
            'message' => 'Gold and dollar trend were updated.',
            'trend' => [
                'gold' => ucfirst($this->trend->gold_trend ?? 'neutral'),
                'dollar' => ucfirst($this->trend->dollar_trend ?? 'neutral'),
                'updated_at' => optional($this->trend->updated_at)?->format('d M Y, h:i A'),
            ],
        ];
    }
}