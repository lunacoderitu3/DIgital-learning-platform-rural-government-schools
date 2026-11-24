<?php

namespace App\Services;

class SchoolUrlService
{
    public static function isLocalHost(?string $host): bool
    {
        $host = static::normalizeHost($host);

        return $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.localhost');
    }

    public static function rootHost(?string $host): string
    {
        $host = static::normalizeHost($host);

        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
            return 'localhost';
        }

        if (str_ends_with($host, '.localhost')) {
            return 'localhost';
        }

        $hostParts = explode('.', $host);

        if (count($hostParts) <= 2) {
            return $host;
        }

        return implode('.', array_slice($hostParts, 1));
    }

    public static function buildDefaultSchoolUrl(?string $schoolDomain, ?string $host = null, ?int $port = null): string
    {
        $schoolDomain = trim((string) ($schoolDomain ?? ''));
        if ($schoolDomain === '') {
            return '';
        }

        $host = $host ?? request()->getHost();
        $normalizedHost = static::normalizeHost($host);
        $port = $port ?? request()->getPort();

        if ($normalizedHost === '' || $normalizedHost === 'localhost' || $normalizedHost === '127.0.0.1' || str_ends_with($normalizedHost, '.localhost')) {
            $base = $schoolDomain . '.localhost';
            if ($port && !in_array($port, [80, 443], true)) {
                return 'http://' . $base . ':' . $port;
            }

            return 'http://' . $base;
        }

        $rootHost = static::rootHost($normalizedHost);

        return 'https://' . $schoolDomain . '.' . $rootHost;
    }

    protected static function normalizeHost(?string $host): string
    {
        $host = trim((string) ($host ?? ''));
        $host = preg_replace('/^https?:\/\//i', '', $host);
        $host = preg_replace('/^www\./i', '', $host);
        $host = preg_replace('/:\d+$/', '', $host);

        return strtolower((string) $host);
    }
}
