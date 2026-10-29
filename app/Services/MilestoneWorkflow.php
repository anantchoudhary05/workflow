<?php

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Exceptions\WorkflowException;
use App\Models\Approval;
use App\Models\Milestone;
use App\Models\User;
use App\Support\Activity;

class MilestoneWorkflow
{
    public function transition(Milestone $m, MilestoneStatus $to, User $user, ?string $overrideReason = null): void
    {
        $from = $m->status;
        if ($from === $to) {
            return;
        }
        $override = filled($overrideReason);
        if ($override && ! $user->isAdmin()) {
            throw new WorkflowException('Only an administrator can override milestone rules.');
        }
        // Dependencies must be complete before a milestone becomes ready/in progress/completed.
        if (in_array($to, [MilestoneStatus::Ready, MilestoneStatus::InProgress, MilestoneStatus::Completed], true) && ! $override) {
            $unmet = $m->unmetDependencies();
            if ($unmet->isNotEmpty()) {
                throw new WorkflowException('Prerequisite milestones are not complete: '.$unmet->pluck('name')->implode(', ').'.');
            }
        }
        // Rule 3: acceptance criteria must be confirmed met before completion.
        if ($to === MilestoneStatus::Completed && ! $override) {
            if (filled($m->acceptance_criteria) && ! $m->criteria_met) {
                throw new WorkflowException('Confirm the acceptance criteria are met before completing this milestone.');
            }
            if ($m->requires_approval && ! Approval::where('approvable_type', $m->getMorphClass())->where('approvable_id', $m->id)->whereIn('outcome', ['approved', 'approved_with_conditions'])->exists()) {
                throw new WorkflowException('This milestone requires a recorded approval before completion.');
            }
        }
        $m->status = $to;
        $m->completed_at = $to === MilestoneStatus::Completed ? now() : null;
        if ($override) {
            $m->override_reason = $overrideReason;
        }
        $m->save();
        Activity::log('milestone.status', "Milestone \"{$m->name}\" moved from {$from->label()} to {$to->label()}".($override ? ' (override)' : ''), $m,
            ['status' => ['from' => $from->value, 'to' => $to->value], 'override_reason' => $overrideReason], true);
    }
}
