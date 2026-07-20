<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForexNewsService
{
    private const CACHE_KEY = 'market:news-feed';
    private const CACHE_MINUTES = 15;

    /** @return array<int, array{title:string,source:string,time:string}> */
    public static function headlines(int $limit = 6): array
    {
        $items = Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), function () {
            $news = array_merge(self::highImpactEvents(), self::fxstreetHeadlines());

            // Newest first, cap the stored list.
            usort($news, fn ($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));

            return array_slice($news, 0, 12);
        });

        return array_map(
            fn ($i) => [
                'title'  => $i['title'],
                'source' => $i['source'],
                'time'   => self::relative($i['ts'] ?? null),
            ],
            array_slice($items, 0, $limit)
        );
    }

    /** FXStreet news RSS -> [{title, source, ts}] */
    private static function fxstreetHeadlines(): array
    {
        try {
            $resp = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'MarketSharks/1.0'])
                ->get('https://www.fxstreet.com/rss/news');

            if (! $resp->ok()) {
                return [];
            }

            $xml = @simplexml_load_string($resp->body());
            if (! $xml || ! isset($xml->channel->item)) {
                return [];
            }

            $out = [];
            foreach ($xml->channel->item as $item) {
                $out[] = [
                    'title'  => trim((string) $item->title),
                    'source' => 'FXStreet',
                    'ts'     => strtotime((string) $item->pubDate) ?: null,
                ];
                if (count($out) >= 8) {
                    break;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('FXStreet news fetch failed: '.$e->getMessage());

            return [];
        }
    }

    /** ForexFactory weekly calendar -> today's high-impact events. */
    private static function highImpactEvents(): array
    {
        try {
            $resp = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'MarketSharks/1.0'])
                ->get('https://nfs.faireconomy.media/ff_calendar_thisweek.json');

            if (! $resp->ok()) {
                return [];
            }

            $events = $resp->json();
            if (! is_array($events)) {
                return [];
            }

            $out = [];
            foreach ($events as $e) {
                if (($e['impact'] ?? '') !== 'High') {
                    continue;
                }

                $ts = strtotime($e['date'] ?? '') ?: null;
                if (! $ts || ! now()->isSameDay(\Carbon\Carbon::createFromTimestamp($ts))) {
                    continue; // today's events only
                }

                $out[] = [
                    'title'  => trim(($e['country'] ?? '').' '.($e['title'] ?? '')).' — high impact',
                    'source' => 'Economic Calendar',
                    'ts'     => $ts,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('FF calendar fetch failed: '.$e->getMessage());

            return [];
        }
    }

    private static function relative(?int $ts): string
    {
        if (! $ts) {
            return '';
        }

        $diff = time() - $ts;
        if ($diff < 0) {
            // Upcoming calendar event.
            $mins = intdiv(-$diff, 60);

            return $mins < 60 ? "in {$mins} min" : 'in '.intdiv($mins, 60).' h';
        }
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return intdiv($diff, 60).' min ago';
        }
        if ($diff < 86400) {
            return intdiv($diff, 3600).' h ago';
        }

        return intdiv($diff, 86400).' d ago';
    }
}