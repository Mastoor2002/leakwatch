<?php

namespace Tests\Fakes;

use App\Scanning\Contracts\DnsResolver;

class FakeDnsResolver implements DnsResolver
{
    /**
     * @param  array<string, array<int, string>>  $addresses  host => IPs
     * @param  array<string, array<int, string>>  $txtRecords  name => records
     */
    public function __construct(
        public array $addresses = [],
        public array $txtRecords = [],
    ) {
        //
    }

    public function resolve(string $host): array
    {
        return $this->addresses[strtolower($host)] ?? [];
    }

    public function txt(string $name): array
    {
        return $this->txtRecords[strtolower($name)] ?? [];
    }
}
