<?php

use App\Enums\FindingStatus;
use App\Models\PackageFinding;
use App\Notifications\NewAdvisoriesFound;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * @return array<string, mixed>
 */
function packagistAdvisories(): array
{
    return ['advisories' => [
        'laravel/framework' => [[
            'advisoryId' => 'PKSA-test-1',
            'packageName' => 'laravel/framework',
            'title' => 'Environment manipulation via query string',
            'cve' => 'CVE-2024-52301',
            'link' => 'https://example.com/advisory',
            'affectedVersions' => '>=11.0.0,<11.31.0',
            'severity' => 'high',
            'reportedAt' => '2024-11-12 00:00:00',
        ]],
    ]];
}

function agentReport(string $laravelVersion = '11.30.0'): array
{
    return [
        'lock_hash' => str_repeat('a', 64),
        'php_version' => '8.3.12',
        'laravel_version' => $laravelVersion,
        'packages' => [
            ['name' => 'laravel/framework', 'version' => 'v'.$laravelVersion, 'dev' => false],
            ['name' => 'pestphp/pest', 'version' => '3.5.0', 'dev' => true],
        ],
    ];
}

beforeEach(function () {
    Notification::fake();
    Http::fake(['packagist.org/*' => Http::response(packagistAdvisories())]);
});

test('reports without a valid token are rejected', function () {
    $this->postJson('/api/v1/agent/report', agentReport())->assertUnauthorized();

    $this->withToken('lw_not-a-real-token')->postJson('/api/v1/agent/report', agentReport())->assertUnauthorized();
});

test('a valid report is stored and matched against advisories', function () {
    [, $site] = userWithSite();
    $token = $site->issueAgentToken();

    $this->withToken($token)->postJson('/api/v1/agent/report', agentReport())
        ->assertAccepted()
        ->assertJson(['status' => 'received', 'packages' => 2]);

    $snapshot = $site->latestSnapshot()->first();

    expect($snapshot->packages()->count())->toBe(2)
        ->and($snapshot->laravel_version)->toBe('11.30.0')
        ->and($snapshot->analyzed_at)->not->toBeNull()
        ->and($site->fresh()->agent_last_seen_at)->not->toBeNull();

    $finding = PackageFinding::sole();
    expect($finding->package_name)->toBe('laravel/framework')
        ->and($finding->status)->toBe(FindingStatus::Open)
        ->and($finding->advisory->cve)->toBe('CVE-2024-52301');

    Notification::assertSentTo($site->team->alertRecipients(), NewAdvisoriesFound::class);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'packagist.org/api/security-advisories'));
});

test('upgrading the package resolves the advisory', function () {
    [, $site] = userWithSite();
    $token = $site->issueAgentToken();

    $this->withToken($token)->postJson('/api/v1/agent/report', agentReport('11.30.0'))->assertAccepted();
    expect(PackageFinding::sole()->status)->toBe(FindingStatus::Open);

    $this->withToken($token)->postJson('/api/v1/agent/report', agentReport('11.31.0'))->assertAccepted();
    expect(PackageFinding::sole()->status)->toBe(FindingStatus::Resolved);
});

test('a token only ever writes to its own site', function () {
    [, $siteA] = userWithSite();
    [, $siteB] = userWithSite('https://example.com');
    $tokenA = $siteA->issueAgentToken();

    $this->withToken($tokenA)->postJson('/api/v1/agent/report', agentReport())->assertAccepted();

    expect($siteA->dependencySnapshots()->count())->toBe(1)
        ->and($siteB->dependencySnapshots()->count())->toBe(0);
});

test('revoked tokens stop working and malformed reports are rejected', function () {
    [, $site] = userWithSite();
    $token = $site->issueAgentToken();

    $this->withToken($token)->postJson('/api/v1/agent/report', ['packages' => [['name' => 'not a package', 'version' => '1']]])
        ->assertUnprocessable();

    $site->revokeAgentToken();

    $this->withToken($token)->postJson('/api/v1/agent/report', agentReport())->assertUnauthorized();
});

test('only the token hash is stored', function () {
    [, $site] = userWithSite();
    $token = $site->issueAgentToken();

    $this->assertDatabaseMissing('sites', ['agent_token_hash' => $token]);
    $this->assertDatabaseHas('sites', ['id' => $site->id, 'agent_token_hash' => hash('sha256', $token)]);
});
