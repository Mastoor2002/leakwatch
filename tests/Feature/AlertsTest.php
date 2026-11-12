<?php

use App\Enums\ScanStatus;
use App\Events\ScanCompleted;
use App\Models\Finding;
use App\Notifications\Channels\SlackWebhookChannel;
use App\Notifications\NewFindingsFound;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

test('new critical findings are alerted once', function () {
    [$user, $site] = userWithSite();
    $scan = $site->scans()->create(['status' => ScanStatus::Completed]);
    Finding::factory()->create(['site_id' => $site->id, 'severity' => 'critical']);
    Finding::factory()->create(['site_id' => $site->id, 'severity' => 'low']);

    ScanCompleted::dispatch($scan);

    Notification::assertSentTo($user, NewFindingsFound::class, fn ($notification) => $notification->findings->count() === 1);

    ScanCompleted::dispatch($scan);

    Notification::assertSentToTimes($user, NewFindingsFound::class, 1);
});

test('alerts also go to the team slack webhook when configured', function () {
    [, $site] = userWithSite();
    $site->team->update(['slack_webhook_url' => 'https://hooks.slack.com/services/T1/B1/abc']);
    $scan = $site->scans()->create(['status' => ScanStatus::Completed]);
    Finding::factory()->create(['site_id' => $site->id, 'severity' => 'high']);

    ScanCompleted::dispatch($scan);

    Notification::assertSentOnDemand(NewFindingsFound::class, function ($notification, array $channels, AnonymousNotifiable $notifiable) {
        return $notifiable->routes[SlackWebhookChannel::class] === 'https://hooks.slack.com/services/T1/B1/abc';
    });
});

test('teams can switch alerts off', function () {
    [$user, $site] = userWithSite();
    $site->team->update(['alerts_enabled' => false]);
    $scan = $site->scans()->create(['status' => ScanStatus::Completed]);
    Finding::factory()->create(['site_id' => $site->id, 'severity' => 'critical']);

    ScanCompleted::dispatch($scan);

    Notification::assertNothingSent();
});

test('only real slack webhook urls are accepted', function (string $url, bool $valid) {
    expect(SlackWebhookChannel::isValidWebhookUrl($url))->toBe($valid);
})->with([
    ['https://hooks.slack.com/services/T000/B000/XXXX', true],
    ['http://hooks.slack.com/services/T000/B000/XXXX', false],
    ['https://hooks.slack.com.evil.example/services/x', false],
    ['http://169.254.169.254/latest/meta-data', false],
]);

test('the demo seeder builds a complete demo account', function () {
    $this->seed(\Database\Seeders\DemoSeeder::class);

    $user = \App\Models\User::where('email', 'demo@leakwatch.test')->sole();

    expect($user->currentTeam->name)->toBe('Northwind Digital')
        ->and($user->currentTeam->sites()->count())->toBe(6)
        ->and(\App\Models\PackageFinding::count())->toBeGreaterThan(0)
        ->and($user->currentTeam->sites()->where('host', 'vulnerable.test')->value('verification_token'))->toBe(config('leakwatch.demo_verification_token'));

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('shop.bluebird.example.com');
});
