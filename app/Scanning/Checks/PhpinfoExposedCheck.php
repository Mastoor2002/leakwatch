<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;

class PhpinfoExposedCheck extends ExposedPathCheck
{
    public function key(): string
    {
        return 'phpinfo_exposed';
    }

    public function name(): string
    {
        return 'phpinfo() pages';
    }

    protected function paths(): array
    {
        return ['/phpinfo.php', '/info.php', '/php_info.php'];
    }

    protected function markers(): array
    {
        return ['phpinfo()', 'PHP Version'];
    }

    protected function severity(): Severity
    {
        return Severity::Medium;
    }

    protected function title(string $path): string
    {
        return "phpinfo() output is public at {$path}";
    }

    protected function summary(): string
    {
        return 'phpinfo() prints the exact PHP version, loaded extensions, file paths and every environment variable visible to PHP - which can include database credentials.';
    }

    public static function remediation(): string
    {
        return 'Delete the file from the server. If you need this information, run `php -i` over SSH instead.';
    }
}
