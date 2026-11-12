<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;

class LogViewerExposedCheck extends ExposedPathCheck
{
    public function key(): string
    {
        return 'log_viewer_exposed';
    }

    public function name(): string
    {
        return 'Log viewer dashboard';
    }

    protected function paths(): array
    {
        return ['/log-viewer', '/logs'];
    }

    protected function markers(): array
    {
        return ['window.LogViewer', 'vendor/log-viewer', 'Laravel log viewer', 'LogViewer'];
    }

    protected function severity(): Severity
    {
        return Severity::High;
    }

    protected function title(string $path): string
    {
        return "Application logs are browsable at {$path}";
    }

    protected function summary(): string
    {
        return 'Application logs contain stack traces, user emails, IP addresses and sometimes tokens or passwords. A public log viewer hands all of it to anyone.';
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. For opcodesio/log-viewer, set an authorization gate (`LogViewer::auth(...)`) or the `viewLogViewer` gate so only admins have access.
2. Remove log viewer packages from production if you don't need them there.
3. Rotate any secrets that appear in the logs.
TXT;
    }
}
