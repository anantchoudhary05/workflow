<?php

namespace App\Services;

use App\Enums\ApprovalOutcome;
use App\Enums\ChangeClassification;
use App\Enums\ChangeRequestStatus as S;
use App\Enums\ClientActionType;
use App\Exceptions\WorkflowException;
use App\Models\Approval;
use App\Models\ChangeRequest;
use App\Models\ClientAction;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChangeRequestWorkflow
{
    /**
     * Rule 5: new-scope changes cannot be scheduled/implemented before internal review
     * and client approval are recorded, unless an admin records an override reason.
     */
    public function transition(ChangeRequest $cr, S $to, User $user, array $data = []): void
    {
        $from = $cr->status;
        $override = ! empty($data['override']);
        $allowed = $from->transitions();
        if ($override && in_array($to, [S::Scheduled, S::InProgress], true)) {
            $allowed = array_merge($allowed, [S::Scheduled, S::InProgress]);
        }
        if (! in_array($to, $allowed, true)) {
            throw new WorkflowException("A change request cannot move from {$from->label()} to {$to->label()}.");
        }
        if ($from === S::AwaitingClientApproval && in_array($to, [S::Approved, S::Rejected], true)) {
            throw new WorkflowException('Record the client decision with its evidence instead.');
        }
        if (in_array($to, [S::AwaitingInternalReview, S::Scheduled, S::InProgress, S::Approved], true) && ! $cr->classification) {
            throw new WorkflowException('Classify the request (bug, in-scope refinement or new scope) first.');
        }
        if ($to === S::AwaitingClientApproval && $cr->internal_reviewed_at === null) {
            throw new WorkflowException('Record the internal review before requesting client approval.');
        }
        if ($to === S::Approved && $cr->classification === ChangeClassification::NewScope && $from !== S::AwaitingClientApproval) {
            throw new WorkflowException('New-scope changes must go through client approval.');
        }
        if (in_array($to, [S::Scheduled, S::InProgress], true) && ! $cr->hasRequiredApprovals()) {
            if (! $override) {
                throw new WorkflowException('New-scope changes need internal review and recorded client approval before scheduling. An administrator may override with a reason.');
            }
            if (! $user->isAdmin() || blank($data['reason'] ?? null)) {
                throw new WorkflowException('Overrides require an administrator and a recorded reason.');
            }
        }
        if (in_array($to, [S::Rejected, S::Deferred, S::Cancelled], true) && blank($data['reason'] ?? null)) {
            throw new WorkflowException("{$to->label()} requires a reason.");
        }

        DB::transaction(function () use ($cr, $from, $to, $user, $data, $override) {
            if ($to === S::AwaitingClientApproval || ($to === S::Approved && $from === S::AwaitingInternalReview)) {
                $cr->internal_reviewed_by ??= $user->id;
                $cr->internal_reviewed_at ??= now();
            }
            if (in_array($to, [S::Rejected, S::Deferred], true)) {
                $cr->decided_by = $user->id;
                $cr->decided_at = now();
                $cr->decision_notes = $data['reason'];
            }
            if ($override && ! $cr->hasRequiredApprovals()) {
                $cr->override_reason = $data['reason'];
            }
            $cr->status = $to;
            $cr->save();
            if ($to === S::AwaitingClientApproval) {
                ClientAction::forceCreate([
                    'client_id' => $cr->project->client_id, 'project_id' => $cr->project_id, 'type' => ClientActionType::ChangeApproval,
                    'title' => Str::limit("Approve change {$cr->number}: {$cr->title}", 250), 'related_type' => $cr->getMorphClass(), 'related_id' => $cr->id,
                    'requested_at' => today(), 'next_follow_up_at' => today()->addDays((int) config('doc.reminders.client_follow_up_days')),
                    'owner_id' => $user->id, 'status' => 'waiting',
                ]);
            }
            Activity::log('change_request.status', "{$cr->number} moved from {$from->label()} to {$to->label()}".($override ? ' (override)' : ''), $cr,
                ['status' => ['from' => $from->value, 'to' => $to->value], 'reason' => $data['reason'] ?? null, 'override' => $override], true);
        });
    }

    public function recordInternalReview(ChangeRequest $cr, User $user, string $notes): void
    {
        if ($cr->status !== S::AwaitingInternalReview) {
            throw new WorkflowException('The change request is not awaiting internal review.');
        }
        $cr->forceFill(['internal_reviewed_by' => $user->id, 'internal_reviewed_at' => now()])->save();
        Activity::log('change_request.reviewed', "{$cr->number} internally reviewed", $cr, ['notes' => $notes], true);
    }

    /** Records the client's decision with evidence (Rule 15: rejected decisions keep rationale). */
    public function recordClientDecision(ChangeRequest $cr, ApprovalOutcome $outcome, array $data, User $user): void
    {
        if ($cr->status !== S::AwaitingClientApproval) {
            throw new WorkflowException('The change request is not awaiting client approval.');
        }
        DB::transaction(function () use ($cr, $outcome, $data, $user) {
            Approval::forceCreate([
                'project_id' => $cr->project_id, 'approvable_type' => $cr->getMorphClass(), 'approvable_id' => $cr->id,
                'outcome' => $outcome, 'version_label' => $cr->number, 'decided_by_name' => $data['decided_by_name'],
                'source_reference' => $data['source_reference'], 'notes' => $data['notes'] ?? null,
                'decided_at' => $data['decided_at'], 'recorded_by' => $user->id,
            ]);
            $to = $outcome->isPositive() ? S::Approved : S::Rejected;
            $cr->forceFill([
                'status' => $to, 'decided_by' => $user->id, 'decided_at' => $data['decided_at'],
                'approval_evidence' => $data['source_reference'], 'decision_notes' => $data['notes'] ?? null,
            ])->save();
            ClientAction::where('related_type', $cr->getMorphClass())->where('related_id', $cr->id)->where('status', 'waiting')
                ->update(['status' => 'received', 'resolved_at' => now(), 'resolution' => $outcome->label()]);
            Activity::log('change_request.decided', "{$cr->number} {$outcome->label()} by client ({$data['decided_by_name']})", $cr,
                ['status' => ['from' => S::AwaitingClientApproval->value, 'to' => $to->value], 'evidence' => $data['source_reference']], true);
        });
    }
}
