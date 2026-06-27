namespace CorexServiceHost;

public sealed class ServiceConfiguration
{
    public string CorexRoot { get; init; } = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), "Corex");

    public int RedisPort { get; init; } = 6379;
    public int PHPFastCGIPort { get; init; } = 9000;
    public int AIGatewayPort { get; init; } = 8000;
    public int NginxPort { get; init; } = 80;
    public int AIGatewayWorkers { get; init; } = 4;

    public string RedisExe => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), "Redis", "redis-server.exe");

    public string RedisConf => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), "Redis", "redis.windows-service.conf");

    public string PHPExe => Path.Combine(CorexRoot, "php", "php-fpm.exe");
    public string PHPFpmConf => Path.Combine(CorexRoot, "php", "php-fpm.conf");
    public string NginxExe => Path.Combine(CorexRoot, "nginx", "nginx.exe");
    public string NginxConf => Path.Combine(CorexRoot, "nginx", "conf", "nginx.conf");
    public string AIGatewayDir => Path.Combine(CorexRoot, "ai-gateway");
    public string AIGatewayMain => Path.Combine(AIGatewayDir, "main.py");
    public string AIGatewayVenvPython => Path.Combine(AIGatewayDir, ".venv", "Scripts", "python.exe");
    public string LogDir => Path.Combine(CorexRoot, "logs");

    public int HealthCheckIntervalSeconds { get; init; } = 30;
    public int ProcessStartTimeoutMs { get; init; } = 30_000;
    public int ProcessStopTimeoutMs { get; init; } = 15_000;
}
