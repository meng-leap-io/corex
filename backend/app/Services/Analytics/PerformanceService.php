<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\PerformanceSnapshot;
use Illuminate\Support\Facades\DB;

class PerformanceService
{
    public function recordSnapshot(): PerformanceSnapshot
    {
        $data = $this->collectSystemMetrics();
        $data['services'] = $this->getServiceStatus();
        $data['extra'] = $this->getApplicationMetrics();

        return PerformanceSnapshot::create($data);
    }

    protected function collectSystemMetrics(): array
    {
        $cpu = null;
        $memUsed = null;
        $memTotal = null;
        $diskUsed = null;
        $diskTotal = null;

        if (PHP_OS_FAMILY === 'Windows') {
            $wmi = @json_decode(shell_exec('wmic cpu get loadpercentage /format:json 2>NUL'), true);
            $cpu = (float) ($wmi[0]['LoadPercentage'] ?? 0);

            $mem = @json_decode(shell_exec('wmic OS get TotalVisibleMemorySize,FreePhysicalMemory /format:json 2>NUL'), true);
            if ($mem) {
                $memTotal = (float) ($mem[0]['TotalVisibleMemorySize'] ?? 0) / 1024;
                $freeMem = (float) ($mem[0]['FreePhysicalMemory'] ?? 0) / 1024;
                $memUsed = $memTotal - $freeMem;
            }

            $disk = @json_decode(shell_exec('wmic logicaldisk where "DeviceID=\'C:\'" get Size,FreeSpace /format:json 2>NUL'), true);
            if ($disk) {
                $diskTotal = (float) ($disk[0]['Size'] ?? 0) / (1024 * 1024);
                $freeDisk = (float) ($disk[0]['FreeSpace'] ?? 0) / (1024 * 1024);
                $diskUsed = $diskTotal - $freeDisk;
            }
        } else {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $cpu = (float) ($load[0] ?? 0);
            }

            $memInfo = @file_get_contents('/proc/meminfo');
            if ($memInfo) {
                preg_match('/MemTotal:\s+(\d+)/', $memInfo, $m);
                $memTotal = (float) ($m[1] ?? 0) / 1024;
                preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $m);
                $memAvail = (float) ($m[1] ?? 0) / 1024;
                $memUsed = $memTotal - $memAvail;
            }

            $stat = @stat('/');
            if ($stat) {
                $diskTotal = (float) ($stat['blocks'] * $stat['blksize']) / (1024 * 1024);
                $diskFree = (float) ($stat['blocks'] * $stat['blksize'] - $stat['blocks'] * $stat['blksize']);
                $diskUsed = $diskTotal - $diskFree;
            }

            $df = @shell_exec('df -B1 / 2>/dev/null');
            if ($df) {
                $parts = preg_split('/\s+/', trim(explode("\n", $df)[1] ?? ''));
                if (count($parts) >= 4) {
                    $diskTotal = (float) ($parts[1] ?? 0) / (1024 * 1024);
                    $diskUsed = (float) ($parts[2] ?? 0) / (1024 * 1024);
                }
            }
        }

        return [
            'cpu_load' => $cpu,
            'memory_used_mb' => $memUsed,
            'memory_total_mb' => $memTotal,
            'disk_used_mb' => $diskUsed,
            'disk_total_mb' => $diskTotal,
            'active_connections' => DB::connection()->getDriverName() === 'pgsql'
                ? (int) DB::select("SELECT count(*) as cnt FROM pg_stat_activity WHERE state = 'active'")[0]->cnt
                : null,
            'queue_size' => (int) DB::table('jobs')->count(),
        ];
    }

    protected function getServiceStatus(): array
    {
        $services = [];

        try {
            $redisPing = DB::connection('redis')->select('PING');
            $services['redis'] = ['status' => 'healthy', 'latency_ms' => 0];
        } catch (\Throwable $e) {
            $services['redis'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }

        try {
            DB::select('SELECT 1');
            $services['database'] = ['status' => 'healthy', 'latency_ms' => 0];
        } catch (\Throwable $e) {
            $services['database'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }

        try {
            $cacheTest = Cache::store('redis')->has('health-check');
            $services['cache'] = ['status' => 'healthy'];
        } catch (\Throwable $e) {
            $services['cache'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }

        return $services;
    }

    protected function getApplicationMetrics(): array
    {
        return [
            'total_users' => \App\Models\User::count(),
            'total_projects' => \App\Models\Project::count(),
            'total_conversations' => \App\Models\Conversation::count(),
        ];
    }

    public function getRequestRatePerMin(): float
    {
        $count = PageView::where('created_at', '>=', now()->subMinute())->count();

        return (float) $count;
    }

    public function getAverageResponseTime(): float
    {
        return (float) PageView::where('created_at', '>=', now()->subMinutes(5))->avg('duration_ms');
    }

    public function getPercentileResponseTime(float $percentile, int $minutes = 5): float
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql' || $driver === 'supabase') {
            $result = DB::select(
                "SELECT percentile_cont(?) WITHIN GROUP (ORDER BY duration_ms) as p
                 FROM page_views WHERE created_at >= ?",
                [$percentile, now()->subMinutes($minutes)]
            );

            return (float) ($result[0]->p ?? 0);
        }

        $times = PageView::where('created_at', '>=', now()->subMinutes($minutes))
            ->pluck('duration_ms')
            ->sort()
           ->values();

        if ($times->isEmpty()) {
            return 0;
        }

        $index = (int) ceil(($percentile / 100) * $times->count()) - 1;

        return (float) ($times->get(max(0, $index)) ?? 0);
    }

    public function getErrorCount(int $minutes = 5): int
    {
        return PageView::where('created_at', '>=', now()->subMinutes($minutes))
            ->where('status_code', '>=', 400)
            ->count();
    }
}
