<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalOutcome;
use App\Enums\ChangeClassification;
use App\Enums\ChangeRequestStatus;
use App\Enums\Priority;
use App\Exceptions\WorkflowException;
use App\Http\Requests\ChangeRequestRequest;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Services\ChangeRequestWorkflow;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChangeRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('change-requests.index', [
            'changes' => ChangeRequest::query()->visibleTo($request->user())->with(['project:id,code,name', 'reviewer:id,name'])
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s),
                    fn ($q) => $q->whereNotIn('status', ['verified', 'rejected', 'cancelled']))
                ->when($request->query('classification'), fn ($q, $c) => $q->where('classification', $c))
                ->latest('received_at')->paginate(40)->withQueryString(),
            'statuses' => ChangeRequestStatus::options(),
            'classifications' => ChangeClassification::options(),
        ]);
    }

    public function forProject(Project $project): View
    {
        $this->authorize('view', $project);

        return view('projects.changes', [
            'project' => $project,
            'changes' => $project->changeRequests()->with('reviewer:id,name')->latest('received_at')->get(),
            'priorities' => Priority::options(),
            'classifications' => ChangeClassification::options(),
            'users' => $this->userOptions(),
            'canLog' => auth()->user()->can('record-communication'),
        ]);
    }

    public function store(ChangeRequestRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);
        $data = $request->validated();
        // Contributors may log a request; only administrators classify and estimate it.
        if (! $request->user()->can('manage')) {
            $data = collect($data)->only(['title', 'description', 'reason', 'source', 'received_at', 'requested_by', 'priority'])->all();
        }
        $cr = DB::transaction(function () use ($data, $project, $request) {
            $cr = new ChangeRequest(collect($data)->except(['milestones', 'requirements'])->all());
            $cr->project_id = $project->id;
            $cr->number = ChangeRequest::nextCode($project->id, 'CR');
            $cr->status = ChangeRequestStatus::Submitted;
            $cr->created_by = $request->user()->id;
            $cr->save();
            $cr->milestones()->sync($data['milestones'] ?? []);
            $cr->requirements()->sync($data['requirements'] ?? []);
            Activity::log('change_request.created', "{$cr->number} logged: {$cr->title}", $cr, [], true);

            return $cr;
        });

        return redirect()->route('change-requests.show', $cr)->with('status', "{$cr->number} logged.");
    }

    public function show(ChangeRequest $changeRequest): View
    {
        $this->authorizeProjectAccess($changeRequest->project_id);
        $changeRequest->load(['project', 'reviewer:id,name', 'internalReviewer:id,name', 'decider:id,name', 'milestones:id,name', 'requirements:id,code,title', 'tasks.assignee:id,name', 'approvals']);
        $project = $changeRequest->project;

        return view('change-requests.show', [
            'cr' => $changeRequest,
            'transitions' => $changeRequest->status->transitions(),
            'history' => $project->activities()->where('subject_type', $changeRequest->getMorphClass())->where('subject_id', $changeRequest->id)->with('user:id,name')->get(),
            'priorities' => Priority::options(),
            'classifications' => ChangeClassification::options(),
            'users' => $this->userOptions(),
            'milestones' => $project->milestones()->pluck('name', 'id')->all(),
            'requirements' => $project->requirements()->get()->mapWithKeys(fn ($r) => [$r->id => "{$r->code} {$r->title}"])->all(),
            'outcomes' => [ApprovalOutcome::Approved->value => ApprovalOutcome::Approved->label(), ApprovalOutcome::ApprovedWithConditions->value => ApprovalOutcome::ApprovedWithConditions->label(), ApprovalOutcome::Rejected->value => ApprovalOutcome::Rejected->label()],
        ]);
    }

    /** Assessment: classification, impact and estimate (admin only). */
    public function update(ChangeRequestRequest $request, ChangeRequest $changeRequest): RedirectResponse
    {
        if (in_array($changeRequest->status, [ChangeRequestStatus::Approved, ChangeRequestStatus::Scheduled, ChangeRequestStatus::InProgress, ChangeRequestStatus::Implemented, ChangeRequestStatus::Verified, ChangeRequestStatus::Rejected, ChangeRequestStatus::Cancelled], true)) {
            throw new WorkflowException('Decided change requests cannot be re-assessed.');
        }
        $data = $request->validated();
        $changeRequest->fill(collect($data)->except(['milestones', 'requirements'])->all());
        $changes = Activity::diff($changeRequest, ['classification', 'extra_hours', 'cost_impact', 'timeline_impact_days', 'priority', 'reviewer_id']);
        // A re-classification invalidates a previous internal review.
        if (isset($changes['classification'])) {
            $changeRequest->forceFill(['internal_reviewed_by' => null, 'internal_reviewed_at' => null]);
        }
        $changeRequest->save();
        $changeRequest->milestones()->sync($data['milestones'] ?? []);
        $changeRequest->requirements()->sync($data['requirements'] ?? []);
        Activity::log('change_request.assessed', "{$changeRequest->number} assessment updated", $changeRequest, $changes, true);

        return back()->with('status', 'Assessment saved.');
    }

    public function status(Request $request, ChangeRequest $changeRequest, ChangeRequestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('manage');
        $data = $request->validate([
            'status' => ['required', Rule::enum(ChangeRequestStatus::class)],
            'reason' => ['nullable', 'string', 'max:5000'],
            'override' => ['nullable', 'boolean'],
        ]);
        $workflow->transition($changeRequest, ChangeRequestStatus::from($data['status']), $request->user(), $data);

        return back()->with('status', "{$changeRequest->number} is now {$changeRequest->status->label()}.");
    }

    /** Records the internal review (required before asking the client to approve). */
    public function internalReview(Request $request, ChangeRequest $changeRequest, ChangeRequestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('manage');
        $data = $request->validate(['notes' => ['required', 'string', 'max:5000']]);
        $workflow->recordInternalReview($changeRequest, $request->user(), $data['notes']);

        return back()->with('status', 'Internal review recorded.');
    }

    public function clientDecision(Request $request, ChangeRequest $changeRequest, ChangeRequestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('manage');
        $data = $request->validate([
            'outcome' => ['required', Rule::in([ApprovalOutcome::Approved->value, ApprovalOutcome::ApprovedWithConditions->value, ApprovalOutcome::Rejected->value])],
            'decided_by_name' => ['required', 'string', 'max:255'],
            'source_reference' => ['required', 'string', 'max:255'],
            'decided_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => [Rule::requiredIf($request->input('outcome') === 'rejected'), 'nullable', 'string', 'max:5000'],
        ]);
        $workflow->recordClientDecision($changeRequest, ApprovalOutcome::from($data['outcome']), $data, $request->user());

        return back()->with('status', 'Client decision recorded.');
    }
}
