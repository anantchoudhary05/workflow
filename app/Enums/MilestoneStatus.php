<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    use EnumHelpers;

    case Planned = 'planned';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function tone(): string
    {
        return match ($this) {
            self::InProgress, self::Ready => 'brand',
            self::AwaitingApproval => 'warning',
            self::Completed => 'success',
            default => 'neutral',
        };
    }
}
