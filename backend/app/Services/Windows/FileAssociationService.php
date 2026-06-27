<?php

declare(strict_types=1);

namespace App\Services\Windows;

class FileAssociationService
{
    private const APP_REG_PATH = 'Software\\Corex';
    private const ASSOC_REG_PATH = 'Software\\Classes';

    public static function isWindows(): bool
    {
        return ComService::isWindows();
    }

    public static function registerProtocol(string $scheme = 'corex', string $description = 'Corex URL Protocol'): bool
    {
        if (!self::isWindows()) {
            return false;
        }

        $installPath = RegistryService::getAppInstallPath() ?? dirname(base_path());
        $launcher = $installPath . '\\start-corex.bat';

        ComService::regWrite("HKEY_CLASSES_ROOT\\$scheme", '', $description);
        ComService::regWrite("HKEY_CLASSES_ROOT\\$scheme\\DefaultIcon", '', "$installPath\\icon.ico");
        ComService::regWrite(
            "HKEY_CLASSES_ROOT\\$scheme\\shell\\open\\command",
            '',
            "\"$launcher\" \"%1\""
        );

        EventLogService::info("Protocol handler registered: $scheme://");
        return true;
    }

    public static function unregisterProtocol(string $scheme = 'corex'): bool
    {
        if (!self::isWindows()) {
            return false;
        }
        ComService::regDelete("HKEY_CLASSES_ROOT\\$scheme");
        EventLogService::info("Protocol handler unregistered: $scheme://");
        return true;
    }

    public static function registerFileAssociation(string $extension, string $progId = null, string $description = null, string $iconPath = null): bool
    {
        if (!self::isWindows()) {
            return false;
        }

        $ext = ltrim($extension, '.');
        $progId = $progId ?? "Corex.$ext";
        $description = $description ?? "Corex $ext File";
        $installPath = RegistryService::getAppInstallPath() ?? dirname(base_path());
        $launcher = $installPath . '\\start-corex.bat';
        $iconPath = $iconPath ?? ($installPath . '\\icon.ico');

        ComService::regWrite("HKEY_CLASSES_ROOT\\.$ext", '', $progId);
        ComService::regWrite("HKEY_CLASSES_ROOT\\$progId", '', $description);
        ComService::regWrite("HKEY_CLASSES_ROOT\\$progId\\DefaultIcon", '', $iconPath);
        ComService::regWrite(
            "HKEY_CLASSES_ROOT\\$progId\\shell\\open\\command",
            '',
            "\"$launcher\" \"%1\""
        );

        return true;
    }

    public static function unregisterFileAssociation(string $extension): bool
    {
        if (!self::isWindows()) {
            return false;
        }
        $ext = ltrim($extension, '.');
        $progId = RegistryService::get("HKEY_CLASSES_ROOT\\.$ext", '');
        if ($progId) {
            ComService::regDelete("HKEY_CLASSES_ROOT\\$progId");
        }
        ComService::regDelete("HKEY_CLASSES_ROOT\\.$ext");
        return true;
    }

    public static function registerContextMenu(string $extension, string $menuText, string $command, string $iconPath = null): bool
    {
        if (!self::isWindows()) {
            return false;
        }

        $ext = ltrim($extension, '.');
        $shellPath = "HKEY_CLASSES_ROOT\\Corex.$ext\\shell\\" . bin2hex(random_bytes(4));

        ComService::regWrite($shellPath, '', $menuText);
        if ($iconPath) {
            ComService::regWrite($shellPath, 'Icon', $iconPath);
        }
        ComService::regWrite(
            "$shellPath\\command",
            '',
            $command
        );

        EventLogService::info("Context menu registered: '$menuText' for .$ext");
        return true;
    }

    public static function registerExplorerContextMenu(): bool
    {
        if (!self::isWindows()) {
            return false;
        }

        $installPath = RegistryService::getAppInstallPath() ?? dirname(base_path());
        $launcher = "$installPath\\start-corex.bat";
        $iconPath = "$installPath\\icon.ico";

        $menuItems = [
            [
                'ext' => '*',
                'label' => 'Open in Corex',
                'command' => "\"$launcher\" \"%1\"",
            ],
            [
                'ext' => 'Directory\\shell\\CorexOpen',
                'label' => 'Open with Corex',
                'command' => "\"$launcher\" \"%1\"",
            ],
            [
                'ext' => 'Directory\\Background\\shell\\CorexNew',
                'label' => 'New Corex Project',
                'command' => "\"$launcher\" --new \"%V\"",
            ],
        ];

        foreach ($menuItems as $item) {
            $path = "HKEY_CLASSES_ROOT\\" . $item['ext'] . "\\shell\\Corex";
            ComService::regWrite($path, '', $item['label']);
            ComService::regWrite($path, 'Icon', $iconPath);
            ComService::regWrite("$path\\command", '', $item['command']);
        }

        EventLogService::info('File Explorer context menu registered');
        return true;
    }

    public static function unregisterExplorerContextMenu(): bool
    {
        if (!self::isWindows()) {
            return false;
        }

        $paths = [
            'HKEY_CLASSES_ROOT\\*\\shell\\Corex',
            'HKEY_CLASSES_ROOT\\Directory\\shell\\Corex',
            'HKEY_CLASSES_ROOT\\Directory\\shell\\CorexOpen',
            'HKEY_CLASSES_ROOT\\Directory\\Background\\shell\\Corex',
            'HKEY_CLASSES_ROOT\\Directory\\Background\\shell\\CorexNew',
        ];

        foreach ($paths as $path) {
            ComService::regDelete($path);
        }

        return true;
    }

    public static function refreshExplorer(): void
    {
        if (!self::isWindows()) {
            return;
        }
        ComService::powershell(
            '$app = New-Object -ComObject Shell.Application;'
            . '$app.Windows() | ForEach-Object { $_.Refresh() };'
            . 'Stop-Process -Name explorer -Force 2>$null'
        );
    }

    public static function getRegisteredExtensions(): array
    {
        $extensions = [];
        $output = ComService::shell('reg query "HKEY_CLASSES_ROOT" /k /f "Corex." 2>nul');
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/Corex\\.(\\w+)/i', $line, $m)) {
                $extensions[] = $m[1];
            }
        }
        return array_unique($extensions);
    }

    public static function registerSendTo(): bool
    {
        if (!self::isWindows()) {
            return false;
        }
        $sendTo = ComService::powershell(
            '[Environment]::GetFolderPath("SendTo")'
        );
        $installPath = RegistryService::getAppInstallPath() ?? dirname(base_path());
        $launcher = "$installPath\\start-corex.bat";

        ComService::createShortcut(
            trim($sendTo) . '\\Corex.lnk',
            $launcher,
            'Open files in Corex',
            "$installPath\\icon.ico"
        );

        return true;
    }
}
