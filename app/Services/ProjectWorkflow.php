<?php

namespace App\Services;

use App\Enums\HandoverStatus;
use App\Enums\ProjectStatus;
use App\Exceptions\WorkflowException;
use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Support\Facades\DB;

class ProjectWorkflow
{
    /**
     * Statuses the user may move the project to from its current status.
     * Administrators may jump to any status (except Closed, which has its own closure workflow) with an override.
     *
     * @return array<string, string> value => label
     */
    public function availableStatuses(Project $project, User $user): array
    {
        $allowed = $project->status->transitions();
        $options = [];
        foreach (ProjectStatus::cases() as $status) {
            if ($status === $project->status || $status === ProjectStatus::Closed) {
                continue;
            }
            if (in_array($status, $allowed, true)) {
                $options[$status->value] = $status->label();
            } elseif ($user->isAdmin() && $project->status !== ProjectStatus::Closed) {
                $options[$status->value] = $status->label().' (admin override)';
            }
        }

        return $options;
    }

    /** Rule 1: Ready to Start requires an approved scope and quotation unless an admin overrides with a reason. */
    public function transition(Project $project, ProjectStatus $to, User $user, ?string $reason = null, bool $override = false): void
    {
        $from = $project->status;
        if ($from === $to) {
            throw new WorkflowException("The project is already {$to->label()}.");
        }
        if ($override && (! $user->isAdmin() || blank($reason))) {
            throw new WorkflowException('Overrides require an administrator and a recorded reason.');
        }
        if ($to === ProjectStatus::Closed || $from === ProjectStatus::Closed) {
            throw new WorkflowException('Use "Close project" / "Reopen project" to close or reopen a project.');
        }
        if (! in_array($to, $from->transitions(), true) && ! $override) {
            throw new WorkflowException("A project cannot move from {$from->label()} to {$to->label()}. An administrator may override with a recorded reason.");
        }
        if (in_array($to, [ProjectStatus::Blocked, ProjectStatus::OnHold, ProjectStatus::Cancelled], true) && blank($reason)) {
            throw new WorkflowException("Moving to {$to->label()} requires a reason.");
        }

        if ($to === ProjectStatus::ReadyToStart && ! $override) {
            $missing = array_filter([
                $project->approved_scope_version_id ? null : 'an approved scope baseline',
                $project->approved_quotation_id ? null : 'an approved quotation',
            ]);
            if ($missing) {
                throw new WorkflowException('Ready to Start requires '.implode(' and ', $missing).'. An administrator may override with a recorded reason.');
            }
        }
        if ($to === ProjectStatus::Delivered && ! $override) {
            $this->assertChecklistComplete($project, 'release', 'Mark delivered');
        }

        DB::transaction(function () use ($project, $from, $to, $user, $reason, $override) {
            $project->status = $to;
            if ($to === ProjectStatus::Delivered) {
                $project->completed_at = now();
                $project->handover_status ??= HandoverStatus::ReadyForHandover;
            }
            $project->save();
            $project->statusHistory()->create(['from_status' => $from, 'to_status' => $to, 'user_id' => $user->id, 'reason' => $reason, 'is_override' => $override]);
            Activity::log('project.status', "{$project->code} moved from {$from->label()} to {$to->label()}".($override ? ' (override)' : ''), $project,
                ['status' => ['from' => $from->value, 'to' => $to->value], 'reason' => $reason, 'override' => $override], true);
        });
    }

    /** Records the status chosen when the project was created, so the history and audit log show where it started. */
    public function recordInitialStatus(Project $project, User $user): void
    {
        $status = $project->status;
        $skipsGates = in_array($status, ProjectStatus::active(), true);
        $project->statusHistory()->create([
            'from_status' => null,
            'to_status' => $status,
            'user_id' => $user->id,
            'reason' => 'Initial status selected when the project was created'.($skipsGates ? ' (scope/quotation gates not applied)' : ''),
            'is_override' => $skipsGates,
        ]);
    }

    /** Rule 7: preserve original target and record every delivery-date change with a reason. */
    public function changeDueDate(Project $project, ?string $newDate, string $reason, ?string $delaySource, User $user): void
    {
        DB::transaction(function () use ($project, $newDate, $reason, $delaySource, $user) {
            $old = $project->due_date?->toDateString();
            $project->original_due_date ??= $newDate;
            $project->due_date = $newDate;
            $project->save();
            $project->dateChanges()->create(['old_date' => $old, 'new_date' => $newDate, 'reason' => $reason, 'delay_source' => $delaySource, 'user_id' => $user->id]);
            Activity::log('project.due_date', "{$project->code} delivery date changed from ".($old ?? 'none').' to '.($newDate ?? 'none'), $project,
                ['due_date' => ['from' => $old, 'to' => $newDate], 'reason' => $reason, 'delay_source' => $delaySource], true);
        });
    }

