<?php

namespace App\Scanning\Checks;

use App\Scanning\ScanContext;

/**
 * Shared logic for the DNS-based email checks.
 */
abstract class EmailCheck extends BaseCheck
{
    /**
     * Reserved top-level domains that never have public DNS.
     *
     * @var array<int, string>
     */
    private const RESERVED_TLDS = ['test', 'localhost', 'local', 'invalid', 'example', 'internal'];

    public function requiresVerification(): bool
    {
        return false;
    }

    public function appliesTo(ScanContext $context): bool
    {
        $domain = $this->domain($context);

        if (filter_var($domain, FILTER_VALIDATE_IP) !== false || ! str_contains($domain, '.')) {
            return false;
        }

        $tld = substr($domain, (int) strrpos($domain, '.') + 1);

        return ! in_array($tld, self::RESERVED_TLDS, true);
    }

    protected function domain(ScanContext $context): string
    {
        return (string) preg_replace('/^www\./', '', $context->host());
    }
}
