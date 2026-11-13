<?php

use App\Scanning\Exceptions\BlockedTargetException;
use App\Scanning\Exceptions\RequestBudgetExceededException;
use App\Scanning\SafeHttp;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('requests to public hosts go through with our user agent', function () {
    fakeDns();
    Http::fake(['https://shop.example.com/*' => Http::response('ok')]);

    $response = app(SafeHttp::class)->get('https://shop.example.com/');

    expect($response->body())->toBe('ok');
    Http::assertSent(fn ($request) => str_starts_with($request->header('User-Agent')[0], 'LeakwatchBot/'));
});

test('hosts that resolve to private or metadata addresses are blocked', function (string $ip) {
    fakeDns(['internal.example.org' => [$ip]]);
    Http::fake();

    expect(fn () => app(SafeHttp::class)->get('http://internal.example.org/'))
        ->toThrow(BlockedTargetException::class);

    Http::assertNothingSent();
})->with(['127.0.0.1', '10.0.0.8', '192.168.1.1', '169.254.169.254', '::1', '::ffff:127.0.0.1']);

test('a host is blocked if ANY of its addresses is private', function () {
    fakeDns(['mixed.example.org' => ['93.184.216.34', '10.0.0.1']]);

    expect(fn () => app(SafeHttp::class)->get('https://mixed.example.org/'))
        ->toThrow(BlockedTargetException::class);
});

test('literal private IPs, odd schemes, ports and credentials are rejected', function (string $url) {
    fakeDns();
    Http::fake();

    expect(fn () => app(SafeHttp::class)->get($url))->toThrow(BlockedTargetException::class);

    Http::assertNothingSent();
})->with([
    'http://127.0.0.1/',
    'http://[::1]/',
    'ftp://shop.example.com/',
    'file:///etc/passwd',
    'https://shop.example.com:6379/',
    'https://user:pass@shop.example.com/',
    'gopher://shop.example.com/',
]);

test('private targets are only allowed in local with the flag on', function () {
    fakeDns(['vulnerable.test' => ['127.0.0.1']]);
    config(['leakwatch.allow_private_targets' => true]);

    // The test environment is "testing", not "local": still blocked.
    expect(fn () => app(SafeHttp::class)->get('http://vulnerable.test/'))->toThrow(BlockedTargetException::class);

    app()->detectEnvironment(fn () => 'local');
    Http::fake(['http://vulnerable.test/*' => Http::response('demo')]);

    expect(app(SafeHttp::class)->get('http://vulnerable.test/')->body())->toBe('demo');
});

test('redirects are followed on the same site but never to another site', function () {
    fakeDns(['evil.example.net' => ['93.184.216.35']]);
    Http::fake([
        'http://shop.example.com/' => Http::response('', 301, ['Location' => 'https://shop.example.com/']),
        'https://shop.example.com/' => Http::response('', 302, ['Location' => 'https://evil.example.net/']),
        '*' => Http::response('should not be fetched'),
    ]);

    [$response, $finalUrl] = app(SafeHttp::class)->getFollowingRedirects('http://shop.example.com/');

    expect($finalUrl)->toBe('https://shop.example.com/')
        ->and($response->status())->toBe(302);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example.net'));
});

test('a per-check request budget stops runaway scans', function () {
    fakeDns();
    config(['leakwatch.http.max_requests_per_check' => 2]);
    Http::fake(['*' => Http::response('ok')]);

    $http = app(SafeHttp::class);
    $http->get('https://shop.example.com/a');
    $http->get('https://shop.example.com/b');

    expect(fn () => $http->get('https://shop.example.com/c'))
        ->toThrow(RequestBudgetExceededException::class);
});
