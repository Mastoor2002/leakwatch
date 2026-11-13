<?php

use App\Enums\FindingStatus;
use App\Enums\ScanStatus;
use App\Enums\TeamRole;
use App\Enums\VerificationMethod;
use App\Models\Client;
use App\Models\Finding;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    fakeDns();
});

test('site pages render for team members', function () {
    [$user, $site] = userWithSite();
    Finding::factory()->create(['site_id' => $site->id]);

    $this->actingAs($user);

    $this->get(route('sites.index'))->assertOk()->assertSee($site->host);
    $this->get(route('sites.show', ['site' => $site]))->assertOk()->assertSee($site->host);
    $this->get(route('clients.index'))->assertOk();
    $this->get(route('advisories.index'))->assertOk();
    $this->get(route('alerts.edit'))->assertOk();
    $this->get(route('dashboard'))->assertOk()->assertSee('Security overview');
    $this->get(route('sites.report', ['site' => $site]))->assertOk()->assertSee('Website security report');
});

test('users cannot see or act on another team\'s site', function () {
    [, $site] = userWithSite();
    $intruder = User::factory()->create();

    $this->actingAs($intruder);

    $this->get(route('sites.show', ['current_team' => $intruder->currentTeam->slug, 'site' => $site]))->assertForbidden();
    $this->get(route('sites.report', ['current_team' => $intruder->currentTeam->slug, 'site' => $site]))->assertForbidden();

    expect($intruder->can('view', $site))->toBeFalse()
        ->and($intruder->can('scan', $site))->toBeFalse()
        ->and($intruder->can('delete', $site))->toBeFalse();
});

test('adding a site normalises the url and creates the client', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::sites.index')
        ->set('url', 'Shop.Example.com/some/path')
        ->set('newClientName', 'Acme Bakery')
        ->call('addSite')
        ->assertHasNoErrors();

    $site = Site::sole();

    expect($site->url)->toBe('https://shop.example.com')
        ->and($site->host)->toBe('shop.example.com')
        ->and($site->team_id)->toBe($user->current_team_id)
        ->and($site->client->name)->toBe('Acme Bakery')
        ->and($site->verification_token)->toStartWith('lw-')
        ->and($site->isVerified())->toBeFalse();
});

test('invalid and duplicate sites are rejected', function () {
    [$user, $site] = userWithSite();
    $this->actingAs($user);

    Livewire::test('pages::sites.index')
        ->set('url', 'ftp://files.example.com')
        ->set('clientId', (string) $site->client_id)
        ->call('addSite')
        ->assertHasErrors('url');

    Livewire::test('pages::sites.index')
        ->set('url', 'https://shop.example.com')
        ->set('clientId', (string) $site->client_id)
        ->call('addSite')
        ->assertHasErrors('url');

    expect(Site::count())->toBe(1);
});

test('a client from another team cannot be attached', function () {
    $user = User::factory()->create();
    $foreignClient = Client::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::sites.index')
        ->set('url', 'https://shop.example.com')
        ->set('clientId', (string) $foreignClient->id)
        ->call('addSite')
        ->assertHasErrors('clientId');
});

test('ownership can be verified with a DNS TXT record', function () {
    [$user, $site] = userWithSite(verified: false);
    fakeDns(txt: ['shop.example.com' => ['leakwatch-verification='.$site->verification_token]]);
    Http::fake(['*' => Http::response('', 404)]);

    $this->actingAs($user);
    Livewire::test('pages::sites.show', ['site' => $site])->call('verify');

    expect($site->fresh()->isVerified())->toBeTrue()
        ->and($site->fresh()->verification_method)->toBe(VerificationMethod::Dns);
});

test('ownership can be verified with a file, and a wrong token fails', function () {
    [$user, $site] = userWithSite(verified: false);
    $this->actingAs($user);

    Http::fake(['https://shop.example.com/.well-known/leakwatch-verification.txt' => Http::response('wrong-token')]);
    Livewire::test('pages::sites.show', ['site' => $site])->call('verify');
    expect($site->fresh()->isVerified())->toBeFalse();

    Http::swap(new Factory);
    Http::fake(['https://shop.example.com/.well-known/leakwatch-verification.txt' => Http::response($site->verification_token."\n")]);
    Livewire::test('pages::sites.show', ['site' => $site])->call('verify');
    expect($site->fresh()->verification_method)->toBe(VerificationMethod::File);
});

