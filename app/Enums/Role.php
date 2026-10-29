<?php

namespace App\Enums;

enum Role: string
{
    use EnumHelpers;

    case Admin = 'admin';
    case Developer = 'developer';
    case Reviewer = 'reviewer';
    case Management = 'management';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Developer => 'Developer',
            self::Reviewer => 'Reviewer / QA',
            self::Management => 'Management (read-only)',
        };
    }

    /** Abilities granted by role. Record-level checks (project membership) are applied separately. */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => ['*'],
            self::Management => ['view-all-projects', 'view-clients', 'view-financials', 'view-reports', 'view-activity', 'view-team'],
            self::Developer => ['work-tasks', 'log-time', 'record-communication', 'manage-previews', 'upload-files', 'comment'],
            self::Reviewer => ['work-tasks', 'review-tasks', 'log-time', 'manage-qa', 'manage-previews', 'upload-files', 'comment', 'update-release-checklist'],
        };
    }
}
