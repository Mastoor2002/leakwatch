<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

/**
 * Passive: requests http:// and expects a redirect to https://.
 */
class HttpsRedirectCheck extends BaseCheck
{
    public function key(): string
    {
        return 'https_redirect';
    }

    public function name(): string
    {
        return 'HTTPS enforcement';
    }

    public function requiresVerification(): bool
    {
        return false;
    }

    public function run(ScanContext $context): CheckResult
    {
        if (! $context->isHttps()) {
            return CheckResult::failed($this->finding(
                Severity::Medium,
                'Site is served without HTTPS',
                'Pages, logins and cookies travel unencrypted, so anyone on the same network (public Wi-Fi, ISP) can read or change them.',
                ['url' => $context->origin],
                'no-https',
            ));
        }

        $port = parse_url($context->origin, PHP_URL_PORT);

        if ($port !== null && $port !== 443) {
            return CheckResult::skipped('Site uses a non-standard HTTPS port.');
        }

        try {
            $response = $context->http->get('http://'.$context->host().'/');
        } catch (\Throwable) {
            return CheckResult::passed('Plain HTTP is not served at all.');
        }

        $location = $response->header('Location');

        if ($response->redirect() && str_starts_with(strtolower($location), 'https://')) {
            return CheckResult::passed('http:// redirects to https://.');
        }

        return CheckResult::failed($this->finding(
            Severity::Medium,
            'Plain HTTP does not redirect to HTTPS',
            'Visitors who type the address without https:// get an unencrypted page, and their session cookie may be sent in the clear.',
            ['url' => 'http://'.$context->host().'/', 'status' => $response->status(), 'location' => $location ?: null],
            'no-redirect',
        ));
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Redirect all HTTP traffic: Nginx `return 301 https://$host$request_uri;` in the port 80 server block (Forge does this when you enable SSL).
2. Then add the Strict-Transport-Security header so browsers never try HTTP again.
TXT;
    }
}
