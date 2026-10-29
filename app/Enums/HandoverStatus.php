<?php

namespace App\Enums;

enum HandoverStatus: string
{
    use EnumHelpers;

    case ReadyForHandover = 'ready_for_handover';
    case InProgress = 'handover_in_progress';
    case AwaitingAcceptance = 'awaiting_acceptance';
    case Accepted = 'accepted';
    case Closed = 'closed';
    case Reopened = 'reopened';

    public function tone(): string
    {
        return match ($this) {
            self::Accepted, self::Closed => 'success',
            self::AwaitingAcceptance => 'warning',
            self::Reopened => 'danger',
            default => 'info',
        };
    }
}
