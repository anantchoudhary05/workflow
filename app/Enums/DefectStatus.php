<?php

namespace App\Enums;

enum DefectStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case Triaged = 'triaged';
    case InProgress = 'in_progress';
    case Fixed = 'fixed';
    case ReadyForRetest = 'ready_for_retest';
    case Verified = 'verified';
    case Reopened = 'reopened';
    case WontFix = 'wont_fix';

    public function label(): string
    {
        return $this === self::WontFix ? "Won't Fix" : ucwords(str_replace('_', ' ', $this->value));
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open, self::Reopened => 'danger',
            self::Triaged, self::InProgress => 'brand',
            self::Fixed, self::ReadyForRetest => 'info',
            self::Verified => 'success',
            self::WontFix => 'neutral',
        };
    }

    public static function unresolved(): array
    {
        return [self::Open, self::Triaged, self::InProgress, self::Fixed, self::ReadyForRetest, self::Reopened];
    }
}
