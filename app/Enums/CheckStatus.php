<?php

namespace App\Enums;

enum CheckStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Passed',
            self::Failed => 'Problem found',
            self::Skipped => 'Skipped',
            self::Error => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Passed => 'green',
            self::Failed => 'red',
            self::Skipped => 'zinc',
            self::Error => 'amber',
        };
    }

    /**
     * Whether the check actually looked at the site (so an absent finding means "fixed").
     */
    public function didRun(): bool
    {
        return $this === self::Passed || $this === self::Failed;
    }
}
