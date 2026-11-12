<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

/**
 * Detects a web server pointing at the project root instead of public/.
 * Then every project file (composer.json, artisan, .env, storage) is downloadable.
 */
class DocrootMisconfiguredCheck extends BaseCheck
{
    public function key(): string
    {
        return 'docroot_misconfigured';
    }

    public function name(): string
    {
        return 'Document root configuration';
    }

    public function run(ScanContext $context): CheckResult
    {
        $evidence = [];

        $composer = $context->fetch('/composer.json');

        if ($context->isRealHit($composer)) {
            $json = json_decode($composer->body(), true);

            if (is_array($json) && isset($json['require']) && is_array($json['require'])) {
                $evidence['composer_json'] = $context->url('/composer.json');
                $evidence['laravel_project'] = array_key_exists('laravel/framework', $json['require']);
            }
        }

        $artisan = $context->fetch('/artisan');

        if ($context->isRealHit($artisan) && (str_contains($artisan->body(), 'LARAVEL_START') || str_contains($artisan->body(), 'Illuminate\\'))) {
            $evidence['artisan'] = $context->url('/artisan');
        }

        if ($evidence === []) {
            return CheckResult::passed();
        }

        return CheckResult::failed($this->finding(
            Severity::Critical,
            'Project files are served from the web root',
            'Files that live outside public/ (composer.json, artisan) can be downloaded. This usually means the web server points at the project folder, so .env, logs and source code are one URL away.',
            $evidence,
            'docroot',
        ));
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Change the web server's document root to the project's `public/` directory (Nginx `root /var/www/app/public;`, Apache `DocumentRoot /var/www/app/public`).
2. On shared hosting that can't change the root, move everything except `public/` above the web folder and update the paths in `public/index.php`.
3. After fixing, re-scan and rotate any secrets from `.env`.
TXT;
    }
}
