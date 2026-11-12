<?php

use App\Enums\CheckStatus;
use App\Enums\Severity;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use App\Scanning\Checks;
use App\Scanning\Contracts\DnsResolver;
use App\Scanning\Contracts\TlsInspector;
use App\Scanning\Data\PageSnapshot;
use App\Scanning\Data\TlsCertificate;
use App\Scanning\SafeHttp;
use App\Scanning\ScanContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeDnsResolver;
use Tests\Fakes\FakeTlsInspector;

const HOME_HTML = '<!DOCTYPE html><html><head><meta name="csrf-token" content="t"><title>Shop</title></head><body>Welcome to the shop, lots of words here.</body></html>';
const NOT_FOUND_HTML = '<!DOCTYPE html><html><head><title>Not Found</title></head><body>404</body></html>';

/**
 * Build a ScanContext for https://shop.example.com with the given homepage
 * headers. $catchAll = true simulates a site that answers 200 + homepage for every path.
 *
 * @param  array<string, array<int, string>>  $homepageHeaders
 */
function scanContext(array $homepageHeaders = [], bool $catchAll = false, string $origin = 'https://shop.example.com', bool $verified = true): ScanContext
{
    if (! app(DnsResolver::class) instanceof FakeDnsResolver) {
        fakeDns();
    }

    $user = User::factory()->create();
    $client = Client::factory()->create(['team_id' => $user->current_team_id]);
    $site = Site::factory()->forUrl($origin)->state(['team_id' => $user->current_team_id, 'client_id' => $client->id])
        ->when($verified, fn ($f) => $f->verified())->create();

    $homepage = new PageSnapshot($origin.'/', 200, PageSnapshot::normalizeHeaders($homepageHeaders), 'Shop', strlen(HOME_HTML), PageSnapshot::hashBody(HOME_HTML), PageSnapshot::detectMarkers(HOME_HTML));
    $baseline = $catchAll
        ? new PageSnapshot($origin.'/missing', 200, [], 'Shop', strlen(HOME_HTML), PageSnapshot::hashBody(HOME_HTML))
        : new PageSnapshot($origin.'/missing', 404, [], 'Not Found', strlen(NOT_FOUND_HTML), PageSnapshot::hashBody(NOT_FOUND_HTML));

    return new ScanContext($site, app(SafeHttp::class), app(DnsResolver::class), app(TlsInspector::class), $origin, $homepage, $baseline);
}

