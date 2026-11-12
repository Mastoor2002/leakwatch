<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

class GitExposedCheck extends BaseCheck
{
    public function key(): string
    {
        return 'git_exposed';
    }

    public function name(): string
    {
        return 'Exposed .git folder';
    }

    public function run(ScanContext $context): CheckResult
    {
        $response = $context->fetch('/.git/HEAD');

        if (! $context->isRealHit($response)) {
            return CheckResult::passed();
        }

        $body = trim($response->body());

        if (! preg_match('/^(ref: refs\/|[0-9a-f]{40}$)/', $body)) {
            return CheckResult::passed();
        }

        return CheckResult::failed($this->finding(
            Severity::High,
            'The .git folder is publicly downloadable',
            'With access to /.git, tools like git-dumper can rebuild the full source code and history, including any secrets that were ever committed.',
            ['url' => $context->url('/.git/HEAD'), 'status' => $response->status()],
            '/.git',
        ));
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Block hidden folders in the web server (Nginx: `location ~ /\.(?!well-known).* { deny all; }`).
2. Point the document root at Laravel's `public/` folder so the repository isn't inside it.
3. Review the git history for committed secrets and rotate them.
TXT;
    }
}
