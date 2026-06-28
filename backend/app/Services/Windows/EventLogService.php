<?php

declare(strict_types=1);

namespace App\Services\Windows;

class EventLogService
{
    private const LOG = 'Application';

    private const SOURCE = 'Corex';

    public static function info(string $message, int $eventId = 100): void
    {
        self::write($message, 'INFO', $eventId);
    }

    public static function warning(string $message, int $eventId = 200): void
    {
        self::write($message, 'WARNING', $eventId);
    }

    public static function error(string $message, int $eventId = 500): void
    {
        self::write($message, 'ERROR', $eventId);
    }

    public static function write(string $message, string $level = 'INFO', int $eventId = 100): void
    {
        if (! ComService::isWindows()) {
            return;
        }

        $levelMap = [
            'INFO' => 4,
            'WARNING' => 3,
            'ERROR' => 1,
        ];
        $entryType = $levelMap[$level] ?? 4;

        self::ensureSource();

        ComService::shell(
            'powershell -Command "'
            .'Write-EventLog -LogName '.self::LOG
            .' -Source '.self::SOURCE
            .' -EntryType '.$level
            .' -EventId '.((int) $eventId)
            .' -Message '.escapeshellarg($message)
            .'" 2>nul'
        );
    }

    private static function ensureSource(): void
    {
        $check = ComService::powershell(
            '[System.Diagnostics.EventLog]::SourceExists("'.self::SOURCE.'")'
        );
        if (trim($check) !== 'True') {
            ComService::powershell(
                'New-EventLog -LogName '.self::LOG.' -Source '.self::SOURCE.' 2>$null'
            );
        }
    }

    public static function getRecent(int $count = 50, ?string $level = null): array
    {
        if (! ComService::isWindows()) {
            return [];
        }
        $filter = "LogName='".self::LOG."',Source='".self::SOURCE."'";
        if ($level) {
            $entryType = match ($level) {
                'error' => 1,
                'warning' => 2,
                'info' => 4,
                default => null,
            };
            if ($entryType) {
                $filter .= ",EntryType=$entryType";
            }
        }
        $script = 'Get-WinEvent -FilterHashtable @{'.$filter.'} -MaxEvents '.$count
            .' | Select-Object TimeCreated,LevelDisplayName,Message,Id'
            .' | ConvertTo-Json -Compress 2>$null';
        $output = ComService::powershell($script);
        $events = json_decode($output, true);
        if (! is_array($events)) {
            return [];
        }
        if (isset($events['TimeCreated'])) {
            $events = [$events];
        }

        return array_map(fn (array $e): array => [
            'time' => $e['TimeCreated'] ?? '',
            'level' => $e['LevelDisplayName'] ?? 'Unknown',
            'message' => $e['Message'] ?? '',
            'id' => $e['Id'] ?? 0,
        ], $events);
    }

    public static function clear(): void
    {
        if (! ComService::isWindows()) {
            return;
        }
        ComService::powershell(
            'Clear-EventLog -LogName '.self::LOG.' 2>$null'
        );
    }
}
