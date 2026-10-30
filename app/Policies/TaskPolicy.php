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

    /** Team Leads and Managers split work into tasks. Project access is checked against the chosen project. */
    public function create(User $user): bool
    {
        return $user->hasAbility('manage-tasks');
    }

    public function update(User $user, Task $task): bool
    {
        return $user->hasAbility('manage-tasks') && $user->canAccessProject($task->project_id);
    }

    /** Soft delete with a reason (restorable from Trash): administrators only. */
    public function delete(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    /** Assignees and reviewers move their own tasks; task managers may move any task. TaskWorkflow enforces the transition rules. */
    public function changeStatus(User $user, Task $task): bool
    {
        if (! $user->canAccessProject($task->project_id)) {
            return false;
        }

        return $user->hasAbility('manage-tasks')
            || ($user->hasAbility('work-tasks') && in_array($user->id, [$task->assignee_id, $task->reviewer_id], true));
    }

    /** Only Managers (and administrators) may mark work as urgent. */
    public function setPriority(User $user, Task $task): bool
    {
        return $user->hasAbility('set-urgency') && $user->canAccessProject($task->project_id);
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
