<?php

namespace App\Http\Controllers;

use App\Models\TradeLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradeLogExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $days = (int) $request->query('days', 30);
        $since = now()->subDays($days);

        $filename = 'market-sharks-trade-logs-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($since) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Closed At',
                'Symbol',
                'Signal Type',
                'Result',
                'Close Reason',
                'Entry Price',
                'Close Price',
                'Take Profit',
                'Stop Loss',
                'Profit / Loss',
            ]);

            TradeLog::where('closed_at', '>=', $since)
                ->latest('closed_at')
                ->latest('id')
                ->chunk(200, function ($logs) use ($out) {
                    foreach ($logs as $log) {
                        fputcsv($out, [
                            $log->closed_at?->format('Y-m-d H:i:s') ?? '',
                            strtoupper($log->symbol ?? 'XAUUSD'),
                            ucfirst($log->signal_type ?? ''),
                            match ($log->result) {
                                'profit' => 'Profit',
                                'loss' => 'Loss',
                                'breakeven' => 'Breakeven',
                                default => (float) $log->profit_loss >= 0 ? 'Profit' : 'Loss',
                            },
                            match ($log->close_reason) {
                                'tp' => 'Take Profit',
                                'sl' => 'Stop Loss',
                                'manual' => 'Manual Close',
                                'cancelled' => 'Cancelled',
                                default => 'Closed',
                            },
                            $log->entry_price ?? '',
                            $log->close_price ?? '',
                            $log->take_profit ?? '',
                            $log->stop_loss ?? '',
                            $log->profit_loss ?? '0.00',
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
