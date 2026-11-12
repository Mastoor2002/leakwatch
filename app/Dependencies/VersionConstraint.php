<?php

namespace App\Dependencies;

/**
 * Decides whether an installed version falls inside an advisory's affected range.
 *
 * Packagist advisories describe ranges like ">=1.0.0,<1.2.3|>=2.0.0,<2.0.4":
 * "|" means OR, "," (or a space) means AND. Small and dependency-free so it
 * is easy to read and test.
 */
class VersionConstraint
{
    public static function matches(string $version, string $constraint): bool
    {
        $version = self::normalize($version);

        if ($version === null) {
            return false;
        }

        foreach (explode('|', $constraint) as $alternative) {
            $alternative = trim($alternative);

            if ($alternative === '') {
                continue;
            }

            if (self::matchesAll($version, $alternative)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turn "v1.2", "1.2.3-beta1" or "1.2.3.0" into something version_compare
     * understands. Returns null for branches like "dev-main".
     */
    public static function normalize(string $version): ?string
    {
        $version = strtolower(trim($version));
        $version = ltrim($version, 'v');

        if ($version === '' || str_starts_with($version, 'dev-') || str_ends_with($version, '-dev') && ! preg_match('/^\d/', $version)) {
            return null;
        }

        if (! preg_match('/^(\d+(?:\.\d+){0,3})([-.+]?[a-z0-9.]*)?$/', $version, $matches)) {
            return null;
        }

        // Pad "8.0" to "8.0.0" - PHP's version_compare treats them as different.
        $numbers = explode('.', $matches[1]);

        while (count($numbers) < 3) {
            $numbers[] = '0';
        }

        return implode('.', $numbers).($matches[2] ?? '');
    }

    private static function matchesAll(string $version, string $rangeSet): bool
    {
        $parts = preg_split('/\s*,\s*|\s+(?=[<>=!])/', trim($rangeSet)) ?: [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || $part === '*') {
                continue;
            }

            if (! preg_match('/^(>=|<=|>|<|==|=|!=)?\s*(.+)$/', $part, $matches)) {
                return false;
            }

            $operator = $matches[1] === '' ? '==' : ($matches[1] === '=' ? '==' : $matches[1]);
            $bound = self::normalize($matches[2]);

            if ($bound === null || ! version_compare($version, $bound, $operator)) {
                return false;
            }
        }

        return true;
    }
}
