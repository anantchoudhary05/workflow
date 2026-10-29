<?php

namespace App\Enums;

enum QuotationStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case InternalReview = 'internal_review';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function tone(): string
    {
        return match ($this) {
            self::InternalReview, self::Sent => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            default => 'neutral',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::InternalReview], true);
    }
}
