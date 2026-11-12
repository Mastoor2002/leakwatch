<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dependency_snapshot_id
 * @property string $name
 * @property string $version
 * @property bool $is_dev
 * @property-read DependencySnapshot $snapshot
 */
#[Fillable(['dependency_snapshot_id', 'name', 'version', 'is_dev'])]
class InstalledPackage extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<DependencySnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DependencySnapshot::class, 'dependency_snapshot_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_dev' => 'boolean',
        ];
    }
}
