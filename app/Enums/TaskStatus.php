<?php

namespace App\Enums;

enum TaskStatus: string
{
    use EnumHelpers;

    case Backlog = 'backlog';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function tone(): string
    {
        return match ($this) {
            self::InProgress => 'brand',
            self::Blocked => 'danger',
            self::InReview => 'info',
            self::ChangesRequested => 'warning',
            self::Done => 'success',
            default => 'neutral',
        };
    }

    public static function open(): array
    {
        return [self::Backlog, self::Ready, self::InProgress, self::Blocked, self::InReview, self::ChangesRequested];
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Backlog => [self::Ready, self::InProgress, self::Cancelled],
            self::Ready => [self::Backlog, self::InProgress, self::Blocked, self::Cancelled],
            self::InProgress => [self::Blocked, self::InReview, self::Ready, self::Done, self::Cancelled],
            self::Blocked => [self::InProgress, self::Ready, self::Cancelled],
            self::InReview => [self::Done, self::ChangesRequested, self::InProgress],
            self::ChangesRequested => [self::InProgress, self::Blocked, self::InReview],
            self::Done => [self::InProgress],
            self::Cancelled => [self::Backlog],
        };
    }
}
