<?php

namespace App\Http\Controllers;

use App\Enums\RequirementStatus;
use App\Models\Project;
use App\Models\ScopeVersion;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScopeController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('projects.scope', [
            'project' => $project,
            'versions' => $project->scopeVersions()->with('recorder:id,name')->latest('version')->get(),
            'approvedRequirements' => $project->requirements()->whereIn('status', [RequirementStatus::Approved, RequirementStatus::Implemented, RequirementStatus::Verified])->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, Project $project, ScopeService $scope): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'decide-requirements']);
        $this->authorize('view', $project);
        $data = $request->validate([
            'summary' => ['required', 'string', 'max:10000'],
            'approval_source' => ['required', 'string', 'max:255'],
            'approved_by_name' => ['required', 'string', 'max:255'],
            'approved_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $version = $scope->baseline($project, $data, $request->user());

        return back()->with('status', "Scope baseline v{$version->version} recorded.");
    }

    public function show(ScopeVersion $scopeVersion): View
    {
        $this->authorize('view', $scopeVersion->project);

        return view('projects.scope-version', ['version' => $scopeVersion->load('recorder:id,name'), 'project' => $scopeVersion->project]);
    }
}
