<?php

namespace App\Enums;

enum TestCaseStatus: string
{
    use EnumHelpers;

    case NotRun = 'not_run';
    case Passed = 'passed';
    case Failed = 'failed';
    case Blocked = 'blocked';
    case NeedsRetest = 'needs_retest';
    case NotApplicable = 'not_applicable';

    public function tone(): string
    {
        return match ($this) {
            self::Passed => 'success',
            self::Failed => 'danger',
            self::Blocked, self::NeedsRetest => 'warning',
            default => 'neutral',
        };
    }
}
