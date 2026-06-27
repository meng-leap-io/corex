using System.Diagnostics;
using System.Runtime.InteropServices;
using System.Text.Json;

namespace CorexUpdater;

public class UpdateEngine
{
    private readonly string _installDir;
    private readonly string _dataDir;
    private readonly string _backupDir;
    private readonly string _tempDir;
    private readonly string _manifestPath;
    private readonly string _stateFile;
    private readonly string _lockFile;
    private readonly string _logFile;
    private const int MaxRetries = 3;
    private static readonly TimeSpan RetryDelay = TimeSpan.FromSeconds(2);

    private record ProgressReport(string Event, string Message, int Percent, string? Error = null);

    public UpdateEngine()
    {
        _installDir = Environment.GetEnvironmentVariable("COREX_INSTALL_DIR")
            ?? Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), "Corex");
        _dataDir = Environment.GetEnvironmentVariable("COREX_DATA_DIR")
            ?? Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Corex");
        _backupDir = Path.Combine(_dataDir, "backups");
        _tempDir = Path.Combine(_dataDir, "update-temp");
        _manifestPath = Path.Combine(_dataDir, "update-manifest.json");
        _stateFile = Path.Combine(_dataDir, "update-state.json");
        _lockFile = Path.Combine(_dataDir, "update.lock");
        _logFile = Path.Combine(_dataDir, "logs", "updater.log");

        Directory.CreateDirectory(_backupDir);
        Directory.CreateDirectory(_tempDir);
        Directory.CreateDirectory(Path.Combine(_dataDir, "logs"));
    }

    public async Task<int> RunAsync(string[] args)
    {
        if (args.Length == 0)
        {
            WriteUsage();
            return 0;
        }

        var command = args[0].ToLowerInvariant();

        try
        {
            return command switch
            {
                "check" => await CheckForUpdateAsync(args),
                "download" => await DownloadUpdateAsync(args),
                "apply" => await ApplyUpdateAsync(args),
                "verify" => await VerifyUpdateAsync(args),
                "rollback" => await RollbackAsync(args),
                "status" => await ShowStatusAsync(),
                "install" => await RunFullUpdateAsync(args),
                _ => throw new ArgumentException($"Unknown command: {command}")
            };
        }
        catch (Exception ex)
        {
            await ReportProgressAsync("error", $"Update failed: {ex.Message}", 0, ex.ToString());
            Log($"FATAL: {ex}");
            return 1;
        }
    }

    private void WriteUsage()
    {
        Console.WriteLine("Corex Updater - Desktop Application Auto-Updater");
        Console.WriteLine();
        Console.WriteLine("Usage: CorexUpdater <command> [options]");
        Console.WriteLine();
        Console.WriteLine("Commands:");
        Console.WriteLine("  check [manifest-url]     Check for available updates");
        Console.WriteLine("  download <url> [hash]    Download update package");
        Console.WriteLine("  apply <package-path>     Apply downloaded update");
        Console.WriteLine("  verify <path>            Verify file integrity");
        Console.WriteLine("  rollback                 Rollback last failed update");
        Console.WriteLine("  status                   Show update system status");
        Console.WriteLine("  install <url> [hash]     Full update: download + verify + apply");
        Console.WriteLine();
        Console.WriteLine("Options:");
        Console.WriteLine("  --channel <stable|beta|nightly>  Release channel");
        Console.WriteLine("  --force                  Force update even if blocked");
        Console.WriteLine("  --no-restart             Don't restart services after update");
    }

    // ═══════════════════════════════════════════════════════════════
    // 1. Check for Updates
    // ═══════════════════════════════════════════════════════════════
    private async Task<int> CheckForUpdateAsync(string[] args)
    {
        var manifestUrl = args.Length > 1 ? args[1] : "https://updates.corex.dev/v1/manifest.json";
        var channel = GetArgValue(args, "--channel", "stable");

        await ReportProgressAsync("checking", "Checking for updates...", 0);

        using var http = new HttpClient { Timeout = TimeSpan.FromSeconds(15) };
        http.DefaultRequestHeaders.UserAgent.ParseAdd($"CorexUpdater/{GetVersion()}");

        var url = manifestUrl;
        if (!url.Contains("/channel/"))
            url = $"{url.TrimEnd('/')}?channel={channel}";

        var response = await http.GetStringAsync(url);
        var manifest = JsonDocument.Parse(response);
        var root = manifest.RootElement;

        var currentBuild = int.TryParse(Environment.GetEnvironmentVariable("COREX_BUILD"), out var b) ? b : 0;
        var latestBuild = root.TryGetProperty("build", out var buildEl) ? buildEl.GetInt32() : 0;
        var version = root.TryGetProperty("version", out var verEl) ? verEl.GetString() : "0.0.0";

        var updateAvailable = latestBuild > currentBuild;

        var result = new
        {
            updateAvailable,
            currentVersion = GetVersion(),
            currentBuild,
            latestVersion = version,
            latestBuild,
            releaseDate = root.TryGetProperty("releaseDate", out var dateEl) ? dateEl.GetString() : null,
            mandatory = root.TryGetProperty("mandatory", out var manEl) && manEl.GetBoolean(),
            size = root.TryGetProperty("size", out var sizeEl) ? sizeEl.GetInt64() : 0,
            changelog = root.TryGetProperty("changelog", out var clEl) ? clEl.ToString() : "[]"
        };

        // Cache manifest for later use
        File.WriteAllText(_manifestPath, response);
        await SaveStateAsync("check", new { manifestUrl, channel, checkedAt = DateTime.UtcNow, result });

        var json = JsonSerializer.Serialize(result, new JsonSerializerOptions { WriteIndented = true });
        Console.WriteLine(json);

        if (updateAvailable)
            await ReportProgressAsync("update-available",
                $"Version {version} available (current: {GetVersion()})", 100);
        else
            await ReportProgressAsync("up-to-date", "Application is up to date", 100);

        return updateAvailable ? 0 : 2; // 0 = update available, 2 = up to date
    }

    // ═══════════════════════════════════════════════════════════════
    // 2. Download Update
    // ═══════════════════════════════════════════════════════════════
    private async Task<int> DownloadUpdateAsync(string[] args)
    {
        if (args.Length < 2) throw new ArgumentException("Download URL required");
        var url = args[1];
        var expectedHash = args.Length > 2 ? args[2] : null;

        var tempZip = Path.Combine(_tempDir, "update.zip");
        var extractDir = Path.Combine(_tempDir, "package");

        Directory.CreateDirectory(extractDir);

        // Acquire lock
        await AcquireLockAsync();

        await ReportProgressAsync("downloading", "Downloading update...", 5);

        using var http = new HttpClient { Timeout = TimeSpan.FromMinutes(30) };
        http.DefaultRequestHeaders.UserAgent.ParseAdd($"CorexUpdater/{GetVersion()}");

        using var response = await http.GetAsync(url, HttpCompletionOption.ResponseHeadersRead);
        response.EnsureSuccessStatusCode();

        var totalSize = response.Content.Headers.ContentLength ?? -1;
        await using var contentStream = await response.Content.ReadAsStreamAsync();
        await using var fileStream = new FileStream(tempZip, FileMode.Create, FileAccess.Write, FileShare.None, 8192, true);

        var buffer = new byte[8192];
        long readSoFar = 0;
        int bytesRead;

        while ((bytesRead = await contentStream.ReadAsync(buffer)) > 0)
        {
            await fileStream.WriteAsync(buffer.AsMemory(0, bytesRead));
            readSoFar += bytesRead;

            if (totalSize > 0)
            {
                var percent = (int)(readSoFar * 90 / totalSize) + 5;
                await ReportProgressAsync("downloading", $"Downloading... {readSoFar / 1024 / 1024}MB / {totalSize / 1024 / 1024}MB", percent);
            }
        }

        await ReportProgressAsync("verifying", "Verifying download...", 95);

        // Verify checksum
        if (expectedHash != null)
        {
            var actualHash = await ComputeFileHashAsync(tempZip);
            if (!string.Equals(actualHash, expectedHash, StringComparison.OrdinalIgnoreCase))
            {
                File.Delete(tempZip);
                throw new InvalidDataException(
                    $"Checksum mismatch. Expected: {expectedHash}, Got: {actualHash}");
            }
        }

        // Extract
        await ReportProgressAsync("extracting", "Extracting package...", 97);
        if (Directory.Exists(extractDir))
            Directory.Delete(extractDir, true);
        System.IO.Compression.ZipFile.ExtractToDirectory(tempZip, extractDir);

        File.Delete(tempZip);

        await SaveStateAsync("downloaded", new { url, expectedHash, downloadedAt = DateTime.UtcNow, extractDir });
        await ReportProgressAsync("downloaded", "Update downloaded and verified", 100);

        Console.WriteLine(extractDir);
        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    // 3. Apply Update
    // ═══════════════════════════════════════════════════════════════
    private async Task<int> ApplyUpdateAsync(string[] args)
    {
        var packageDir = args.Length > 1 ? args[1] : Path.Combine(_tempDir, "package");
        var force = args.Contains("--force");
        var noRestart = args.Contains("--no-restart");

        if (!Directory.Exists(packageDir))
            throw new DirectoryNotFoundException($"Package directory not found: {packageDir}");

        await ReportProgressAsync("applying", "Starting update...", 0);

        // Read package manifest
        var packageManifest = Path.Combine(packageDir, "update.json");
        var fileList = new List<UpdateFile>();
        if (File.Exists(packageManifest))
        {
            var manifest = JsonDocument.Parse(File.ReadAllText(packageManifest));
            foreach (var file in manifest.RootElement.GetProperty("files").EnumerateArray())
            {
                fileList.Add(new UpdateFile
                {
                    RelativePath = file.GetProperty("path").GetString()!,
                    Checksum = file.TryGetProperty("checksum", out var c) ? c.GetString() : null,
                    Size = file.TryGetProperty("size", out var s) ? s.GetInt64() : 0,
                    Mode = file.TryGetProperty("mode", out var m) ? m.GetString() : "replace"
                });
            }
        }
        else
        {
            // Build file list from directory
            var allFiles = Directory.GetFiles(packageDir, "*", SearchOption.AllDirectories);
            foreach (var f in allFiles)
            {
                var relPath = Path.GetRelativePath(packageDir, f);
                if (relPath.Equals("update.json", StringComparison.OrdinalIgnoreCase)) continue;
                fileList.Add(new UpdateFile
                {
                    RelativePath = relPath,
                    Checksum = await ComputeFileHashAsync(f),
                    Size = new FileInfo(f).Length,
                    Mode = "replace"
                });
            }
        }

        // 1. Backup current version
        await ReportProgressAsync("backing-up", "Backing up current version...", 5);
        var backupManifest = await BackupCurrentFilesAsync(fileList);

        // 2. Stop services
        await ReportProgressAsync("stopping-services", "Stopping services...", 10);
        var serviceNames = new[] { "CorexAIGateway", "CorexNginx", "CorexPHP", "CorexRedis", "CorexServiceHost" };
        foreach (var svc in serviceNames) await StopServiceAsync(svc);

        // 3. Copy files
        var totalFiles = fileList.Count;
        var filesProcessed = 0;
        var failedFiles = new List<string>();

        foreach (var file in fileList)
        {
            filesProcessed++;
            var percent = 10 + (filesProcessed * 70 / totalFiles);
            await ReportProgressAsync("copying", $"Copying: {file.RelativePath}", percent);

            var source = Path.Combine(packageDir, file.RelativePath);
            var dest = Path.Combine(_installDir, file.RelativePath);

            if (!File.Exists(source))
            {
                Log($"Source file missing: {source}");
                failedFiles.Add(file.RelativePath);
                continue;
            }

            try
            {
                var destDir = Path.GetDirectoryName(dest)!;
                Directory.CreateDirectory(destDir);

                // Retry loop for file copy
                for (var retry = 0; retry < MaxRetries; retry++)
                {
                    try
                    {
                        File.Copy(source, dest, true);
                        File.SetAttributes(dest, FileAttributes.Normal);
                        break;
                    }
                    catch (IOException) when (retry < MaxRetries - 1)
                    {
                        await Task.Delay(RetryDelay);
                    }
                }

                // Verify copied file
                if (file.Checksum != null)
                {
                    var destHash = await ComputeFileHashAsync(dest);
                    if (!string.Equals(destHash, file.Checksum, StringComparison.OrdinalIgnoreCase))
                    {
                        Log($"Checksum mismatch after copy: {file.RelativePath}");
                        failedFiles.Add(file.RelativePath);
                    }
                }
            }
            catch (Exception ex)
            {
                Log($"Failed to copy {file.RelativePath}: {ex.Message}");
                failedFiles.Add(file.RelativePath);
            }
        }

        // 4. Handle failures
        if (failedFiles.Count > 0)
        {
            Log($"{failedFiles.Count} files failed. Attempting rollback...");
            await ReportProgressAsync("rollback", $"Rolling back ({failedFiles.Count} files failed)", 90);

            await RestoreFromBackupAsync(backupManifest);

            await SaveStateAsync("failed", new
            {
                failedFiles,
                attemptedAt = DateTime.UtcNow,
                rolledBack = true
            });

            await ReportProgressAsync("error", $"Update failed: {failedFiles.Count} files could not be copied", 0, string.Join(", ", failedFiles));
            return 1;
        }

        // 5. Post-update tasks
        await ReportProgressAsync("finalizing", "Finalizing update...", 85);

        // Update version file
        var versionFile = Path.Combine(_installDir, "version.json");
        if (File.Exists(packageManifest))
        {
            File.Copy(packageManifest, versionFile, true);
        }

        // Copy installer-level files (e.g., .exe, .dll) with special handling
        var systemFiles = fileList.Where(f =>
            f.RelativePath.EndsWith(".exe", StringComparison.OrdinalIgnoreCase) ||
            f.RelativePath.EndsWith(".dll", StringComparison.OrdinalIgnoreCase)).ToList();

        if (systemFiles.Count > 0)
        {
            await ReportProgressAsync("registering", "Registering updated binaries...", 90);
            // On Windows, replaced EXE/DLL files may need registration
            foreach (var sf in systemFiles)
            {
                var fullPath = Path.Combine(_installDir, sf.RelativePath);
                if (sf.RelativePath.EndsWith(".exe", StringComparison.OrdinalIgnoreCase))
                {
                    // Set ACLs
                    try
                    {
                        var psi = new ProcessStartInfo("icacls", $"\"{fullPath}\" /grant \"Users:RX\" /Q")
                        {
                            CreateNoWindow = true,
                            UseShellExecute = false
                        };
                        Process.Start(psi)?.WaitForExit(5000);
                    }
                    catch { }
                }
            }
        }

        // 6. Start services (unless --no-restart)
        if (!noRestart)
        {
            await ReportProgressAsync("starting-services", "Starting services...", 93);
            foreach (var svc in serviceNames.Reverse()) await StartServiceAsync(svc);

            await ReportProgressAsync("waiting", "Waiting for services to stabilize...", 96);
            await Task.Delay(3000);

            // Verify services
            foreach (var svc in serviceNames)
            {
                var status = await GetServiceStatusAsync(svc);
                Log($"Service {svc}: {status}");
            }
        }

        // 7. Cleanup
        await ReportProgressAsync("cleaning", "Cleaning up...", 98);
        try
        {
            Directory.Delete(packageDir, true);
            CleanOldBackups();
        }
        catch { }

        await SaveStateAsync("completed", new
        {
            appliedAt = DateTime.UtcNow,
            filesUpdated = totalFiles,
            version = GetVersion(),
            previousState = backupManifest
        });

        await ReportProgressAsync("completed", "Update completed successfully", 100);
        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    // 4. Verify
    // ═══════════════════════════════════════════════════════════════
    private async Task<int> VerifyUpdateAsync(string[] args)
    {
        var path = args.Length > 1 ? args[1] : _installDir;
        var manifest = args.Length > 2 ? args[2] : _manifestPath;

        if (!File.Exists(manifest))
        {
            Console.Error.WriteLine($"Manifest not found: {manifest}");
            return 1;
        }

        await ReportProgressAsync("verifying", "Verifying installation integrity...", 0);

        var doc = JsonDocument.Parse(File.ReadAllText(manifest));
        var files = doc.RootElement.GetProperty("files").EnumerateArray();

        var verified = 0;
        var failed = 0;
        var total = 0;

        foreach (var fileEntry in files)
        {
            total++;
            var relPath = fileEntry.GetProperty("path").GetString()!;
            var expectedHash = fileEntry.GetProperty("checksum").GetString()!;
            var fullPath = Path.Combine(path, relPath);

            if (!File.Exists(fullPath))
            {
                Log($"MISSING: {relPath}");
                failed++;
                continue;
            }

            var actualHash = await ComputeFileHashAsync(fullPath);
            if (string.Equals(actualHash, expectedHash, StringComparison.OrdinalIgnoreCase))
            {
                verified++;
            }
            else
            {
                Log($"CHANGED: {relPath} (hash mismatch)");
                failed++;
            }

            var percent = total * 100 / (files.Count());
            await ReportProgressAsync("verifying", $"Verifying: {relPath}", percent);
        }

        var result = new { verified, failed, total, timestamp = DateTime.UtcNow };
        Console.WriteLine(JsonSerializer.Serialize(result));
        await ReportProgressAsync("verified", $"Verified {verified}/{total} files, {failed} failures", 100);

        return failed > 0 ? 1 : 0;
    }

    // ═══════════════════════════════════════════════════════════════
    // 5. Rollback
    // ═══════════════════════════════════════════════════════════════
    private async Task<int> RollbackAsync(string[] args)
    {
        var targetVersion = args.Length > 1 ? args[1] : null;

        await ReportProgressAsync("rollback", "Starting rollback...", 0);

        // Find backup
        var backupDir = _backupDir;
        var backupManifest = targetVersion != null
            ? Directory.GetFiles(backupDir, $"*{targetVersion}*.json").FirstOrDefault()
            : Directory.GetFiles(backupDir, "backup-*.json")
                .OrderByDescending(f => new FileInfo(f).CreationTime)
                .FirstOrDefault();

        if (backupManifest == null)
        {
            await ReportProgressAsync("error", "No backup found to roll back to", 0, "Backup not found");
            return 1;
        }

        await ReportProgressAsync("restoring", $"Restoring from: {Path.GetFileName(backupManifest)}", 10);

        var manifest = JsonSerializer.Deserialize<BackupManifest>(File.ReadAllText(backupManifest));
        if (manifest == null) throw new InvalidDataException("Invalid backup manifest");

        // Stop services
        var serviceNames = new[] { "CorexAIGateway", "CorexNginx", "CorexPHP", "CorexRedis" };
        foreach (var svc in serviceNames) await StopServiceAsync(svc);

        await RestoreFromBackupAsync(manifest);

        // Start services
        foreach (var svc in serviceNames.Reverse()) await StartServiceAsync(svc);

        await ReportProgressAsync("completed", $"Rolled back to version {manifest.Version}", 100);
        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    // 6. Status
    // ═══════════════════════════════════════════════════════════════
    private async Task<int> ShowStatusAsync()
    {
        var status = new Dictionary<string, object?>
        {
            ["installDir"] = _installDir,
            ["dataDir"] = _dataDir,
            ["version"] = GetVersion(),
            ["state"] = File.Exists(_stateFile)
                ? JsonSerializer.Deserialize<object>(File.ReadAllText(_stateFile))
                : null,
            ["hasPendingUpdate"] = Directory.Exists(Path.Combine(_tempDir, "package")),
            ["backupCount"] = Directory.GetFiles(_backupDir, "backup-*.json").Length,
            ["lockAcquired"] = !File.Exists(_lockFile),
            ["diskFree"] = new DriveInfo(Path.GetPathRoot(_installDir)!).AvailableFreeSpace
        };

        // Check service statuses
        var services = new[] { "CorexRedis", "CorexPHP", "CorexNginx", "CorexAIGateway", "CorexServiceHost" };
        var serviceStatuses = new Dictionary<string, string>();
        foreach (var svc in services)
        {
            serviceStatuses[svc] = await GetServiceStatusAsync(svc);
        }
        status["services"] = serviceStatuses;

        Console.WriteLine(JsonSerializer.Serialize(status, new JsonSerializerOptions { WriteIndented = true }));
        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    // 7. Full Update (download + apply)
    // ═══════════════════════════════════════════════════════════════
    private async Task<int> RunFullUpdateAsync(string[] args)
    {
        if (args.Length < 2) throw new ArgumentException("Update URL required");
        var url = args[1];
        var hash = args.Length > 2 ? args[2] : null;

        var exitCode = await DownloadUpdateAsync(new[] { "download", url, hash ?? "" });
        if (exitCode != 0) return exitCode;

        var packageDir = Path.Combine(_tempDir, "package");
        exitCode = await ApplyUpdateAsync(new[] { "apply", packageDir }.Concat(args.Skip(2)).ToArray());
        return exitCode;
    }

    // ═══════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════

    private async Task<string> ComputeFileHashAsync(string path)
    {
        await using var stream = File.OpenRead(path);
        var hash = await System.Security.Cryptography.SHA256.HashDataAsync(stream);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    private async Task<BackupManifest> BackupCurrentFilesAsync(List<UpdateFile> files)
    {
        var timestamp = DateTime.UtcNow.ToString("yyyyMMdd-HHmmss");
        var version = GetVersion();
        var backupId = $"v{version}-{timestamp}";
        var backupDir = Path.Combine(_backupDir, backupId);

        Directory.CreateDirectory(backupDir);

        var entries = new List<BackupEntry>();
        foreach (var file in files)
        {
            var sourcePath = Path.Combine(_installDir, file.RelativePath);
            if (!File.Exists(sourcePath)) continue;

            var destPath = Path.Combine(backupDir, file.RelativePath);
            var destDir = Path.GetDirectoryName(destPath)!;
            Directory.CreateDirectory(destDir);

            for (var retry = 0; retry < MaxRetries; retry++)
            {
                try
                {
                    File.Copy(sourcePath, destPath, true);
                    break;
                }
                catch (IOException) when (retry < MaxRetries - 1)
                {
                    await Task.Delay(RetryDelay);
                }
            }

            entries.Add(new BackupEntry
            {
                RelativePath = file.RelativePath,
                Checksum = await ComputeFileHashAsync(sourcePath),
                Size = new FileInfo(sourcePath).Length
            });
        }

        var manifest = new BackupManifest
        {
            BackupId = backupId,
            Version = version,
            Timestamp = DateTime.UtcNow,
            Files = entries,
            TotalSize = entries.Sum(e => e.Size)
        };

        var manifestPath = Path.Combine(_backupDir, $"backup-{backupId}.json");
        File.WriteAllText(manifestPath, JsonSerializer.Serialize(manifest, new JsonSerializerOptions { WriteIndented = true }));

        Log($"Backup created: {backupId} ({entries.Count} files, {manifest.TotalSize / 1024 / 1024}MB)");
        return manifest;
    }

    private async Task RestoreFromBackupAsync(BackupManifest manifest)
    {
        var backupDir = Path.Combine(_backupDir, manifest.BackupId);
        if (!Directory.Exists(backupDir))
            throw new DirectoryNotFoundException($"Backup directory not found: {backupDir}");

        var restored = 0;
        foreach (var file in manifest.Files)
        {
            var sourcePath = Path.Combine(backupDir, file.RelativePath);
            var destPath = Path.Combine(_installDir, file.RelativePath);

            if (!File.Exists(sourcePath))
            {
                Log($"Backup file missing: {sourcePath}");
                continue;
            }

            var destDir = Path.GetDirectoryName(destPath)!;
            Directory.CreateDirectory(destDir);

            for (var retry = 0; retry < MaxRetries; retry++)
            {
                try
                {
                    File.Copy(sourcePath, destPath, true);
                    break;
                }
                catch (IOException) when (retry < MaxRetries - 1)
                {
                    await Task.Delay(RetryDelay);
                }
            }

            restored++;
            var pct = restored * 100 / manifest.Files.Count;
            await ReportProgressAsync("restoring", $"Restoring: {file.RelativePath}", pct);
        }

        Log($"Restored {restored}/{manifest.Files.Count} files from backup {manifest.BackupId}");
    }

    private void CleanOldBackups()
    {
        var backups = Directory.GetFiles(_backupDir, "backup-*.json")
            .Select(f => new FileInfo(f))
            .OrderByDescending(f => f.CreationTime)
            .Skip(3) // Keep last 3 backups
            .ToList();

        foreach (var backup in backups)
        {
            try
            {
                var backupId = Path.GetFileNameWithoutExtension(backup.Name).Replace("backup-", "");
                var dir = Path.Combine(_backupDir, backupId);
                if (Directory.Exists(dir)) Directory.Delete(dir, true);
                backup.Delete();
                Log($"Cleaned old backup: {backupId}");
            }
            catch { }
        }
    }

    private async Task StopServiceAsync(string name)
    {
        try
        {
            var psi = new ProcessStartInfo("sc", $"stop \"{name}\"")
            {
                CreateNoWindow = true,
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true
            };
            using var proc = Process.Start(psi);
            if (proc != null)
            {
                proc.WaitForExit(20000);
                await proc.WaitForExitAsync().WaitAsync(TimeSpan.FromSeconds(20));
            }
        }
        catch (Exception ex)
        {
            Log($"Warning: Could not stop service {name}: {ex.Message}");
        }
    }

    private async Task StartServiceAsync(string name)
    {
        try
        {
            var psi = new ProcessStartInfo("sc", $"start \"{name}\"")
            {
                CreateNoWindow = true,
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true
            };
            using var proc = Process.Start(psi);
            if (proc != null)
            {
                await proc.WaitForExitAsync().WaitAsync(TimeSpan.FromSeconds(30));
            }
        }
        catch (Exception ex)
        {
            Log($"Warning: Could not start service {name}: {ex.Message}");
        }
    }

    private async Task<string> GetServiceStatusAsync(string name)
    {
        try
        {
            using var proc = new Process
            {
                StartInfo = new ProcessStartInfo("sc", $"query \"{name}\"")
                {
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    RedirectStandardOutput = true
                }
            };
            proc.Start();
            var output = await proc.StandardOutput.ReadToEndAsync();
            await proc.WaitForExitAsync().WaitAsync(TimeSpan.FromSeconds(10));

            if (output.Contains("RUNNING")) return "Running";
            if (output.Contains("STOPPED")) return "Stopped";
            if (output.Contains("PAUSED")) return "Paused";
            return "Unknown";
        }
        catch
        {
            return "Not Found";
        }
    }

    private async Task AcquireLockAsync()
    {
        try
        {
            using var fs = new FileStream(_lockFile, FileMode.CreateNew, FileAccess.Write, FileShare.None);
            fs.Write(BitConverter.GetBytes(Environment.ProcessId));
            await fs.FlushAsync();
        }
        catch (IOException)
        {
            // Check if lock is stale
            if (File.Exists(_lockFile))
            {
                try
                {
                    var pidBytes = File.ReadAllBytes(_lockFile);
                    var pid = BitConverter.ToInt32(pidBytes);
                    var proc = Process.GetProcessById(pid);
                    if (!proc.HasExited)
                        throw new InvalidOperationException($"Another update is in progress (PID: {pid})");
                }
                catch (ArgumentException)
                {
                    // Process no longer exists, stale lock
                }
            }
            File.Delete(_lockFile);
            using var fs = new FileStream(_lockFile, FileMode.CreateNew, FileAccess.Write, FileShare.None);
            fs.Write(BitConverter.GetBytes(Environment.ProcessId));
        }
    }

    private async Task SaveStateAsync(string action, object data)
    {
        var state = new Dictionary<string, object?>
        {
            ["action"] = action,
            ["timestamp"] = DateTime.UtcNow,
            ["data"] = data,
            ["version"] = GetVersion()
        };
        await File.WriteAllTextAsync(_stateFile, JsonSerializer.Serialize(state, new JsonSerializerOptions { WriteIndented = true }));
    }

    private async Task ReportProgressAsync(string eventName, string message, int percent, string? error = null)
    {
        var report = new ProgressReport(eventName, message, percent, error);
        var json = JsonSerializer.Serialize(report);
        Console.Error.WriteLine(json); // progress on stderr, result on stdout
        Log(json);
        await Task.CompletedTask;
    }

    private static string GetVersion()
    {
        return Assembly.GetEntryAssembly()?.GetName()?.Version?.ToString(3) ?? "1.0.0";
    }

    private static string GetArgValue(string[] args, string name, string defaultValue)
    {
        var idx = Array.IndexOf(args, name);
        return idx >= 0 && idx + 1 < args.Length ? args[idx + 1] : defaultValue;
    }

    private void Log(string message)
    {
        try
        {
            File.AppendAllText(_logFile,
                $"{DateTime.UtcNow:yyyy-MM-dd HH:mm:ss} [PID:{Environment.ProcessId}] {message}{Environment.NewLine}");
        }
        catch { }
    }
}

// ── Data types ────────────────────────────────────────────────────────────

public record UpdateFile
{
    public string RelativePath { get; init; } = "";
    public string? Checksum { get; init; }
    public long Size { get; init; }
    public string Mode { get; init; } = "replace";
}

public record BackupEntry
{
    public string RelativePath { get; init; } = "";
    public string Checksum { get; init; } = "";
    public long Size { get; init; }
}

public record BackupManifest
{
    public string BackupId { get; init; } = "";
    public string Version { get; init; } = "";
    public DateTime Timestamp { get; init; }
    public List<BackupEntry> Files { get; init; } = new();
    public long TotalSize { get; init; }
}
