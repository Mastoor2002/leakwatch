<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A known vulnerability in a Composer package, cached from Packagist.
 *
 * @property int $id
 * @property string $advisory_id
 * @property string $package_name
 * @property string $title
 * @property string|null $cve
 * @property string|null $link
 * @property string $affected_versions
 * @property string|null $severity
 * @property Carbon|null $reported_at
 * @property-read Collection<int, PackageFinding> $packageFindings
 */
#[Fillable(['advisory_id', 'package_name', 'title', 'cve', 'link', 'affected_versions', 'severity', 'reported_at'])]
class Advisory extends Model
{
    /**
     * @return HasMany<PackageFinding, $this>
     */
    public function packageFindings(): HasMany
    {
        return $this->hasMany(PackageFinding::class);
    }

    public function severityColor(): string
    {
        return match (strtolower((string) $this->severity)) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'sky',
            default => 'zinc',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
        ];
    }
}
