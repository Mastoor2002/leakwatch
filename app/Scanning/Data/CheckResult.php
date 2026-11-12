<?php

namespace App\Scanning\Data;

use App\Enums\CheckStatus;

readonly class CheckResult
{
    /**
     * @param  array<int, FindingData>  $findings
     */
    private function __construct(
        public CheckStatus $status,
        public array $findings = [],
        public ?string $note = null,
    ) {
        //
    }

    public static function passed(?string $note = null): self
    {
        return new self(CheckStatus::Passed, [], $note);
    }

    public static function failed(FindingData ...$findings): self
    {
        return $findings === []
            ? self::passed()
            : new self(CheckStatus::Failed, array_values($findings));
    }

    /**
     * @param  array<int, FindingData>  $findings
     */
    public static function fromFindings(array $findings): self
    {
        return self::failed(...$findings);
    }

    public static function skipped(string $reason): self
    {
        return new self(CheckStatus::Skipped, [], $reason);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'note' => $this->note,
            'findings' => array_map(fn (FindingData $finding) => $finding->toArray(), $this->findings),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            CheckStatus::from($data['status']),
            array_map(fn (array $finding) => FindingData::fromArray($finding), $data['findings'] ?? []),
            $data['note'] ?? null,
        );
    }
}
