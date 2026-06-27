<?php

declare(strict_types=1);

namespace App\Services\Windows;

class PerformanceMonitorService
{
    public static function getCpuUsage(): float
    {
        if (!ComService::isWindows()) {
            return 0.0;
        }
        return (float) ComService::powershell(
            'Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average | Select-Object -ExpandProperty Average'
        );
    }

    public static function getMemoryUsage(): array
    {
        if (!ComService::isWindows()) {
            return ['total_bytes' => 0, 'available_bytes' => 0, 'used_bytes' => 0, 'used_percent' => 0.0];
        }
        $json = ComService::powershell(
            'Get-CimInstance Win32_OperatingSystem | '
            . 'Select-Object TotalVisibleMemorySize,FreePhysicalMemory | '
            . 'ConvertTo-Json 2>$null'
        );
        $data = json_decode(trim($json), true);
        if (!$data) {
            return ['total_bytes' => 0, 'available_bytes' => 0, 'used_bytes' => 0, 'used_percent' => 0.0];
        }
        $totalKb = (int) ($data['TotalVisibleMemorySize'] ?? 0);
        $freeKb = (int) ($data['FreePhysicalMemory'] ?? 0);
        $usedKb = $totalKb - $freeKb;
        return [
            'total_bytes' => $totalKb * 1024,
            'available_bytes' => $freeKb * 1024,
            'used_bytes' => $usedKb * 1024,
            'used_percent' => $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0.0,
        ];
    }

    public static function getDiskUsage(string $drive = 'C:'): array
    {
        if (!ComService::isWindows()) {
            return ['drive' => $drive, 'total_bytes' => 0, 'free_bytes' => 0, 'used_percent' => 0.0];
        }
        $json = ComService::powershell(
            'Get-CimInstance Win32_LogicalDisk -Filter "DeviceID=\'$drive\'" | '
            . 'Select-Object DeviceID,Size,FreeSpace | ConvertTo-Json 2>$null'
        );
        $data = json_decode(trim($json), true);
        if (!$data) {
            return ['drive' => $drive, 'total_bytes' => 0, 'free_bytes' => 0, 'used_percent' => 0.0];
        }
        $total = (int) ($data['Size'] ?? 0);
        $free = (int) ($data['FreeSpace'] ?? 0);
        return [
            'drive' => $drive,
            'total_bytes' => $total,
            'free_bytes' => $free,
            'used_percent' => $total > 0 ? round((($total - $free) / $total) * 100, 1) : 0.0,
        ];
    }

    public static function getNetworkUsage(): array
    {
        if (!ComService::isWindows()) {
            return [];
        }
        $json = ComService::powershell(
            'Get-CimInstance Win32_NetworkAdapter -Filter "NetEnabled=true" | '
            . 'Select-Object Name,AdapterType,NetEnabled,Speed,MacAddress | '
            . 'ConvertTo-Json 2>$null'
        );
        return json_decode(trim($json), true) ?? [];
    }

    public static function getProcessList(int $top = 20): array
    {
        if (!ComService::isWindows()) {
            return [];
        }
        $json = ComService::powershell(
            "Get-Process | Sort-Object CPU -Descending | Select-Object -First $top "
            . 'Name,Id,CPU,@{N="WorkingSetMB";E={[math]::Round($_.WorkingSet64/1MB,1)}},'
            . '@{N="PrivateMemoryMB";E={[math]::Round($_.PrivateMemorySize64/1MB,1)}},'
            . 'StartTime,MainWindowTitle | ConvertTo-Json 2>$null'
        );
        return json_decode(trim($json), true) ?? [];
    }

    public static function getSystemInfo(): array
    {
        if (!ComService::isWindows()) {
            return [];
        }
        $json = ComService::powershell(
            'Get-CimInstance Win32_ComputerSystem | '
            . 'Select-Object Manufacturer,Model,TotalPhysicalMemory,NumberOfProcessors,'
            . 'NumberOfLogicalProcessors,SystemType,Domain,UserName | '
            . 'ConvertTo-Json 2>$null'
        );
        return json_decode(trim($json), true) ?? [];
    }

