<?php

namespace App\Scanning\Contracts;

use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

/**
 * One security check. Each check lives in its own class in app/Scanning/Checks
 * and is registered in config/leakwatch.php.
 */
interface Check
{
    /**
     * Stable machine name, e.g. "env_exposed". Stored with findings.
     */
    public function key(): string;

    /**
     * Human name shown in the scan progress list.
     */
    public function name(): string;

    /**
     * Path probes (requesting URLs other than the homepage) must only run on
     * sites whose ownership has been verified.
     */
    public function requiresVerification(): bool;

    /**
     * Whether the check makes sense for this site (e.g. TLS only for https).
     */
    public function appliesTo(ScanContext $context): bool;

    public function run(ScanContext $context): CheckResult;

    /**
     * Plain-English fix shown with every finding from this check.
     */
    public static function remediation(): string;
}
