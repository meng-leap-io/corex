<?php

declare(strict_types=1);

namespace App\Services\Windows;

class RegistryService
{
    private const HKLM = 'HKEY_LOCAL_MACHINE';
    private const HKCU = 'HKEY_CURRENT_USER';
    private const HKCR = 'HKEY_CLASSES_ROOT';

    public static function get(string $path, string $value, string $hive = self::HKLM): ?string
    {
        return ComService::regRead($hive . '\\' . $path, $value);
    }

    public static function set(string $path, string $value, string $data, string $hive = self::HKLM): bool
    {
        return ComService::regWrite($hive . '\\' . $path, $value, $data);
    }

    public static function delete(string $path, string $value = null, string $hive = self::HKLM): bool
    {
        return ComService::regDelete($hive . '\\' . $path, $value);
    }

    public static function getWindowsTheme(): string
    {
        $theme = self::get(
            'SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Themes\\Personalize',
            'AppsUseLightTheme',
            self::HKCU
        );
        if ($theme === null) {
            return 'dark';
        }
        return ((int) $theme) === 1 ? 'light' : 'dark';
    }

    public static function getSystemTheme(): string
    {
        $theme = self::get(
            'SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Themes\\Personalize',
            'SystemUsesLightTheme',
            self::HKCU
        );
        if ($theme === null) {
            return 'dark';
        }
        return ((int) $theme) === 1 ? 'light' : 'dark';
    }

    public static function getAccentColor(): ?string
    {
        $color = self::get(
            'SOFTWARE\\Microsoft\\Windows\\DWM',
            'AccentColor',
            self::HKCU
        );
        if ($color === null) {
            return null;
        }
        $dec = (int) $color;
        return sprintf('#%06X', $dec & 0x00FFFFFF);
    }

    public static function getColorPrevalence(): bool
    {
        $val = self::get(
            'SOFTWARE\\Microsoft\\Windows\\DWM',
            'ColorPrevalence',
            self::HKCU
        );
        return $val !== null && ((int) $val) === 1;
    }

    public static function getTransparency(): bool
    {
        $val = self::get(
            'SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Themes\\Personalize',
            'EnableTransparency',
            self::HKCU
        );
        return $val !== null && ((int) $val) === 1;
    }

    public static function getDpiScale(): float
    {
        $val = self::get(
            'SOFTWARE\\Microsoft\\Windows\\Dwm',
            'LogPixels',
            self::HKCU
        );
        if ($val === null) {
            return 1.0;
        }
        return ((int) $val) / 96.0;
    }

    public static function getTaskbarLocation(): string
    {
        $val = self::get(
            'SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Explorer\\StuckRects3',
            'Settings',
            self::HKCU
        );
        return 'bottom';
    }

    public static function isHighContrast(): bool
    {
        $val = self::get(
            'SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Themes',
            'HighContrast',
            self::HKCU
        );
        return $val !== null && ((int) $val) === 1;
    }

    public static function getProxySettings(): array
    {
        $enabled = self::get(
            'SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Internet Settings',
            'ProxyEnable',
            self::HKCU
        );
        $server = self::get(
            'SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Internet Settings',
            'ProxyServer',
            self::HKCU
        );
        $bypass = self::get(
            'SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Internet Settings',
            'ProxyOverride',
            self::HKCU
        );
        return [
            'enabled' => $enabled !== null && ((int) $enabled) === 1,
            'server' => $server ?? '',
            'bypass' => $bypass ?? '',
        ];
    }

    public static function getInstalledFonts(): array
    {
        $fonts = [];
        $output = ComService::shell(
            'reg query "HKLM\\SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion\\Fonts" 2>nul'
        );
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^\\s+(.+?)\\s+REG_SZ\\s+(.+)$/', $line, $m)) {
                $fonts[] = [
                    'name' => trim($m[1]),
                    'path' => trim($m[2]),
                ];
            }
        }
        return $fonts;
    }

    public static function getAppInstallPath(): ?string
    {
        return self::get('Software\\Corex', 'InstallPath', self::HKLM);
    }

    public static function getAppVersion(): ?string
    {
        return self::get('Software\\Corex', 'Version', self::HKLM);
    }

    public static function setAppSettings(array $settings): void
    {
        $path = 'Software\\Corex\\Settings';
        foreach ($settings as $key => $value) {
            self::set($path, $key, (string) $value, self::HKCU);
        }
    }

    public static function getAppSettings(): array
    {
        $settings = [];
        $output = ComService::shell(
            'reg query "HKEY_CURRENT_USER\\Software\\Corex\\Settings" 2>nul'
        );
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^\\s+(.+?)\\s+REG_\\w+\\s+(.+)$/', $line, $m)) {
                $settings[trim($m[1])] = trim($m[2]);
            }
        }
        return $settings;
    }
}
