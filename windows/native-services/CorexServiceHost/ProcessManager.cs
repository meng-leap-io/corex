using System.ComponentModel;
using System.Diagnostics;
using System.Runtime.InteropServices;

namespace CorexServiceHost;

public sealed class ManagedProcess
{
    public required string Name { get; init; }
    public required string FileName { get; init; }
    public string Arguments { get; init; } = "";
    public string? WorkingDirectory { get; init; }
    public Dictionary<string, string> Environment { get; init; } = [];
    public Process? Process { get; set; }
    public DateTime LastRestart { get; set; }
    public int RestartCount { get; set; }
    public const int MaxRestartsPerWindow = 5;
    public static readonly TimeSpan RestartWindow = TimeSpan.FromMinutes(5);
}

public sealed class ProcessManager : IDisposable
{
    private readonly ServiceConfiguration _config;
    private readonly EventLogger _logger;
    private readonly Dictionary<string, ManagedProcess> _processes = [];
    private readonly object _lock = new();
    private readonly CancellationTokenSource _globalCts = new();
    private bool _disposed;

    public event Action<string, int>? ProcessExited;

    public ProcessManager(ServiceConfiguration config, EventLogger logger)
    {
        _config = config;
        _logger = logger;
        InitializeProcesses();
    }

    private void InitializeProcesses()
    {
        var corexRoot = _config.CorexRoot;
        var logDir = _config.LogDir;

        _processes["redis"] = new ManagedProcess
        {
            Name = "redis-server",
            FileName = _config.RedisExe,
            Arguments = $"\"{_config.RedisConf}\" --service-run",
        };

        _processes["php"] = new ManagedProcess
        {
            Name = "php-fpm",
            FileName = _config.PHPExe,
            Arguments = $"--fpm-config \"{_config.PHPFpmConf}\" --nodaemonize",
            WorkingDirectory = Path.Combine(corexRoot, "php"),
        };

        _processes["nginx"] = new ManagedProcess
        {
            Name = "nginx",
            FileName = _config.NginxExe,
            Arguments = $"-p \"{Path.Combine(corexRoot, "nginx")}\" -c \"{_config.NginxConf}\"",
            WorkingDirectory = Path.Combine(corexRoot, "nginx"),
        };

        _processes["ai-gateway"] = new ManagedProcess
        {
            Name = "ai-gateway",
            FileName = _config.AIGatewayVenvPython,
            Arguments = $"-m uvicorn main:app --host 0.0.0.0 --port {_config.AIGatewayPort} --workers {_config.AIGatewayWorkers} --log-level info --limit-max-requests 10000",
            WorkingDirectory = _config.AIGatewayDir,
            Environment = new Dictionary<string, string>
            {
                ["PYTHONUNBUFFERED"] = "1",
                ["COREX_ROOT"] = corexRoot,
            },
        };
    }

    public async Task StartAllAsync()
    {
        // Start order: redis -> php -> ai-gateway -> nginx
        await StartProcessAsync("redis");
        await Task.Delay(2000);
        await StartProcessAsync("php");
        await Task.Delay(2000);
        await StartProcessAsync("ai-gateway");
        await Task.Delay(3000);
        await StartProcessAsync("nginx");
    }

    public async Task StopAllAsync()
    {
        // Reverse order: nginx -> ai-gateway -> php -> redis
        await StopProcessAsync("nginx");
        await StopProcessAsync("ai-gateway");
        await StopProcessAsync("php");
        await StopProcessAsync("redis");
    }

