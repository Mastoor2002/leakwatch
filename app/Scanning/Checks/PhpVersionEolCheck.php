<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;
use Carbon\CarbonImmutable;

/**
 * Reads the X-Powered-By header from the homepage (passive).
 */
class PhpVersionEolCheck extends BaseCheck
{
    public function key(): string
    {
        return 'php_version_eol';
    }

    public function name(): string
    {
        return 'PHP version';
    }

    public function requiresVerification(): bool
    {
        return false;
    }

    public function appliesTo(ScanContext $context): bool
    {
        return $context->homepage !== null;
    }

    public function run(ScanContext $context): CheckResult
    {
        $poweredBy = (string) $context->homepage->header('x-powered-by');

        if (! preg_match('/PHP\/(\d+)\.(\d+)(?:\.(\d+))?/i', $poweredBy, $matches)) {
            return CheckResult::passed('PHP version not disclosed.');
        }

        $branch = "{$matches[1]}.{$matches[2]}";
        $version = $branch.(isset($matches[3]) ? ".{$matches[3]}" : '');
        $findings = [];

        $findings[] = $this->finding(
            Severity::Low,
            'Server reveals its exact PHP version',
            "The X-Powered-By header announces PHP {$version}. This helps attackers pick exploits for your exact version.",
            ['header' => 'X-Powered-By: '.$poweredBy],
            'header',
        );

        // Read the whole map: "8.1" contains a dot, so config("leakwatch.php_eol.8.1") would not work.
        $eol = ((array) config('leakwatch.php_eol', []))[$branch] ?? null;

        if ($eol !== null && CarbonImmutable::parse($eol)->isPast()) {
            $findings[] = $this->finding(
                Severity::Medium,
                "PHP {$branch} no longer receives security fixes",
                "PHP {$branch} reached end of life on {$eol}. New vulnerabilities in it will never be patched.",
                ['version' => $version, 'end_of_life' => $eol],
                "eol-{$branch}",
            );
        }

        return CheckResult::fromFindings($findings);
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Hide the header: set `expose_php = Off` in php.ini and restart PHP-FPM.
2. If the version is end-of-life, upgrade to a supported PHP release (see php.net/supported-versions) and run your test suite.
TXT;
    }
}
