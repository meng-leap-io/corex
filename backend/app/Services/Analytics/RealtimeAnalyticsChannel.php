<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Services\Supabase\Realtime\RealtimeChannel;

class RealtimeAnalyticsChannel
{
    public const string CHANNEL_PATTERN = 'analytics:{scope}';

    public static function dashboard(?string $userId = null): RealtimeChannel
    {
        return RealtimeChannel::make(
            pattern: self::CHANNEL_PATTERN,
            id: $userId ? "dashboard:{$userId}" : 'dashboard',
            private: false,
        );
    }

    public static function admin(?string $userId = null): RealtimeChannel
    {
        return RealtimeChannel::make(
            pattern: self::CHANNEL_PATTERN,
            id: $userId ? "admin:{$userId}" : 'admin',
            private: true,
        );
    }

    public static function alerts(): RealtimeChannel
    {
        return RealtimeChannel::make(
            pattern: self::CHANNEL_PATTERN,
            id: 'alerts',
            private: true,
        );
    }
}
