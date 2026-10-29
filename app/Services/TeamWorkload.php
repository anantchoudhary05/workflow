<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/** Per-person workload computed from real task and time data. */
class TeamWorkload
{
    public static function rows(?array $projectIds = null): Collection
    {
        $tasks = Task::query()->open()->whereNotNull('assignee_id')
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds ?: [0]))
            ->get(['id', 'assignee_id', 'status', 'due_date', 'estimated_hours'])->groupBy('assignee_id');
        $week = TimeEntry::query()->whereBetween('work_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds ?: [0]))
            ->selectRaw('user_id, sum(hours) as hours')->groupBy('user_id')->pluck('hours', 'user_id');

        return User::active()->orderBy('name')->get()->map(function (User $u) use ($tasks, $week) {
            $mine = $tasks->get($u->id, collect());
            $capacity = (float) ($u->weekly_capacity_hours ?? 0);
            $remaining = (float) $mine->sum('estimated_hours');

            return (object) [
                'user' => $u,
                'open' => $mine->count(),
                'in_progress' => $mine->where('status', TaskStatus::InProgress)->count(),
                'blocked' => $mine->where('status', TaskStatus::Blocked)->count(),
                'in_review' => $mine->where('status', TaskStatus::InReview)->count(),
                'overdue' => $mine->filter(fn ($t) => $t->due_date && $t->due_date->lt(today()))->count(),
                'due_this_week' => $mine->filter(fn ($t) => $t->due_date && $t->due_date->between(today(), today()->endOfWeek()))->count(),
                'estimated_remaining' => $remaining,
                'logged_this_week' => (float) ($week[$u->id] ?? 0),
                'capacity' => $capacity,
                // Load = remaining estimated hours vs. one week of capacity; null when capacity is unknown.
                'load' => $capacity > 0 ? (int) round($remaining / $capacity * 100) : null,
            ];
        });
    }
}
