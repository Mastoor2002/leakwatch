<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;

class PulseExposedCheck extends ExposedPathCheck
{
    public function key(): string
    {
        return 'pulse_exposed';
    }

    public function name(): string
    {
        return 'Laravel Pulse dashboard';
    }

    protected function paths(): array
    {
        return ['/pulse'];
    }

    protected function markers(): array
    {
        return ['Laravel Pulse', 'pulse::'];
    }

    protected function severity(): Severity
    {
        return Severity::Medium;
    }

    protected function title(string $path): string
    {
        return 'Laravel Pulse dashboard is publicly accessible';
    }

    protected function summary(): string
    {
        return 'Pulse reveals slow endpoints, busy users, exceptions and server load - a useful map for an attacker planning a denial-of-service or targeting specific users.';
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Define the `viewPulse` gate (in `AppServiceProvider::boot()`) so only admins can open `/pulse`.
2. Make sure production runs with `APP_ENV=production`.
TXT;
    }
}
