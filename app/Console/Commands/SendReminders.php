<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\ClientAction;
use App\Models\Task;
use App\Services\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily in-app reminders. Each reminder carries a per-day dedupe key,
 * so running the command more than once a day does not duplicate notifications.
 */
#[Signature('doc:send-reminders')]
#[Description('Send in-app reminders for overdue and due-today tasks, stale in-progress tasks and client follow-ups')]
class SendReminders extends Command
{
    public function handle(): int
    {
        $day = today()->toDateString();
        $sent = 0;

        Task::query()->open()->whereNotNull('assignee_id')->whereDate('due_date', '<=', today())
            ->with(['assignee', 'project:id,code'])->lazyById()
            ->each(function (Task $task) use ($day, &$sent): void {
                $overdue = $task->due_date->lt(today());
                $title = ($overdue ? 'Overdue' : 'Due today').": {$task->project->code} · {$task->title}";
                Notifier::send($task->assignee, $title, route('tasks.show', $task), $overdue ? 'danger' : 'warning', "task-due-{$task->id}-{$day}");
                $sent++;
            });

        $staleBefore = now()->subDays((int) config('doc.reminders.stale_task_days'));
        Task::query()->where('status', TaskStatus::InProgress)->whereNotNull('assignee_id')->where('updated_at', '<', $staleBefore)
            ->with(['assignee', 'project:id,code'])->lazyById()
            ->each(function (Task $task) use ($day, &$sent): void {
                Notifier::send($task->assignee, "No progress recorded recently: {$task->project->code} · {$task->title}", route('tasks.show', $task), 'warning', "task-stale-{$task->id}-{$day}");
                $sent++;
            });

        ClientAction::query()->waiting()->whereNotNull('owner_id')->whereDate('next_follow_up_at', '<=', today())
            ->with(['owner', 'client:id,name'])->lazyById()
            ->each(function (ClientAction $action) use ($day, &$sent): void {
                Notifier::send($action->owner, "Follow up with {$action->client->name}: {$action->title}", route('client-actions.index'), 'warning', "client-follow-up-{$action->id}-{$day}");
                $sent++;
            });

        $this->info("Checked {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
