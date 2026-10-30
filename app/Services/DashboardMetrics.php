<?php

namespace App\Services;

use App\Enums\ChangeRequestStatus;
use App\Enums\PreviewStatus;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Enums\ReviewRequestStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\ClientAction;
use App\Models\ClientDocument;
use App\Models\Milestone;
use App\Models\Preview;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\ReviewRequest;
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

    /**
     * The KPI cards for the user's role, in display order. Administrators keep the full delivery overview.
     *
     * @return array<string, array{label: string, value: int, url: string, tone: string, icon: string, hint: string}>
     */
    public function kpisForRole(Collection $active): array
    {
        $all = $this->kpis($active) + $this->roleKpis();
        $keys = match ($this->user->role) {
            Role::Admin => ['active', 'pipeline', 'at_risk', 'urgent', 'overdue', 'blocked', 'client_waiting', 'approvals'],
            Role::Manager => ['active', 'at_risk', 'urgent', 'reviews', 'overdue', 'blocked', 'approvals', 'client_waiting'],
            Role::TeamLead => ['active', 'urgent', 'handoffs', 'unassigned', 'due_today', 'overdue', 'blocked', 'my_reviews'],
            Role::Marketing => ['pipeline', 'clients', 'my_handoffs', 'client_waiting'],
            Role::Management => ['active', 'pipeline', 'at_risk', 'overdue', 'blocked', 'client_waiting', 'approvals', 'team'],
            default => ['my_open', 'urgent', 'my_due_today', 'my_overdue'],
        };

        $order = array_flip($keys);

        return array_intersect_key(array_replace($order, $all), $order);
    }

    /**
     * Extra KPI cards used by the role dashboards.
     *
     * @return array<string, array{label: string, value: int, url: string, tone: string, icon: string, hint: string}>
     */
    private function roleKpis(): array
    {
        $tasks = Task::query()->visibleTo($this->user)->open();
        $mineOnly = ! $this->user->hasAbility('manage-tasks');

        return [
            'urgent' => ['label' => 'Urgent', 'value' => $this->urgentTasks(100)->count(), 'url' => route('tasks.index', array_filter(['priority' => 'urgent', 'assignee' => $mineOnly ? $this->user->id : null])), 'tone' => 'danger', 'icon' => 'zap', 'hint' => $mineOnly ? 'Urgent tasks assigned to you' : 'Open tasks marked Urgent'],
            'reviews' => ['label' => 'Awaiting your review', 'value' => ReviewRequest::query()->visibleTo($this->user)->where('status', ReviewRequestStatus::Pending)->count(), 'url' => route('reviews.index'), 'tone' => 'violet', 'icon' => 'eye', 'hint' => 'Ready for preview?'],
            'my_reviews' => ['label' => 'Reviews pending', 'value' => ReviewRequest::query()->where('requested_by', $this->user->id)->where('status', ReviewRequestStatus::Pending)->count(), 'url' => route('reviews.index'), 'tone' => 'violet', 'icon' => 'eye', 'hint' => 'Sent to the Manager'],
            'handoffs' => ['label' => 'New handoffs', 'value' => $this->pendingHandoffs()->count(), 'url' => route('dashboard').'#handoffs', 'tone' => 'teal', 'icon' => 'briefcase', 'hint' => 'From Marketing, not yet acknowledged'],
            'my_handoffs' => ['label' => 'Handoffs not picked up', 'value' => Project::query()->where('handed_off_by', $this->user->id)->whereNotNull('handed_off_at')->whereNull('handoff_acknowledged_at')->count(), 'url' => route('dashboard').'#handoffs', 'tone' => 'teal', 'icon' => 'briefcase', 'hint' => 'Sent to the dev team'],
            'unassigned' => ['label' => 'Unassigned tasks', 'value' => (clone $tasks)->whereNull('assignee_id')->count(), 'url' => route('tasks.index', ['assignee' => 'none']), 'tone' => 'warning', 'icon' => 'user-check', 'hint' => 'Need an owner'],
            'my_open' => ['label' => 'My open tasks', 'value' => $this->user->openTasks()->count(), 'url' => route('my-work'), 'tone' => 'brand', 'icon' => 'check-square', 'hint' => 'Assigned to you'],
            'my_due_today' => ['label' => 'Due today', 'value' => $this->user->openTasks()->whereDate('due_date', today())->count(), 'url' => route('tasks.index', ['assignee' => 'me', 'due' => 'today']), 'tone' => 'info', 'icon' => 'calendar', 'hint' => 'Your tasks due '.today()->format('d M')],
            'my_overdue' => ['label' => 'My overdue', 'value' => $this->user->openTasks()->whereDate('due_date', '<', today())->count(), 'url' => route('tasks.index', ['assignee' => 'me', 'due' => 'overdue']), 'tone' => 'danger', 'icon' => 'clock', 'hint' => 'Your tasks past due'],
            'clients' => ['label' => 'Clients', 'value' => Client::query()->visibleTo($this->user)->count(), 'url' => route('clients.index'), 'tone' => 'info', 'icon' => 'building', 'hint' => 'Leads and customers'],
            'team' => ['label' => 'Active people', 'value' => User::active()->count(), 'url' => route('team.index'), 'tone' => 'info', 'icon' => 'users', 'hint' => 'Team activity'],
        ];
    }

    /** Open Urgent tasks (only your own when you do not manage tasks). */
    public function urgentTasks(int $limit = 8): Collection
    {
        return Task::query()->visibleTo($this->user)->open()->where('priority', Priority::Urgent)
            ->when(! $this->user->hasAbility('manage-tasks'), fn ($q) => $q->where('assignee_id', $this->user->id))
            ->with(['project:id,code', 'assignee:id,name'])->orderByRaw('due_date is null')->orderBy('due_date')->limit($limit)->get();
    }

    /** Active or pipeline projects marked Urgent. */
    public function urgentProjects(): Collection
    {
        return Project::query()->visibleTo($this->user)->where('priority', Priority::Urgent)
            ->whereNotIn('status', [ProjectStatus::Closed, ProjectStatus::Cancelled])->with('client:id,name')->orderBy('due_date')->limit(6)->get();
    }

    /** Marketing handoffs that no Team Lead has acknowledged yet. */
    public function pendingHandoffs(): Collection
    {
        return Project::query()->visibleTo($this->user)->whereNotNull('handed_off_at')->whereNull('handoff_acknowledged_at')
            ->with(['client:id,name', 'handedOffBy:id,name'])->oldest('handed_off_at')->limit(8)->get();
    }

    /** Projects this user handed to the dev team, with their pickup status. */
    public function myHandoffs(): Collection
    {
        return Project::query()->where('handed_off_by', $this->user->id)->whereNotNull('handed_off_at')
            ->with(['client:id,name', 'handoffAcknowledgedBy:id,name'])->latest('handed_off_at')->limit(8)->get();
    }

    public function reviewQueue(): Collection
    {
        return ReviewRequest::query()->visibleTo($this->user)
            ->when(! $this->user->hasAbility('approve-for-preview'), fn ($q) => $q->where('requested_by', $this->user->id))
            ->with(['project:id,code,name', 'requester:id,name'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [ReviewRequestStatus::Pending->value])->latest()->limit(8)->get();
    }

    public function unassignedTasks(): Collection
    {
        return Task::query()->visibleTo($this->user)->open()->whereNull('assignee_id')->with('project:id,code')
            ->orderByRaw('due_date is null')->orderBy('due_date')->limit(8)->get();
    }

    public function pipelineProjects(): Collection
    {
        return Project::query()->visibleTo($this->user)->pipeline()->with('client:id,name')->latest()->limit(8)->get();
    }

    public function recentClientDocuments(): Collection
    {
        $clientIds = $this->user->hasAbility('view-clients') ? null : Client::query()->visibleTo($this->user)->pluck('id');

        return ClientDocument::query()->when($clientIds !== null, fn ($q) => $q->whereIn('client_id', $clientIds))
            ->with(['client:id,name', 'uploader:id,name'])->latest()->limit(6)->get();
    }

    /** The signed-in user's open to-dos, overdue and due-today first. */
    public function myTodos(): Collection
    {
        return $this->user->todos()->open()->with('project:id,code')->ordered()->limit(10)->get();
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
