<?php

namespace App\Enums;

enum Severity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Higher rank = more severe. Used for sorting.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 5,
            self::High => 4,
            self::Medium => 3,
            self::Low => 2,
            self::Info => 1,
        };
    }

    /**
     * Points subtracted from the site score for each open finding.
     */
    public function penalty(): int
    {
        return (int) config("leakwatch.score_penalties.{$this->value}", 0);
    }

    /**
     * Flux badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::High => 'orange',
            self::Medium => 'yellow',
            self::Low => 'sky',
            self::Info => 'zinc',
        };
    }

    public function isAlertWorthy(): bool
    {
        return $this->rank() >= self::High->rank();
    }

    /**
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return [self::Critical, self::High, self::Medium, self::Low, self::Info];
    }
}
