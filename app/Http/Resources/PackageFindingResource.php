<?php

namespace App\Http\Resources;

use App\Models\PackageFinding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PackageFinding
 */
class PackageFindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_name' => $this->package_name,
            'installed_version' => $this->installed_version,
            'is_dev' => $this->is_dev,
            'status' => $this->status->value,
            'advisory' => $this->whenLoaded('advisory', fn () => [
                'id' => $this->advisory->advisory_id,
                'title' => $this->advisory->title,
                'cve' => $this->advisory->cve,
                'link' => $this->advisory->link,
                'severity' => $this->advisory->severity,
                'affected_versions' => $this->advisory->affected_versions,
            ]),
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
        ];
    }
}
