<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;

class HorizonExposedCheck extends ExposedPathCheck
{
    public function key(): string
    {
        return 'horizon_exposed';
    }

    public function name(): string
    {
        return 'Laravel Horizon dashboard';
    }

    protected function paths(): array
    {
        return ['/horizon'];
    }

    protected function markers(): array
    {
        return ['window.Horizon', 'vendor/horizon/app.js'];
    }

    protected function severity(): Severity
    {
        return Severity::High;
    }

    protected function title(string $path): string
    {
        return 'Laravel Horizon dashboard is publicly accessible';
    }

    protected function summary(): string
    {
        return 'Horizon shows every queued job and its payload (often customer emails, IDs and tokens) and lets visitors retry or delete jobs.';
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Make sure production uses `APP_ENV=production` - Horizon is open to everyone only in the local environment.
2. Define the `viewHorizon` gate in `app/Providers/HorizonServiceProvider.php` to allow only specific admin users.
3. Consider putting `/horizon` behind your VPN or an IP allow-list.
TXT;
    }
}
