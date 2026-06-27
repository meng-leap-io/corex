#Requires -Version 5.1

<#
.SYNOPSIS
    Python Windows Diagnostic Script

.DESCRIPTION
    Debugs Python-specific Windows issues: DLL loading errors, asyncio event
    loop problems, process management, signal handling, Unicode/encoding issues,
    and virtual environment health.

.PARAMETER PythonPath
    Path to Python executable. Auto-detects if not provided (prefers .venv).

.PARAMETER OutputDir
    Directory for diagnostic output.

.EXAMPLE
    PS> .\python-debug.ps1
    PS> .\python-debug.ps1 -PythonPath "C:\tools\python\python.exe"
#>

param(
    [string]$PythonPath,
    [string]$OutputDir
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $PSCommandPath
$ProjectRoot = Resolve-Path (Join-Path $ScriptDir '..\..')
$LogDir = $OutputDir ? $OutputDir : (Join-Path $ScriptDir '..\logs' "python-debug-$(Get-Date -Format 'yyyyMMdd-HHmmss')")
$ReportFile = Join-Path $LogDir 'python-debug.txt'
$JsonFile = Join-Path $LogDir 'python-debug.json'

New-Item -ItemType Directory -Path $LogDir -Force | Out-Null

function Write-Result { param([string]$M, [string]$L='Info') $c=@{Info='Gray';Ok='Green';Warn='Yellow';Fail='Red';H='Cyan'} [$L]; Write-Host "$(@{Info='  ';Ok='✓ ';Warn='⚠ ';Fail='✗ ';H='──'}[$L])$M" -ForegroundColor $c }
function H { param([string]$M) Write-Result $M H; "" }

# ──────────────────────────────────────────────────────────────────────────
# 1. Find Python & Virtual Environment
# ──────────────────────────────────────────────────────────────────────────
H "1. Python Discovery"

$data = @{ python_path = $null; version = $null; venv = $null; errors = @(); warnings = @(); passed = @() }
$errors = $data.errors; $warnings = $data.warnings; $passed = $data.passed

# Prefer .venv in project
$venvCandidates = @(
    "$ProjectRoot\.venv\Scripts\python.exe",
    "$ProjectRoot\venv\Scripts\python.exe",
    "$ProjectRoot\ai-gateway\.venv\Scripts\python.exe",
    "$ProjectRoot\ai-gateway\venv\Scripts\python.exe"
)

$python = $null
if ($PythonPath) {
    $python = $PythonPath
} else {
    $python = $venvCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
}
if (-not $python) { $python = (Get-Command python -ErrorAction SilentlyContinue).Source }
if (-not $python) { $python = (Get-Command python3 -ErrorAction SilentlyContinue).Source }
if (-not $python) {
    Write-Result "Python not found. Check PATH or provide -PythonPath" Fail
    $errors += "Python not found."
    $data | ConvertTo-Json -Depth 5 | Out-File $JsonFile -Encoding utf8
    return
}

$data.python_path = (Resolve-Path $python).Path
Write-Result "Python: $($data.python_path)" Ok

# Detect virtual env
$venvDir = Split-Path (Split-Path $python) -Parent
if ((Test-Path (Join-Path $venvDir 'pyvenv.cfg')) -or (Test-Path (Join-Path $venvDir '..\pyvenv.cfg'))) {
    $data.venv = $venvDir
    Write-Result "Virtual environment: $venvDir" Ok
    $passed += "Virtual environment found"
} else {
    Write-Result "No virtual environment detected (running system Python)" Warn
    $warnings += "No virtual environment. Use python -m venv .venv to create one."
}

$version = & $python --version 2>&1
$data.version = "$version"
if ($version -match '3\.1[2-9]|3\.\d+') {
    Write-Result "Version: $version ✓" Ok
} else {
    Write-Result "Version: $version" Warn
    $warnings += "Python $version detected. Python 3.12+ recommended."
}

# Bitness
$arch = & $python -c "import sys; print('64bit' if sys.maxsize > 2**32 else '32bit')" 2>&1
$data.arch = "$arch"
if ($arch -eq '64bit') { Write-Result "Architecture: x64 ✓" Ok } else { Write-Result "Architecture: $arch (use 64-bit)" Warn }

# ──────────────────────────────────────────────────────────────────────────
# 2. DLL Loading
# ──────────────────────────────────────────────────────────────────────────
H "2. DLL Loading & Dependencies"

$dllTests = @(
    @{ Name = 'kernel32.dll'; Module = 'ctypes' },
    @{ Name = 'ws2_32.dll'; Module = 'socket' },
    @{ Name = 'ole32.dll'; Module = 'comtypes' },
    @{ Name = 'advapi32.dll'; Module = 'pywin32' },
    @{ Name = 'shell32.dll'; Module = 'pywin32' }
)

$script = @'
import sys, importlib

results = {}
dlls = ['kernel32', 'ws2_32', 'ole32', 'advapi32', 'shell32', 'user32', 'gdi32', 'ntdll']
for dll in dlls:
    try:
        import ctypes
        ctypes.windll.LoadLibrary(dll + '.dll')
        results[dll] = 'ok'
    except Exception as e:
        results[dll] = f'fail: {e}'

# Check critical modules
modules = ['asyncio', 'ssl', 'ctypes', 'socket', 'json', 'multiprocessing', 'threading', 'signal', 'selectors', 'http']
for mod in modules:
    try:
        importlib.import_module(mod)
        results[mod] = 'ok'
    except ImportError as e:
        results[mod] = f'missing: {e}'

# Try optional Windows modules
win_modules = ['winreg', 'msvcrt', 'wmi', 'pythoncom', 'win32api', 'win32con', 'win32event', 'win32process']
for mod in win_modules:
    try:
        importlib.import_module(mod)
        results[mod] = 'ok'
    except ImportError:
        pass

import json; print(json.dumps(results))
'@

try {
    $dllResult = & $python -c $script 2>&1 | Where-Object { $_ -match '^{' }
    if ($dllResult) {
        $dllData = $dllResult | ConvertFrom-Json
        foreach ($key in $dllData.PSObject.Properties.Name) {
            $val = $dllData.$key
            if ($val -eq 'ok') {
                Write-Result "DLL/module: $key" Ok
            } else {
                Write-Result "DLL/module: $key — $val" Fail
                $errors += "Python DLL check: $key — $val"
            }
        }
    }
} catch {
    Write-Result "DLL diagnostic script failed: $_" Warn
}

# ──────────────────────────────────────────────────────────────────────────
# 3. asyncio Event Loop
# ──────────────────────────────────────────────────────────────────────────
H "3. asyncio Event Loop"

$asyncScript = @'
import asyncio, sys, platform

results = {}
# Default event loop policy
loop = asyncio.new_event_loop()
results['default_loop_type'] = type(loop).__name__
asyncio.set_event_loop(loop)

# Test basic async
async def test():
    await asyncio.sleep(0.01)
    return 'ok'

try:
    result = loop.run_until_complete(test())
    results['async_test'] = result
except Exception as e:
    results['async_test'] = f'fail: {e}'

# Check proactor (Windows uses ProactorEventLoop)
results['platform'] = platform.system()
results['python_impl'] = platform.python_implementation()
results['win32_ver'] = str(sys.getwindowsversion()) if hasattr(sys, 'getwindowsversion') else 'n/a'

# Check if uvloop is available
try:
    import uvloop
    results['uvloop'] = 'available'
except ImportError:
    results['uvloop'] = 'not available (standard on Windows)'

# Check selector event loop (deprecated on 3.12+)
try:
    import selectors
    sel = selectors.DefaultSelector()
    results['selector'] = type(sel).__name__
except Exception as e:
    results['selector'] = f'fail: {e}'

loop.close()
import json; print(json.dumps(results))
'@

try {
    $asyncResult = & $python -c $asyncScript 2>&1 | Where-Object { $_ -match '^{' }
    if ($asyncResult) {
        $asyncData = $asyncResult | ConvertFrom-Json
        Write-Result "Event loop: $($asyncData.default_loop_type)" Ok
        Write-Result "Async test: $($asyncData.async_test)" Ok

        if ($asyncData.default_loop_type -eq 'ProactorEventLoop') {
            Write-Result "Using ProactorEventLoop (Windows default) ✓" Ok
            $passed += "ProactorEventLoop (correct for Windows)"
        } elseif ($asyncData.default_loop_type -match 'Selector') {
            Write-Result "Using SelectorEventLoop (not ideal on Windows)" Warn
            $warnings += "Python is using $($asyncData.default_loop_type) on Windows. ProactorEventLoop is recommended for Windows I/O."
        }

        if ($asyncData.uvloop -eq 'available') {
            Write-Result "uvloop available (uses SelectorEventLoop, not recommended on Windows)" Info
        }

        $data.asyncio = @{}
        foreach ($prop in $asyncData.PSObject.Properties.Name) {
            $data.asyncio[$prop] = $asyncData.$prop
        }
    }
} catch {
    Write-Result "asyncio test failed: $_" Fail
    $errors += "asyncio test: $_"
}

# ──────────────────────────────────────────────────────────────────────────
# 4. Signals
# ──────────────────────────────────────────────────────────────────────────
H "4. Signal Handling"

$signalScript = @'
import signal, sys, json
results = {}
available = []
for name in ['SIGINT', 'SIGTERM', 'SIGBREAK', 'SIGABRT', 'SIGFPE', 'SIGILL', 'SIGSEGV']:
    if hasattr(signal, name):
        available.append(name)
results['available_signals'] = available
results['sigint_handler'] = str(signal.getsignal(signal.SIGINT))
results['sigterm_handler'] = str(signal.getsignal(signal.SIGTERM))
try:
    results['sigbreak'] = 'available' if hasattr(signal, 'SIGBREAK') else 'not available'
except: results['sigbreak'] = 'error'
import json; print(json.dumps(results))
'@

try {
    $sigResult = & $python -c $signalScript 2>&1 | Where-Object { $_ -match '^{' }
    if ($sigResult) {
        $sigData = $sigResult | ConvertFrom-Json
        Write-Result "Available signals: $($sigData.available_signals -join ', ')" Ok
        if ($sigData.available_signals -contains 'SIGBREAK') {
            Write-Result "SIGBREAK available (Ctrl+Break, CTRL_CLOSE_EVENT) ✓" Ok
            $passed += "SIGBREAK available for Windows service control"
        } else {
            Write-Result "SIGBREAK not available (Ctrl+Break handling limited)" Warn
            $warnings += "SIGBREAK (Ctrl+Break) not available. Use SetConsoleCtrlHandler via pywin32 for service shutdown."
        }
        $data.signals = @{}
        foreach ($prop in $sigData.PSObject.Properties.Name) { $data.signals[$prop] = $sigData.$prop }
    }
} catch {
    Write-Result "Signal test failed: $_" Warn
}

# ──────────────────────────────────────────────────────────────────────────
# 5. Process Management
# ──────────────────────────────────────────────────────────────────────────
H "5. Process Management"

$processScript = @'
import subprocess, sys, os, json, tempfile
results = {}

# Test subprocess
try:
    r = subprocess.run([sys.executable, '-c', 'print("hello")'], capture_output=True, text=True, timeout=10)
    results['subprocess'] = 'ok' if r.stdout.strip() == 'hello' else f'unexpected: {r.stdout}'
except Exception as e:
    results['subprocess'] = f'fail: {e}'

# Test process creation flags (Windows)
if sys.platform == 'win32':
    try:
        import subprocess
        # CREATE_NO_WINDOW
        r = subprocess.Popen([sys.executable, '-c', 'pass'], creationflags=0x08000000)
        r.wait(timeout=10)
        results['create_no_window'] = 'ok'
    except Exception as e:
        results['create_no_window'] = f'fail: {e}'

# Test multiprocessing
try:
    import multiprocessing
    results['cpu_count'] = multiprocessing.cpu_count()
except Exception as e:
    results['multiprocessing'] = f'fail: {e}'

# Test temp directory access
try:
    tf = tempfile.NamedTemporaryFile(delete=False)
    tf.write(b'test')
    tf.close()
    import os; os.unlink(tf.name)
    results['temp_write'] = 'ok'
except Exception as e:
    results['temp_write'] = f'fail: {e}'

results['pid'] = os.getpid()
results['ppid'] = os.getppid() if hasattr(os, 'getppid') else 'n/a'
import json; print(json.dumps(results))
'@

try {
    $procResult = & $python -c $processScript 2>&1 | Where-Object { $_ -match '^{' }
    if ($procResult) {
        $procData = $procResult | ConvertFrom-Json
        if ($procData.subprocess -eq 'ok') { Write-Result "Subprocess: OK ✓" Ok } else { Write-Result "Subprocess: $($procData.subprocess)" Fail }
        if ($procData.create_no_window -eq 'ok') { Write-Result "CREATE_NO_WINDOW: OK ✓" Ok }
        if ($procData.temp_write -eq 'ok') { Write-Result "Temp file write: OK ✓" Ok }
        Write-Result "CPU count: $($procData.cpu_count)" Info
        $data.processes = @{}
        foreach ($prop in $procData.PSObject.Properties.Name) { $data.processes[$prop] = $procData.$prop }
    }
} catch {
    Write-Result "Process test failed: $_" Warn
}

# ──────────────────────────────────────────────────────────────────────────
# 6. Unicode / Encoding
# ──────────────────────────────────────────────────────────────────────────
H "6. Unicode & Encoding"

$encScript = @'
import sys, locale, json
results = {}
results['stdin_encoding'] = sys.stdin.encoding
results['stdout_encoding'] = sys.stdout.encoding
results['stderr_encoding'] = sys.stderr.encoding
results['filesystem_encoding'] = sys.getfilesystemencoding()
results['preferred_encoding'] = locale.getpreferredencoding()
results['default_locale'] = locale.getdefaultlocale()

# Test CJK characters
try:
    test_str = '中文测试日本語テスト한국어テスト'
    encoded = test_str.encode('utf-8')
    decoded = encoded.decode('utf-8')
    results['unicode_roundtrip'] = 'ok' if test_str == decoded else 'fail'
except Exception as e:
    results['unicode_roundtrip'] = f'fail: {e}'

import json; print(json.dumps(results))
'@

try {
    $encResult = & $python -c $encScript 2>&1 | Where-Object { $_ -match '^{' }
    if ($encResult) {
        $encData = $encResult | ConvertFrom-Json
        Write-Result "stdin: $($encData.stdin_encoding) | stdout: $($encData.stdout_encoding) | stderr: $($encData.stderr_encoding)" Info
        Write-Result "Filesystem: $($encData.filesystem_encoding) | Preferred: $($encData.preferred_encoding)" Info
        if ($encData.unicode_roundtrip -eq 'ok') { Write-Result "Unicode UTF-8 roundtrip: OK ✓" Ok } else { Write-Result "Unicode: $($encData.unicode_roundtrip)" Fail }

        if ($encData.stdout_encoding -ne 'utf-8' -and $encData.stdout_encoding -ne 'UTF-8') {
            Write-Result "stdout encoding is $($encData.stdout_encoding), not UTF-8" Warn
            $warnings += "Python stdout encoding is $($encData.stdout_encoding). Set PYTHONIOENCODING=utf-8 or use -X utf8 mode."
        }
        $data.encoding = @{}
        foreach ($prop in $encData.PSObject.Properties.Name) { $data.encoding[$prop] = $encData.$prop }
    }
} catch {
    Write-Result "Encoding test failed: $_" Warn
}

# ──────────────────────────────────────────────────────────────────────────
# 7. AI Gateway Dependencies
# ──────────────────────────────────────────────────────────────────────────
H "7. AI Gateway Dependencies"

$reqFile = "$ProjectRoot\ai-gateway\requirements.txt"
if (Test-Path $reqFile) {
    $deps = Get-Content $reqFile | Where-Object { $_ -and $_ -notmatch '^#' -and $_ -notmatch ';' } | ForEach-Object { $_ -replace '\[.*\].*', '' -replace '==.*', '' -replace '>=.*', '' -replace '<=.*', '' -replace '~=.*', '' -replace '!=.*', '' }.Trim()
    $pipList = & $python -m pip list --format=json 2>&1 | Where-Object { $_ -match '^\[|^\{' } | ConvertFrom-Json

    $installed = @{}
    if ($pipList) { foreach ($pkg in $pipList) { $installed[$pkg.name.ToLower()] = $pkg.version } }

    $missing = @()
    foreach ($dep in $deps) {
        if ($dep -and $installed.ContainsKey($dep.ToLower())) {
            Write-Result "Package: $dep $($installed[$dep.ToLower()])" Ok
        } elseif ($dep -and $dep.Length -gt 1) {
            $missing += $dep
            Write-Result "Package: $dep — NOT INSTALLED" Fail
            $errors += "Python package '$dep' not installed. Run: pip install -r requirements.txt"
        }
    }

    if ($missing.Count -eq 0) { Write-Result "All requirements installed ✓" Ok;$passed += "All Python dependencies installed" }
    $data.dependencies = @{ missing = $missing; required_count = $deps.Count; installed_count = $installed.Count }
} else {
    Write-Result "requirements.txt not found at $reqFile" Warn
}

# ──────────────────────────────────────────────────────────────────────────
# 8. uvicorn / FastAPI
# ──────────────────────────────────────────────────────────────────────────
H "8. uvicorn & FastAPI"

$uvicornScript = @'
import json, sys
results = {}
try:
    import uvicorn
    results['uvicorn'] = uvicorn.__version__
    # Check for uvloop (not on Windows)
    try:
        import uvloop
        results['uvloop'] = 'available'
    except ImportError:
        results['uvloop'] = 'not available (expected on Windows)'
except ImportError:
    results['uvicorn'] = 'not installed'

try:
    import fastapi
    results['fastapi'] = fastapi.__version__ if hasattr(fastapi, '__version__') else 'installed'
except ImportError:
    results['fastapi'] = 'not installed'

try:
    import httpx
    results['httpx'] = httpx.__version__
except ImportError:
    results['httpx'] = 'not installed'

try:
    import pydantic
    results['pydantic'] = pydantic.__version__
except ImportError:
    results['pydantic'] = 'not installed'

try:
    import structlog
    results['structlog'] = structlog.__version__
except ImportError:
    results['structlog'] = 'not installed'

import json; print(json.dumps(results))
'@

try {
    $uvResult = & $python -c $uvicornScript 2>&1 | Where-Object { $_ -match '^{' }
    if ($uvResult) {
        $uvData = $uvResult | ConvertFrom-Json
        if ($uvData.uvicorn -ne 'not installed') { Write-Result "uvicorn $($uvData.uvicorn)" Ok } else { Write-Result "uvicorn NOT INSTALLED" Fail;$errors += "uvicorn not installed" }
        if ($uvData.fastapi -ne 'not installed') { Write-Result "fastapi $($uvData.fastapi)" Ok } else { Write-Result "fastapi NOT INSTALLED" Fail;$errors += "fastapi not installed" }
        if ($uvData.httpx -ne 'not installed') { Write-Result "httpx $($uvData.httpx)" Ok } else { Write-Result "httpx NOT INSTALLED" Fail;$errors += "httpx not installed" }
        if ($uvData.pydantic -ne 'not installed') { Write-Result "pydantic $($uvData.pydantic)" Ok } else { Write-Result "pydantic NOT INSTALLED" Fail }
        if ($uvData.structlog -ne 'not installed') { Write-Result "structlog $($uvData.structlog)" Ok } else { Write-Result "structlog NOT INSTALLED" Fail }

        if ($uvData.uvloop -eq 'available') { Write-Result "uvloop available (not typically used on Windows)" Info }
        $data.framework = @{}
        foreach ($prop in $uvData.PSObject.Properties.Name) { $data.framework[$prop] = $uvData.$prop }
    }
} catch {
    Write-Result "uvicorn/FastAPI check failed: $_" Warn
}

# ──────────────────────────────────────────────────────────────────────────
# Report
# ──────────────────────────────────────────────────────────────────────────
H "PYTHON DIAGNOSTIC SUMMARY"

Write-Host "Errors: $($errors.Count)" -ForegroundColor $(if ($errors.Count -gt 0) { 'Red' } else { 'Green' })
Write-Host "Warnings: $($warnings.Count)" -ForegroundColor $(if ($warnings.Count -gt 0) { 'Yellow' } else { 'Green' })
Write-Host "Passed: $($passed.Count)" -ForegroundColor Green

if ($errors.Count -gt 0) {
    Write-Host "`nErrors:" -ForegroundColor Red
    $errors | ForEach-Object { Write-Host "  ✗ $_" -ForegroundColor Red }
}
if ($warnings.Count -gt 0) {
    Write-Host "`nWarnings:" -ForegroundColor Yellow
    $warnings | ForEach-Object { Write-Host "  ⚠ $_" -ForegroundColor Yellow }
}

$data | ConvertTo-Json -Depth 5 | Out-File $JsonFile -Encoding utf8

Write-Host "`nReport: $ReportFile" -ForegroundColor Gray
Write-Host "JSON: $JsonFile" -ForegroundColor Gray

exit ($errors.Count -gt 0 ? 1 : 0)