    public function setHandoverStatus(Project $project, HandoverStatus $to, User $user, ?string $reason = null): void
    {
        if ($project->status !== ProjectStatus::Delivered) {
            throw new WorkflowException('Handover starts once the project is marked Delivered.');
        }
        if ($to === HandoverStatus::Accepted && ! $project->hasAcceptance()) {
            throw new WorkflowException('Record the client\'s final acceptance (with a source reference) before marking handover as Accepted.');
        }
        if ($to === HandoverStatus::Closed) {
            throw new WorkflowException('Use "Close project" to close.');
        }
        $from = $project->handover_status;
        $project->handover_status = $to;
        $project->save();
        Activity::log('project.handover', "{$project->code} handover: ".($from?->label() ?? 'Not started')." → {$to->label()}", $project,
            ['handover_status' => ['from' => $from?->value, 'to' => $to->value], 'reason' => $reason], true);
    }

    /** Rule 4: closure needs QA (release checklist), handover checklist and acceptance, unless overridden. */
    public function close(Project $project, User $user, ?string $notes, bool $override = false, ?string $overrideReason = null): void
    {
        if (! in_array($project->status, [ProjectStatus::Delivered], true)) {
            throw new WorkflowException('Only delivered projects can be closed.');
        }
        if ($override) {
            if (! $user->isAdmin() || blank($overrideReason)) {
                throw new WorkflowException('Closing with an exception requires an administrator and a reason.');
            }
        } else {
            $this->assertChecklistComplete($project, 'release', 'Close');
            $this->assertChecklistComplete($project, 'handover', 'Close');
            if ($project->handover_status !== HandoverStatus::Accepted) {
                throw new WorkflowException('Client acceptance must be recorded (handover status Accepted) before closing.');
            }
        }

        DB::transaction(function () use ($project, $user, $notes, $override, $overrideReason) {
            $from = $project->status;
            $project->status = ProjectStatus::Closed;
            $project->handover_status = HandoverStatus::Closed;
            $project->closed_by = $user->id;
            $project->closed_at = now();
            $project->closure_notes = trim(($notes ?? '').($override ? "\n\nException: {$overrideReason}" : ''));
            $project->save();
            $project->statusHistory()->create(['from_status' => $from, 'to_status' => ProjectStatus::Closed, 'user_id' => $user->id, 'reason' => $override ? $overrideReason : $notes, 'is_override' => $override]);
            Activity::log('project.closed', "{$project->code} closed".($override ? ' with exception' : ''), $project, ['override' => $override, 'reason' => $overrideReason], true);
        });
    }

    public function reopen(Project $project, User $user, string $reason): void
    {
        if ($project->status !== ProjectStatus::Closed || ! $user->isAdmin()) {
            throw new WorkflowException('Only an administrator can reopen a closed project.');
        }
        DB::transaction(function () use ($project, $user, $reason) {
            $project->status = ProjectStatus::Delivered;
            $project->handover_status = HandoverStatus::Reopened;
            $project->closed_at = null;
            $project->closed_by = null;
            $project->save();
            $project->statusHistory()->create(['from_status' => ProjectStatus::Closed, 'to_status' => ProjectStatus::Delivered, 'user_id' => $user->id, 'reason' => $reason, 'is_override' => false]);
            Activity::log('project.reopened', "{$project->code} reopened", $project, ['reason' => $reason], true);
        });
    }

    public function assertChecklistComplete(Project $project, string $type, string $action): void
    {
        $items = ChecklistItem::where('project_id', $project->id)->where('type', $type)->get();
        if ($items->isEmpty()) {
            throw new WorkflowException("{$action}: the {$type} checklist has not been created yet.");
        }
        $open = $items->filter(fn ($i) => $i->is_mandatory && ! $i->isSatisfied());
        if ($open->isNotEmpty()) {
            throw new WorkflowException("{$action}: {$open->count()} mandatory {$type} checklist item(s) are incomplete (".$open->pluck('label')->take(3)->implode(', ').').');
        }
    }
}
