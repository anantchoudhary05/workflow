<?php

namespace App\Services;

use App\Enums\DefectSeverity;
use App\Models\ChangeRequest;
use App\Models\ClientAction;
use App\Models\Defect;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

/** Each report returns ['title', 'columns', 'rows'] built from live data, scoped to the user. */
class Reports
{
    public const LABELS = [
        'project-status' => 'Project status',
        'overdue-tasks' => 'Overdue tasks',
        'time-by-project' => 'Time by project',
        'client-waiting' => 'Client waiting items',
        'change-requests' => 'Change requests',
        'defects' => 'Defects by project',
        'quotation-pipeline' => 'Quotation pipeline',
        'team-workload' => 'Team workload',
    ];

    public function __construct(private User $user, private Carbon $from, private Carbon $to) {}

    public static function available(User $user): array
    {
        return collect(self::LABELS)->when(! $user->can('view-financials'), fn ($c) => $c->except('quotation-pipeline'))->all();
    }

    public function build(string $key): array
    {
        $method = str($key)->camel()->toString();

        return ['title' => self::LABELS[$key]] + $this->{$method}();
    }

    private function projectStatus(): array
    {
        $rows = ProjectHealth::withData(Project::visibleTo($this->user)->whereNotIn('status', ['closed', 'cancelled']))
            ->with(['client:id,name', 'manager:id,name'])->orderBy('code')->get()
            ->map(function ($p) {
                $h = ProjectHealth::evaluate($p);
                $pr = ProjectHealth::progress($p);

                return [$p->code, $p->name, $p->client?->name, $p->manager?->name, $p->status->label(), $h['label'], $pr['percent'] !== null ? $pr['percent'].'%' : '',
                    $p->due_date?->toDateString(), $p->tasks_overdue, $p->tasks_blocked, implode('; ', $h['reasons'])];
            });

        return ['columns' => ['Code', 'Project', 'Client', 'Manager', 'Status', 'Health', 'Progress', 'Due', 'Overdue tasks', 'Blocked tasks', 'Reasons'], 'rows' => $rows->all()];
    }

    private function overdueTasks(): array
    {
        $rows = Task::visibleTo($this->user)->overdue()->with(['project:id,code', 'assignee:id,name'])->orderBy('due_date')->get()
            ->map(fn ($t) => [$t->project->code, $t->title, $t->assignee?->name ?? 'Unassigned', $t->status->label(), $t->priority->label(), $t->due_date->toDateString(), (int) $t->due_date->diffInDays(today())]);

        return ['columns' => ['Project', 'Task', 'Assignee', 'Status', 'Priority', 'Due', 'Days overdue'], 'rows' => $rows->all()];
    }

    private function timeByProject(): array
    {
        $sums = TimeEntry::visibleTo($this->user)->whereBetween('work_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw('project_id, sum(hours) as hours, sum(case when billable = 1 then hours else 0 end) as billable')
            ->groupBy('project_id')->get()->keyBy('project_id');
        $rows = Project::whereIn('id', $sums->keys())->orderBy('code')->get()
            ->map(fn ($p) => [$p->code, $p->name, round((float) $sums[$p->id]->hours, 2), round((float) $sums[$p->id]->billable, 2), $p->estimated_hours !== null ? (float) $p->estimated_hours : '']);

        return ['columns' => ['Code', 'Project', 'Hours', 'Billable hours', 'Estimated hours (project)'], 'rows' => $rows->all()];
    }

    private function clientWaiting(): array
    {
        $rows = ClientAction::visibleTo($this->user)->waiting()->with(['client:id,name', 'project:id,code'])->orderBy('requested_at')->get()
            ->map(fn ($a) => [$a->client->name, $a->project?->code, $a->title, $a->type->label(), $a->requested_at->toDateString(), $a->daysWaiting(), $a->next_follow_up_at?->toDateString()]);

        return ['columns' => ['Client', 'Project', 'Item', 'Type', 'Requested', 'Days waiting', 'Next follow-up'], 'rows' => $rows->all()];
    }

    private function changeRequests(): array
    {
        $fin = $this->user->can('view-financials');
        $rows = ChangeRequest::visibleTo($this->user)->whereBetween('received_at', [$this->from->toDateString(), $this->to->toDateString()])
            ->with('project:id,code')->orderBy('received_at')->get()
            ->map(fn ($c) => array_merge([$c->number, $c->project->code, $c->title, $c->classification?->label() ?? 'Unclassified', $c->status->label(), $c->extra_hours, $c->timeline_impact_days],
                $fin ? [$c->cost_impact] : [], [$c->received_at->toDateString()]));
        $cols = array_merge(['Number', 'Project', 'Title', 'Classification', 'Status', 'Extra hours', 'Timeline impact (days)'], $fin ? ['Cost impact'] : [], ['Received']);

        return ['columns' => $cols, 'rows' => $rows->all()];
    }

    private function defects(): array
    {
        $counts = Defect::visibleTo($this->user)->unresolved()->selectRaw('project_id, severity, count(*) as n')->groupBy('project_id', 'severity')->get()->groupBy('project_id');
        $rows = Project::whereIn('id', $counts->keys())->orderBy('code')->get()->map(function ($p) use ($counts) {
            $by = $counts[$p->id]->pluck('n', 'severity');

            return array_merge([$p->code, $p->name], array_map(fn ($s) => (int) ($by[$s->value] ?? 0), DefectSeverity::cases()), [(int) $by->sum()]);
        });

        return ['columns' => array_merge(['Code', 'Project'], array_map(fn ($s) => $s->label(), DefectSeverity::cases()), ['Total open']), 'rows' => $rows->all()];
    }

    private function quotationPipeline(): array
    {
        abort_unless($this->user->can('view-financials'), 403);
        $rows = Quotation::visibleTo($this->user)->with('project:id,code')->whereNotIn('status', ['superseded'])->orderBy('number')->get()
            ->map(fn ($q) => [$q->reference(), $q->project->code, $q->title, $q->status->label(), $q->currency, Money::format($q->total, $q->currency), Money::format($q->internal_cost_total, $q->currency), $q->sent_at?->toDateString(), $q->decided_at?->toDateString()]);

        return ['columns' => ['Quotation', 'Project', 'Title', 'Status', 'Currency', 'Total', 'Internal cost', 'Sent', 'Decided'], 'rows' => $rows->all()];
    }

    private function teamWorkload(): array
    {
        abort_unless($this->user->can('view-team'), 403);
        $rows = TeamWorkload::rows($this->user->accessibleProjectIds())
            ->map(fn ($r) => [$r->user->name, $r->user->role->label(), $r->open, $r->in_progress, $r->blocked, $r->overdue, $r->estimated_remaining, $r->logged_this_week, $r->capacity ?: '', $r->load !== null ? $r->load.'%' : '']);

        return ['columns' => ['Person', 'Role', 'Open tasks', 'In progress', 'Blocked', 'Overdue', 'Est. hours remaining', 'Logged this week', 'Weekly capacity', 'Load'], 'rows' => $rows->all()];
    }
}
