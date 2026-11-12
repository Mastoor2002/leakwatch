<?php

namespace App\Scanning\Contracts;

use App\Scanning\Data\TlsCertificate;

interface TlsInspector
{
    /**
     * Read the certificate the server presents for $host when we connect to $ip.
     * Returns null when no TLS connection could be made at all.
     */
    public function inspect(string $host, string $ip, int $port = 443): ?TlsCertificate;
}
