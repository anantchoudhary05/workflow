<?php

namespace App\Http\Controllers;

use App\Enums\FileCategory;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Http\Requests\ProjectRequest;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Project;
use App\Models\User;
use App\Services\MilestoneTrack;
use App\Services\MyWork;
use App\Services\ProjectHealth;
use App\Services\ProjectWorkflow;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $status = is_string($request->query('status')) ? $request->query('status') : null;
        $projects = ProjectHealth::withData(Project::query()->visibleTo($request->user()))
            ->search($request->query('q'))
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'pipeline', fn ($q) => $q->pipeline())
            ->when($status === 'all', fn ($q) => $q)
            ->when($status && ! in_array($status, ['active', 'pipeline', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when(! $status, fn ($q) => $q->whereNotIn('status', [ProjectStatus::Closed, ProjectStatus::Cancelled]))
            ->when($request->query('client'), fn ($q, $c) => $q->where('client_id', $c))
            ->when($request->query('manager'), fn ($q, $m) => $q->where('manager_id', $m))
            ->with(['client:id,name', 'manager:id,name'])
            ->orderByRaw('due_date is null')->orderBy('due_date')->get()
            ->each(function (Project $p) {
                $p->health = ProjectHealth::evaluate($p);
                $p->progress = ProjectHealth::progress($p);
            });

        if ($request->query('health') === 'attention') {
            $projects = $projects->filter(fn ($p) => in_array($p->health['key'], ['at_risk', 'overdue', 'blocked'], true))->values();
        }

        return view('projects.index', [
            'projects' => $projects,
            'statuses' => ['active' => 'Active (in delivery)', 'pipeline' => 'Pipeline (lead → approval)', 'all' => 'All, including closed'] + ProjectStatus::options(),
            'managers' => $this->userOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Project::class);

        return view('projects.form', $this->formData(new Project([
            'client_id' => $request->integer('client') ?: null,
            'priority' => Priority::Medium,
            'status' => ProjectStatus::Lead,
            'manager_id' => $request->user()->id,
        ])));
    }

    public function store(ProjectRequest $request, ProjectWorkflow $workflow): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $project = DB::transaction(function () use ($data, $user, $workflow) {
            $project = new Project(collect($data)->except(['members', 'status'])->all());
            // "status" is deliberately not mass assignable; the initial status is validated against ProjectStatus::initial().
            $project->status = ProjectStatus::tryFrom((string) ($data['status'] ?? '')) ?? ProjectStatus::Lead;
            $project->save();
            $project->members()->sync(collect($data['members'] ?? [])->push($project->manager_id)->filter()->unique()->all());
            $workflow->recordInitialStatus($project, $user);
            Activity::log('project.created', "Project {$project->code} {$project->name} created as {$project->status->label()}", $project,
                ['status' => ['from' => null, 'to' => $project->status->value]], true);

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('status', 'Project created.');
    }

    public function show(Request $request, Project $project, ProjectWorkflow $workflow): View
    {
        $this->authorize('view', $project);
        $project = ProjectHealth::withData(Project::whereKey($project->id))->firstOrFail();
        $project->load(['client:id,name', 'manager:id,name', 'members', 'approvedScope', 'statusHistory.user:id,name', 'dateChanges.user:id,name', 'closer:id,name', 'handedOffBy:id,name', 'handoffAcknowledgedBy:id,name']);

        $track = (new MilestoneTrack($project))->build();
        // ?milestone={id|unplanned} opens that stop's detail panel on load (works without JavaScript and is linkable).
        $wanted = is_string($request->query('milestone')) ? $request->query('milestone') : null;
        $openStop = $wanted === null ? null : ($track['stops']->firstWhere('key', $wanted)?->key
            ?? ($wanted === 'unplanned' && $track['unplanned'] ? 'unplanned' : null));

        return view('projects.show', [
            'project' => $project,
            'health' => ProjectHealth::evaluate($project),
            'progress' => ProjectHealth::progress($project),
            'nextAction' => ProjectHealth::nextAction($project),
            'track' => $track,
            'openStop' => $openStop,
            'waiting' => $project->clientActions()->where('status', 'waiting')->orderBy('requested_at')->get(),
            'activity' => $project->activities()->with('user:id,name')->limit(12)->get(),
            'users' => $this->userOptions(),
            'statusOptions' => $workflow->availableStatuses($project, $request->user()),
            'clientDocuments' => ClientDocument::query()->where('client_id', $project->client_id)->with('uploader:id,name')->latest()->get(),
            'canUploadClientDocs' => $project->client && $request->user()->can('create', [ClientDocument::class, $project->client]),
            'projectFiles' => $project->files()->with('uploader:id,name')
                ->when(! $request->user()->can('view-financials'), fn ($q) => $q->where('category', '!=', FileCategory::Quotation))
                ->latest()->limit(6)->get(),
            'myTodos' => $request->user()->todos()->where('project_id', $project->id)->ordered()->limit(20)->get(),
            'myWork' => $this->myWork($request->user(), $project),
            'reviewRequests' => $project->reviewRequests()->with(['requester:id,name', 'decider:id,name'])->latest()->limit(5)->get(),
        ]);
    }

    /**
     * The signed-in user's work in this project (same rules as the My work page). The panel opens on "My to-dos"
     * for people who are not given tasks (Marketing, Management) when they have no work here.
     */
    private function myWork(User $user, Project $project): array
    {
        $work = MyWork::for($user, $project->id);
        $data = ['tasks' => $work->tasks(), 'reviews' => $work->reviewQueue(), 'followUps' => $work->followUps()];
        $hasWork = collect($data)->contains(fn ($c) => $c->isNotEmpty());
        $data['tab'] = ! $hasWork && ! $user->hasAbility('work-tasks') ? 'todos' : 'tasks';

        return $data;
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.form', $this->formData($project));
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->fill(collect($request->validated())->except('members')->all());
        $changes = Activity::diff($project, ['name', 'manager_id', 'priority', 'start_date', 'launch_date', 'client_id', 'type']);
        $project->save();
        Activity::log('project.updated', "Project {$project->code} details updated", $project, $changes, (bool) $changes);

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    public function status(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate([
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'override' => ['nullable', 'boolean'],
        ]);
        $workflow->transition($project, ProjectStatus::from($data['status']), $request->user(), $data['reason'] ?? null, (bool) ($data['override'] ?? false));

        return back()->with('status', 'Project status updated.');
    }

    public function dueDate(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'set-deadlines']);
        $data = $request->validate([
            'due_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'delay_source' => ['nullable', Rule::in(['internal', 'client', 'scope_change'])],
        ]);
        $workflow->changeDueDate($project, $data['due_date'] ?? null, $data['reason'], $data['delay_source'] ?? null, $request->user());

        return back()->with('status', 'Delivery date changed and recorded.');
    }

    public function addMember(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id,deleted_at,NULL'], 'role' => ['nullable', 'string', 'max:60']]);
        $project->members()->syncWithoutDetaching([$data['user_id'] => ['role' => $data['role'] ?? null]]);
        Activity::log('project.member_added', 'Added '.User::find($data['user_id'])->name.' to the team', $project, [], true);

        return back()->with('status', 'Team member added.');
    }

    public function removeMember(Project $project, User $user): RedirectResponse
    {
        $this->authorize('update', $project);
        $project->members()->detach($user->id);
        Activity::log('project.member_removed', "Removed {$user->name} from the team", $project, [], true);

        return back()->with('status', 'Team member removed.');
    }

    public function activity(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        return view('projects.activity', [
            'project' => $project,
            'logs' => ActivityLog::where('project_id', $project->id)->with('user:id,name')
                ->when($request->boolean('audit'), fn ($q) => $q->where('is_audit', true))
                ->latest('id')->paginate(50)->withQueryString(),
        ]);
    }

    private function formData(Project $project): array
    {
        return [
            'project' => $project,
            'clients' => Client::where('status', '!=', 'archived')->orderBy('name')->pluck('name', 'id')->all(),
            'users' => $this->userOptions(),
            'types' => ProjectType::options(),
            'priorities' => Priority::options(),
            'initialStatuses' => ProjectStatus::initialOptions(),
        ];
    }
}
