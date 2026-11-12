<?php

namespace App\Enums;

enum ScanTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
