<?php

namespace App\Http\Resources;

use App\Models\Finding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Finding
 */
class FindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'check_key' => $this->check_key,
            'severity' => $this->severity->value,
            'severity_rank' => $this->severity->rank(),
            'title' => $this->title,
            'summary' => $this->summary,
            'evidence' => $this->evidence ?? [],
            'remediation' => $this->remediation,
            'status' => $this->status->value,
            'ignored_reason' => $this->ignored_reason,
            'first_seen_at' => $this->created_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