    public async Task StartProcessAsync(string key)
    {
        if (!_processes.TryGetValue(key, out var mp))
            throw new ArgumentException($"Unknown process key: {key}");

        lock (_lock)
        {
            if (mp.Process is not null && !mp.Process.HasExited)
            {
                _logger.LogInfo($"{mp.Name} is already running (PID {mp.Process.Id})");
                return;
            }
        }

        var psi = new ProcessStartInfo
        {
            FileName = mp.FileName,
            Arguments = mp.Arguments,
            WorkingDirectory = mp.WorkingDirectory ?? _config.CorexRoot,
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
        };

        foreach (var (k, v) in mp.Environment)
            psi.EnvironmentVariables[k] = v;

        var process = new Process { StartInfo = psi };

        process.OutputDataReceived += (_, e) =>
        {
            if (!string.IsNullOrEmpty(e.Data))
                _logger.LogInfo($"[{mp.Name}] {e.Data}");
        };
        process.ErrorDataReceived += (_, e) =>
        {
            if (!string.IsNullOrEmpty(e.Data))
                _logger.LogError($"[{mp.Name}] {e.Data}");
        };

        try
        {
            process.Start();
            process.BeginOutputReadLine();
            process.BeginErrorReadLine();
        }
        catch (Win32Exception ex)
        {
            _logger.LogError($"Failed to start {mp.Name}: {ex.Message}");
            throw;
        }

        lock (_lock)
        {
            mp.Process = process;
            mp.LastRestart = DateTime.UtcNow;
        }

        _logger.LogInfo($"{mp.Name} started (PID {process.Id})");

        // Fire-and-forget monitor task
        _ = MonitorProcessAsync(mp, _globalCts.Token);
    }

    public async Task StopProcessAsync(string key)
    {
        if (!_processes.TryGetValue(key, out var mp))
            return;

        Process? proc;
        lock (_lock)
        {
            proc = mp.Process;
        }

        if (proc is null || proc.HasExited)
            return;

        _logger.LogInfo($"Stopping {mp.Name} (PID {proc.Id})...");

        // Graceful shutdown: send Ctrl+C / SIGTERM
        if (RuntimeInformation.IsOSPlatform(OSPlatform.Windows))
        {
            // Attach to console and send Ctrl+Break
            if (AttachConsole((uint)proc.Id))
            {
                GenerateConsoleCtrlEvent(CtrlTypes.CTRL_BREAK_EVENT, 0);
                FreeConsole();
            }

            using var cts = new CancellationTokenSource(_config.ProcessStopTimeoutMs);
            try
            {
                await proc.WaitForExitAsync(cts.Token);
            }
            catch (OperationCanceledException)
            {
                _logger.LogWarning($"{mp.Name} did not exit gracefully, killing...");
                proc.Kill(entireProcessTree: true);
            }
        }
        else
        {
            proc.Kill(entireProcessTree: true);
            await proc.WaitForExitAsync();
        }

        lock (_lock)
        {
            mp.Process = null;
        }

        _logger.LogInfo($"{mp.Name} stopped");
    }

    private async Task MonitorProcessAsync(ManagedProcess mp, CancellationToken ct)
    {
        try
        {
            await mp.Process!.WaitForExitAsync(ct);
        }
        catch (OperationCanceledException)
        {
            return; // shutdown requested
        }

        int exitCode;
        lock (_lock)
        {
            exitCode = mp.Process?.ExitCode ?? -1;
            mp.Process = null;
        }

        _logger.LogWarning($"{mp.Name} exited with code {exitCode}");

        ProcessExited?.Invoke(mp.Name, exitCode);

        // Auto-restart if not shutting down
        if (!_globalCts.IsCancellationRequested)
        {
            var restartAllowed = CanRestart(mp);
            if (restartAllowed)
            {
                _logger.LogInfo($"Restarting {mp.Name} in 5 seconds...");
                await Task.Delay(5000, ct);
                await StartProcessAsync(mp.Name.ToLowerInvariant());
            }
            else
            {
                _logger.LogError($"{mp.Name} exceeded restart limit, not restarting");
            }
        }
    }

    private static bool CanRestart(ManagedProcess mp)
    {
        lock (_lock)
        {
            var now = DateTime.UtcNow;
            if (now - mp.LastRestart > ManagedProcess.RestartWindow)
            {
                mp.RestartCount = 0;
            }
            mp.RestartCount++;
            return mp.RestartCount <= ManagedProcess.MaxRestartsPerWindow;
        }
    }

    #region Native Methods for Graceful Console Shutdown

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool AttachConsole(uint dwProcessId);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool GenerateConsoleCtrlEvent(CtrlTypes dwCtrlEvent, uint dwProcessGroupId);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool FreeConsole();

    private enum CtrlTypes : uint
    {
        CTRL_C_EVENT = 0,
        CTRL_BREAK_EVENT = 1,
    }

    #endregion

    public void Dispose()
    {
        if (_disposed) return;
        _disposed = true;
        _globalCts.Cancel();
        _globalCts.Dispose();
    }
}
