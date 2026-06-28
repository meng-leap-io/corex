<?php

declare(strict_types=1);

namespace App\Services\Windows;

class ToastNotificationService
{
    public static function send(string $title, string $message, array $options = []): bool
    {
        if (! ComService::isWindows()) {
            return false;
        }

        $silent = $options['silent'] ?? false;
        $icon = $options['icon'] ?? 'Info';
        $duration = $options['duration'] ?? 'short';

        $xml = '<toast duration="'.$duration.'">'
            .'<visual><binding template="ToastGeneric">'
            .'<text>'.self::escapeXml($title).'</text>'
            .'<text>'.self::escapeXml($message).'</text>';
        if (! $silent) {
            $xml .= '<audio src="ms-winsoundevent:Notification.Default"/>';
        }
        $xml .= '</binding></visual></toast>';

        $xmlPath = sys_get_temp_dir().'\\corex-toast-'.bin2hex(random_bytes(4)).'.xml';
        file_put_contents($xmlPath, $xml);

        $script = '[Windows.UI.Notifications.ToastNotificationManager,'
            .'Windows.UI.Notifications,'
            .'ContentType = WindowsRuntime]::CreateToastNotifier("Corex")'
            .'.Show((New-Object Windows.UI.Notifications.ToastNotification'
            .'(New-Object Windows.Data.Xml.Dom.XmlDocument)));'
            .'(New-Object Windows.Data.Xml.Dom.XmlDocument).LoadXml((Get-Content '
            .escapeshellarg($xmlPath).' -Raw))';

        ComService::powershell(
            'Add-Type -AssemblyName System.Runtime.WindowsRuntime;'
            .'$null = [Windows.UI.Notifications.ToastNotificationManager,'
            .'Windows.UI.Notifications,ContentType=WindowsRuntime];'
            .'$template = [Windows.UI.Notifications.ToastNotificationManager]'
            .'::GetTemplateContent([Windows.UI.Notifications.ToastTemplateType]::ToastText02);'
            .'$toast = [Windows.UI.Notifications.ToastNotification]::new($template);'
            .'[Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier("Corex").Show($toast)'
            .' 2>$null'
        );

        @unlink($xmlPath);

        return true;
    }

    public static function sendWithActions(string $title, string $message, array $actions = []): bool
    {
        if (! ComService::isWindows()) {
            return false;
        }

        $xml = '<toast><visual><binding template="ToastGeneric">'
            .'<text>'.self::escapeXml($title).'</text>'
            .'<text>'.self::escapeXml($message).'</text>'
            .'</binding></visual><actions>';

        foreach ($actions as $action) {
            $xml .= '<action content="'.self::escapeXml($action['label'] ?? '')
                .'" arguments="'.self::escapeXml($action['arguments'] ?? '')
                .'" activationType="'.($action['foreground'] ?? 'foreground').'"/>';
        }

        $xml .= '</actions></toast>';

        $tempPath = sys_get_temp_dir().'\\corex-toast-'.bin2hex(random_bytes(4)).'.xml';
        file_put_contents($tempPath, $xml);

        ComService::powershell(
            'Add-Type -AssemblyName System.Runtime.WindowsRuntime;'
            .'$xml = New-Object Windows.Data.Xml.Dom.XmlDocument;'
            .'$xml.LoadXml((Get-Content '.escapeshellarg($tempPath).' -Raw));'
            .'$toast = [Windows.UI.Notifications.ToastNotification]::new($xml);'
            .'[Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier("Corex").Show($toast)'
            .' 2>$null'
        );

        @unlink($tempPath);

        return true;
    }

    public static function clear(): void
    {
        if (! ComService::isWindows()) {
            return;
        }
        ComService::powershell(
            '[Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier("Corex").Hide() 2>$null'
        );
    }

    private static function escapeXml(string $str): string
    {
        return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
