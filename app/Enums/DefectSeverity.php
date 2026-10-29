<?php

namespace App\Enums;

enum DefectSeverity: string
{
    use EnumHelpers;

    case Critical = 'critical';
    case Major = 'major';
    case Minor = 'minor';
    case Trivial = 'trivial';

    public function tone(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Major => 'warning',
            self::Minor => 'info',
            self::Trivial => 'neutral',
        };
    }
}
