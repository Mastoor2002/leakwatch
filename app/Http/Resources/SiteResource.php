<?php

namespace App\Http\Resources;

use App\Enums\Severity;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $open = $this->relationLoaded('openFindings') ? $this->openFindings : $this->openFindings()->get();

        $counts = [];

        foreach (Severity::ordered() as $severity) {
            $counts[$severity->value] = $open->where('severity', $severity)->count();
        }

        $activeScan = $this->activeScan();

        return [
            'id' => $this->id,
            'url' => $this->url,
            'host' => $this->host,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'is_laravel' => $this->is_laravel,
            'is_verified' => $this->isVerified(),
            'verification' => [
                'token' => $this->verification_token,
                'dns_record' => config('leakwatch.verification.dns_prefix').$this->verification_token,
                'file_path' => config('leakwatch.verification.file_path'),
            ],
            'score' => $this->score,
            'grade' => $this->grade,
            'scan_frequency' => $this->scan_frequency->value,
            'last_scanned_at' => $this->last_scanned_at?->toIso8601String(),
            'open_findings' => $open->count(),
            'open_by_severity' => $counts,
            'active_scan' => $activeScan ? new ScanResource($activeScan) : null,
            'agent' => [
                'connected' => $this->agent_token_hash !== null,
                'last_seen_at' => $this->agent_last_seen_at?->toIso8601String(),
            ],
            'findings' => FindingResource::collection($this->whenLoaded('findings')),
            'package_findings' => PackageFindingResource::collection($this->whenLoaded('packageFindings')),
            'last_scan' => $this->whenLoaded('lastScan', fn () => $this->lastScan ? new ScanResource($this->lastScan) : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
