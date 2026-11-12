<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One report from the Leakwatch agent: the packages installed on a site at a point in time.
 *
 * @property int $id
 * @property int $site_id
 * @property string|null $lock_hash
 * @property string|null $php_version
 * @property string|null $laravel_version
 * @property int $package_count
 * @property Carbon $reported_at
 * @property Carbon|null $analyzed_at
 * @property-read Site $site
 * @property-read Collection<int, InstalledPackage> $packages
 */
#[Fillable(['site_id', 'lock_hash', 'php_version', 'laravel_version', 'package_count', 'reported_at', 'analyzed_at'])]
class DependencySnapshot extends Model
{
    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return HasMany<InstalledPackage, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(InstalledPackage::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'package_count' => 'integer',
        ];
    }
}
