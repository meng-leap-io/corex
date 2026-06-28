<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateUserAgent
{
    private const BLOCKED_BOTS = [
        'zgrab',
        'masscan',
        'nmap',
        'nessus',
        'openvas',
        'sqlmap',
        'nikto',
        'dirbuster',
        'gobuster',
        'wpscan',
        'acunetix',
        'netsparker',
        'appscan',
        'burpsuite',
        'postman',
        'python-requests',
        'python-urllib',
        'aiohttp',
        'scrapy',
        'curl',
        'wget',
        'Go-http-client',
        'fasthttp',
        'Apache-HttpClient',
        'okhttp',
        'Java',
        'perl',
        'ruby',
    ];

    private const BLOCKED_EXTENSIONS = [
        '.env', '.sql', '.bak', '.old', '.swp', '.save',
        '.php.bak', '.php.old', '.php.save', '.php.swp',
        '.inc', '.po', '.mo', '.sh', '.pl', '.py',
    ];

    private const BLOCKED_PATHS = [
        'wp-admin', 'wp-content', 'wp-includes',
        'administrator', 'admin.php',
        'xmlrpc.php', 'wp-login.php',
        '.git/config', '.svn/entries',
        'vendor/phpunit', 'node_modules',
        'backup', 'dump', 'sql',
    ];

    private const SUSPICIOUS_PARAMS = [
        'dbuser', 'dbpass', 'db_host', 'db_name',
        'mysql', 'pgsql', 'mongodb',
        'exec', 'system', 'passthru', 'shell_exec',
        'cmd', 'command', 'input',
        'debug', 'test', 'xdebug',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isSuspiciousRequest($request)) {
            return response()->json([
                'message' => 'Request blocked by security policy.',
                'code' => 'BLOCKED',
            ], 403);
        }

        return $next($request);
    }

    private function isSuspiciousRequest(Request $request): bool
    {
        if ($this->hasBlockedUserAgent($request)) {
            return true;
        }

        if ($this->hasBlockedExtension($request)) {
            return true;
        }

        if ($this->hasBlockedPath($request)) {
            return true;
        }

        if ($this->hasSuspiciousParameters($request)) {
            return true;
        }

        return false;
    }

    private function hasBlockedUserAgent(Request $request): bool
    {
        $ua = $request->userAgent();
        if (! $ua) {
            return false;
        }

        $uaLower = strtolower($ua);

        foreach (self::BLOCKED_BOTS as $bot) {
            if (str_contains($uaLower, strtolower($bot))) {
                return true;
            }
        }

        return false;
    }

    private function hasBlockedExtension(Request $request): bool
    {
        $path = $request->path();

        foreach (self::BLOCKED_EXTENSIONS as $ext) {
            if (str_ends_with($path, $ext)) {
                return true;
            }
        }

        return false;
    }

    private function hasBlockedPath(Request $request): bool
    {
        $path = strtolower($request->path());

        foreach (self::BLOCKED_PATHS as $blocked) {
            if (str_contains($path, strtolower($blocked))) {
                return true;
            }
        }

        return false;
    }

    private function hasSuspiciousParameters(Request $request): bool
    {
        foreach (self::SUSPICIOUS_PARAMS as $param) {
            if ($request->has($param)) {
                return true;
            }
        }

        foreach ($request->query() as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $lower = strtolower($value);

            if (preg_match('/(\bUNION\b.*\bSELECT\b|\bSELECT\b.*\bFROM\b|\bDROP\b.*\bTABLE\b)/i', $lower)) {
                return true;
            }

            if (preg_match('/<script[\s>]/i', $lower)) {
                return true;
            }

            if (preg_match('/\b(?:exec|system|passthru|shell_exec|popen|proc_open)\s*\(/i', $lower)) {
                return true;
            }

            if (preg_match('/\.\.\/|\.\.\\\|\.\.%2f|\.\.%5c/i', $lower)) {
                return true;
            }
        }

        return false;
    }
}
