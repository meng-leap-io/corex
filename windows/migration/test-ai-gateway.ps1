#Requires -Version 5.1

param(
    [string]$ProjectRoot,
    [string]$LogDir = "$LogDir"
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\test-ai-gateway.log"

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow }
function Err { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red }

Log "=== AI Gateway Test ==="

$allOk = $true

# ── 1. Process check ──
$pythonProcs = Get-Process -Name 'python*' -ErrorAction SilentlyContinue
$uvicornProcs = Get-Process | Where-Object { $_.CommandLine -match 'uvicorn' -or $_.ProcessName -match 'uvicorn' -or ($_.ProcessName -eq 'python' -and $_.CommandLine -match 'app.main') }

if ($uvicornProcs) {
    Ok "AI Gateway process running: $($uvicornProcs.Count) instance(s)"
} elseif ($pythonProcs) {
    $found = $false
    foreach ($p in $pythonProcs) {
        try { if ($p.CommandLine -match 'app\.main') { $found = $true; break } } catch { }
    }
    if ($found) { Ok "AI Gateway Python process found" }
    else { Warn "Python running but AI Gateway not detected in command line" }
} else {
    Warn "No AI Gateway process detected (may be starting or not installed)"
}

# ── 2. Health endpoint ──
$baseUrls = @('http://127.0.0.1:8001', 'http://localhost:8001', 'http://127.0.0.1:8000/ai')
$gatewayReachable = $false

foreach ($url in $baseUrls) {
    try {
        $req = [System.Net.Http.HttpClient]::new()
        $req.Timeout = [TimeSpan]::FromSeconds(5)
        $resp = $req.GetAsync("$url/health").GetAwaiter().GetResult()
        if ($resp.IsSuccessStatusCode) {
            $body = $resp.Content.ReadAsStringAsync().GetAwaiter().GetResult()
            Ok "Health endpoint: $url/health → $([int]$resp.StatusCode)"
            Log "  Response: $body"
            $gatewayReachable = $true
            break
        }
    } catch {
        continue
    }
}

if (-not $gatewayReachable) {
    Warn "AI Gateway health endpoint not reachable on any port"
    $allOk = $false
}

# ── 3. OpenAPI docs ──
if ($gatewayReachable) {
    try {
        $req = [System.Net.Http.HttpClient]::new()
        $req.Timeout = [TimeSpan]::FromSeconds(3)
        $resp = $req.GetAsync("$($baseUrls[0])/openapi.json").GetAwaiter().GetResult()
        if ($resp.IsSuccessStatusCode) {
            Ok "OpenAPI schema available"
        }
    } catch {
        Warn "OpenAPI schema not available"
    }

    try {
        $req = [System.Net.Http.HttpClient]::new()
        $req.Timeout = [TimeSpan]::FromSeconds(3)
        $resp = $req.GetAsync("$($baseUrls[0])/docs").GetAwaiter().GetResult()
        if ($resp.IsSuccessStatusCode) {
            Ok "Swagger UI available at $($baseUrls[0])/docs"
        }
    } catch {
        Warn "Swagger UI not available"
    }
}

# ── 4. Provider configuration ──
Log ""
Log "Provider availability:"
$providers = @('openai', 'anthropic', 'groq', 'deepseek', 'ollama')
foreach ($provider in $providers) {
    $envVar = "${provider}_api_key" -replace 'ollama', 'ollama'
    $val = [Environment]::GetEnvironmentVariable("${provider}_API_KEY", 'User') ?? [Environment]::GetEnvironmentVariable("${provider}_API_KEY", 'Machine')
    if ($provider -eq 'ollama') {
        $ollamaPort = netstat -ano | Select-String ":11434" | Select-String "LISTEN"
        if ($ollamaPort) {
            Ok "Ollama: running (port 11434)"
        } else {
            Warn "Ollama: not running (try: ollama serve)"
        }
    } elseif ($val) {
        Ok "$provider: API key configured"
    } else {
        if ($provider -notin @('deepseek')) {
            Warn "$provider: API key not configured (set ${provider}_API_KEY in .env)"
        } else {
            Log "$provider: not configured"
        }
    }
}

# ── 5. Model list ──
Log ""
Log "Available Ollama models:"
try {
    $ollamaResp = & curl.exe -s http://127.0.0.1:11434/api/tags 2>$null
    if ($ollamaResp) {
        $models = $ollamaResp | ConvertFrom-Json | Select-Object -ExpandProperty models
        if ($models) {
            foreach ($m in $models) {
                Ok "  $($m.name) ($([math]::Round($m.size/1GB,2))GB)"
            }
        } else {
            Log "  No models pulled. Run: ollama pull llama3.2"
        }
    }
} catch {
    Log "  Cannot reach Ollama (expected if not running)"
}

# ── 6. Gateway Python environment ──
Log ""
Log "Python environment for AI Gateway:"
$pythonPaths = @(
    "$ProjectRoot\.venv\Scripts\python.exe",
    "$ProjectRoot\ai-gateway\.venv\Scripts\python.exe",
    (Get-Command python -ErrorAction SilentlyContinue).Source
)
$pyexe = $pythonPaths | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1

if ($pyexe) {
    $version = & $pyexe --version 2>&1
    Log "  $version"
    $checks = @('fastapi', 'uvicorn', 'httpx', 'pydantic', 'structlog')
    foreach ($c in $checks) {
        $available = & $pyexe -c "try: import $c; print('ok'); except: print('missing')" 2>&1
        Log "  $c: $available"
    }
}

# ── Summary ──
Log ""
if ($allOk) {
    Ok "AI Gateway is operational"
} else {
    Warn "AI Gateway has issues to resolve. Check logs above."
}

Log "Log: $LogFile"
exit ($allOk ? 0 : 1)
