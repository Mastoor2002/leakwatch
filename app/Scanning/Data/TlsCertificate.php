<?php

namespace App\Scanning\Data;

use Carbon\CarbonImmutable;

readonly class TlsCertificate
{
    /**
     * @param  array<int, string>  $altNames
     */
    public function __construct(
        public string $subject,
        public string $issuer,
        public CarbonImmutable $validFrom,
        public CarbonImmutable $validTo,
        public array $altNames,
        public bool $trusted,
        public ?string $error = null,
    ) {
        //
    }

    public function daysRemaining(): int
    {
        return (int) floor(CarbonImmutable::now()->diffInDays($this->validTo, false));
    }

    public function isExpired(): bool
    {
        return $this->validTo->isPast();
    }
}
