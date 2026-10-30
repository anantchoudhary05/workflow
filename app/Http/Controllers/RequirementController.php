<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\RequirementCategory;
use App\Enums\RequirementStatus;
use App\Exceptions\WorkflowException;
use App\Http\Requests\RequirementRequest;
use App\Models\Project;
use App\Models\Requirement;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequirementController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $project->load('approvedScope');
        $requirements = $project->requirements()->with(['owner:id,name', 'decider:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->orderBy('code')->get();
        $baselined = collect($project->approvedScope?->snapshot)->pluck('id')->all();

        return view('projects.requirements', [
            'project' => $project,
            'requirements' => $requirements,
            'baselined' => $baselined,
            'categories' => RequirementCategory::options(),
            'priorities' => Priority::options(),
            'statuses' => RequirementStatus::options(),
            'users' => $this->userOptions(),
        ]);
    }

    public function store(RequirementRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);
        $req = new Requirement($request->validated());
        $req->project_id = $project->id;
        $req->code = Requirement::nextCode($project->id, 'REQ');
        $req->status = RequirementStatus::Draft;
        $req->save();
        Activity::log('requirement.created', "Requirement {$req->code} \"{$req->title}\" added", $req);

        return back()->with('status', "Requirement {$req->code} added.");
    }

    public function update(RequirementRequest $request, Requirement $requirement): RedirectResponse
    {
        $this->authorize('view', $requirement->project);
        if ($requirement->isBaselined()) {
            throw new WorkflowException("{$requirement->code} is part of the approved scope baseline. Raise a change request instead.");
        }
        $requirement->fill($request->validated());
        $changes = Activity::diff($requirement, ['title', 'priority', 'category', 'acceptance_criteria', 'estimated_hours']);
        $requirement->save();
        Activity::log('requirement.updated', "Requirement {$requirement->code} updated", $requirement, $changes, (bool) $changes);

        return back()->with('status', "Requirement {$requirement->code} updated.");
    }

    /** Status/decision changes. Rejections and deferrals keep their rationale (Rule 15). */
    public function decide(Request $request, Requirement $requirement): RedirectResponse
    {
        $this->authorize('project-ability', [$requirement->project, 'decide-requirements']);
        $this->authorize('view', $requirement->project);
        $data = $request->validate([
            'status' => ['required', Rule::enum(RequirementStatus::class)],
            'decision_notes' => [Rule::requiredIf(in_array($request->input('status'), ['rejected', 'deferred', 'clarification_required'], true)), 'nullable', 'string', 'max:5000'],
        ]);
        $to = RequirementStatus::from($data['status']);
        if ($requirement->isBaselined() && in_array($to, [RequirementStatus::Rejected, RequirementStatus::Deferred, RequirementStatus::Draft], true)) {
            throw new WorkflowException('Removing a baselined requirement from scope requires a change request.');
        }
        $from = $requirement->status;
        $requirement->status = $to;
        if ($to->isDecision()) {
            $requirement->decided_by = $request->user()->id;
            $requirement->decided_at = now();
        }
        if (filled($data['decision_notes'] ?? null)) {
            $requirement->decision_notes = $data['decision_notes'];
        }
        $requirement->save();
        Activity::log('requirement.status', "Requirement {$requirement->code} moved from {$from->label()} to {$to->label()}", $requirement,
            ['status' => ['from' => $from->value, 'to' => $to->value], 'notes' => $data['decision_notes'] ?? null], true);

        return back()->with('status', "Requirement {$requirement->code} is now {$to->label()}.");
    }
}
