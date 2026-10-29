<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalOutcome;
use App\Enums\ChangeRequestStatus;
use App\Enums\MilestoneStatus;
use App\Enums\PreviewStatus;
use App\Enums\QuotationStatus;
use App\Models\Approval;
use App\Models\ChangeRequest;
use App\Models\Milestone;
use App\Models\Preview;
use App\Models\Quotation;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Approval center: what is pending and the evidence trail of recorded decisions. */
class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('approvals.index', [
            'quotations' => $user->can('view-financials')
                ? Quotation::visibleTo($user)->where('status', QuotationStatus::Sent)->with('project:id,code,name')->orderBy('sent_at')->get() : collect(),
            'changes' => ChangeRequest::visibleTo($user)->where('status', ChangeRequestStatus::AwaitingClientApproval)->with('project:id,code,name')->get(),
            'previews' => Preview::visibleTo($user)->whereIn('status', PreviewStatus::waiting())->with('project:id,code,name')->orderBy('review_due_at')->get(),
            'milestones' => Milestone::visibleTo($user)->where('status', MilestoneStatus::AwaitingApproval)->with('project:id,code,name')->get(),
            'recent' => Approval::visibleTo($user)->with(['project:id,code', 'approvable', 'recorder:id,name'])
                ->when(! $user->can('view-financials'), fn ($q) => $q->where('approvable_type', '!=', (new Quotation)->getMorphClass()))
                ->latest('decided_at')->limit(30)->get(),
            'outcomes' => ApprovalOutcome::options(),
        ]);
    }

    public function milestone(Request $request, Milestone $milestone): RedirectResponse
    {
        $this->authorize('manage');
        $data = $request->validate([
            'outcome' => ['required', Rule::enum(ApprovalOutcome::class)],
            'decided_by_name' => ['required', 'string', 'max:255'],
            'source_reference' => ['required', 'string', 'max:255'],
            'decided_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        Approval::forceCreate($data + [
            'project_id' => $milestone->project_id, 'approvable_type' => $milestone->getMorphClass(), 'approvable_id' => $milestone->id,
            'version_label' => $milestone->name, 'recorded_by' => $request->user()->id,
        ]);
        Activity::log('milestone.approval', "Approval recorded for milestone \"{$milestone->name}\": ".ApprovalOutcome::from($data['outcome'])->label(), $milestone, ['source' => $data['source_reference']], true);

        return back()->with('status', 'Approval recorded. You can now complete the milestone.');
    }
}
