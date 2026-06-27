using System.Diagnostics;

namespace CorexServiceHost;

public sealed class PerformanceMonitor : IDisposable
{
    private readonly ServiceConfiguration _config;
    private readonly EventLogger _logger;
    private readonly Dictionary<string, PerformanceCounter> _counters = [];
    private readonly PeriodicTimer? _timer;
    private readonly Task? _monitorTask;
    private readonly CancellationTokenSource _cts = new();
    private bool _disposed;

    public PerformanceMonitor(ServiceConfiguration config, EventLogger logger)
    {
        _config = config;
        _logger = logger;
    }

    public void Start()
    {
        try
        {
            RegisterCounters();
        }
        catch (Exception ex)
        {
            _logger.LogWarning($"Performance counters not available (run as admin): {ex.Message}");
            return;
        }

        _ = MonitorLoopAsync(_cts.Token);
    }

    private void RegisterCounters()
    {
        // Process-level counters for each service
        var processCounters = new Dictionary<string, string[]>
        {
            ["redis"] = ["redis-server"],
            ["php-fpm"] = ["php-fpm"],
            ["nginx"] = ["nginx"],
            ["ai-gateway"] = ["python"],
        };

        foreach (var (name, instances) in processCounters)
        {
            foreach (var instance in instances)
            {
                try
                {
                    _counters[$"{name}_cpu"] = new PerformanceCounter("Process", "% Processor Time", instance, readOnly: true);
                    _counters[$"{name}_mem"] = new PerformanceCounter("Process", "Private Bytes", instance, readOnly: true);
                    _counters[$"{name}_ws"] = new PerformanceCounter("Process", "Working Set", instance, readOnly: true);
                    _counters[$"{name}_threads"] = new PerformanceCounter("Process", "Thread Count", instance, readOnly: true);
                    _counters[$"{name}_handles"] = new PerformanceCounter("Process", "Handle Count", instance, readOnly: true);
                }
                catch
                {
                    // Process may not be running yet — skip
                }
            }
        }

        // System-level counters
        try
        {
            _counters["system_cpu"] = new PerformanceCounter("Processor", "% Processor Time", "_Total", readOnly: true);
            _counters["system_mem_avail"] = new PerformanceCounter("Memory", "Available MBytes", readOnly: true);
            _counters["system_mem_committed"] = new PerformanceCounter("Memory", "Committed Bytes", readOnly: true);
        }
        catch { }

        _logger.LogInfo($"Registered {_counters.Count} performance counters");
    }

    private async Task MonitorLoopAsync(CancellationToken ct)
    {
        using var timer = new PeriodicTimer(TimeSpan.FromSeconds(30));

        try
        {
            while (await timer.WaitForNextTickAsync(ct))
            {
                Snapshot();
            }
        }
        catch (OperationCanceledException) { }
    }

    private void Snapshot()
    {
        var snapshot = new Dictionary<string, float>();

        foreach (var (name, counter) in _counters)
        {
            try
            {
                snapshot[name] = counter.NextValue();
            }
            catch
            {
                // Counter stale — re-sample on next cycle
            }
        }

        var cpuCores = Environment.ProcessorCount;
        var timestamp = DateTime.UtcNow.ToString("yyyy-MM-dd HH:mm:ss");

        _logger.LogInfo(
            $"PERF {timestamp} | " +
            $"CPU: {snapshot.GetValueOrDefault("system_cpu", 0):F1}% | " +
            $"MemAvail: {snapshot.GetValueOrDefault("system_mem_avail", 0):F0} MB | " +
            $"RedisCPU: {snapshot.GetValueOrDefault("redis_cpu", 0) / cpuCores:F1}% | " +
            $"PHPCpu: {snapshot.GetValueOrDefault("php-fpm_cpu", 0) / cpuCores:F1}% | " +
            $"NginxCPU: {snapshot.GetValueOrDefault("nginx_cpu", 0) / cpuCores:F1}% | " +
            $"AIGatewayCPU: {snapshot.GetValueOrDefault("ai-gateway_cpu", 0) / cpuCores:F1}%"
        );
    }

    public void Dispose()
    {
        if (_disposed) return;
        _disposed = true;
        _cts.Cancel();
        _cts.Dispose();
        foreach (var counter in _counters.Values)
            counter.Dispose();
        _counters.Clear();
    }
}
