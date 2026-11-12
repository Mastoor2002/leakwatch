<?php

namespace App\Scanning\Net;

use App\Scanning\Contracts\DnsResolver;

class SystemDnsResolver implements DnsResolver
{
    /**
     * @return array<int, string>
     */
    public function resolve(string $host): array
    {
        $ips = [];

        // gethostbynamel() uses the system resolver, so it also sees the hosts
        // file (this is how local *.test sites resolve under Herd/Valet).
        foreach (@gethostbynamel($host) ?: [] as $ip) {
            $ips[] = $ip;
        }

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        if ($ips === []) {
            foreach (@dns_get_record($host, DNS_A) ?: [] as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * @return array<int, string>
     */
    public function txt(string $name): array
    {
        $values = [];

        foreach (@dns_get_record($name, DNS_TXT) ?: [] as $record) {
            if (isset($record['entries']) && is_array($record['entries'])) {
                $values[] = implode('', $record['entries']);
            } elseif (isset($record['txt'])) {
                $values[] = (string) $record['txt'];
            }
        }

        return $values;
    }
}
