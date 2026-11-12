<?php

namespace App\Enums;

enum VerificationMethod: string
{
    case Dns = 'dns';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Dns => 'DNS TXT record',
            self::File => 'Verification file',
        };
    }
}
