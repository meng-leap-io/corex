using System.Diagnostics;

namespace CorexServiceHost;

public sealed class EventLogger : IDisposable
{
    private const string Source = "CorexServiceHost";
    private const string LogName = "Application";
    private readonly string _logFile;
    private readonly StreamWriter? _fileWriter;
    private bool _disposed;

    public EventLogger(ServiceConfiguration config)
    {
        var logDir = Path.Combine(config.LogDir, "CorexServiceHost");
        Directory.CreateDirectory(logDir);

        _logFile = Path.Combine(logDir, $"service-{DateTime.UtcNow:yyyyMMdd}.log");
        try
        {
            _fileWriter = new StreamWriter(_logFile, append: true) { AutoFlush = true };
        }
        catch
        {
            // File log is best-effort
        }

        try
        {
            if (!EventLog.SourceExists(Source))
            {
                EventLog.CreateEventSource(Source, LogName);
            }
        }
        catch
        {
            // Non-admin: EventLog source creation will fail — safe to ignore
        }
    }

    private void Write(string level, string message, EventLogEntryType eventType = EventLogEntryType.Information)
    {
        var line = $"[{DateTime.UtcNow:yyyy-MM-dd HH:mm:ss}] [{level}] {message}";

        // Console
        var color = level switch
        {
            "ERROR" => ConsoleColor.Red,
            "WARN" => ConsoleColor.Yellow,
            _ => ConsoleColor.Gray,
        };
        lock (Console.Out)
        {
            Console.ForegroundColor = color;
            Console.WriteLine(line);
            Console.ResetColor();
        }

        // File
        _fileWriter?.WriteLine(line);

        // Windows Event Log
        try
        {
            EventLog.WriteEntry(Source, message, eventType, 1000 + (int)eventType);
        }
        catch { /* best-effort */ }
    }

    public void LogInfo(string message) => Write("INFO", message, EventLogEntryType.Information);
    public void LogWarning(string message) => Write("WARN", message, EventLogEntryType.Warning);
    public void LogError(string message) => Write("ERROR", message, EventLogEntryType.Error);

    public void Dispose()
    {
        if (_disposed) return;
        _disposed = true;
        _fileWriter?.Dispose();
    }
}
