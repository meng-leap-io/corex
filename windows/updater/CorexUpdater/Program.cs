using System.Diagnostics;
using System.Reflection;
using CorexUpdater;

var updater = new UpdateEngine();
var exitCode = await updater.RunAsync(args);
Environment.Exit(exitCode);
