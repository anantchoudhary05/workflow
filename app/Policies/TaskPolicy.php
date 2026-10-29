<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $user->canAccessProject($task->project_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Task $task): bool
    {
        return false;
    }

    public function delete(User $user, Task $task): bool
    {
        return false;
    }

    /** Assignees and reviewers move their own tasks; TaskWorkflow enforces the transition rules. */
    public function changeStatus(User $user, Task $task): bool
    {
        return $user->hasAbility('work-tasks') && $user->canAccessProject($task->project_id)
            && in_array($user->id, [$task->assignee_id, $task->reviewer_id], true);
    }

    public function comment(User $user, Task $task): bool
    {
        return $user->hasAbility('comment') && $user->canAccessProject($task->project_id);
    }

    public function logTime(User $user, Task $task): bool
    {
        return $user->hasAbility('log-time') && $user->canAccessProject($task->project_id);
    }
}
