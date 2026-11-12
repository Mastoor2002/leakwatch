<?php

namespace App\Models;

use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use Database\Factories\ScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property ScanStatus $status
 * @property ScanTrigger $trigger
 * @property array<string, mixed>|null $context
 * @property int|null $score
 * @property string|null $grade
 * @property int $new_findings_count
 * @property int $resolved_findings_count
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site $site
 * @property-read Collection<int, ScanCheckRun> $checkRuns
 */
#[Fillable([
    'site_id', 'status', 'trigger', 'context', 'score', 'grade', 'new_findings_count',
    'resolved_findings_count', 'error', 'started_at', 'finished_at',
])]
class Scan extends Model
{
    /** @use HasFactory<ScanFactory> */
    use HasFactory;

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'trigger' => 'manual',
        'new_findings_count' => 0,
        'resolved_findings_count' => 0,
    ];

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return HasMany<ScanCheckRun, $this>
     */
    public function checkRuns(): HasMany
    {
        return $this->hasMany(ScanCheckRun::class)->orderBy('position');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Percentage of checks finished, for the progress bar.
     */
    public function progress(): int
    {
        $total = $this->checkRuns()->count();

        if ($total === 0) {
            return $this->status === ScanStatus::Completed ? 100 : 0;
        }

        $done = $this->checkRuns()->whereNotNull('status')->count();

        return (int) round($done / $total * 100);
    }

    public function durationForHumans(): ?string
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        $seconds = max(0, (int) $this->started_at->diffInSeconds($this->finished_at));

        return $seconds < 60 ? "{$seconds}s" : intdiv($seconds, 60).'m '.($seconds % 60).'s';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScanStatus::class,
            'trigger' => ScanTrigger::class,
            'context' => 'array',
            'score' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
