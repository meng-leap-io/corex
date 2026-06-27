<?php

declare(strict_types=1);

namespace App\Services\Windows;

class ThemeService
{
    public static function detect(): array
    {
        return [
            'theme' => self::theme(),
            'system_theme' => self::systemTheme(),
            'accent_color' => self::accentColor(),
            'accent_in_titlebar' => self::accentInTitleBar(),
            'transparency' => self::transparency(),
            'high_contrast' => self::highContrast(),
            'dpi_scale' => self::dpiScale(),
            'font_scale' => self::fontScale(),
        ];
    }

    public static function theme(): string
    {
        return RegistryService::getWindowsTheme();
    }

    public static function systemTheme(): string
    {
        return RegistryService::getSystemTheme();
    }

    public static function accentColor(): ?string
    {
        return RegistryService::getAccentColor();
    }

    public static function accentInTitleBar(): bool
    {
        return RegistryService::getColorPrevalence();
    }

    public static function transparency(): bool
    {
        return RegistryService::getTransparency();
    }

    public static function highContrast(): bool
    {
        return RegistryService::isHighContrast();
    }

    public static function dpiScale(): float
    {
        return RegistryService::getDpiScale();
    }

    public static function fontScale(): float
    {
        if (!ComService::isWindows()) {
            return 1.0;
        }
        $val = ComService::regRead(
            'HKEY_CURRENT_USER\\Control Panel\\Desktop',
            'LogPixels'
        );
        if ($val === null) {
            return 1.0;
        }
        return ((int) $val) / 96.0;
    }

    public static function isDarkMode(): bool
    {
        return self::theme() === 'dark';
    }

    public static function cssVariables(): array
    {
        $theme = self::theme();
        $accent = self::accentColor() ?? '#2563eb';

        if ($theme === 'dark') {
            return [
                '--bg-primary' => '#0f172a',
                '--bg-secondary' => '#1e293b',
                '--bg-tertiary' => '#334155',
                '--text-primary' => '#f1f5f9',
                '--text-secondary' => '#94a3b8',
                '--border' => '#334155',
                '--accent' => $accent,
                '--accent-hover' => self::adjustBrightness($accent, -20),
                '--surface' => '#1e293b',
                '--surface-hover' => '#334155',
                '--scrollbar' => '#475569',
                '--scrollbar-hover' => '#64748b',
            ];
        }

        return [
            '--bg-primary' => '#ffffff',
            '--bg-secondary' => '#f8fafc',
            '--bg-tertiary' => '#f1f5f9',
            '--text-primary' => '#0f172a',
            '--text-secondary' => '#475569',
            '--border' => '#e2e8f0',
            '--accent' => $accent,
            '--accent-hover' => self::adjustBrightness($accent, -20),
            '--surface' => '#ffffff',
            '--surface-hover' => '#f1f5f9',
            '--scrollbar' => '#cbd5e1',
            '--scrollbar-hover' => '#94a3b8',
        ];
    }

    private static function adjustBrightness(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $r = max(0, min(255, $r + $percent));
        $g = max(0, min(255, $g + $percent));
        $b = max(0, min(255, $b + $percent));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public static function tailwindClasses(): array
    {
        return self::isDarkMode() ? [
            'bg' => 'bg-slate-900',
            'bg-secondary' => 'bg-slate-800',
            'text' => 'text-slate-100',
            'text-secondary' => 'text-slate-400',
            'border' => 'border-slate-700',
            'hover' => 'hover:bg-slate-700',
        ] : [
            'bg' => 'bg-white',
            'bg-secondary' => 'bg-slate-50',
            'text' => 'text-slate-900',
            'text-secondary' => 'text-slate-500',
            'border' => 'border-slate-200',
            'hover' => 'hover:bg-slate-100',
        ];
    }
}
