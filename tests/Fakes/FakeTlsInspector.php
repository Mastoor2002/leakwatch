<?php

namespace Tests\Fakes;

use App\Scanning\Contracts\TlsInspector;
use App\Scanning\Data\TlsCertificate;

class FakeTlsInspector implements TlsInspector
{
    public function __construct(public ?TlsCertificate $certificate = null)
    {
        //
    }

    public function inspect(string $host, string $ip, int $port = 443): ?TlsCertificate
    {
        return $this->certificate;
    }
}
