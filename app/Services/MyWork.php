<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\ClientAction;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "My work" for one user: open tasks assigned to them, reviews waiting on them and client follow-ups they own.
 * Used by the sidebar My work page (all projects, optional project filter) and the project page panel (one project),
 * so both apply the same rules. Deleted tasks, and the work of deleted projects and clients, are excluded by the
 * models' soft delete and global scopes.
 */
class MyWork
{
    public function __construct(private User $user, private ?int $projectId = null) {}

    public static function for(User $user, ?int $projectId = null): self
    {
        return new self($user, $projectId);
    }

    /** Open tasks assigned to the user, most pressing first: overdue, urgent, then by due date and priority. */
    public function tasks(): Collection
    {
        $today = today();

        return $this->user->openTasks()
            ->when($this->projectId, fn ($q, $id) => $q->where('project_id', $id))
            ->with(['project:id,code,name', 'reviewer:id,name'])
            ->get()
            ->sortBy(fn (Task $t) => [
                $t->due_date && $t->due_date->lt($today) ? 0 : 1,
                $t->priority === Priority::Urgent ? 0 : 1,
                $t->due_date ? $t->due_date->format('Y-m-d') : '9999-12-31',
                -$t->priority->rank(),
                $t->id,
            ])
            ->values();
    }

    /** The My work page groups (a task can appear in more than one, e.g. overdue and blocked). */
    public static function groups(Collection $tasks): array
    {
        $today = today();
        $week = $today->copy()->addDays(7);
        $rules = [
            'Overdue' => fn ($t) => $t->due_date && $t->due_date->lt($today),
            'Due today' => fn ($t) => $t->due_date?->isSameDay($today),
            'Blocked' => fn ($t) => $t->status === TaskStatus::Blocked,
            'Changes requested' => fn ($t) => $t->status === TaskStatus::ChangesRequested,
            'Next 7 days' => fn ($t) => $t->due_date && $t->due_date->gt($today) && $t->due_date->lte($week),
            'Later / no due date' => fn ($t) => true,
        ];

        // Each task is listed once, in the first group that applies.
        $groups = array_map(fn () => collect(), $rules);
        foreach ($tasks as $task) {
            foreach ($rules as $label => $matches) {
                if ($matches($task)) {
                    $groups[$label]->push($task);
                    break;
                }
            }
        }

        return $groups;
    }

    /** Tasks in review where the user is the reviewer, on projects they may see. */
    public function reviewQueue(): Collection
    {
        return Task::query()->visibleTo($this->user)
            ->where('reviewer_id', $this->user->id)->where('status', TaskStatus::InReview)
            ->when($this->projectId, fn ($q, $id) => $q->where('project_id', $id))
            ->with(['project:id,code,name', 'assignee:id,name'])
            ->orderByRaw('due_date is null')->orderBy('due_date')
            ->get();
    }

    /** Waiting-on-client actions the user owns. */
    public function followUps(): Collection
    {
        return ClientAction::query()->visibleTo($this->user)->waiting()
            ->where('owner_id', $this->user->id)
            ->when($this->projectId, fn ($q, $id) => $q->where('project_id', $id))
            ->with(['client:id,name', 'project:id,code,name'])
            ->orderBy('next_follow_up_at')
            ->get();
    }

    public function hoursThisWeek(): float
    {
        return (float) $this->user->timeEntries()
            ->when($this->projectId, fn ($q, $id) => $q->where('project_id', $id))
            ->whereBetween('work_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->sum('hours');
    }

    /** Projects (id => "CODE · Name") where the user has open tasks, reviews or follow-ups: the My work project filter. */
    public function projectOptions(): array
    {
        $ids = self::for($this->user)->tasks()->pluck('project_id')
            ->merge(self::for($this->user)->reviewQueue()->pluck('project_id'))
            ->merge(self::for($this->user)->followUps()->pluck('project_id'))
            ->filter()->unique()->all();

        return Project::query()->whereIn('id', $ids ?: [0])->orderBy('code')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Project $p) => [$p->id => "{$p->code} · {$p->name}"])->all();
    }
}
