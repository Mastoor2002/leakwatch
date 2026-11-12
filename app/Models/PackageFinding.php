<?php

namespace App\Models;

use App\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Site X has package Y at a version affected by advisory Z."
 *
 * @property int $id
 * @property int $site_id
 * @property int $advisory_id
 * @property string $package_name
 * @property string $installed_version
 * @property bool $is_dev
 * @property FindingStatus $status
 * @property Carbon $first_seen_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $notified_at
 * @property-read Site $site
 * @property-read Advisory $advisory
 */
#[Fillable(['site_id', 'advisory_id', 'package_name', 'installed_version', 'is_dev', 'status', 'first_seen_at', 'resolved_at', 'notified_at'])]
class PackageFinding extends Model
{
    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'is_dev' => false,
    ];
    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Advisory, $this>
     */
    public function advisory(): BelongsTo
    {
        return $this->belongsTo(Advisory::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FindingStatus::class,
            'is_dev' => 'boolean',
            'first_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }
}
