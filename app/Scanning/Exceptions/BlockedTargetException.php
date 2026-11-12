<?php

namespace App\Scanning\Exceptions;

use RuntimeException;

/**
 * Thrown when a URL points somewhere the scanner must never connect to
 * (private network, loopback, cloud metadata, odd ports or schemes).
 */
class BlockedTargetException extends RuntimeException
{
    public static function privateAddress(string $host, string $ip): self
    {
        return new self("Blocked: {$host} resolves to a private or internal address ({$ip}).");
    }

    public static function unresolvable(string $host): self
    {
        return new self("Could not resolve {$host}.");
    }

    public static function invalidUrl(string $url, string $reason): self
    {
        return new self("Blocked URL {$url}: {$reason}");
    }
}
