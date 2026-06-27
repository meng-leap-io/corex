using CorexServiceHost;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;

var builder = Host.CreateApplicationBuilder(args);

builder.Services.AddWindowsService(options =>
{
    options.ServiceName = "CorexServiceHost";
});

builder.Services.AddSingleton<ServiceConfiguration>();
builder.Services.AddSingleton<ProcessManager>();
builder.Services.AddSingleton<EventLogger>();
builder.Services.AddSingleton<PerformanceMonitor>();
builder.Services.AddHostedService<WindowsBackgroundService>();

var host = builder.Build();
await host.RunAsync();
