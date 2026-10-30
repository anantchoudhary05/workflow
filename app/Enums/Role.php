<?php

namespace App\Enums;

enum Role: string
{
    use EnumHelpers;

    case Admin = 'admin';
    case Manager = 'manager';
    case TeamLead = 'team_lead';
    case Developer = 'developer';
    case Reviewer = 'reviewer';
    case Marketing = 'marketing';
    case Management = 'management';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::TeamLead => 'Team Lead',
            self::Developer => 'Developer',
            self::Reviewer => 'QA / Reviewer',
            self::Marketing => 'Marketing',
            self::Management => 'Management (read-only)',
        };
    }

    /** One-line description shown on the user form and in the role matrix. */
    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access, user and role management, administrator overrides.',
            self::Manager => 'Reviews every project, sets deadlines, priority and urgency, approves work for client preview.',
            self::TeamLead => 'Plans delivery: splits work into tasks, assigns the team, works own tasks, requests manager review.',
            self::Developer => 'Works assigned tasks on member projects, logs time, records client communication.',
            self::Reviewer => 'Developer abilities plus task reviews, QA and release checklist updates.',
            self::Marketing => 'Onboards clients, captures requirements, uploads client documents, quotations, hands projects to the dev team.',
            self::Management => 'Directors / HR: read-only view of all projects, clients, team, reports and activity.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Manager => 'brand',
            self::TeamLead => 'info',
            self::Marketing => 'warning',
            self::Management => 'neutral',
            default => 'success',
        };
    }

    /**
     * Abilities granted by role. Record-level checks (project membership) are applied separately.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        $developer = ['work-tasks', 'log-time', 'record-communication', 'manage-previews', 'upload-files', 'comment'];
        $reviewer = [...$developer, 'review-tasks', 'manage-qa', 'update-release-checklist'];
        $oversight = ['view-all-projects', 'view-clients', 'view-reports', 'view-activity', 'view-team'];
        $teamLead = [
            ...$reviewer, ...$oversight,
            'manage-projects', 'manage-tasks', 'manage-milestones', 'manage-requirements', 'assess-change-requests',
            'manage-release', 'manage-client-documents', 'review-work', 'request-review', 'receive-handoffs',
        ];

        return match ($this) {
            self::Admin => ['*'],
            self::Manager => [
                ...$teamLead,
                'view-financials', 'create-projects', 'set-deadlines', 'set-urgency', 'decide-requirements',
                'decide-change-requests', 'manage-clients', 'manage-quotations', 'approve-quotations',
                'record-client-approvals', 'close-projects', 'approve-for-preview',
            ],
            self::TeamLead => $teamLead,
            self::Marketing => [
                'view-all-projects', 'view-clients', 'view-financials', 'manage-clients', 'create-projects',
                'manage-requirements', 'manage-quotations', 'record-client-approvals', 'record-communication',
                'manage-client-documents', 'manage-previews', 'upload-files', 'comment', 'hand-off-projects',
            ],
            self::Management => [...$oversight, 'view-financials'],
            self::Developer => $developer,
            self::Reviewer => $reviewer,
        };
    }

    /** Roles that may keep personal to-dos (everyone; heads use them most). */
    public function isHead(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::TeamLead, self::Marketing, self::Management], true);
    }
}
