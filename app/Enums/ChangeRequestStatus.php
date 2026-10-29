<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    use EnumHelpers;

    case Submitted = 'submitted';
    case UnderAssessment = 'under_assessment';
    case EstimateRequired = 'estimate_required';
    case AwaitingInternalReview = 'awaiting_internal_review';
    case AwaitingClientApproval = 'awaiting_client_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Deferred = 'deferred';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Implemented = 'implemented';
    case Verified = 'verified';
    case Cancelled = 'cancelled';

    public function tone(): string
    {
        return match ($this) {
            self::AwaitingInternalReview, self::AwaitingClientApproval, self::EstimateRequired => 'warning',
            self::Approved, self::Verified, self::Implemented => 'success',
            self::Rejected, self::Cancelled => 'danger',
            self::Scheduled, self::InProgress => 'brand',
            default => 'neutral',
        };
    }

    public static function pendingDecision(): array
    {
        return [self::AwaitingInternalReview, self::AwaitingClientApproval];
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderAssessment, self::Cancelled],
            self::UnderAssessment => [self::EstimateRequired, self::AwaitingInternalReview, self::Scheduled, self::Rejected, self::Deferred, self::Cancelled],
            self::EstimateRequired => [self::AwaitingInternalReview, self::Cancelled],
            self::AwaitingInternalReview => [self::AwaitingClientApproval, self::Approved, self::Rejected, self::UnderAssessment],
            self::AwaitingClientApproval => [self::Approved, self::Rejected, self::Deferred],
            self::Approved => [self::Scheduled, self::InProgress],
            self::Deferred => [self::UnderAssessment, self::Cancelled],
            self::Scheduled => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Implemented],
            self::Implemented => [self::Verified, self::InProgress],
            self::Rejected, self::Verified, self::Cancelled => [],
        };
    }
}
