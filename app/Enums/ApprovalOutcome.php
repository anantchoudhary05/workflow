<?php

namespace App\Enums;

enum ApprovalOutcome: string
{
    use EnumHelpers;

    case Approved = 'approved';
    case ApprovedWithConditions = 'approved_with_conditions';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';

    public function tone(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::ApprovedWithConditions => 'info',
            self::ChangesRequested => 'warning',
            self::Rejected => 'danger',
        };
    }

    public function isPositive(): bool
    {
        return in_array($this, [self::Approved, self::ApprovedWithConditions], true);
    }
}
