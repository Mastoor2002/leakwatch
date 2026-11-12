<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Contracts\Check;
use App\Scanning\Data\FindingData;
use App\Scanning\ScanContext;

/**
 * Shared defaults for checks. Path probes require verification by default;
 * passive checks (homepage headers, DNS, TLS) override requiresVerification().
 */
abstract class BaseCheck implements Check
{
    public function requiresVerification(): bool
    {
        return true;
    }

    public function appliesTo(ScanContext $context): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    protected function finding(Severity $severity, string $title, string $summary, array $evidence = [], string $detail = ''): FindingData
    {
        return new FindingData($severity, $title, $summary, $evidence, $detail);
    }
}
