<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalOutcome;
use App\Enums\HandoverStatus;
use App\Models\Approval;
use App\Models\Project;
use App\Services\ProjectWorkflow;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Handover, client acceptance and project closure (all administrator actions). */
class ClosureController extends Controller
{
    public function __construct(private ProjectWorkflow $workflow) {}

    public function handover(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'manage-release']);
        $data = $request->validate([
            'handover_status' => ['required', Rule::enum(HandoverStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->workflow->setHandoverStatus($project, HandoverStatus::from($data['handover_status']), $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Handover status updated.');
    }

    public function acceptance(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'record-client-approvals']);
        $data = $request->validate([
            'outcome' => ['required', Rule::in([ApprovalOutcome::Approved->value, ApprovalOutcome::ApprovedWithConditions->value, ApprovalOutcome::Rejected->value])],
            'decided_by_name' => ['required', 'string', 'max:255'],
            'source_reference' => ['required', 'string', 'max:255'],
            'decided_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        Approval::forceCreate($data + [
            'project_id' => $project->id, 'approvable_type' => $project->getMorphClass(), 'approvable_id' => $project->id,
            'version_label' => 'Final acceptance', 'recorded_by' => $request->user()->id,
        ]);
        Activity::log('project.acceptance', "Final acceptance recorded for {$project->code}: ".ApprovalOutcome::from($data['outcome'])->label()." ({$data['decided_by_name']})", $project, ['source' => $data['source_reference']], true);

        return back()->with('status', 'Client acceptance recorded.');
    }

    public function close(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'close-projects']);
        $data = $request->validate([
            'closure_notes' => ['nullable', 'string', 'max:5000'],
            'override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'required_if:override,1', 'string', 'max:2000'],
        ]);
        $this->workflow->close($project, $request->user(), $data['closure_notes'] ?? null, $request->boolean('override'), $data['override_reason'] ?? null);

        return redirect()->route('projects.show', $project)->with('status', 'Project closed.');
    }

    public function reopen(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manage'); // administrator override
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->workflow->reopen($project, $request->user(), $data['reason']);

        return back()->with('status', 'Project reopened.');
    }
}