function catchAllFake(): void
{
    Http::fake(['*' => Http::response(HOME_HTML)]);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('env_exposed', function () {
    test('detects a public .env and stores names only', function () {
        Http::fake([
            'https://shop.example.com/.env' => Http::response("APP_KEY=base64:topsecret\nDB_PASSWORD=hunter2\nMAIL_HOST=smtp\n"),
            '*' => Http::response(NOT_FOUND_HTML, 404),
        ]);

        $result = (new Checks\EnvExposedCheck)->run(scanContext());

        expect($result->status)->toBe(CheckStatus::Failed)
            ->and($result->findings)->toHaveCount(1)
            ->and($result->findings[0]->severity)->toBe(Severity::Critical)
            ->and($result->findings[0]->evidence['sensitive_variables_present'])->toBe(['APP_KEY', 'DB_PASSWORD'])
            ->and(json_encode($result->toArray()))->not->toContain('topsecret')->not->toContain('hunter2');
    });

    test('passes when .env is not served', function () {
        Http::fake(['*' => Http::response(NOT_FOUND_HTML, 404)]);

        expect((new Checks\EnvExposedCheck)->run(scanContext())->status)->toBe(CheckStatus::Passed);
    });

    test('does not flag catch-all 200 pages', function () {
        catchAllFake();

        expect((new Checks\EnvExposedCheck)->run(scanContext(catchAll: true))->status)->toBe(CheckStatus::Passed);
    });
});

describe('debug_mode', function () {
    test('detects a debug error page and the ignition endpoint', function () {
        Http::fake([
            'https://shop.example.com/_ignition/health-check' => Http::response('{"can_execute_commands":true}'),
            'https://shop.example.com/' => fn ($request) => $request->method() === 'POST'
                ? Http::response('<h1>MethodNotAllowedHttpException</h1> vendor/laravel/framework/src/Illuminate/Routing/AbstractRouteCollection.php', 405)
                : Http::response(HOME_HTML),
            '*' => Http::response(NOT_FOUND_HTML, 404),
        ]);

        $result = (new Checks\DebugModeCheck)->run(scanContext());

        expect($result->findings)->toHaveCount(2)
            ->and($result->findings[0]->severity)->toBe(Severity::Critical);
    });

    test('a plain production error page passes', function () {
        Http::fake(['*' => Http::response('<h1>405 Method Not Allowed</h1>', 405)]);

        expect((new Checks\DebugModeCheck)->run(scanContext())->status)->toBe(CheckStatus::Passed);
    });

    test('catch-all sites are not flagged', function () {
        catchAllFake();

        expect((new Checks\DebugModeCheck)->run(scanContext(catchAll: true))->status)->toBe(CheckStatus::Passed);
    });
});

describe('exposed dashboards and files', function () {
    test('detects each exposed path', function (string $class, string $path, string $body) {
        Http::fake([
            "https://shop.example.com{$path}" => Http::response($body),
            '*' => Http::response(NOT_FOUND_HTML, 404),
        ]);

        expect((new $class)->run(scanContext())->status)->toBe(CheckStatus::Failed);
    })->with([
        'telescope' => [Checks\TelescopeExposedCheck::class, '/telescope', '<title>Telescope</title><script>window.Telescope = {}</script>'],
        'horizon' => [Checks\HorizonExposedCheck::class, '/horizon', '<script>window.Horizon = {}</script>'],
        'pulse' => [Checks\PulseExposedCheck::class, '/pulse', '<title>Laravel Pulse</title>'],
        'log viewer' => [Checks\LogViewerExposedCheck::class, '/log-viewer', '<script>window.LogViewer = {}</script>'],
        'phpinfo' => [Checks\PhpinfoExposedCheck::class, '/phpinfo.php', '<title>phpinfo()</title><h1>PHP Version 8.2.0</h1>'],
        'git' => [Checks\GitExposedCheck::class, '/.git/HEAD', "ref: refs/heads/main\n"],
        'log file' => [Checks\LaravelLogExposedCheck::class, '/storage/logs/laravel.log', "[2026-01-01 10:00:00] production.ERROR: boom\n"],
        'docroot' => [Checks\DocrootMisconfiguredCheck::class, '/composer.json', '{"require":{"laravel/framework":"^11.0"}}'],
    ]);

    test('nothing is flagged when the paths return 404', function (string $class) {
        Http::fake(['*' => Http::response(NOT_FOUND_HTML, 404)]);

        expect((new $class)->run(scanContext())->status)->toBe(CheckStatus::Passed);
    })->with([
        Checks\TelescopeExposedCheck::class, Checks\HorizonExposedCheck::class, Checks\PulseExposedCheck::class,
        Checks\LogViewerExposedCheck::class, Checks\PhpinfoExposedCheck::class, Checks\GitExposedCheck::class,
        Checks\LaravelLogExposedCheck::class, Checks\DocrootMisconfiguredCheck::class,
    ]);

    test('nothing is flagged on catch-all sites', function (string $class) {
        catchAllFake();

        expect((new $class)->run(scanContext(catchAll: true))->status)->toBe(CheckStatus::Passed);
    })->with([
        Checks\TelescopeExposedCheck::class, Checks\HorizonExposedCheck::class, Checks\PulseExposedCheck::class,
        Checks\LogViewerExposedCheck::class, Checks\PhpinfoExposedCheck::class, Checks\GitExposedCheck::class,
        Checks\LaravelLogExposedCheck::class, Checks\DocrootMisconfiguredCheck::class,
    ]);
});

describe('passive checks', function () {
    test('missing security headers are reported individually', function () {
        $result = (new Checks\SecurityHeadersCheck)->run(scanContext());

        expect(collect($result->findings)->pluck('detail')->all())->toBe(['hsts', 'nosniff', 'framing', 'referrer']);
    });

    test('a well configured site passes the header check', function () {
        $result = (new Checks\SecurityHeadersCheck)->run(scanContext([
            'Strict-Transport-Security' => ['max-age=31536000'],
            'X-Content-Type-Options' => ['nosniff'],
            'Content-Security-Policy' => ["frame-ancestors 'self'"],
            'Referrer-Policy' => ['strict-origin-when-cross-origin'],
        ]));

        expect($result->status)->toBe(CheckStatus::Passed);
    });

    test('session cookie flags are checked but XSRF-TOKEN is allowed to be readable by JavaScript', function () {
        $result = (new Checks\CookieFlagsCheck)->run(scanContext([
            'Set-Cookie' => [
                'XSRF-TOKEN=a; path=/; secure; samesite=lax',
                'shop_session=b; path=/; samesite=lax',
            ],
        ]));

        expect($result->findings)->toHaveCount(1)
            ->and($result->findings[0]->evidence)->toBe(['cookie' => 'shop_session', 'missing_flags' => ['Secure', 'HttpOnly']]);
    });

    test('old PHP versions are flagged as end of life', function () {
        $result = (new Checks\PhpVersionEolCheck)->run(scanContext(['X-Powered-By' => ['PHP/8.0.30']]));

        expect(collect($result->findings)->pluck('detail')->all())->toBe(['header', 'eol-8.0']);
    });

    test('a supported PHP version only reports the header leak', function () {
        $result = (new Checks\PhpVersionEolCheck)->run(scanContext(['X-Powered-By' => ['PHP/8.5.1']]));

        expect(collect($result->findings)->pluck('detail')->all())->toBe(['header']);
    });

    test('http sites are told to use https', function () {
        Http::fake();

        $result = (new Checks\HttpsRedirectCheck)->run(scanContext(origin: 'http://shop.example.com'));

        expect($result->findings[0]->detail)->toBe('no-https');
    });

    test('https sites that serve plain http without redirecting are flagged', function () {
        Http::fake(['http://shop.example.com/' => Http::response(HOME_HTML)]);

        expect((new Checks\HttpsRedirectCheck)->run(scanContext())->status)->toBe(CheckStatus::Failed);
    });

    test('https sites that redirect plain http pass', function () {
        Http::fake(['http://shop.example.com/' => Http::response('', 301, ['Location' => 'https://shop.example.com/'])]);

        expect((new Checks\HttpsRedirectCheck)->run(scanContext())->status)->toBe(CheckStatus::Passed);
    });

    test('expiring and untrusted certificates are reported', function (int $days, bool $trusted, string $detail, Severity $severity) {
        $context = scanContext();
        app()->instance(TlsInspector::class, new FakeTlsInspector(new TlsCertificate(
            'shop.example.com', "Let's Encrypt", CarbonImmutable::now()->subDays(60), CarbonImmutable::now()->addDays($days)->addHour(), ['shop.example.com'], $trusted,
        )));
        $context = new ScanContext($context->site, $context->http, $context->dns, app(TlsInspector::class), $context->origin, $context->homepage, $context->baseline);

        $result = (new Checks\TlsCertificateCheck)->run($context);

        expect($result->findings[0]->detail)->toBe($detail)
            ->and($result->findings[0]->severity)->toBe($severity);
    })->with([
        'expires in 3 days' => [3, true, 'expiring', Severity::High],
        'expires in 15 days' => [15, true, 'expiring', Severity::Medium],
        'self signed' => [200, false, 'untrusted', Severity::High],
    ]);

    test('missing SPF and DMARC are reported, good records pass', function () {
        $dns = fakeDns(txt: []);
        $context = scanContext();

        expect((new Checks\EmailSpfCheck)->run($context)->findings[0]->detail)->toBe('missing')
            ->and((new Checks\EmailDmarcCheck)->run($context)->findings[0]->detail)->toBe('missing');

        $dns->txtRecords = [
            'shop.example.com' => ['v=spf1 include:_spf.google.com ~all'],
            '_dmarc.shop.example.com' => ['v=DMARC1; p=reject'],
        ];

        expect((new Checks\EmailSpfCheck)->run($context)->status)->toBe(CheckStatus::Passed)
            ->and((new Checks\EmailDmarcCheck)->run($context)->status)->toBe(CheckStatus::Passed);
    });

    test('email checks skip reserved local domains', function () {
        expect((new Checks\EmailSpfCheck)->appliesTo(scanContext(origin: 'http://vulnerable.test')))->toBeFalse();
    });

    test('framework detection marks Laravel sites', function () {
        $context = scanContext(['Set-Cookie' => ['XSRF-TOKEN=a; path=/', 'shop_session=b; path=/; httponly']]);

        (new Checks\FrameworkDetectCheck)->run($context);

        expect($context->site->fresh()->is_laravel)->toBeTrue();
    });
});
