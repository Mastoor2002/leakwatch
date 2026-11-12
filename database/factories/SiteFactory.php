<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        $host = fake()->unique()->domainWord().'.example.com';

        return [
            'client_id' => Client::factory(),
            'team_id' => fn (array $attributes) => Client::find($attributes['client_id'])->team_id,
            'url' => "https://{$host}",
            'host' => $host,
            'scan_frequency' => 'weekly',
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verified_at' => now(),
            'verification_method' => 'dns',
        ]);
    }

    public function forUrl(string $url): static
    {
        return $this->state(fn () => [
            'url' => $url,
            'host' => (string) parse_url($url, PHP_URL_HOST),
        ]);
    }
}
