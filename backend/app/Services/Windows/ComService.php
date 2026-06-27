<?php

declare(strict_types=1);

namespace App\Services\Windows;

class ComService
{
    private static array $instances = [];

    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public static function shell(string $command, array &$output = null, int &$returnCode = null): string
    {
        exec($command, $rawOutput, $returnCode);
        $output = $rawOutput;
        return implode("\n", $rawOutput);
    }

    public static function powershell(string $script): string
    {
        return self::shell(
            'powershell -ExecutionPolicy Bypass -NoProfile -Command ' . escapeshellarg($script)
        );
    }

    public static function regRead(string $path, string $value): ?string
    {
        if (!self::isWindows()) {
            return null;
        }
        $output = self::shell(
            'reg query ' . escapeshellarg($path) . ' /v ' . escapeshellarg($value) . ' 2>nul'
        );
        if (preg_match('/' . preg_quote($value, '/') . '\s+REG_\w+\s+(.+)$/m', $output, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public static function regWrite(string $path, string $value, string $data, string $type = 'REG_SZ'): bool
    {
        if (!self::isWindows()) {
            return false;
        }
        $output = self::shell(
            'reg add ' . escapeshellarg($path) . ' /v ' . escapeshellarg($value) . ' /t ' . $type . ' /d ' . escapeshellarg($data) . ' /f 2>nul',
            $_, $code
        );
        return $code === 0;
    }

    public static function regDelete(string $path, string $value = null): bool
    {
        if (!self::isWindows()) {
            return false;
        }
        $cmd = 'reg delete ' . escapeshellarg($path);
        if ($value !== null) {
            $cmd .= ' /v ' . escapeshellarg($value);
        }
        $cmd .= ' /f 2>nul';
        self::shell($cmd, $_, $code);
        return $code === 0;
    }

    public static function com(string $class): ?object
    {
        if (!self::isWindows() || !extension_loaded('com_dotnet')) {
            return null;
        }
        $key = $class;
        if (!isset(self::$instances[$key])) {
            try {
                self::$instances[$key] = new \COM($class);
            } catch (\Throwable) {
                return null;
            }
        }
        return self::$instances[$key];
    }

    public static function invariant(): ?object
    {
        return self::com('IWshRuntimeLibrary.WshShell');
    }

    public static function scheduler(): ?object
    {
        return self::com('Schedule.Service');
    }

    public static function sendKeys(string $keys): void
    {
        $wsh = self::invariant();
        if ($wsh) {
            $wsh->SendKeys($keys);
        }
    }

    public static function createShortcut(string $path, string $target, string $description = '', string $icon = '', string $args = ''): bool
    {
        $wsh = self::invariant();
        if (!$wsh) {
            return false;
        }
        try {
            $shortcut = $wsh->CreateShortcut($path);
            $shortcut->TargetPath = $target;
            if ($description) {
                $shortcut->Description = $description;
            }
            if ($icon) {
                $shortcut->IconLocation = $icon;
            }
            if ($args) {
                $shortcut->Arguments = $args;
            }
            $shortcut->Save();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function createLink(string $path, string $comment = ''): bool
    {
        $shell = self::com('Shell.Application');
        if (!$shell) {
            return false;
        }
        try {
            $shell->Namespace(\dirname($path))->ParseName(\basename($path))->InvokeVerb('link');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
