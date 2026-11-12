<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

/**
 * APP_DEBUG=true in production shows full error pages with file paths, stack
 * traces and sometimes environment values. It also exposes the old Ignition
 * endpoints that have had remote-code-execution bugs (CVE-2021-3129).
 */
class DebugModeCheck extends BaseCheck
{
    /**
     * Strings that only appear on a framework debug page, never on a normal
     * production error page.
     *
     * @var array<int, string>
     */
    public const DEBUG_MARKERS = [
        'vendor/laravel/framework',
        'vendor\\laravel\\framework',
        'Illuminate\\Routing\\',
        'Illuminate\\Foundation\\',
        'Symfony\\Component\\HttpKernel\\Exception',
        'Whoops\\Handler',
        'ignition-',
        '"trace":[',
    ];

    public function key(): string
    {
        return 'debug_mode';
    }

    public function name(): string
    {
        return 'Debug mode';
    }

    public function run(ScanContext $context): CheckResult
    {
        $findings = [];

        $ignition = $context->fetch('/_ignition/health-check', 'GET', ['Accept' => 'application/json']);

        if ($ignition !== null && $ignition->successful() && str_contains($ignition->body(), 'can_execute_commands')) {
            $findings[] = $this->finding(
                Severity::Critical,
                'Ignition debug endpoint is publicly reachable',
                'The /_ignition endpoints exist only when debug tooling is active. Older versions allowed remote code execution (CVE-2021-3129), and their presence means debug mode is on in production.',
                ['url' => $context->url('/_ignition/health-check'), 'status' => $ignition->status()],
                'ignition',
            );
        }

        // A POST to the homepage normally isn't allowed (405). In production that
        // is a plain error page; with APP_DEBUG=true it is a full stack trace.
        $error = $context->fetch('/', 'POST', ['Accept' => 'text/html']);

        if ($error !== null && $error->status() >= 400) {
            $markers = self::matchedMarkers($error->body());

            if ($markers !== []) {
                $findings[] = $this->finding(
                    Severity::Critical,
                    'Debug mode is on: error pages show stack traces',
                    'An error triggered by an ordinary request returned a detailed debug page with file paths and framework internals. Attackers use these pages to map the application and sometimes read configuration values.',
                    [
                        'url' => $context->url('/'),
                        'request' => 'POST / (empty body)',
                        'status' => $error->status(),
                        'markers_found' => $markers,
                    ],
                    'error_page',
                );
            }
        }

        return CheckResult::fromFindings($findings);
    }

    /**
     * @return array<int, string>
     */
    public static function matchedMarkers(string $body): array
    {
        return array_values(array_filter(
            self::DEBUG_MARKERS,
            fn (string $marker) => str_contains($body, $marker),
        ));
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. In the production `.env`, set `APP_DEBUG=false` and `APP_ENV=production`.
2. Run `php artisan config:cache` (or `php artisan optimize`) so the new values are used.
3. Remove debug packages from production installs: deploy with `composer install --no-dev`.
4. Check your error tracker still receives exceptions (Nightwatch, Sentry, Flare) - users should only see a generic error page.
TXT;
    }
}
