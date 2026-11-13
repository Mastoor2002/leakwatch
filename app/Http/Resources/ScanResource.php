<?php

namespace App\Http\Resources;

use App\Models\Scan;
use App\Models\ScanCheckRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Scan
 */
class ScanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'is_active' => $this->isActive(),
            'trigger' => $this->trigger->value,
            'progress' => $this->progress(),
            'score' => $this->score,
            'grade' => $this->grade,
            'new_findings_count' => $this->new_findings_count,
            'resolved_findings_count' => $this->resolved_findings_count,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration' => $this->durationForHumans(),
            'created_at' => $this->created_at?->toIso8601String(),
            'checks' => $this->whenLoaded('checkRuns', fn () => $this->checkRuns->map(fn (ScanCheckRun $run) => [
                'key' => $run->check_key,
                'name' => $run->checkName(),
                'status' => $run->status?->value,
                'duration_ms' => $run->duration_ms,
                'error' => $run->error,
            ])->values()),
        ];
    }
}
