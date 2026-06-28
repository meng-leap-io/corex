<?php

declare(strict_types=1);

namespace App\Helpers;

use Carbon\Carbon;

class FormatHelper
{
    public static function bytesToHuman(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        if ($bytes === 0) {
            return '0 B';
        }

        $i = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 2).' '.$units[$i];
    }

    public static function timeAgo(Carbon $date): string
    {
        $diff = $date->diffInSeconds(now());

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);

            return $minutes === 1 ? '1 minute ago' : "{$minutes} minutes ago";
        }

        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);

            return $hours === 1 ? '1 hour ago' : "{$hours} hours ago";
        }

        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);

            return $days === 1 ? '1 day ago' : "{$days} days ago";
        }

        if ($diff < 2592000) {
            $weeks = (int) floor($diff / 604800);

            return $weeks === 1 ? '1 week ago' : "{$weeks} weeks ago";
        }

        if ($diff < 31536000) {
            $months = (int) floor($diff / 2592000);

            return $months === 1 ? '1 month ago' : "{$months} months ago";
        }

        $years = (int) floor($diff / 31536000);

        return $years === 1 ? '1 year ago' : "{$years} years ago";
    }

    public static function truncate(string $text, int $length = 100): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length).'...';
    }

    public static function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\w\s-]/u', '', $text);
        $text = preg_replace('/[\s_]+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);

        return trim($text, '-');
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return $email;
        }

        [$name, $domain] = $parts;
        $nameLength = mb_strlen($name);

        if ($nameLength <= 2) {
            $maskedName = $name[0].str_repeat('*', $nameLength - 1);
        } else {
            $maskedName = $name[0].str_repeat('*', $nameLength - 2).$name[$nameLength - 1];
        }

        $domainParts = explode('.', $domain);

        if (count($domainParts) >= 2) {
            $domainName = $domainParts[0];
            $domainNameLength = mb_strlen($domainName);

            if ($domainNameLength <= 2) {
                $maskedDomain = $domainName[0].str_repeat('*', $domainNameLength - 1);
            } else {
                $maskedDomain = $domainName[0].str_repeat('*', $domainNameLength - 2).$domainName[$domainNameLength - 1];
            }

            $domainParts[0] = $maskedDomain;
            $maskedDomain = implode('.', $domainParts);
        } else {
            $maskedDomain = $domain;
        }

        return $maskedName.'@'.$maskedDomain;
    }
}
