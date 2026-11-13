<?php

use App\Scanning\Checks;

return [

    /*
    |--------------------------------------------------------------------------
    | Private targets (SSRF protection)
    |--------------------------------------------------------------------------
    |
    | The scanner refuses to connect to private, loopback and link-local
    | addresses. The only way to scan a local demo site is to set this to
    | true, and it is ignored unless APP_ENV=local.
    |
    */

    'allow_private_targets' => (bool) env('LEAKWATCH_ALLOW_PRIVATE_TARGETS', false),

    /*
    |--------------------------------------------------------------------------
    | Email verification
    |--------------------------------------------------------------------------
    |
    | Accounts must verify their email address by default. Set this to false
    | for demo deployments that have no outgoing mail configured, so new
    | accounts can sign in straight away.
    |
    */

    'require_email_verification' => (bool) env('LEAKWATCH_REQUIRE_EMAIL_VERIFICATION', true),

    /*
    |--------------------------------------------------------------------------
    | Scan driver
    |--------------------------------------------------------------------------
    |
    | "queue"  - a queued RunScan job runs every check (production).
    | "inline" - the site page runs one check per poll request, so scans work
    |            on a laptop without a queue worker running (local default).
    |
    */

    'scan_driver' => env('LEAKWATCH_SCAN_DRIVER', 'inline'),

    'http' => [
        'user_agent' => env('LEAKWATCH_USER_AGENT', 'LeakwatchBot/0.1 (+'.rtrim((string) env('APP_URL', 'http://localhost'), '/').'/bot)'),
        'timeout' => (int) env('LEAKWATCH_HTTP_TIMEOUT', 10),
        'max_requests_per_check' => 15,
        'max_body_bytes' => 2 * 1024 * 1024,
        'delay_ms' => (int) env('LEAKWATCH_REQUEST_DELAY_MS', 100),
        'allowed_ports' => [80, 443, 8080, 8443],
    ],

    /*
    |--------------------------------------------------------------------------
    | Checks
    |--------------------------------------------------------------------------
    |
    | Every check that runs during a scan, in order. Adding a check = writing
    | one class that implements App\Scanning\Contracts\Check and adding it here.
    |
    */

    'checks' => [
        Checks\FrameworkDetectCheck::class,
        Checks\EnvExposedCheck::class,
        Checks\DebugModeCheck::class,
        Checks\DocrootMisconfiguredCheck::class,
        Checks\GitExposedCheck::class,
        Checks\LaravelLogExposedCheck::class,
        Checks\TelescopeExposedCheck::class,
        Checks\HorizonExposedCheck::class,
        Checks\PulseExposedCheck::class,
        Checks\LogViewerExposedCheck::class,
        Checks\PhpinfoExposedCheck::class,
        Checks\PhpVersionEolCheck::class,
        Checks\TlsCertificateCheck::class,
        Checks\HttpsRedirectCheck::class,
        Checks\SecurityHeadersCheck::class,
        Checks\CookieFlagsCheck::class,
        Checks\EmailSpfCheck::class,
        Checks\EmailDmarcCheck::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring
    |--------------------------------------------------------------------------
    */

    'score_penalties' => [
        'critical' => 40,
        'high' => 20,
        'medium' => 8,
        'low' => 2,
        'info' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | PHP end-of-life dates (security support end)
    |--------------------------------------------------------------------------
    |
    | Keep in sync with https://www.php.net/supported-versions.php
    |
    */

    'php_eol' => [
        '5.6' => '2018-12-31',
        '7.0' => '2019-01-10',
        '7.1' => '2019-12-01',
        '7.2' => '2020-11-30',
        '7.3' => '2021-12-06',
        '7.4' => '2022-11-28',
        '8.0' => '2023-11-26',
        '8.1' => '2025-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
        '8.5' => '2029-12-31',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership verification
    |--------------------------------------------------------------------------
    */

    'verification' => [
        'dns_prefix' => 'leakwatch-verification=',
        'file_path' => '/.well-known/leakwatch-verification.txt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dependency advisories
    |--------------------------------------------------------------------------
    */

    'advisories' => [
        'endpoint' => 'https://packagist.org/api/security-advisories/',
        'batch_size' => 50,
        'cache_minutes' => 360,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    */

    'certificate_warning_days' => 14,

    /*
    |--------------------------------------------------------------------------
    | AI explanations (optional)
    |--------------------------------------------------------------------------
    |
    | When an Anthropic API key is set, findings get an "Explain with AI"
    | button. Without a key, the static remediation text is shown instead.
    |
    */

    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('LEAKWATCH_AI_MODEL', 'claude-sonnet-5-5'),
        'endpoint' => 'https://api.anthropic.com/v1/messages',
        'max_tokens' => 700,
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo
    |--------------------------------------------------------------------------
    |
    | Token used by the demo seeder for the local "vulnerable.test" site, so the
    | demo app in demo/vulnerable-app can answer the verification file check.
    |
    */

    'demo_verification_token' => env('LEAKWATCH_DEMO_TOKEN', 'lw-demo-6f1c2b8e9a4d4c1f'),

];
