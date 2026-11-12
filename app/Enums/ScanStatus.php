<?php

namespace App\Enums;

enum ScanStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'zinc',
            self::Running => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
