<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

class LaravelLogExposedCheck extends BaseCheck
{
    public function key(): string
    {
        return 'laravel_log_exposed';
    }

    public function name(): string
    {
        return 'Public laravel.log';
    }

    public function run(ScanContext $context): CheckResult
    {
        $response = $context->fetch('/storage/logs/laravel.log');

        if (! $context->isRealHit($response)) {
            return CheckResult::passed();
        }

        $lines = preg_match_all(
            '/^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*\]\s+\w+\.(?:EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG):/m',
            $response->body(),
        );

        if ($lines < 1) {
            return CheckResult::passed();
        }

        return CheckResult::failed($this->finding(
            Severity::High,
            'The Laravel log file is publicly readable',
            'storage/logs/laravel.log is reachable from the web. Logs contain stack traces, file paths and often personal data or tokens.',
            ['url' => $context->url('/storage/logs/laravel.log'), 'status' => $response->status(), 'log_entries_seen' => $lines],
            '/storage/logs/laravel.log',
        ));
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Point the web server's document root at `public/` - the storage folder must never be web-accessible.
2. Remove any symlink that exposes `storage/logs`.
3. Clear and rotate the log, and rotate any secrets that appeared in it.
TXT;
    }
}
