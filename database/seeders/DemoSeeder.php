<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Dependencies\VersionConstraint;
use App\Enums\FindingStatus;
use App\Enums\Severity;
use App\Models\Advisory;
use App\Models\Client;
use App\Models\Scan;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Scanning\Checks\HttpsRedirectCheck;
use App\Scanning\Contracts\Check;
use App\Scanning\Data\CheckResult;
use App\Scanning\Data\FindingData;
use App\Scanning\Scoring;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data so the dashboard looks alive: an agency with three clients and
 * six sites, realistic findings and vulnerable packages, plus the local
 * "vulnerable.test" demo site ready to be verified and scanned for real.
 *
 * Login: demo@leakwatch.test / password
 *
 * All example.com hosts and findings are fictional demo data.
 */
class DemoSeeder extends Seeder
{
    public function run(CreateTeam $createTeam): void
    {
        DB::transaction(function () use ($createTeam) {
            $user = User::where('email', 'demo@leakwatch.test')->first()
                ?? User::factory()->create([
                    'name' => 'Demo User',
                    'email' => 'demo@leakwatch.test',
                ]);

            $team = Team::where('name', 'Northwind Digital')->first()
                ?? $createTeam->handle($user, 'Northwind Digital');

            $user->switchTeam($team);

            if ($team->sites()->exists()) {
                return;
            }

            $bakery = $team->clients()->create(['name' => 'Bluebird Bakery', 'contact_email' => 'owner@bluebird.example.com', 'notes' => 'Online shop on Laravel 10, hosted on a shared VPS.']);
            $legal = $team->clients()->create(['name' => 'Harbor Legal', 'contact_email' => 'it@harborlegal.example.com']);
            $fitness = $team->clients()->create(['name' => 'Peak Fitness', 'contact_email' => 'ops@peakfitness.example.com']);
            $demo = $team->clients()->create(['name' => 'Leakwatch demo', 'notes' => 'Local deliberately-vulnerable app in demo/vulnerable-app. Never deploy it.']);

            $shop = $this->site($team, $bakery, 'https://shop.bluebird.example.com', true, [
                ['env_exposed', Severity::Critical, 'Environment file publicly readable at /.env', '/.env', ['url' => 'https://shop.bluebird.example.com/.env', 'status' => 200, 'variable_count' => 34, 'sensitive_variables_present' => ['APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'STRIPE_SECRET'], 'note' => 'Values were not stored. Treat every secret in this file as compromised.']],
                ['debug_mode', Severity::Critical, 'Debug mode is on: error pages show stack traces', 'error_page', ['url' => 'https://shop.bluebird.example.com/', 'request' => 'POST / (empty body)', 'status' => 405, 'markers_found' => ['vendor/laravel/framework', 'Illuminate\\Routing\\']]],
                ['cookie_flags', Severity::Medium, 'Cookie laravel_session is missing Secure', 'laravel_session', ['cookie' => 'laravel_session', 'missing_flags' => ['Secure']]],
                ['security_headers', Severity::Low, 'Missing Strict-Transport-Security header', 'hsts', ['header' => 'Strict-Transport-Security']],
                ['security_headers', Severity::Low, 'Site can be embedded in other sites (clickjacking)', 'framing', ['header' => 'X-Frame-Options / CSP frame-ancestors']],
            ]);

            $this->site($team, $bakery, 'https://bluebird.example.com', false, [
                ['security_headers', Severity::Low, 'Missing X-Content-Type-Options: nosniff', 'nosniff', ['header' => 'X-Content-Type-Options']],
                ['security_headers', Severity::Low, 'Missing Referrer-Policy header', 'referrer', ['header' => 'Referrer-Policy']],
                ['email_dmarc', Severity::Low, 'DMARC policy for bluebird.example.com is monitor-only (p=none)', 'p-none', ['domain' => 'bluebird.example.com', 'record' => 'v=DMARC1; p=none']],
            ]);

            $portal = $this->site($team, $legal, 'https://portal.harborlegal.example.com', true, [
                ['telescope_exposed', Severity::High, 'Laravel Telescope is publicly accessible', '/telescope', ['url' => 'https://portal.harborlegal.example.com/telescope', 'status' => 200, 'markers_found' => ['window.Telescope']]],
                ['tls_certificate', Severity::Medium, 'TLS certificate expires in 12 days', 'expiring', ['subject' => 'portal.harborlegal.example.com', 'issuer' => "Let's Encrypt", 'expires_at' => now()->addDays(12)->toDateString(), 'days_remaining' => 12]],
                ['php_version_eol', Severity::Low, 'Server reveals its exact PHP version', 'header', ['header' => 'X-Powered-By: PHP/8.2.12']],
            ]);

            $this->site($team, $legal, 'https://harborlegal.example.com', false, []);

            $app = $this->site($team, $fitness, 'https://app.peakfitness.example.com', true, [
                ['horizon_exposed', Severity::High, 'Laravel Horizon dashboard is publicly accessible', '/horizon', ['url' => 'https://app.peakfitness.example.com/horizon', 'status' => 200, 'markers_found' => ['window.Horizon']]],
                ['php_version_eol', Severity::Medium, 'PHP 8.1 no longer receives security fixes', 'eol-8.1', ['version' => '8.1.27', 'end_of_life' => '2025-12-31']],
                ['php_version_eol', Severity::Low, 'Server reveals its exact PHP version', 'header', ['header' => 'X-Powered-By: PHP/8.1.27']],
            ]);

            $team->sites()->create([
                'client_id' => $demo->id,
                'url' => 'http://vulnerable.test',
                'host' => 'vulnerable.test',
                'verification_token' => config('leakwatch.demo_verification_token'),
                'scan_frequency' => 'off',
            ]);

            $this->packages($shop, '10.48.10', [
                ['laravel/framework', '10.48.10'], ['livewire/livewire', '3.5.0'], ['guzzlehttp/psr7', '2.6.2'], ['laravel/sanctum', '3.3.3'],
            ]);
            $this->packages($portal, '11.9.2', [
                ['laravel/framework', '11.9.2'], ['guzzlehttp/psr7', '2.4.1'], ['laravel/telescope', '5.0.5'],
            ]);
            $this->packages($app, '11.31.0', [
                ['laravel/framework', '11.31.0'], ['livewire/livewire', '3.6.4'], ['laravel/horizon', '5.29.1'],
            ]);
        });
    }

    /**
     * @param  array<int, array{0: string, 1: Severity, 2: string, 3: string, 4: array<string, mixed>}>  $findings
     */
    private function site(Team $team, Client $client, string $url, bool $laravel, array $findings): Site
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        $site = $team->sites()->create([
            'client_id' => $client->id,
            'url' => $url,
            'host' => $host,
            'verified_at' => now()->subDays(20),
            'verification_method' => 'dns',
            'verification_checked_at' => now()->subDays(2),
            'is_laravel' => $laravel,
        ]);

        // An older scan with an extra issue that has since been fixed, so history and "resolved" show up.
        $old = $site->scans()->create([
            'status' => 'completed', 'trigger' => 'scheduled',
            'started_at' => now()->subDays(7), 'finished_at' => now()->subDays(7)->addSeconds(19),
            'new_findings_count' => count($findings) + 1,
        ]);

        $site->findings()->create([
            'check_key' => 'https_redirect', 'fingerprint' => hash('sha256', 'https_redirect|no-redirect'),
            'severity' => Severity::Medium, 'title' => 'Plain HTTP does not redirect to HTTPS',
            'summary' => 'Visitors who type the address without https:// get an unencrypted page.',
            'evidence' => ['url' => "http://{$host}/", 'status' => 200], 'remediation' => HttpsRedirectCheck::remediation(),
            'status' => FindingStatus::Resolved, 'resolved_at' => now()->subDays(1),
            'first_seen_scan_id' => $old->id, 'last_seen_scan_id' => $old->id,
        ]);

        $scan = $site->scans()->create([
            'status' => 'completed', 'trigger' => 'manual',
            'started_at' => now()->subHours(3), 'finished_at' => now()->subHours(3)->addSeconds(23),
            'new_findings_count' => 0, 'resolved_findings_count' => 1,
        ]);

        $failedByCheck = [];

        foreach ($findings as [$checkKey, $severity, $title, $detail, $evidence]) {
            $class = $this->checkClass($checkKey);

            $site->findings()->create([
                'check_key' => $checkKey,
                'fingerprint' => hash('sha256', $checkKey.'|'.$detail),
                'severity' => $severity,
                'title' => $title,
                'summary' => $this->summaryFor($checkKey),
                'evidence' => $evidence,
                'remediation' => $class::remediation(),
                'status' => FindingStatus::Open,
                'first_seen_scan_id' => $old->id,
                'last_seen_scan_id' => $scan->id,
                'created_at' => now()->subDays(7),
            ]);

            $failedByCheck[$checkKey][] = new FindingData($severity, $title, $this->summaryFor($checkKey), $evidence, $detail);
        }

        foreach (array_values(config('leakwatch.checks')) as $position => $class) {
            /** @var Check $check */
            $check = app($class);
            $result = isset($failedByCheck[$check->key()])
                ? CheckResult::fromFindings($failedByCheck[$check->key()])
                : CheckResult::passed();

            $scan->checkRuns()->create([
                'check_key' => $check->key(),
                'check_class' => $class,
                'position' => $position,
                'status' => $result->status,
                'result' => $result->toArray(),
                'duration_ms' => random_int(40, 900),
            ]);
        }

        $score = Scoring::calculate(array_map(fn ($finding) => $finding[1], $findings));
        $scan->update($score);
        $old->update(Scoring::calculate([...array_map(fn ($finding) => $finding[1], $findings), Severity::Medium]));

        $site->forceFill($score + ['last_scan_id' => $scan->id, 'last_scanned_at' => $scan->finished_at])->save();

        return $site;
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $packages
     */
    private function packages(Site $site, string $laravelVersion, array $packages): void
    {
        $snapshot = $site->dependencySnapshots()->create([
            'lock_hash' => hash('sha256', $site->host),
            'php_version' => '8.2.12',
            'laravel_version' => $laravelVersion,
            'package_count' => count($packages),
            'reported_at' => now()->subHours(5),
            'analyzed_at' => now()->subHours(5),
        ]);

        foreach ($packages as [$name, $version]) {
            $snapshot->packages()->create(['name' => $name, 'version' => $version, 'is_dev' => false]);
        }

        $site->forceFill(['agent_last_seen_at' => now()->subHours(5)])->save();

        foreach ($this->advisories() as $advisory) {
            $installed = collect($packages)->firstWhere(0, $advisory->package_name);

            if ($installed && VersionConstraint::matches($installed[1], $advisory->affected_versions)) {
                $site->packageFindings()->create([
                    'advisory_id' => $advisory->id,
                    'package_name' => $advisory->package_name,
                    'installed_version' => $installed[1],
                    'status' => FindingStatus::Open,
                    'first_seen_at' => now()->subDays(2),
                    'notified_at' => now()->subDays(2),
                ]);
            }
        }
    }

    /**
     * Real published advisories, cached locally so the demo works offline.
     *
     * @return array<int, Advisory>
     */
    private function advisories(): array
    {
        return [
            Advisory::firstOrCreate(['advisory_id' => 'demo-CVE-2024-52301'], [
                'package_name' => 'laravel/framework',
                'title' => 'Environment manipulation via query string (register_argc_argv)',
                'cve' => 'CVE-2024-52301',
                'link' => 'https://nvd.nist.gov/vuln/detail/CVE-2024-52301',
                'affected_versions' => '<6.20.45|>=7.0.0,<7.30.7|>=8.0.0,<8.83.28|>=9.0.0,<9.52.17|>=10.0.0,<10.48.23|>=11.0.0,<11.31.0',
                'severity' => 'high',
                'reported_at' => '2024-11-12',
            ]),
            Advisory::firstOrCreate(['advisory_id' => 'demo-CVE-2025-54068'], [
                'package_name' => 'livewire/livewire',
                'title' => 'Remote code execution via property update hydration',
                'cve' => 'CVE-2025-54068',
                'link' => 'https://nvd.nist.gov/vuln/detail/CVE-2025-54068',
                'affected_versions' => '>=3.0.0,<3.6.4',
                'severity' => 'critical',
                'reported_at' => '2025-07-17',
            ]),
            Advisory::firstOrCreate(['advisory_id' => 'demo-CVE-2023-29197'], [
                'package_name' => 'guzzlehttp/psr7',
                'title' => 'Improper header validation',
                'cve' => 'CVE-2023-29197',
                'link' => 'https://nvd.nist.gov/vuln/detail/CVE-2023-29197',
                'affected_versions' => '<1.9.1|>=2.0.0,<2.4.5',
                'severity' => 'medium',
                'reported_at' => '2023-04-17',
            ]),
        ];
    }

    /**
     * @return class-string<Check>
     */
    private function checkClass(string $key): string
    {
        foreach (config('leakwatch.checks') as $class) {
            if (app($class)->key() === $key) {
                return $class;
            }
        }

        throw new \InvalidArgumentException("Unknown check {$key}");
    }

    private function summaryFor(string $checkKey): string
    {
        return match ($checkKey) {
            'env_exposed' => 'Anyone can download this file. Laravel .env files usually contain database passwords, the APP_KEY and third-party API keys.',
            'debug_mode' => 'An ordinary request returned a detailed debug page with file paths and framework internals.',
            'cookie_flags' => 'The session cookie can be sent over plain HTTP.',
            'telescope_exposed' => 'Telescope records every request, query, job and exception, and anyone can read that history.',
            'horizon_exposed' => 'Horizon shows every queued job and its payload and lets visitors retry or delete jobs.',
            'tls_certificate' => 'Certificates from automated issuers normally renew 30 days before expiry. Check the renewal job.',
            'php_version_eol' => 'The X-Powered-By header reveals the PHP version, and old branches stop receiving security fixes.',
            'email_dmarc' => 'Receivers report spoofed mail but still deliver it.',
            default => 'Browsers and attackers can take advantage of the missing protection.',
        };
    }
}
