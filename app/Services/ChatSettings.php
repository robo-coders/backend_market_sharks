<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class ChatSettings
{
    private const CACHE_KEY = 'chat:feature-flags';

    public const FEATURES = ['edit', 'delete', 'read_receipts'];

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $map = [];
            foreach (self::FEATURES as $feature) {
                $map[$feature] = Setting::get(self::settingKey($feature)) === '1';
            }
            return $map;
        });
    }

    public static function enabled(string $feature): bool
    {
        return self::all()[$feature] ?? false;
    }

    public static function setEnabled(string $feature, bool $on): void
    {
        if (! in_array($feature, self::FEATURES, true)) {
            throw new \InvalidArgumentException("Unknown chat feature [{$feature}].");
        }

        Setting::set(self::settingKey($feature), $on ? '1' : '0');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function settingKey(string $feature): string
    {
        return "chat.feature.{$feature}";
    }
}
