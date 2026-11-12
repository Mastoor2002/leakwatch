<?php

namespace Database\Factories;

use App\Models\Scan;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scan>
 */
class ScanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'status' => 'queued',
            'trigger' => 'manual',
        ];
    }

    public function completed(int $score = 90, string $grade = 'A'): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'score' => $score,
            'grade' => $grade,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
