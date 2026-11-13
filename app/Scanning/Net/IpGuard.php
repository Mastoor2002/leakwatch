<?php

namespace App\Scanning\Net;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Decides whether an IP address is private/internal and therefore off-limits.
 * This is the heart of Surfacewatch's SSRF protection.
 */
class IpGuard
{
    /**
     * @var array<int, string>
     */
    public const BLOCKED_RANGES = [
        // IPv4
        '0.0.0.0/8',        // "this" network
        '10.0.0.0/8',       // private
        '100.64.0.0/10',    // carrier-grade NAT
        '127.0.0.0/8',      // loopback
        '169.254.0.0/16',   // link-local, incl. cloud metadata 169.254.169.254
        '172.16.0.0/12',    // private
        '192.0.0.0/24',     // IETF protocol assignments
        '192.0.2.0/24',     // documentation
        '192.168.0.0/16',   // private
        '198.18.0.0/15',    // benchmarking
        '198.51.100.0/24',  // documentation
        '203.0.113.0/24',   // documentation
        '224.0.0.0/4',      // multicast
        '240.0.0.0/4',      // reserved, incl. 255.255.255.255
        // IPv6
        '::/128',           // unspecified
        '::1/128',          // loopback
        '64:ff9b:1::/48',   // NAT64 local-use prefix (RFC 8215)
        '100::/64',         // discard
        '2001:db8::/32',    // documentation
        'fc00::/7',         // unique local
        'fe80::/10',        // link-local
        'ff00::/8',         // multicast
    ];

    public static function isBlocked(string $ip): bool
    {
        $ip = trim($ip, '[] ');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        // IPv4-mapped (::ffff:127.0.0.1) and NAT64 (64:ff9b::a00:1) IPv6 addresses
        // carry an IPv4 address - judge that embedded address, so a NAT64 path to
        // an internal IPv4 is still blocked while one to a public IPv4 is allowed.
        $mapped = self::embeddedIpv4($ip);

        if ($mapped !== null) {
            return self::isBlocked($mapped);
        }

        return IpUtils::checkIp($ip, self::BLOCKED_RANGES);
    }

    private static function embeddedIpv4(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return null;
        }

        $packed = inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        $isMapped = substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff";

        // The well-known NAT64 prefix 64:ff9b::/96 (RFC 6052). DNS64 resolvers, such
        // as the one on Laravel Cloud, return these for IPv4-only hosts.
        $isNat64 = substr($packed, 0, 12) === hex2bin('0064ff9b0000000000000000');

        if ($isMapped || $isNat64) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return null;
    }
}