test('the scan button starts a scan and polling completes it', function () {
    [$user, $site] = userWithSite();
    Http::fake(['*' => Http::response('<title>Shop</title>')]);
    $this->actingAs($user);

    $component = Livewire::test('pages::sites.show', ['site' => $site])->call('scan');

    $scan = $site->scans()->sole();
    expect($scan->status)->toBe(ScanStatus::Running);

    for ($i = 0; $i < 25 && $scan->fresh()->isActive(); $i++) {
        $component->call('tick');
    }

    expect($scan->fresh()->status)->toBe(ScanStatus::Completed)
        ->and($site->fresh()->grade)->not->toBeNull();
});

test('findings can be ignored with a reason and reopened', function () {
    [$user, $site] = userWithSite();
    $finding = Finding::factory()->create(['site_id' => $site->id, 'severity' => 'critical']);
    $this->actingAs($user);

    Livewire::test('pages::sites.show', ['site' => $site])
        ->call('startIgnoring', $finding->id)
        ->call('ignore')
        ->assertHasErrors('ignoreReason')
        ->set('ignoreReason', 'Internal staging box')
        ->call('ignore')
        ->assertHasNoErrors();

    expect($finding->fresh()->status)->toBe(FindingStatus::Ignored)
        ->and($site->fresh()->grade)->toBe('A');

    Livewire::test('pages::sites.show', ['site' => $site])->call('reopen', $finding->id);

    expect($finding->fresh()->status)->toBe(FindingStatus::Open)
        ->and($site->fresh()->grade)->toBe('D');
});

test('findings from other sites cannot be touched', function () {
    [$user, $site] = userWithSite();
    $otherFinding = Finding::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::sites.show', ['site' => $site])
        ->call('reopen', $otherFinding->id)
        ->assertNotFound();
});

test('only owners and admins can create agent tokens and delete sites', function () {
    [$owner, $site] = userWithSite();
    $member = User::factory()->create();
    $site->team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($site->team);

    $this->actingAs($member);
    Livewire::test('pages::sites.show', ['site' => $site])->call('issueToken')->assertForbidden();
    Livewire::test('pages::sites.show', ['site' => $site])->call('deleteSite')->assertForbidden();

    $this->actingAs($owner);
    $owner->switchTeam($site->team);
    $component = Livewire::test('pages::sites.show', ['site' => $site])->call('issueToken');

    expect($component->get('newToken'))->toStartWith('lw_')
        ->and($site->fresh()->agent_token_hash)->toBe(hash('sha256', $component->get('newToken')));
});

test('clients can be created, edited and deleted', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::clients.index')
        ->set('name', 'Harbor Legal')
        ->set('contactEmail', 'it@harbor.example.com')
        ->call('save')
        ->assertHasNoErrors();

    $client = Client::sole();
    expect($client->team_id)->toBe($user->current_team_id);

    Livewire::test('pages::clients.index')
        ->call('edit', $client->id)
        ->set('name', 'Harbor Legal LLP')
        ->call('save');
    expect($client->fresh()->name)->toBe('Harbor Legal LLP');

    Livewire::test('pages::clients.index')->call('delete', $client->id);
    expect(Client::count())->toBe(0);
});

test('slack webhooks must be real slack urls', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::alerts.edit')
        ->set('slackWebhookUrl', 'http://169.254.169.254/latest/meta-data')
        ->call('save')
        ->assertHasErrors('slackWebhookUrl');

    Livewire::test('pages::alerts.edit')
        ->set('slackWebhookUrl', 'https://hooks.slack.com/services/T000/B000/XXXX')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->currentTeam->fresh()->slack_webhook_url)->toBe('https://hooks.slack.com/services/T000/B000/XXXX');
});

test('the public bot page and landing page render', function () {
    $this->get('/')->assertOk()->assertSee('Find the leaks attackers scan for');
    $this->get(route('bot'))->assertOk()->assertSee('SurfacewatchBot');
});
