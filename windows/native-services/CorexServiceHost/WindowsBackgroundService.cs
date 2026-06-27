using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace CorexServiceHost;

public sealed class WindowsBackgroundService : BackgroundService
{
    private readonly ServiceConfiguration _config;
    private readonly ProcessManager _processManager;
    private readonly EventLogger _logger;
    private readonly PerformanceMonitor _perfMon;
    private readonly IHostApplicationLifetime _hostLifetime;
    private readonly ILogger<WindowsBackgroundService> _dotnetLogger;

    public WindowsBackgroundService(
        ServiceConfiguration config,
        ProcessManager processManager,
        EventLogger logger,
        PerformanceMonitor perfMon,
        IHostApplicationLifetime hostLifetime,
        ILogger<WindowsBackgroundService> dotnetLogger)
    {
        _config = config;
        _processManager = processManager;
        _logger = logger;
        _perfMon = perfMon;
        _hostLifetime = hostLifetime;
        _dotnetLogger = dotnetLogger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInfo("========================================");
        _logger.LogInfo("Corex Service Host starting");
        _logger.LogInfo($"CorexRoot: {_config.CorexRoot}");
        _logger.LogInfo($"LogDir: {_config.LogDir}");
        _logger.LogInfo($"Worker threads: {Environment.ProcessorCount}");
        _logger.LogInfo("========================================");

        // Log startup to Windows Event Log
        try
        {
            EventLog.WriteEntry(
                "CorexServiceHost",
                "Corex Service Host started successfully",
                EventLogEntryType.Information,
                1001);
        }
        catch { }

        // Register process exit handler
        _processManager.ProcessExited += OnProcessExited;

        // Start performance monitoring
        _perfMon.Start();

        // Start all child processes
        try
        {
            await _processManager.StartAllAsync();
        }
        catch (Exception ex)
        {
            _logger.LogError($"Failed to start services: {ex.Message}");
            _logger.LogError("Shutting down...");
            _hostLifetime.StopApplication();
            return;
        }

        _logger.LogInfo("All services started. Monitoring health...");

        // Health check loop
        using var healthTimer = new PeriodicTimer(
            TimeSpan.FromSeconds(_config.HealthCheckIntervalSeconds));

        try
        {
            while (!stoppingToken.IsCancellationRequested)
            {
                await healthTimer.WaitForNextTickAsync(stoppingToken);
                await PerformHealthCheckAsync();
            }
        }
        catch (OperationCanceledException)
        {
            // Normal shutdown
        }

        _logger.LogInfo("Corex Service Host stopping...");
        await _processManager.StopAllAsync();
        _logger.LogInfo("Corex Service Host stopped");
    }

    private async Task PerformHealthCheckAsync()
    {
        // Check HTTP health endpoints
        var aiGatewayHealthy = await CheckHttpEndpointAsync(
            $"http://127.0.0.1:{_config.AIGatewayPort}/health",
            "AI Gateway");
    }

    private async Task<bool> CheckHttpEndpointAsync(string url, string name)
    {
        try
        {
            using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(10) };
            var response = await client.GetAsync(url);
            if (response.IsSuccessStatusCode)
                return true;

            _logger.LogWarning($"{name} health check returned {response.StatusCode}");
            return false;
        }
        catch (Exception ex)
        {
            _logger.LogWarning($"{name} health check failed: {ex.Message}");
            return false;
        }
    }

    private void OnProcessExited(string name, int exitCode)
    {
        _logger.LogWarning($"PROCESS_EXITED: {name} (exit code {exitCode})");

        try
        {
            EventLog.WriteEntry(
                "CorexServiceHost",
                $"Child process '{name}' exited with code {exitCode}. Restart initiated.",
                EventLogEntryType.Warning,
                2000);
        }
        catch { }
    }

    public override async Task StopAsync(CancellationToken cancellationToken)
    {
        _logger.LogInfo("Shutdown requested");
        await base.StopAsync(cancellationToken);
    }
}
