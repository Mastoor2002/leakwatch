<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

/**
 * Passive: checks the Set-Cookie flags of the session and XSRF cookies.
 */
class CookieFlagsCheck extends BaseCheck
{
    public function key(): string
    {
        return 'cookie_flags';
    }

    public function name(): string
    {
        return 'Cookie security flags';
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
        $findings = [];

        foreach ($context->homepage->cookies() as $cookie) {
            $isSession = str_ends_with($cookie['name'], '_session');
            $isXsrf = $cookie['name'] === 'XSRF-TOKEN';

            if (! $isSession && ! $isXsrf) {
                continue;
            }

            $missing = [];

            if ($context->isHttps() && ! $cookie['secure']) {
                $missing[] = 'Secure';
            }

            // XSRF-TOKEN is meant to be readable by JavaScript, so HttpOnly is not expected on it.
            if ($isSession && ! $cookie['httponly']) {
                $missing[] = 'HttpOnly';
            }

            if ($cookie['samesite'] === null || $cookie['samesite'] === 'none' && ! $cookie['secure']) {
                $missing[] = 'SameSite';
            }

            if ($missing === []) {
                continue;
            }

            $findings[] = $this->finding(
                $isSession ? Severity::Medium : Severity::Low,
                "Cookie {$cookie['name']} is missing ".implode(', ', $missing),
                $isSession
                    ? 'The session cookie identifies a logged-in user. Without these flags it can be stolen by injected JavaScript (HttpOnly), sent over plain HTTP (Secure) or used in cross-site attacks (SameSite).'
                    : 'The CSRF cookie should only travel over HTTPS and stay on your own site.',
                ['cookie' => $cookie['name'], 'missing_flags' => $missing],
                $cookie['name'],
            );
        }

        return CheckResult::fromFindings($findings);
    }

    public static function remediation(): string
    {
        return <<<'TXT'
In the production `.env`:

    SESSION_SECURE_COOKIE=true
    SESSION_HTTP_ONLY=true
    SESSION_SAME_SITE=lax

Then run `php artisan config:cache`. These map to `config/session.php`.
TXT;
    }
}
