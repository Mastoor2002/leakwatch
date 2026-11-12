<?php

use App\Enums\CheckStatus;
use App\Enums\FindingStatus;
use App\Enums\ScanStatus;
use App\Events\ScanCompleted;
use App\Models\Finding;
use App\Scanning\ScanLauncher;
use App\Scanning\ScanRunner;
use App\Scanning\Scoring;
use App\Enums\Severity;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const SHOP_HOME = '<!DOCTYPE html><html><head><meta name="csrf-token" content="t"><title>Shop</title></head><body>Shop home page with enough text.</body></html>';

/**
 * A vulnerable site: .env exposed, everything else 404.
 */
function fakeVulnerableSite(bool $envExposed = true): void
{
    Http::fake(array_filter([
        'https://shop.example.com/.env' => $envExposed ? Http::response("APP_KEY=x\nDB_PASSWORD=y\nAPP_ENV=production\n") : null,
        'https://shop.example.com/' => Http::response(SHOP_HOME, 200, [
            'Set-Cookie' => ['XSRF-TOKEN=a; path=/; secure; samesite=lax', 'shop_session=b; path=/; secure; httponly; samesite=lax'],
            'Strict-Transport-Security' => 'max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'same-origin',
        ]),
        'http://shop.example.com/' => Http::response('', 301, ['Location' => 'https://shop.example.com/']),
        '*' => Http::response('<title>Not Found</title>', 404),
    ]));
}

beforeEach(function () {
    fakeDns(txt: [
        'shop.example.com' => ['v=spf1 -all'],
        '_dmarc.shop.example.com' => ['v=DMARC1; p=reject'],
    ]);
    Event::fake([ScanCompleted::class]);
});

test('a full scan finds problems, scores the site and fires an event', function () {
    [, $site] = userWithSite();
    fakeVulnerableSite();

    $scan = app(ScanLauncher::class)->launch($site);
    app(ScanRunner::class)->runToCompletion($scan);

    $scan->refresh();
    $site->refresh();

    expect($scan->status)->toBe(ScanStatus::Completed)
        ->and($scan->checkRuns)->toHaveCount(count(config('leakwatch.checks')))
        ->and($scan->checkRuns->whereNull('status'))->toBeEmpty()
        ->and($site->findings()->pluck('check_key')->all())->toContain('env_exposed')
        ->and($site->is_laravel)->toBeTrue()
        ->and($site->grade)->toBe('D')
        ->and($site->last_scan_id)->toBe($scan->id);

    Event::assertDispatched(ScanCompleted::class);
});

test('fixed problems are resolved on the next scan and come back as regressions', function () {
    [, $site] = userWithSite();
    $runner = app(ScanRunner::class);
    $launcher = app(ScanLauncher::class);

    fakeVulnerableSite();
    $runner->runToCompletion($launcher->launch($site));
    $finding = $site->findings()->where('check_key', 'env_exposed')->sole();
    expect($finding->status)->toBe(FindingStatus::Open);

    Http::swap(new \Illuminate\Http\Client\Factory);
    fakeVulnerableSite(envExposed: false);
    $second = $launcher->launch($site);
    $runner->runToCompletion($second);

    expect($finding->fresh()->status)->toBe(FindingStatus::Resolved)
        ->and($second->fresh()->resolved_findings_count)->toBe(1)
        ->and($site->fresh()->grade)->toBe('A');

    Http::swap(new \Illuminate\Http\Client\Factory);
    fakeVulnerableSite();
    $runner->runToCompletion($launcher->launch($site));

    expect($finding->fresh()->status)->toBe(FindingStatus::Open)
        ->and(Finding::where('site_id', $site->id)->where('check_key', 'env_exposed')->count())->toBe(1);
});

test('path probes are skipped until the site is verified', function () {
    [, $site] = userWithSite(verified: false);
    fakeVulnerableSite();

    $scan = app(ScanLauncher::class)->launch($site);
    app(ScanRunner::class)->runToCompletion($scan);

    $envRun = $scan->checkRuns()->where('check_key', 'env_exposed')->sole();

    expect($envRun->status)->toBe(CheckStatus::Skipped)
        ->and($site->findings()->where('check_key', 'env_exposed')->exists())->toBeFalse();

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/.env'));
});

test('scans of private targets fail safely without sending requests', function () {
    [, $site] = userWithSite('https://intranet.example.org');
    fakeDns(['intranet.example.org' => ['10.0.0.5']]);
    Http::fake();

    $scan = app(ScanLauncher::class)->launch($site);
    app(ScanRunner::class)->runToCompletion($scan);

    expect($scan->fresh()->status)->toBe(ScanStatus::Failed)
        ->and($scan->fresh()->error)->toContain('private');

    Http::assertNothingSent();
});

test('the inline driver advances one step per call', function () {
    [, $site] = userWithSite();
    fakeVulnerableSite();
    $runner = app(ScanRunner::class);

    $scan = app(ScanLauncher::class)->launch($site);
    $runner->step($scan);
    expect($scan->fresh()->status)->toBe(ScanStatus::Running)
        ->and($scan->checkRuns()->whereNotNull('status')->count())->toBe(0);

    $runner->step($scan);
    expect($scan->checkRuns()->whereNotNull('status')->count())->toBe(1);

    for ($i = 0; $i < 30 && $scan->fresh()->isActive(); $i++) {
        $runner->step($scan);
    }

    expect($scan->fresh()->status)->toBe(ScanStatus::Completed);
});

test('launching while a scan is running returns the same scan', function () {
    [, $site] = userWithSite();
    $launcher = app(ScanLauncher::class);

    expect($launcher->launch($site)->id)->toBe($launcher->launch($site)->id);
});

test('ignored findings do not count toward the score', function () {
    expect(Scoring::calculate([Severity::Critical]))->toBe(['score' => 60, 'grade' => 'D'])
        ->and(Scoring::calculate([Severity::Low, Severity::Low]))->toBe(['score' => 96, 'grade' => 'A'])
        ->and(Scoring::calculate([Severity::High, Severity::High, Severity::Medium]))->toBe(['score' => 52, 'grade' => 'D'])
        ->and(Scoring::calculate([]))->toBe(['score' => 100, 'grade' => 'A']);
});
