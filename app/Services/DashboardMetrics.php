<?php

namespace App\Services;

use App\Enums\ChangeRequestStatus;
use App\Enums\PreviewStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Enums\TaskStatus;
use App\Models\ChangeRequest;
use App\Models\ClientAction;
use App\Models\Milestone;
use App\Models\Preview;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Every number on the dashboard comes from these queries, scoped to what the user may see. */
class DashboardMetrics
{
    public function __construct(private User $user) {}

    public function activeProjects(): Collection
    {
        return ProjectHealth::withData(Project::query()->visibleTo($this->user)->active())
            ->with(['client:id,name', 'manager:id,name'])->orderBy('due_date')->get()
            ->each(function (Project $p) {
                $p->health = ProjectHealth::evaluate($p);
                $p->progress = ProjectHealth::progress($p);
            });
    }

    /**
     * Dashboard KPI cards. "Active" always means ProjectStatus::active() (delivery work), the same definition used by
     * the active-projects list, the "Active (in delivery)" filter, client counts and reports.
     *
     * @return array<string, array{label: string, value: int, url: string, tone: string, icon: string, hint: string}>
     */
    public function kpis(Collection $active): array
    {
        $tasks = Task::query()->visibleTo($this->user);
        $attention = $active->filter(fn ($p) => in_array($p->health['key'], ['at_risk', 'overdue', 'blocked'], true))->count();

        return [
            'active' => ['label' => 'Active projects', 'value' => $active->count(), 'url' => route('projects.index', ['status' => 'active']), 'tone' => 'brand', 'icon' => 'rocket', 'hint' => 'Ready to start → launch'],
            'pipeline' => ['label' => 'Pipeline', 'value' => Project::query()->visibleTo($this->user)->pipeline()->count(), 'url' => route('projects.index', ['status' => 'pipeline']), 'tone' => 'violet', 'icon' => 'target', 'hint' => 'Leads, planning & approval'],
            'at_risk' => ['label' => 'At risk / overdue', 'value' => $attention, 'url' => route('projects.index', ['status' => 'active', 'health' => 'attention']), 'tone' => 'warning', 'icon' => 'alert-triangle', 'hint' => 'Active projects needing attention'],
            'due_today' => ['label' => 'Tasks due today', 'value' => (clone $tasks)->dueToday()->count(), 'url' => route('tasks.index', ['due' => 'today']), 'tone' => 'info', 'icon' => 'calendar', 'hint' => 'Open tasks due '.today()->format('d M')],
            'overdue' => ['label' => 'Overdue tasks', 'value' => (clone $tasks)->overdue()->count(), 'url' => route('tasks.index', ['due' => 'overdue']), 'tone' => 'danger', 'icon' => 'clock', 'hint' => 'Past their due date'],
            'blocked' => ['label' => 'Blocked tasks', 'value' => (clone $tasks)->where('status', TaskStatus::Blocked)->count(), 'url' => route('tasks.index', ['status' => TaskStatus::Blocked->value]), 'tone' => 'rose', 'icon' => 'lock', 'hint' => 'Waiting on a blocker'],
            'client_waiting' => ['label' => 'Awaiting client', 'value' => ClientAction::query()->visibleTo($this->user)->waiting()->count(), 'url' => route('client-actions.index'), 'tone' => 'teal', 'icon' => 'hourglass', 'hint' => 'Feedback, content & approvals'],
            'approvals' => ['label' => 'Pending approvals', 'value' => $this->pendingApprovalsCount(), 'url' => route('approvals.index'), 'tone' => 'success', 'icon' => 'badge-check', 'hint' => 'Changes, previews & quotes'],
        ];
    }

    public function pendingApprovalsCount(): int
    {
        $n = ChangeRequest::query()->visibleTo($this->user)->whereIn('status', ChangeRequestStatus::pendingDecision())->count()
            + Preview::query()->visibleTo($this->user)->whereIn('status', PreviewStatus::waiting())->count();
        if ($this->user->can('view-financials')) {
            $n += Quotation::query()->visibleTo($this->user)->whereIn('status', [QuotationStatus::InternalReview, QuotationStatus::Sent])->count();
        }

        return $n;
    }

    public function upcomingMilestones(): Collection
    {
        return Milestone::query()->visibleTo($this->user)->with('project:id,code,name')
            ->whereNotIn('status', ['completed', 'cancelled'])->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today()->addDays(14))->orderBy('due_date')->limit(8)->get();
    }

    public function blockedTasks(): Collection
    {
        return Task::query()->visibleTo($this->user)->with(['project:id,code', 'assignee:id,name'])
            ->where('status', TaskStatus::Blocked)->latest('updated_at')->limit(8)->get();
    }

    public function clientWaiting(): Collection
    {
        return ClientAction::query()->visibleTo($this->user)->waiting()->with(['client:id,name', 'project:id,code'])
            ->orderBy('requested_at')->limit(8)->get();
    }

    public function upcomingLaunches(): Collection
    {
        return Project::query()->visibleTo($this->user)->whereIn('status', [ProjectStatus::Qa, ProjectStatus::ReadyForLaunch])
            ->with('client:id,name')->orderBy('launch_date')->limit(6)->get();
    }

    public function myTasks(): Collection
    {
        return $this->user->openTasks()->with('project:id,code,name')
            ->orderByRaw('due_date is null')->orderBy('due_date')->limit(8)->get();
    }
}