    public static function getOsInfo(): array
    {
        if (!ComService::isWindows()) {
            return [];
        }
        $json = ComService::powershell(
            'Get-CimInstance Win32_OperatingSystem | '
            . 'Select-Object Caption,Version,BuildNumber,OSArchitecture,InstallDate,'
            . 'LastBootUpTime,SerialNumber | ConvertTo-Json 2>$null'
        );
        $data = json_decode(trim($json), true);
        if ($data) {
            $data['UptimeDays'] = self::uptimeDays();
        }
        return $data ?? [];
    }

    public static function uptimeDays(): float
    {
        if (!ComService::isWindows()) {
            return 0.0;
        }
        return (float) ComService::powershell(
            '([Environment]::TickCount / 86400000)'
        );
    }

    public static function getServiceStatus(string $serviceName): ?string
    {
        if (!ComService::isWindows()) {
            return null;
        }
        $json = ComService::powershell(
            '(Get-Service -Name ' . escapeshellarg($serviceName) . ' -ErrorAction SilentlyContinue) | '
            . 'Select-Object Name,Status,StartType,DisplayName | ConvertTo-Json 2>$null'
        );
        return json_decode(trim($json), true);
    }

    public static function getCorexServices(): array
    {
        $serviceNames = ['CorexRedis', 'CorexPHP', 'CorexNginx', 'CorexAIGateway', 'CorexServiceHost', 'Redis', 'nginx'];
        $services = [];
        foreach ($serviceNames as $name) {
            $status = self::getServiceStatus($name);
            if ($status) {
                $services[] = $status;
            }
        }
        return $services;
    }

    public static function getPerformanceCounters(): array
    {
        if (!ComService::isWindows()) {
            return [];
        }
        $json = ComService::powershell(
            'Get-Counter -Counter "\\Processor(_Total)\\% Processor Time",'
            . '"\\Memory\\Available MBytes",'
            . '"\\PhysicalDisk(_Total)\\% Disk Time",'
            . '"\\Network Interface(*)\\Bytes Total/sec" '
            . '-SampleInterval 1 -MaxSamples 1 2>$null | ConvertTo-Json 2>$null'
        );
        return json_decode(trim($json), true) ?? [];
    }

    public static function getApplicationMetrics(): array
    {
        return [
            'php' => self::getProcessInfo('php'),
            'python' => self::getProcessInfo('python'),
            'nginx' => self::getProcessInfo('nginx'),
            'redis' => self::getProcessInfo('redis'),
            'node' => self::getProcessInfo('node'),
            'electron' => self::getProcessInfo('electron'),
        ];
    }

    private static function getProcessInfo(string $name): array
    {
        if (!ComService::isWindows()) {
            return ['running' => false, 'cpu' => 0.0, 'memory_mb' => 0.0];
        }
        $json = ComService::powershell(
            "Get-Process -Name $name -ErrorAction SilentlyContinue | "
            . 'Measure-Object -Property CPU,WorkingSet -Average | '
            . 'Select-Object @{N="Running";E={\$true}},'
            . '@{N="CPU";E={[math]::Round(\$_.AverageCPU,2)}},'
            . '@{N="MemoryMB";E={[math]::Round(\$_.AverageWorkingSet/1MB,1)}} | '
            . 'ConvertTo-Json 2>$null'
        );
        $data = json_decode(trim($json), true);
        if (!$data) {
            return ['running' => false, 'cpu' => 0.0, 'memory_mb' => 0.0];
        }
        return [
            'running' => true,
            'cpu' => (float) ($data['CPU'] ?? 0),
            'memory_mb' => (float) ($data['MemoryMB'] ?? 0),
        ];
    }

    public static function takeSnapshot(): array
    {
        return [
            'timestamp' => date('c'),
            'hostname' => gethostname(),
            'os' => self::getOsInfo(),
            'system' => self::getSystemInfo(),
            'cpu' => ['usage_percent' => self::getCpuUsage()],
            'memory' => self::getMemoryUsage(),
            'disks' => [self::getDiskUsage('C:')],
            'services' => self::getCorexServices(),
            'processes' => self::getProcessList(10),
            'uptime_days' => self::uptimeDays(),
        ];
    }
}
