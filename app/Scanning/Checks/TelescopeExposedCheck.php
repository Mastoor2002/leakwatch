<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;

class TelescopeExposedCheck extends ExposedPathCheck
{
    public function key(): string
    {
        return 'telescope_exposed';
    }

    public function name(): string
    {
        return 'Laravel Telescope dashboard';
    }

    protected function paths(): array
    {
        return ['/telescope'];
    }

    protected function markers(): array
    {
        return ['window.Telescope', 'vendor/telescope/app.js'];
    }

    protected function severity(): Severity
    {
        return Severity::High;
    }

    protected function title(string $path): string
    {
        return 'Laravel Telescope is publicly accessible';
    }

    protected function summary(): string
    {
        return 'Telescope records every request, query, job, mail and exception - including headers, session data and sometimes passwords submitted in forms. Anyone who can open it can read that history.';
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Telescope's dashboard is only protected when `APP_ENV` is not `local`. Make sure production has `APP_ENV=production`.
2. Restrict access in `app/Providers/TelescopeServiceProvider.php` `gate()` to named admin emails.
3. Better: only install Telescope in development (`composer require laravel/telescope --dev`) or set `TELESCOPE_ENABLED=false` in production.
4. Prune recorded data: `php artisan telescope:prune`.
TXT;
    }
}
