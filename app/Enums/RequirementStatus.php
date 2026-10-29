<?php

namespace App\Enums;

enum RequirementStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case UnderDiscussion = 'under_discussion';
    case ClarificationRequired = 'clarification_required';
    case Estimated = 'estimated';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Deferred = 'deferred';
    case Implemented = 'implemented';
    case Verified = 'verified';

    public function tone(): string
    {
        return match ($this) {
            self::ClarificationRequired => 'warning',
            self::Approved, self::Verified => 'success',
            self::Implemented => 'brand',
            self::Rejected => 'danger',
            self::Estimated, self::UnderDiscussion => 'info',
            default => 'neutral',
        };
    }

    /** Approval-type statuses need a recorded decision note/source. */
    public function isDecision(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Deferred], true);
    }
}
