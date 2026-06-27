<?php

declare(strict_types=1);

namespace App\Services\Windows;

class JumpListService
{
    public static function addRecent(string $path, string $title = null): bool
    {
        if (!ComService::isWindows()) {
            return false;
        }
        $title = $title ?? basename($path);
        $appId = 'Corex.Desktop';

        ComService::powershell(
            'Add-Type -AssemblyName System.Runtime.WindowsRuntime;'
            . '$jl = [Windows.UI.StartScreen.JumpList]::LoadCurrent();'
            . '$item = New-Object Windows.UI.StartScreen.JumpListItem('
            . escapeshellarg($title) . ', ' . escapeshellarg($path) . ');'
            . '$item.Description = ' . escapeshellarg("Opened: $title") . ';'
            . '$item.GroupName = "Recent";'
            . '$jl.Items.Add($item);'
            . 'if ($jl.Items.Count -gt 20) { $jl.Items.RemoveAt(0) };'
            . '$jl.SaveAsync().GetAwaiter().GetResult() 2>$null'
        );
        return true;
    }

    public static function addTask(string $title, string $path, string $iconPath = null, string $args = null): bool
    {
        if (!ComService::isWindows()) {
            return false;
        }
        $appId = 'Corex.Desktop';

        $script = 'Add-Type -AssemblyName System.Runtime.WindowsRuntime;'
            . '$jl = [Windows.UI.StartScreen.JumpList]::LoadCurrent();'
            . '$item = New-Object Windows.UI.StartScreen.JumpListItem('
            . escapeshellarg($title) . ', ' . escapeshellarg($path) . ');'
            . '$item.Description = ' . escapeshellarg($title) . ';'
            . '$item.GroupName = "Tasks";';

        if ($args) {
            $script .= '$item.Arguments = ' . escapeshellarg($args) . ';';
        }
        if ($iconPath) {
            $script .= '$item.LogoDisplay = [Windows.UI.StartScreen.JumpListItemLogoDisplay]::None;';
        }

        $script .= '$jl.Items.Add($item);'
            . '$jl.SaveAsync().GetAwaiter().GetResult() 2>$null';

        ComService::powershell($script);
        return true;
    }

    public static function addCategory(string $categoryName, array $items): bool
    {
        if (!ComService::isWindows()) {
            return false;
        }

        $appId = 'Corex.Desktop';
        $script = 'Add-Type -AssemblyName System.Runtime.WindowsRuntime;'
            . '$jl = [Windows.UI.StartScreen.JumpList]::LoadCurrent();';

        foreach ($items as $item) {
            $title = $item['title'] ?? '';
            $path = $item['path'] ?? '';
            $args = $item['args'] ?? '';

            $script .= '$item = New-Object Windows.UI.StartScreen.JumpListItem('
                . escapeshellarg($title) . ', ' . escapeshellarg($path) . ');'
                . '$item.GroupName = ' . escapeshellarg($categoryName) . ';';
            if ($args) {
                $script .= '$item.Arguments = ' . escapeshellarg($args) . ';';
            }
            $script .= '$jl.Items.Add($item);';
        }

        $script .= '$jl.SaveAsync().GetAwaiter().GetResult() 2>$null';
        ComService::powershell($script);
        return true;
    }

    public static function setRecentFiles(array $files): bool
    {
        $items = [];
        foreach ($files as $file) {
            $items[] = [
                'title' => $file['name'] ?? basename($file['path'] ?? ''),
                'path' => $file['path'] ?? '',
                'args' => '',
            ];
        }
        return self::addCategory('Recent', $items);
    }

    public static function clear(): bool
    {
        if (!ComService::isWindows()) {
            return false;
        }
        ComService::powershell(
            'Add-Type -AssemblyName System.Runtime.WindowsRuntime;'
            . '$jl = [Windows.UI.StartScreen.JumpList]::LoadCurrent();'
            . '$jl.Items.Clear();'
            . '$jl.SaveAsync().GetAwaiter().GetResult() 2>$null'
        );
        return true;
    }

    public static function getRecentFiles(int $count = 10): array
    {
        if (!ComService::isWindows()) {
            return [];
        }
        $recentDir = ComService::powershell(
            '[Environment]::GetFolderPath("Recent")'
        );
        $recentDir = trim($recentDir);
        if (!$recentDir || !is_dir($recentDir)) {
            return [];
        }

        $files = [];
        $items = glob($recentDir . '\\*.lnk');
        usort($items, fn($a, $b) => filemtime($b) - filemtime($a));
        $items = array_slice($items, 0, $count);

        foreach ($items as $lnk) {
            $wsh = ComService::invariant();
            if (!$wsh) {
                continue;
            }
            try {
                $shortcut = $wsh->CreateShortcut($lnk);
                $files[] = [
                    'path' => $shortcut->TargetPath,
                    'name' => basename($shortcut->TargetPath),
                    'accessed' => date('c', filemtime($lnk)),
                    'arguments' => $shortcut->Arguments,
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $files;
    }
}
