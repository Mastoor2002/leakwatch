<?php

namespace Database\Factories;

use App\Models\Finding;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finding>
 */
class FindingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'check_key' => 'security_headers',
            'fingerprint' => hash('sha256', fake()->uuid()),
            'severity' => 'low',
            'title' => 'Missing X-Content-Type-Options: nosniff',
            'summary' => 'Browsers may guess file types.',
            'evidence' => ['header' => 'X-Content-Type-Options'],
            'remediation' => 'Add the header.',
            'status' => 'open',
        ];
    }
}
