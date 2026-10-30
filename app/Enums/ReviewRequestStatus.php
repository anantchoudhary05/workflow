<?php

namespace App\Enums;

enum ReviewRequestStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case ReadyForPreview = 'ready_for_preview';
    case NeedsCorrections = 'needs_corrections';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting manager review',
            self::ReadyForPreview => 'Ready for client preview',
            self::NeedsCorrections => 'Needs corrections',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::ReadyForPreview => 'success',
            self::NeedsCorrections => 'danger',
        };
    }
}
