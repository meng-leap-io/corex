<?php

declare(strict_types=1);

namespace App\Services\Windows;

class ProxySettingsService
{
    public static function get(): array
    {
        return RegistryService::getProxySettings();
    }

    public static function isProxyEnabled(): bool
    {
        return self::get()['enabled'] ?? false;
    }

    public static function getProxyServer(): string
    {
        return self::get()['server'] ?? '';
    }

    public static function getProxyBypass(): string
    {
        return self::get()['bypass'] ?? '';
    }

    public static function getProxyForUrl(string $url): ?string
    {
        if (!self::isProxyEnabled()) {
            return null;
        }

        $server = self::getProxyServer();
        $bypass = self::getProxyBypass();

        $host = parse_url($url, PHP_URL_HOST);
        if ($host && self::isBypassed($host, $bypass)) {
            return null;
        }

        if (!$server) {
            return null;
        }

        return $server;
    }

    private static function isBypassed(string $host, string $bypassList): bool
    {
        if (empty($bypassList)) {
            return false;
        }

        $patterns = explode(';', $bypassList);
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) {
                continue;
            }
            if ($pattern === '<local>') {
                return !str_contains($host, '.');
            }
            $regex = '/^' . preg_quote($pattern, '/') . '$/i';
            $regex = str_replace('\*', '.*', $regex);
            if (preg_match($regex, $host)) {
                return true;
            }
        }

        return false;
    }

    public static function configureHttpClient(): array
    {
        $proxy = self::getProxyForUrl('https://api.corex.dev');
        if (!$proxy) {
            return [];
        }

        $parts = parse_url($proxy);
        $config = [];

        if (isset($parts['host'])) {
            $proxyUrl = 'http://' . $parts['host'];
            if (isset($parts['port'])) {
                $proxyUrl .= ':' . $parts['port'];
            }
            $config['proxy'] = $proxyUrl;
        }

        if (isset($parts['user']) && isset($parts['pass'])) {
            $config['proxy_auth'] = $parts['user'] . ':' . $parts['pass'];
        }

        return $config;
    }

    public static function set(string $server, string $bypass = '', bool $enabled = true): bool
    {
        if (!ComService::isWindows()) {
            return false;
        }

        RegistryService::set(
            'Software\\Microsoft\\Windows\\CurrentVersion\\Internet Settings',
            'ProxyEnable',
            $enabled ? '1' : '0',
            ComService::HKCU
        );

        if ($enabled && $server) {
            RegistryService::set(
                'Software\\Microsoft\\Windows\\CurrentVersion\\Internet Settings',
                'ProxyServer',
                $server,
                ComService::HKCU
            );
        }

        if ($bypass) {
            RegistryService::set(
                'Software\\Microsoft\\Windows\\CurrentVersion\\Internet Settings',
                'ProxyOverride',
                $bypass,
                ComService::HKCU
            );
        }

        self::notifyChange();
        return true;
    }

    public static function disable(): bool
    {
        return self::set('', '', false);
    }

    public static function detectChanges(): ?array
    {
        static $previous = null;
        $current = self::get();

        if ($previous !== null && $previous !== $current) {
            $changed = [];
            foreach ($current as $key => $value) {
                if (($previous[$key] ?? null) !== $value) {
                    $changed[$key] = $value;
                }
            }
            $previous = $current;
            return $changed;
        }

        $previous = $current;
        return null;
    }

    private static function notifyChange(): void
    {
        $notify = ComService::com('InternetExplorer.Application');
        if ($notify) {
            try {
                $notify->RegisterAsDropTarget = false;
                $notify->Visible = false;
            } catch (\Throwable) {
            }
        }

        ComService::powershell(
            'Remove-Item -Path "HKCU:\\Software\\Microsoft\\Windows\\CurrentVersion\\Internet Settings\\Connections"'
            . ' -Recurse -Force -ErrorAction SilentlyContinue;'
            . '$null = [System.Net.WebRequest]::DefaultWebProxy'
        );
    }
}
