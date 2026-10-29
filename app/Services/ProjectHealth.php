<?php

namespace App\Services;

use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * Health and progress are derived from records, never typed in.
 * Rules are documented in docs/WORKFLOW_GUIDE.md ("Project health").
 */
class ProjectHealth
{
    public const AT_RISK_DAYS = 7;

    public const CLIENT_WAIT_DAYS = 5;

    /** Adds every aggregate needed by evaluate()/progress() in a single query. */
    public static function withData(Builder $query): Builder
    {
        $open = array_map(fn ($s) => $s->value, TaskStatus::open());

        return $query
            ->withSum(['milestones as weight_total' => fn ($q) => $q->where('status', '!=', MilestoneStatus::Cancelled->value)], 'weight')
            ->withSum(['milestones as weight_done' => fn ($q) => $q->where('status', MilestoneStatus::Completed->value)], 'weight')
            ->withCount([
                'tasks as tasks_total' => fn ($q) => $q->where('status', '!=', TaskStatus::Cancelled->value),
                'tasks as tasks_done' => fn ($q) => $q->where('status', TaskStatus::Done->value),
                'tasks as tasks_blocked' => fn ($q) => $q->where('status', TaskStatus::Blocked->value),
                'tasks as tasks_overdue' => fn ($q) => $q->whereIn('status', $open)->whereDate('due_date', '<', today()),
                'milestones as milestones_overdue' => fn ($q) => $q->whereNotIn('status', [MilestoneStatus::Completed->value, MilestoneStatus::Cancelled->value])->whereDate('due_date', '<', today()),
                'clientActions as client_waiting' => fn ($q) => $q->where('status', 'waiting'),
                'clientActions as client_waiting_long' => fn ($q) => $q->where('status', 'waiting')->whereDate('requested_at', '<=', today()->subDays(self::CLIENT_WAIT_DAYS)),
                'defects as critical_defects' => fn ($q) => $q->where('severity', DefectSeverity::Critical->value)->whereIn('status', array_map(fn ($s) => $s->value, DefectStatus::unresolved())),
            ]);
    }

    /** @return array{percent: ?int, basis: string} */
    public static function progress(Project $p): array
    {
        self::ensureLoaded($p);
        if ($p->status === ProjectStatus::Closed) {
            return ['percent' => 100, 'basis' => 'Project closed'];
        }
        if ((int) $p->weight_total > 0) {
            $pct = (int) floor($p->weight_done / $p->weight_total * 100);
            $basis = 'Milestone-weighted: '.(int) $p->weight_done.' of '.(int) $p->weight_total.' weight points complete';
        } elseif ((int) $p->tasks_total > 0) {
            $pct = (int) floor($p->tasks_done / $p->tasks_total * 100);
            $basis = "Task completion: {$p->tasks_done} of {$p->tasks_total} tasks done (no milestones defined)";
        } else {
            return ['percent' => null, 'basis' => 'No milestones or tasks yet'];
        }
        if ($pct >= 100 && $p->status !== ProjectStatus::Delivered) {
            $pct = 95;
            $basis .= '. Capped at 95% until delivery, QA and acceptance are recorded';
        }

        return ['percent' => $pct, 'basis' => $basis];
    }

    /** @return array{key: string, label: string, tone: string, reasons: array<string>} */
    public static function evaluate(Project $p): array
    {
        self::ensureLoaded($p);
        $status = $p->status;
        if (in_array($status, [ProjectStatus::Delivered, ProjectStatus::Closed], true)) {
            return self::make('completed', 'Completed', 'success', []);
        }
        if ($status === ProjectStatus::Cancelled) {
            return self::make('cancelled', 'Cancelled', 'neutral', []);
        }

        $reasons = [];
        if ($status === ProjectStatus::Blocked) {
            return self::make('blocked', 'Blocked', 'danger', ['Project status is Blocked']);
        }

        $overdue = [];
        if ($p->due_date && $p->due_date->lt(today())) {
            $overdue[] = 'Agreed delivery date '.$p->due_date->format('d M Y').' has passed';
        }
        if ($p->milestones_overdue > 0) {
            $overdue[] = "{$p->milestones_overdue} milestone(s) overdue";
        }
        if ($overdue) {
            return self::make('overdue', 'Overdue', 'danger', $overdue);
        }

        if ($p->tasks_blocked > 0) {
            $reasons[] = "{$p->tasks_blocked} blocked task(s)";
        }
        if ($p->tasks_overdue > 0) {
            $reasons[] = "{$p->tasks_overdue} overdue task(s)";
        }
        if ($p->critical_defects > 0) {
            $reasons[] = "{$p->critical_defects} unresolved critical defect(s)";
        }
        if ($p->client_waiting_long > 0) {
            $reasons[] = "{$p->client_waiting_long} client item(s) waiting > ".self::CLIENT_WAIT_DAYS.' days';
        }
        $progress = self::progress($p)['percent'];
        if ($p->due_date && $p->due_date->lte(today()->addDays(self::AT_RISK_DAYS)) && ($progress ?? 0) < 80) {
            $reasons[] = 'Delivery within '.self::AT_RISK_DAYS.' days with '.($progress ?? 0).'% progress';
        }
        if ($reasons) {
            return self::make('at_risk', 'At Risk', 'warning', $reasons);
        }
        if (in_array($status, [ProjectStatus::Lead, ProjectStatus::Planning, ProjectStatus::AwaitingApproval, ProjectStatus::OnHold], true)) {
            return self::make('not_started', $status === ProjectStatus::OnHold ? 'On Hold' : 'Not Started', 'neutral', []);
        }

        return self::make('on_track', 'On Track', 'success', []);
    }

    public static function nextAction(Project $p): string
    {
        return match ($p->status) {
            ProjectStatus::Lead => 'Document requirements and move to Planning',
            ProjectStatus::Planning => $p->approved_scope_version_id ? 'Prepare and send the quotation' : 'Approve a scope baseline',
            ProjectStatus::AwaitingApproval => $p->approved_quotation_id ? 'Move to Ready to Start' : 'Record quotation approval',
            ProjectStatus::ReadyToStart => 'Plan milestones and assign tasks, then start',
            ProjectStatus::InProgress => 'Deliver milestones; share previews for feedback',
            ProjectStatus::Blocked => 'Resolve blockers',
            ProjectStatus::OnHold => 'Decide whether to resume',
            ProjectStatus::InReview => 'Collect client feedback on the preview',
            ProjectStatus::Qa => 'Execute test cases and close critical defects',
            ProjectStatus::ReadyForLaunch => 'Complete the release checklist and launch',
            ProjectStatus::Delivered => 'Complete handover and record client acceptance',
            ProjectStatus::Closed, ProjectStatus::Cancelled => 'None',
        };
    }

    private static function make(string $key, string $label, string $tone, array $reasons): array
    {
        return compact('key', 'label', 'tone', 'reasons');
    }

    private static function ensureLoaded(Project $p): void
    {
        if (! array_key_exists('tasks_total', $p->getAttributes())) {
            $fresh = self::withData(Project::withTrashed()->whereKey($p->id))->first();
            foreach (['weight_total', 'weight_done', 'tasks_total', 'tasks_done', 'tasks_blocked', 'tasks_overdue', 'milestones_overdue', 'client_waiting', 'client_waiting_long', 'critical_defects'] as $k) {
                $p->setAttribute($k, $fresh?->{$k} ?? 0);
            }
        }
    }
}
