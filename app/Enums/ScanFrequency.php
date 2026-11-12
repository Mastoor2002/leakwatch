<?php

namespace App\Enums;

use Carbon\CarbonInterval;

enum ScanFrequency: string
{
    case Off = 'off';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function interval(): ?CarbonInterval
    {
        return match ($this) {
            self::Off => null,
            self::Daily => CarbonInterval::day(),
            self::Weekly => CarbonInterval::week(),
        };
    }
}
