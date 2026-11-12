<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

/**
 * Passive: inspects the homepage response headers.
 */
class SecurityHeadersCheck extends BaseCheck
{
    public function key(): string
    {
        return 'security_headers';
    }

    public function name(): string
    {
        return 'Security headers';
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
        $page = $context->homepage;
        $findings = [];

        if ($context->isHttps() && ! $page->hasHeader('strict-transport-security')) {
            $findings[] = $this->finding(Severity::Low, 'Missing Strict-Transport-Security header',
                'Without HSTS, browsers may still try plain HTTP first, which allows downgrade attacks.',
                ['header' => 'Strict-Transport-Security'], 'hsts');
        }

        if (strtolower((string) $page->header('x-content-type-options')) !== 'nosniff') {
            $findings[] = $this->finding(Severity::Low, 'Missing X-Content-Type-Options: nosniff',
                'Browsers may guess ("sniff") file types, which can turn an uploaded file into executable script.',
                ['header' => 'X-Content-Type-Options'], 'nosniff');
        }

        $csp = (string) $page->header('content-security-policy');

        if (! $page->hasHeader('x-frame-options') && ! str_contains(strtolower($csp), 'frame-ancestors')) {
            $findings[] = $this->finding(Severity::Low, 'Site can be embedded in other sites (clickjacking)',
                'Without X-Frame-Options or a CSP frame-ancestors rule, another site can load yours in an invisible frame and trick users into clicking buttons.',
                ['header' => 'X-Frame-Options / CSP frame-ancestors'], 'framing');
        }

        if (! $page->hasHeader('referrer-policy')) {
            $findings[] = $this->finding(Severity::Low, 'Missing Referrer-Policy header',
                'Full URLs (which can include tokens or IDs) may leak to third-party sites through the Referer header.',
                ['header' => 'Referrer-Policy'], 'referrer');
        }

        return CheckResult::fromFindings($findings);
    }

    public static function remediation(): string
    {
        return <<<'TXT'
Add the headers once, for every response. In Laravel, a small middleware appended to the `web` group works well:

    $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

Or set them in the Nginx server block with `add_header ... always;`.
TXT;
    }
}
