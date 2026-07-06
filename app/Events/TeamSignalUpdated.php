<?php

namespace App\Events;

use App\Models\TradeLog;
use App\Models\TradingSignal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamSignalUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TradingSignal $signal,
        public ?TradeLog $tradeLog = null,
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
        return 'signal.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'signal' => [
                'id' => $this->signal->id,
                'symbol' => $this->signal->symbol,
                'type' => ucfirst($this->signal->signal_type),
                'entry_price' => (string) $this->signal->entry_price,
                'stop_loss' => (string) $this->signal->stop_loss,
                'take_profit' => (string) $this->signal->take_profit,
                'status' => ucfirst($this->signal->status ?? 'open'),
                'status_raw' => $this->signal->status ?? 'open',
                'updated_at' => optional($this->signal->opened_at ?? $this->signal->updated_at)?->format('d M Y, h:i A'),
            ],
            'trade_log' => $this->tradeLog ? [
                'id' => $this->tradeLog->id,
                'symbol' => $this->tradeLog->symbol,
                'signal_type' => $this->tradeLog->signal_type,
                'close_price' => (string) $this->tradeLog->close_price,
                'profit_loss' => (string) $this->tradeLog->profit_loss,
                'result' => $this->tradeLog->result,
                'close_reason' => $this->tradeLog->close_reason,
                'closed_at' => optional($this->tradeLog->closed_at)?->format('d M Y, h:i A'),
            ] : null,
        ];
    }
}