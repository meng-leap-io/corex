<?php

declare(strict_types=1);

namespace App\Services\Windows;

class TaskSchedulerService
{
    public static function createTask(
        string $taskName,
        string $scriptPath,
        string $triggerType = 'daily',
        string $triggerTime = '09:00',
        array $options = []
    ): bool {
        if (! ComService::isWindows()) {
            return false;
        }

        $description = $options['description'] ?? "Corex: $taskName";
        $workingDir = $options['working_dir'] ?? dirname(base_path());
        $user = $options['user'] ?? 'SYSTEM';
        $interval = $options['interval_minutes'] ?? null;

        $ps = self::buildCreateTaskScript(
            $taskName,
            $scriptPath,
            $description,
            $workingDir,
            $triggerType,
            $triggerTime,
            $interval,
            $user
        );

        ComService::powershell($ps);
        EventLogService::info("Scheduled task created: $taskName");

        return true;
    }

    private static function buildCreateTaskScript(
        string $taskName,
        string $scriptPath,
        string $description,
        string $workingDir,
        string $triggerType,
        string $triggerTime,
        ?int $interval,
        string $user
    ): string {
        $escapedName = escapeshellarg($taskName);
        $escapedPath = escapeshellarg($scriptPath);
        $escapedDir = escapeshellarg($workingDir);
        $escapedDesc = escapeshellarg($description);
        $escapedUser = escapeshellarg($user);

        $trigger = match ($triggerType) {
            'daily' => '-Daily -At '.escapeshellarg($triggerTime),
            'hourly' => '-Daily -At '.escapeshellarg($triggerTime).' -RepetitionInterval (New-TimeSpan -Minutes 60)',
            'onstart' => '-AtStartup',
            'onlogon' => '-AtLogOn',
            'onidle' => '-AtIdle',
            'minute' => $interval
                ? '-Daily -At '.escapeshellarg($triggerTime)." -RepetitionInterval (New-TimeSpan -Minutes $interval)"
                : '-Daily -At '.escapeshellarg($triggerTime),
            default => '-Daily -At '.escapeshellarg($triggerTime),
        };

        return <<<PS
Register-ScheduledJob -Name $escapedName -ScriptBlock {
    & "$escapedPath"
} -Trigger (New-JobTrigger $trigger) -ScheduledJobOption (New-ScheduledJobOption -RunElevated -WakeToRun) 2>&1 | Out-Null
Start-Sleep -Seconds 1
Unregister-ScheduledJob -Name $escapedName -Force -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $escapedName -Action (New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -File $escapedPath" -WorkingDirectory $escapedDir) -Trigger (New-ScheduledTaskTrigger $trigger) -Settings (New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable) -Principal (New-ScheduledTaskPrincipal -UserId $escapedUser -LogonType ServiceAccount -RunLevel Highest) -Description $escapedDesc -Force
PS;
    }

    public static function deleteTask(string $taskName): bool
    {
        if (! ComService::isWindows()) {
            return false;
        }
        $escapedName = escapeshellarg($taskName);
        ComService::powershell(
            "Unregister-ScheduledTask -TaskName $escapedName -Confirm:\$false 2>\$null"
        );
        EventLogService::info("Scheduled task deleted: $taskName");

        return true;
    }

    public static function enableTask(string $taskName): bool
    {
        if (! ComService::isWindows()) {
            return false;
        }
        ComService::powershell(
            'Enable-ScheduledTask -TaskName '.escapeshellarg($taskName).' 2>$null'
        );

        return true;
    }

    public static function disableTask(string $taskName): bool
    {
        if (! ComService::isWindows()) {
            return false;
        }
        ComService::powershell(
            'Disable-ScheduledTask -TaskName '.escapeshellarg($taskName).' 2>$null'
        );

        return true;
    }

    public static function startTask(string $taskName): bool
    {
        if (! ComService::isWindows()) {
            return false;
        }
        ComService::powershell(
            'Start-ScheduledTask -TaskName '.escapeshellarg($taskName).' 2>$null'
        );

        return true;
    }

    public static function stopTask(string $taskName): bool
    {
        if (! ComService::isWindows()) {
            return false;
        }
        ComService::powershell(
            'Stop-ScheduledTask -TaskName '.escapeshellarg($taskName).' 2>$null'
        );

        return true;
    }

    public static function getTaskStatus(string $taskName): ?string
    {
        if (! ComService::isWindows()) {
            return null;
        }
        $output = ComService::powershell(
            '(Get-ScheduledTask -TaskName '.escapeshellarg($taskName).').State 2>$null'
        );

        return trim($output) ?: null;
    }

    public static function listTasks(string $prefix = 'Corex'): array
    {
        if (! ComService::isWindows()) {
            return [];
        }
        $output = ComService::powershell(
            "Get-ScheduledTask -TaskName '$prefix*' | "
            .'Select-Object TaskName,State,LastRunTime,NextRunTime | '
            .'ConvertTo-Json 2>$null'
        );

        return json_decode(trim($output), true) ?? [];
    }

    public static function getTaskDetails(string $taskName): ?array
    {
        if (! ComService::isWindows()) {
            return null;
        }
        $output = ComService::powershell(
            '(Get-ScheduledTask -TaskName '.escapeshellarg($taskName).') | '
            .'Select-Object TaskName,State,Description,Author,Date,'
            .'@{N="LastRunTime";E={$_.LastRunTime.ToString("o")}},'
            .'@{N="NextRunTime";E={$_.NextRunTime.ToString("o")}},'
            .'@{N="Actions";E={$_.Actions | Select-Object Id,Execute,Arguments}},'
            .'@{N="Triggers";E={$_.Triggers | Select-Object Id,Type,Enabled,StartBoundary,Repetition}} | '
            .'ConvertTo-Json -Depth 3 2>$null'
        );

        return json_decode(trim($output), true) ?: null;
    }

    public static function scheduleBackup(int $hour = 2, int $minute = 0): bool
    {
        $backupScript = base_path().'\\artisan';

        return self::createTask(
            'Corex Database Backup',
            $backupScript,
            'daily',
            sprintf('%02d:%02d', $hour, $minute),
            [
                'description' => 'Daily database backup for Corex',
                'working_dir' => base_path(),
                'user' => 'SYSTEM',
            ]
        );
    }

    public static function scheduleHealthCheck(int $intervalMinutes = 5): bool
    {
        $healthScript = base_path().'\\artisan corex:health';

        return self::createTask(
            'Corex Health Check',
            $healthScript,
            'minute',
            date('H:i'),
            [
                'description' => 'Periodic health check for Corex services',
                'interval_minutes' => $intervalMinutes,
                'working_dir' => base_path(),
                'user' => 'SYSTEM',
            ]
        );
    }

    public static function clearAllCorexTasks(): void
    {
        if (! ComService::isWindows()) {
            return;
        }

        $tasks = self::listTasks('Corex');
        if (! empty($tasks) && isset($tasks[0])) {
            foreach ($tasks as $task) {
                self::deleteTask($task['TaskName']);
            }
        } elseif (! empty($tasks)) {
            self::deleteTask($tasks['TaskName']);
        }
    }
}
