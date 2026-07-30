<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\MarketStructure;
use App\Models\MarketTrend;
use App\Models\TradeLog;
use App\Models\TradingSignal;
use App\Services\ForexNewsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // Prefer a live signal (open) or a placed-but-waiting one (pending)
        // over the most recent closed/cancelled record.
        $signal = TradingSignal::whereIn('status', ['open', 'pending'])
                ->latest('opened_at')->latest('id')->first()
            ?? TradingSignal::latest('opened_at')->latest('id')->first();

        $structure = MarketStructure::first();
        $trend = MarketTrend::first();
        $logs = TradeLog::latest('closed_at')->latest('id')->take(10)->get();

        // Human label for the signal card:
        //   open    → "Active trade"  (price reached entry, live position)
        //   pending → "Active signal" (placed, waiting for entry)
        //   else    → closed/cancelled
        $statusLabel = match ($signal?->status) {
            'open' => 'Active trade',
            'pending' => 'Active signal',
            'cancelled' => 'Cancelled',
            'closed' => 'Closed',
            default => 'Closed',
        };

        return Inertia::render('Team/Dashboard', [
            'market' => [
                'symbol' => $signal?->symbol ?? 'XAUUSD',
                'live_price' => (string) ($signal?->gold_price_at_entry ?? $signal?->entry_price ?? '0.00'),
                'price_change' => '+0.00',
                'price_change_percent' => '+0.00%',
                'updated_at' => $signal?->opened_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A'),
            ],
            'livePriceEndpoint' => route('team.gold-price'),
            'trend' => [
                'gold' => ucfirst($trend?->gold_trend ?? 'neutral'),
                'dollar' => ucfirst($trend?->dollar_trend ?? 'neutral'),
                'updated_at' => $trend?->updated_at?->format('d M Y, h:i A'),
            ],
            'signal' => [
                'type' => ucfirst($signal?->signal_type ?? 'buy'),
                // Raw machine status the Vue side branches on.
                'status_raw' => $signal?->status ?? 'closed',
                // Back-compat: 'Active' whenever the signal is live-ish
                // (pending or open), so existing checks keep working.
                'status' => in_array($signal?->status, ['open', 'pending'], true)
                    ? 'Active'
                    : ucfirst($signal?->status ?? 'closed'),
                // Card headline label: "Active trade" vs "Active signal".
                'status_label' => $statusLabel,
                'is_pending' => $signal?->status === 'pending',
                'is_live_trade' => $signal?->status === 'open',
                'entry_price' => (string) ($signal?->entry_price ?? '0.00'),
                'stop_loss' => (string) ($signal?->stop_loss ?? '0.00'),
                'take_profit' => (string) ($signal?->take_profit ?? '0.00'),
                'updated_at' => $signal?->opened_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A'),
            ],
            'levels' => [
                // Positions preserved (nulls included) rather than
                // filtered out — filtering + reindexing was shifting
                // later levels into earlier slots and mislabeling them
                // (e.g. support_3's value showing under "S2").
                'supports' => [
                    $structure?->support_1,
                    $structure?->support_2,
                    $structure?->support_3,
                ],
                'resistances' => [
                    $structure?->resistance_1,
                    $structure?->resistance_2,
                    $structure?->resistance_3,
                ],
                'updated_at' => $structure?->updated_at?->format('d M Y, h:i A'),
            ],
            // Live market news: FXStreet headlines + today's high-impact
            // economic-calendar events, cached 15 min in ForexNewsService.
            // Falls back to an empty list if the feeds are unreachable, so
            // the card never breaks.
            'news' => ForexNewsService::headlines(6),
            // Enables the Export button on the Market Logs card.
            'logsExportUrl' => route('team.trade-logs.export'),
            'logs' => $logs->map(function ($log) {
                $resultLabel = match ($log->result) {
                    'profit' => 'Profit',
                    'loss' => 'Loss',
                    'breakeven' => 'Breakeven',
                    default => (float) $log->profit_loss >= 0 ? 'Profit' : 'Loss',
                };

                return [
                    'result' => $resultLabel,
                    'signal_type' => ucfirst($log->signal_type),
                    'hit_level' => match ($log->close_reason) {
                        'tp' => 'Take Profit',
                        'sl' => 'Stop Loss',
                        'manual' => 'Manual Close',
                        'cancelled' => 'Cancelled',
                        default => 'Closed',
                    },
                    'price' => (string) ($log->close_price ?? '0.00'),
                    'time' => $log->closed_at?->format('d M Y, h:i A') ?? '',
                ];
            })->values(),
        ]);
    }
}