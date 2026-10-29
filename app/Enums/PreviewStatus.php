<?php

namespace App\Enums;

enum PreviewStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case ReadyToShare = 'ready_to_share';
    case Shared = 'shared';
    case AwaitingFeedback = 'awaiting_feedback';
    case FeedbackReceived = 'feedback_received';
    case ChangesRequired = 'changes_required';
    case Approved = 'approved';
    case Superseded = 'superseded';

    public function tone(): string
    {
        return match ($this) {
            self::Shared, self::AwaitingFeedback => 'warning',
            self::ChangesRequired => 'danger',
            self::Approved => 'success',
            self::FeedbackReceived, self::ReadyToShare => 'info',
            default => 'neutral',
        };
    }

    public static function waiting(): array
    {
        return [self::Shared, self::AwaitingFeedback];
    }
}
