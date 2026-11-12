<?php

namespace App\Models;

use App\Enums\FindingStatus;
use App\Enums\Severity;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One distinct problem on a site. Updated across scans rather than duplicated.
 *
 * @property int $id
 * @property int $site_id
 * @property string $check_key
 * @property string $fingerprint
 * @property Severity $severity
 * @property string $title
 * @property string $summary
 * @property array<string, mixed>|null $evidence
 * @property string $remediation
 * @property FindingStatus $status
 * @property int|null $first_seen_scan_id
 * @property int|null $last_seen_scan_id
 * @property Carbon|null $resolved_at
 * @property string|null $ignored_reason
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site $site
 */
#[Fillable([
    'site_id', 'check_key', 'fingerprint', 'severity', 'title', 'summary', 'evidence', 'remediation',
    'status', 'first_seen_scan_id', 'last_seen_scan_id', 'resolved_at', 'ignored_reason', 'notified_at',
])]
class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isOpen(): bool
    {
        return $this->status === FindingStatus::Open;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => Severity::class,
            'status' => FindingStatus::class,
            'evidence' => 'array',
            'resolved_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }
}
