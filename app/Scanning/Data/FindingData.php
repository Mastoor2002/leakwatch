<?php

namespace App\Scanning\Data;

use App\Enums\Severity;

/**
 * A problem a check found, before it is saved as a Finding.
 *
 * $detail makes the fingerprint unique when one check can report several
 * problems (e.g. one finding per exposed URL).
 */
readonly class FindingData
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public Severity $severity,
        public string $title,
        public string $summary,
        public array $evidence = [],
        public string $detail = '',
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'evidence' => $this->evidence,
            'detail' => $this->detail,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            severity: Severity::from($data['severity']),
            title: (string) $data['title'],
            summary: (string) $data['summary'],
            evidence: (array) ($data['evidence'] ?? []),
            detail: (string) ($data['detail'] ?? ''),
        );
    }

    public function fingerprint(string $checkKey): string
    {
        return hash('sha256', $checkKey.'|'.$this->detail);
    }
}
