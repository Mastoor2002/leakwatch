<?php

use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use App\Scanning\Contracts\DnsResolver;
use App\Scanning\Contracts\TlsInspector;
use App\Scanning\Data\TlsCertificate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeDnsResolver;
use Tests\Fakes\FakeTlsInspector;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Leakwatch helpers
|--------------------------------------------------------------------------
*/

/**
 * Swap real DNS for a fake. By default example hosts resolve to a public IP.
 *
 * @param  array<string, array<int, string>>  $addresses
 * @param  array<string, array<int, string>>  $txt
 */
function fakeDns(array $addresses = [], array $txt = []): FakeDnsResolver
{
    $resolver = new FakeDnsResolver($addresses + [
        'shop.example.com' => ['93.184.216.34'],
        'example.com' => ['93.184.216.34'],
    ], $txt);

    app()->instance(DnsResolver::class, $resolver);

    // A healthy certificate by default; tests about TLS problems swap their own in.
    app()->instance(TlsInspector::class, new FakeTlsInspector(new TlsCertificate(
        'shop.example.com', "Let's Encrypt", CarbonImmutable::now()->subDays(10), CarbonImmutable::now()->addDays(80), ['shop.example.com'], true,
    )));

    return $resolver;
}

/**
 * A user with a site in their current team.
 *
 * @return array{0: User, 1: Site}
 */
function userWithSite(string $url = 'https://shop.example.com', bool $verified = true): array
{
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $client = Client::factory()->create(['team_id' => $team->id]);

    $site = Site::factory()
        ->forUrl($url)
        ->state(['team_id' => $team->id, 'client_id' => $client->id])
        ->when($verified, fn ($factory) => $factory->verified())
        ->create();

    return [$user, $site];
}
