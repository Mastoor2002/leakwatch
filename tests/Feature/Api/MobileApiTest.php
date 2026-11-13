<?php

use App\Enums\ScanStatus;
use App\Models\ApiToken;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Log a user in through the API and return a bearer token.
 */
function apiTokenFor(User $user, string $device = 'Pixel 9'): string
{
    return ApiToken::issue($user, $device)['token'];
}

/**
 * @return array<string, string>
 */
function bearer(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

function fakeMobileScanTarget(): void
{
    Http::fake([
        'https://shop.example.com/.env' => Http::response("APP_KEY=x\nDB_PASSWORD=y\nAPP_ENV=production\n"),
        'https://shop.example.com/' => Http::response('<!DOCTYPE html><html><head><meta name="csrf-token" content="t"><title>Shop</title></head><body>Shop home page with enough text.</body></html>', 200, [
            'Set-Cookie' => ['XSRF-TOKEN=a; path=/; secure; samesite=lax', 'shop_session=b; path=/; secure; httponly; samesite=lax'],
            'Strict-Transport-Security' => 'max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'same-origin',
        ]),
        'http://shop.example.com/' => Http::response('', 301, ['Location' => 'https://shop.example.com/']),
        '*' => Http::response('<title>Not Found</title>', 404),
    ]);
}

beforeEach(function () {
    fakeDns(txt: [
        'shop.example.com' => ['v=spf1 -all'],
        '_dmarc.shop.example.com' => ['v=DMARC1; p=reject'],
    ]);
});

test('a user can exchange credentials for a token', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $response = $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'iPhone 16',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.current_team.slug', $user->currentTeam->slug)
        ->assertJsonPath('user.teams.0.role', 'owner');

    $token = $response->json('token');

    expect($token)->toStartWith('lwu_')
        ->and(ApiToken::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(ApiToken::query()->first()->token_hash)->toBe(hash('sha256', $token))
        ->and(ApiToken::query()->first()->name)->toBe('iPhone 16');
});

test('wrong credentials are rejected without leaking which part was wrong', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'nope',
        'device_name' => 'Test',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    expect(ApiToken::count())->toBe(0);
});

test('requests without a valid token are unauthenticated', function () {
    $user = User::factory()->create();

    $this->getJson('/api/v1/me')->assertUnauthorized();
    $this->getJson('/api/v1/me', bearer('lwu_not-a-real-token'))->assertUnauthorized();

    $expired = ApiToken::issue($user, 'old', now()->subMinute())['token'];
    $this->getJson('/api/v1/me', bearer($expired))->assertUnauthorized();
});

test('me returns the user and their teams', function () {
    [$user] = userWithSite();

    $this->getJson('/api/v1/me', bearer(apiTokenFor($user)))
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.current_team.name', $user->currentTeam->name);
});

test('a token can be revoked', function () {
    [$user] = userWithSite();
    $token = apiTokenFor($user);

    $this->deleteJson('/api/v1/auth/token', [], bearer($token))->assertOk();
    $this->getJson('/api/v1/me', bearer($token))->assertUnauthorized();
});

test('the site list is scoped to the team and includes severity counts', function () {
    [$user, $site] = userWithSite();
    Finding::factory()->count(2)->create(['site_id' => $site->id, 'severity' => 'critical']);
    Finding::factory()->create(['site_id' => $site->id, 'severity' => 'low']);

    $slug = $user->currentTeam->slug;

    $this->getJson("/api/v1/teams/{$slug}/sites", bearer(apiTokenFor($user)))
        ->assertOk()
        ->assertJsonCount(1, 'sites')
        ->assertJsonPath('sites.0.host', 'shop.example.com')
        ->assertJsonPath('sites.0.is_verified', true)
        ->assertJsonPath('sites.0.open_findings', 3)
        ->assertJsonPath('sites.0.open_by_severity.critical', 2)
        ->assertJsonPath('sites.0.open_by_severity.low', 1)
        ->assertJsonPath('sites.0.client.name', $site->client->name);
});

test('members of other teams cannot read a team', function () {
    [$user] = userWithSite();
    $stranger = User::factory()->create();

    $this->getJson("/api/v1/teams/{$user->currentTeam->slug}/sites", bearer(apiTokenFor($stranger)))
        ->assertForbidden();
});

