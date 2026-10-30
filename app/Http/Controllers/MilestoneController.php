<?php

namespace App\Http\Controllers;

use App\Enums\MilestoneStatus;
use App\Enums\Priority;
use App\Models\Milestone;
use App\Models\Project;
use App\Services\MilestoneWorkflow;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MilestoneController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);
        $milestones = $project->milestones()->with(['owner:id,name', 'dependencies:id,name,status'])
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done')])->get();

        return view('projects.milestones', [
            'project' => $project,
            'milestones' => $milestones,
            'statuses' => MilestoneStatus::options(),
            'priorities' => Priority::options(),
            'users' => $this->userOptions(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'manage-milestones']);
        $this->authorize('view', $project);
        $data = $this->validated($request, $project);
        DB::transaction(function () use ($data, $project) {
            $m = new Milestone(collect($data)->except('depends_on')->all());
            $m->project_id = $project->id;
            $m->status = MilestoneStatus::Planned;
            $m->sort_order ??= (int) $project->milestones()->max('sort_order') + 1;
            $m->save();
            $m->dependencies()->sync($data['depends_on'] ?? []);
            Activity::log('milestone.created', "Milestone \"{$m->name}\" added", $m);
        });

        return back()->with('status', 'Milestone added.');
    }

    public function update(Request $request, Milestone $milestone): RedirectResponse
    {
        $this->authorize('project-ability', [$milestone->project, 'manage-milestones']);
        $data = $this->validated($request, $milestone->project, $milestone);
        $milestone->fill(collect($data)->except('depends_on')->all());
        $changes = Activity::diff($milestone, ['name', 'due_date', 'owner_id', 'criteria_met', 'requires_approval', 'weight']);
        $milestone->save();
        $milestone->dependencies()->sync($data['depends_on'] ?? []);
        Activity::log('milestone.updated', "Milestone \"{$milestone->name}\" updated", $milestone, $changes, (bool) $changes);

        return back()->with('status', 'Milestone updated.');
    }

    public function status(Request $request, Milestone $milestone, MilestoneWorkflow $workflow): RedirectResponse
    {
        $this->authorize('project-ability', [$milestone->project, 'manage-milestones']);
        $data = $request->validate([
            'status' => ['required', Rule::enum(MilestoneStatus::class)],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->transition($milestone, MilestoneStatus::from($data['status']), $request->user(), $data['override_reason'] ?? null);

        return back()->with('status', 'Milestone status updated.');
    }

    private function validated(Request $request, Project $project, ?Milestone $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', 'exists:users,id,deleted_at,NULL'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'acceptance_criteria' => ['nullable', 'string', 'max:5000'],
            'criteria_met' => ['boolean'],
            'deliverables' => ['nullable', 'string', 'max:5000'],
            'requires_approval' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'depends_on' => ['array'],
            'depends_on.*' => [Rule::exists('milestones', 'id')->where('project_id', $project->id)],
        ]);
        $data['criteria_met'] = $request->boolean('criteria_met');
        $data['requires_approval'] = $request->boolean('requires_approval');
        if ($current && in_array($current->id, $data['depends_on'] ?? [])) {
            throw ValidationException::withMessages(['depends_on' => 'A milestone cannot depend on itself.']);
        }

        return $data;
    }
}
