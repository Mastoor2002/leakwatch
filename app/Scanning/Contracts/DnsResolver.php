<?php

namespace App\Scanning\Contracts;

/**
 * DNS lookups live behind an interface so tests can fake them.
 */
interface DnsResolver
{
    /**
     * All IPv4 and IPv6 addresses for a host name.
     *
     * @return array<int, string>
     */
    public function resolve(string $host): array;

    /**
     * TXT records for a name.
     *
     * @return array<int, string>
     */
    public function txt(string $name): array;
}