test('a site from another team is not found through the wrong team', function () {
    [$user, $site] = userWithSite();
    [$other] = userWithSite('https://other.example.com');

    $this->getJson("/api/v1/teams/{$other->currentTeam->slug}/sites/{$site->id}", bearer(apiTokenFor($other)))
        ->assertNotFound();
});

test('a site detail includes findings, the last scan and verification instructions', function () {
    [$user, $site] = userWithSite();
    $finding = Finding::factory()->create(['site_id' => $site->id, 'severity' => 'high', 'title' => 'Debug mode is on']);

    $this->getJson("/api/v1/teams/{$user->currentTeam->slug}/sites/{$site->id}", bearer(apiTokenFor($user)))
        ->assertOk()
        ->assertJsonPath('site.id', $site->id)
        ->assertJsonPath('site.findings.0.id', $finding->id)
        ->assertJsonPath('site.findings.0.title', 'Debug mode is on')
        ->assertJsonPath('site.findings.0.severity', 'high')
        ->assertJsonPath('site.verification.dns_record', 'leakwatch-verification='.$site->verification_token)
        ->assertJsonPath('site.last_scan', null);
});

test('the overview summarises a team', function () {
    [$user, $site] = userWithSite();
    $site->forceFill(['score' => 40, 'grade' => 'F', 'last_scanned_at' => now()])->save();
    Finding::factory()->create(['site_id' => $site->id, 'severity' => 'critical']);

    $this->getJson("/api/v1/teams/{$user->currentTeam->slug}/overview", bearer(apiTokenFor($user)))
        ->assertOk()
        ->assertJsonPath('stats.sites', 1)
        ->assertJsonPath('stats.scanned_sites', 1)
        ->assertJsonPath('stats.open_findings', 1)
        ->assertJsonPath('stats.open_by_severity.critical', 1)
        ->assertJsonPath('stats.grades.F', 1)
        ->assertJsonPath('stats.average_score', 40)
        ->assertJsonPath('needs_attention.0.id', $site->id);
});

test('a scan can be started and polled to completion from the API', function () {
    config(['leakwatch.scan_driver' => 'inline']);
    [$user, $site] = userWithSite();
    fakeMobileScanTarget();
    $headers = bearer(apiTokenFor($user));
    $slug = $user->currentTeam->slug;

    $this->postJson("/api/v1/teams/{$slug}/sites/{$site->id}/scan", [], $headers)
        ->assertStatus(202)
        ->assertJsonPath('scan.is_active', true);

    // Poll until the scan finishes; each poll advances the inline driver.
    $status = null;

    for ($i = 0; $i < 40; $i++) {
        $status = $this->getJson("/api/v1/teams/{$slug}/sites/{$site->id}/scan", $headers)->assertOk();

        if ($status->json('scan.is_active') === false) {
            break;
        }
    }

    expect($status->json('scan.status'))->toBe(ScanStatus::Completed->value)
        ->and($status->json('scan.progress'))->toBe(100)
        ->and($status->json('scan.checks'))->toHaveCount(count(config('leakwatch.checks')))
        ->and($status->json('site.grade'))->not->toBeNull()
        ->and($site->fresh()->findings()->pluck('check_key')->all())->toContain('env_exposed');
});

test('starting a scan twice returns the same active scan', function () {
    config(['leakwatch.scan_driver' => 'queue']);
    Queue::fake();
    [$user, $site] = userWithSite();
    $headers = bearer(apiTokenFor($user));
    $slug = $user->currentTeam->slug;

    $first = $this->postJson("/api/v1/teams/{$slug}/sites/{$site->id}/scan", [], $headers)->json('scan.id');
    $second = $this->postJson("/api/v1/teams/{$slug}/sites/{$site->id}/scan", [], $headers)->json('scan.id');

    expect($first)->toBe($second)
        ->and($site->scans()->count())->toBe(1);
});

test('verification can be triggered from the API', function () {
    [$user, $site] = userWithSite(verified: false);
    fakeDns(txt: ['shop.example.com' => ['leakwatch-verification='.$site->verification_token]]);

    $this->postJson("/api/v1/teams/{$user->currentTeam->slug}/sites/{$site->id}/verify", [], bearer(apiTokenFor($user)))
        ->assertOk()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('method', 'dns');

    expect($site->fresh()->isVerified())->toBeTrue();
});
