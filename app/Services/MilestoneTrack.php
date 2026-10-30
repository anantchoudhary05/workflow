<?php

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Data for the milestone journey on the project overview: one stop per milestone (in plan order) with its task progress,
 * the tasks completed in it and those still open, an "Unplanned" bucket for tasks without a milestone, and a merged log of
 * recently completed tasks and milestones. Cancelled tasks never count; trashed tasks and milestones are excluded by
 * their soft-delete scopes. Callers must already be allowed to view the project.
 */
class MilestoneTrack
{
    /** How many entries the completed log loads (the first few show, the rest sit behind "Show more"). */
    public const LOG_LIMIT = 20;

    public function __construct(private readonly Project $project) {}

    /**
     * @return array{stops: Collection, unplanned: ?object, overall: array{done: int, total: int, percent: int, milestonesDone: int, milestones: int}, cancelled: int, log: Collection}
     */
    public function build(): array
    {
        $tasks = Task::query()->where('project_id', $this->project->id)->where('status', '!=', TaskStatus::Cancelled)
            ->with('assignee:id,name')
            ->get(['id', 'project_id', 'milestone_id', 'title', 'status', 'priority', 'assignee_id', 'due_date', 'completed_at']);
        $byMilestone = $tasks->groupBy(fn (Task $t) => $t->milestone_id ?? 0);

        $milestones = Milestone::query()->where('project_id', $this->project->id)->orderBy('sort_order')->orderBy('due_date')->orderBy('id')->get();
        $cancelled = $milestones->where('status', MilestoneStatus::Cancelled)->count();
        $milestones = $milestones->where('status', '!=', MilestoneStatus::Cancelled)->values();

        $foundCurrent = false;
        $stops = $milestones->map(function (Milestone $m, int $i) use ($byMilestone, &$foundCurrent) {
            $stop = $this->bucket($byMilestone->get($m->id, collect()));
            $stop->key = (string) $m->id;
            $stop->milestone = $m;
            $stop->name = $m->name;
            $stop->index = $i;
            $stop->overdue = $m->isOverdue();
            $completed = $m->status === MilestoneStatus::Completed;
            // A signed-off milestone fills its whole leg of the journey even if a straggler task is still open.
            $stop->fill = $completed ? 100 : $stop->percent;
            $stop->state = match (true) {
                $completed => 'done',
                ! $foundCurrent => 'current',
                default => 'upcoming',
            };
            if ($stop->state === 'current') {
                $foundCurrent = true;
            }

            return $stop;
        });

        $unplannedTasks = $byMilestone->get(0, collect());
        $unplanned = null;
        if ($unplannedTasks->isNotEmpty()) {
            $unplanned = $this->bucket($unplannedTasks);
            $unplanned->key = 'unplanned';
            $unplanned->name = 'Unplanned work';
        }

        $done = $tasks->where('status', TaskStatus::Done)->count();

        return [
            'stops' => $stops,
            'unplanned' => $unplanned,
            'overall' => [
                'done' => $done,
                'total' => $tasks->count(),
                'percent' => $tasks->count() ? (int) round($done * 100 / $tasks->count()) : 0,
                'milestonesDone' => $stops->where('state', 'done')->count(),
                'milestones' => $stops->count(),
            ],
            'cancelled' => $cancelled,
            'log' => $this->completedLog($tasks, $milestones),
        ];
    }

    /** Counts and task lists for one group of tasks. */
    private function bucket(Collection $tasks): object
    {
        $done = $tasks->where('status', TaskStatus::Done)
            ->sortByDesc(fn (Task $t) => $t->completed_at?->getTimestamp() ?? 0)->values();
        $open = $tasks->where('status', '!=', TaskStatus::Done)
            ->sortBy(fn (Task $t) => [$t->due_date === null ? 1 : 0, $t->due_date?->getTimestamp() ?? 0, $t->id])->values();
        $total = $tasks->count();

        return (object) [
            'total' => $total,
            'done' => $done->count(),
            'percent' => $total ? (int) round($done->count() * 100 / $total) : 0,
            'completedTasks' => $done,
            'openTasks' => $open,
        ];
    }

    /**
     * Recently completed tasks and milestones, newest first, with who completed them (from the audit log when recorded,
     * otherwise the task's assignee).
     */
    private function completedLog(Collection $tasks, Collection $milestones): Collection
    {
        $doneTasks = $tasks->where('status', TaskStatus::Done)->whereNotNull('completed_at')
            ->sortByDesc(fn (Task $t) => $t->completed_at->getTimestamp())->take(self::LOG_LIMIT);
        $doneMilestones = $milestones->where('status', MilestoneStatus::Completed)->whereNotNull('completed_at')
            ->sortByDesc(fn (Milestone $m) => $m->completed_at->getTimestamp())->take(self::LOG_LIMIT);

        $who = $this->completedBy((new Task)->getMorphClass(), $doneTasks->pluck('id'), 'task.status', TaskStatus::Done->value)
            + $this->completedBy((new Milestone)->getMorphClass(), $doneMilestones->pluck('id'), 'milestone.status', MilestoneStatus::Completed->value, 'm');
        $names = $milestones->pluck('name', 'id');

        $entries = $doneTasks->map(fn (Task $t) => (object) [
            'type' => 'task',
            'id' => $t->id,
            'title' => $t->title,
            'at' => $t->completed_at,
            'by' => $who['t'.$t->id] ?? $t->assignee?->name,
            'milestone' => $t->milestone_id ? $names->get($t->milestone_id) : null,
            'url' => route('tasks.show', $t),
        ])->concat($doneMilestones->map(fn (Milestone $m) => (object) [
            'type' => 'milestone',
            'id' => $m->id,
            'title' => $m->name,
            'at' => $m->completed_at,
            'by' => $who['m'.$m->id] ?? null,
            'milestone' => null,
            'url' => route('projects.show', ['project' => $this->project, 'milestone' => $m->id]).'#milestone-journey',
        ]));

        return $entries->sortByDesc(fn ($e) => $e->at->getTimestamp())->take(self::LOG_LIMIT)->values();
    }

    /** @return array<string, string> "t{id}" / "m{id}" => name of the person whose status change completed it */
    private function completedBy(string $type, Collection $ids, string $event, string $to, string $prefix = 't'): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        $out = [];
        ActivityLog::query()->where('project_id', $this->project->id)->where('event', $event)->where('subject_type', $type)
            ->whereIn('subject_id', $ids)->with('user:id,name')->orderByDesc('id')->get(['id', 'user_id', 'subject_id', 'changes'])
            ->each(function (ActivityLog $log) use (&$out, $to, $prefix) {
                $key = $prefix.$log->subject_id;
                if (! isset($out[$key]) && ($log->changes['status']['to'] ?? null) === $to && $log->user) {
                    $out[$key] = $log->user->name;
                }
            });

        return $out;
    }
}
