<?php

namespace App\Models;

use App\Enums\CheckStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One check inside one scan. status = null means "not run yet".
 *
 * @property int $id
 * @property int $scan_id
 * @property string $check_key
 * @property string $check_class
 * @property int $position
 * @property CheckStatus|null $status
 * @property array<string, mixed>|null $result
 * @property int|null $duration_ms
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Scan $scan
 */
#[Fillable(['scan_id', 'check_key', 'check_class', 'position', 'status', 'result', 'duration_ms', 'error'])]
class ScanCheckRun extends Model
{
    /**
     * @return BelongsTo<Scan, $this>
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function checkName(): string
    {
        $class = $this->check_class;

        return class_exists($class) ? app($class)->name() : $this->check_key;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CheckStatus::class,
            'result' => 'array',
            'duration_ms' => 'integer',
            'position' => 'integer',
        ];
    }
}
