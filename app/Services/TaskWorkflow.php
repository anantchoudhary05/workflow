<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Exceptions\WorkflowException;
use App\Models\Task;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Support\Facades\DB;

class TaskWorkflow
{
    public function transition(Task $task, TaskStatus $to, User $user, ?string $reason = null): void
    {
        $from = $task->status;
        if ($from === $to) {
            return;
        }
        if (! in_array($to, $from->transitions(), true)) {
            throw new WorkflowException("A task cannot move from {$from->label()} to {$to->label()}.");
        }
        // Rule 8: blocked tasks need a reason.
        if ($to === TaskStatus::Blocked && blank($reason)) {
            throw new WorkflowException('Blocked tasks require a blocker reason.');
        }
        // Rule 9: review needs a reviewer.
        if ($to === TaskStatus::InReview && ! $task->reviewer_id) {
            throw new WorkflowException('Assign a reviewer before submitting the task for review.');
        }
        // Completing a review: the assigned reviewer, a Team Lead/Manager ("review-work") or an administrator.
        if ($from === TaskStatus::InReview && in_array($to, [TaskStatus::Done, TaskStatus::ChangesRequested], true)
            && ! ($user->hasAbility('review-work') || $user->id === $task->reviewer_id)) {
            throw new WorkflowException('Only the assigned reviewer, a Team Lead, a Manager or an administrator can complete a review.');
        }
        if ($to === TaskStatus::ChangesRequested && blank($reason)) {
            throw new WorkflowException('Describe the changes requested.');
        }

        DB::transaction(function () use ($task, $from, $to, $user, $reason) {
            $task->status = $to;
            $task->blocker_reason = $to === TaskStatus::Blocked ? $reason : null;
            $task->completed_at = $to === TaskStatus::Done ? now() : null;
            $task->save();
            if ($reason && $to !== TaskStatus::Blocked) {
                $task->comments()->create(['user_id' => $user->id, 'kind' => 'comment', 'body' => "[{$to->label()}] {$reason}"]);
            }
            Activity::log('task.status', "Task \"{$task->title}\" moved from {$from->label()} to {$to->label()}", $task,
                ['status' => ['from' => $from->value, 'to' => $to->value], 'reason' => $reason], true);
        });

        $url = route('tasks.show', $task);
        match ($to) {
            TaskStatus::InReview => Notifier::send($task->reviewer, "Review requested: {$task->title}", $url, 'review'),
            TaskStatus::ChangesRequested => Notifier::send($task->assignee, "Changes requested: {$task->title}", $url, 'warning'),
            TaskStatus::Blocked => Notifier::send($task->project->manager, "Task blocked: {$task->title}", $url, 'danger'),
            default => null,
        };
    }

    /** Records assignment/reviewer changes in the audit log and notifies the new assignee. */
    public function logAssignmentChanges(Task $task, array $diff): void
    {
        if (isset($diff['assignee_id'])) {
            $name = $task->assignee?->name ?? 'nobody';
            Activity::log('task.assigned', "Task \"{$task->title}\" assigned to {$name}", $task, ['assignee_id' => $diff['assignee_id']], true);
            Notifier::send($task->assignee, "Task assigned: {$task->title}", route('tasks.show', $task), 'assignment');
        }
        if (isset($diff['reviewer_id'])) {
            Activity::log('task.reviewer', "Reviewer for \"{$task->title}\" set to ".($task->reviewer?->name ?? 'nobody'), $task, ['reviewer_id' => $diff['reviewer_id']], true);
        }
        if (isset($diff['due_date'])) {
            Activity::log('task.due_date', "Due date of \"{$task->title}\" changed", $task, ['due_date' => $diff['due_date']], true);
        }
    }
}
