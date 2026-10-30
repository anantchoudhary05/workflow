@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    <x-page-header title="Dashboard" :subtitle="'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')).', '.auth()->user()->name.'. Here is where delivery stands today.'">
        <x-slot:meta><p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted"><x-badge :enum="auth()->user()->role" /> {{ auth()->user()->role->description() }}</p></x-slot:meta>
        <x-slot:actions>
            @can('create', App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New project</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 min-[420px]:grid-cols-2 lg:grid-cols-4">
        @foreach ($kpis as $key => $kpi)
            <x-stat :label="$kpi['label']" :value="$kpi['value']" :href="$kpi['url']" :tone="$kpi['tone']" :icon="$kpi['icon']" :hint="$kpi['hint']" data-kpi="{{ $key }}" />
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-1">
            @include('partials.todo-panel', ['todos' => $todos, 'projectOptions' => $todoProjects])
        </div>

        @if ($show['reviews'])
            <x-card :title="auth()->user()->can('approve-for-preview') ? 'Review queue' : 'My review requests'" icon="eye" :padded="false" data-widget="reviews">
                <x-slot:actions><a href="{{ route('reviews.index') }}" class="link">Open queue</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($reviewQueue as $review)
                        <li class="px-4 py-2.5">
                            <p class="flex items-start justify-between gap-2"><a class="font-medium hover:underline" href="{{ route('projects.show', $review->project_id) }}">{{ $review->title }}</a><x-badge :enum="$review->status" /></p>
                            <p class="text-xs text-muted">{{ $review->project?->code }} · {{ $review->requester?->name }} · {{ $review->created_at?->diffForHumans() }}</p>
                        </li>
                    @empty
                        <li><x-empty-state title="No review requests" icon="eye" /></li>
                    @endforelse
                </ul>
            </x-card>
        @endif

        @if ($show['urgent'])
            <x-card title="Urgent" icon="zap" :padded="false" data-widget="urgent">
                <x-slot:actions><a href="{{ route('tasks.index', ['priority' => 'urgent']) }}" class="link">All urgent</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @foreach ($urgentProjects as $project)
                        <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div class="min-w-0"><a href="{{ route('projects.show', $project) }}" class="font-medium hover:underline">{{ $project->name }}</a><p class="text-xs text-muted">Project · {{ $project->client?->name }} · <x-date :value="$project->due_date" overdue empty="no date" /></p></div>
                            <x-badge :enum="$project->priority" />
                        </li>
                    @endforeach
                    @foreach ($urgentTasks as $task)
                        <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div class="min-w-0"><a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a><p class="text-xs text-muted">{{ $task->project?->code }} · {{ $task->assignee?->name ?? 'Unassigned' }} · <x-date :value="$task->due_date" overdue empty="no due date" /></p></div>
                            <x-badge :enum="$task->status" />
                        </li>
                    @endforeach
                    @if ($urgentProjects->isEmpty() && $urgentTasks->isEmpty())
                        <li><x-empty-state title="Nothing urgent" icon="zap">Managers mark projects and tasks as Urgent.</x-empty-state></li>
                    @endif
                </ul>
            </x-card>
        @endif

        @if ($show['handoffs'])
            <x-card title="New from Marketing" icon="briefcase" :padded="false" id="handoffs" data-widget="handoffs">
                <ul class="divide-y divide-line">
                    @forelse ($handoffs as $project)
                        <li class="px-4 py-2.5">
                            <a href="{{ route('projects.show', $project) }}" class="font-medium hover:underline">{{ $project->name }}</a>
                            <p class="text-xs text-muted">{{ $project->client?->name }} · sent by {{ $project->handedOffBy?->name }} {{ $project->handed_off_at->diffForHumans() }}</p>
                            <p class="mt-0.5 line-clamp-2 text-xs">{{ $project->handoff_notes }}</p>
                        </li>
                    @empty
                        <li><x-empty-state title="No handoffs waiting" icon="briefcase" /></li>
                    @endforelse
                </ul>
            </x-card>
        @endif

        @if ($show['myHandoffs'])
            <x-card title="Sent to the dev team" icon="briefcase" :padded="false" :id="$show['handoffs'] ? null : 'handoffs'" data-widget="my-handoffs">
                <ul class="divide-y divide-line">
                    @forelse ($myHandoffs as $project)
                        <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div class="min-w-0"><a href="{{ route('projects.show', $project) }}" class="font-medium hover:underline">{{ $project->name }}</a><p class="text-xs text-muted">{{ $project->client?->name }} · sent {{ $project->handed_off_at->format('d M') }}</p></div>
                            @if ($project->handoff_acknowledged_at)
                                <span class="badge tone-success" title="{{ $project->handoffAcknowledgedBy?->name }}">Picked up</span>
                            @else
                                <span class="badge tone-warning">Waiting</span>
                            @endif
                        </li>
                    @empty
                        <li><x-empty-state title="Nothing handed off yet" icon="briefcase">Open a project and use “Send to dev team” once the client details, requirements and documents are ready.</x-empty-state></li>
                    @endforelse
                </ul>
            </x-card>
        @endif

        @if ($show['unassigned'])
            <x-card title="Unassigned tasks" icon="user-check" :padded="false" data-widget="unassigned">
                <x-slot:actions><a href="{{ route('tasks.index', ['assignee' => 'none']) }}" class="link">All</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($unassigned as $task)
                        <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div class="min-w-0"><a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a><p class="text-xs text-muted">{{ $task->project?->code }} · <x-date :value="$task->due_date" overdue empty="no due date" /></p></div>
                            <x-badge :enum="$task->priority" />
                        </li>
                    @empty
                        <li><x-empty-state title="Every open task has an owner" icon="user-check" /></li>
                    @endforelse
                </ul>
            </x-card>
        @endif

        @if ($show['pipeline'])
            <x-card title="Pipeline (leads & planning)" icon="target" :padded="false" data-widget="pipeline">
                <x-slot:actions><a href="{{ route('projects.index', ['status' => 'pipeline']) }}" class="link">All</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($pipeline as $project)
                        <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div class="min-w-0"><a href="{{ route('projects.show', $project) }}" class="font-medium hover:underline">{{ $project->name }}</a><p class="text-xs text-muted">{{ $project->client?->name }}@if ($project->handed_off_at) · handed off @endif</p></div>
                            <x-badge :enum="$project->status" />
                        </li>
                    @empty
                        <li><x-empty-state title="No leads or projects in planning" icon="target" /></li>
                    @endforelse
                </ul>
            </x-card>
        @endif

        @if ($show['clientDocuments'])
            <x-card title="Recent client documents" icon="paperclip" :padded="false" data-widget="client-documents">
                <ul class="divide-y divide-line">
                    @forelse ($clientDocuments as $document)
                        <li class="px-4 py-2.5">
                            <a class="font-medium hover:underline" href="{{ route('clients.show', $document->client_id) }}#client-documents">{{ $document->original_name }}</a>
                            <p class="text-xs text-muted">{{ $document->client?->name }} · {{ $document->category->label() }} · {{ $document->created_at?->diffForHumans() }}</p>
                        </li>
                    @empty
                        <li><x-empty-state title="No client documents yet" icon="paperclip">Upload briefs and contracts from a client page.</x-empty-state></li>
                    @endforelse
                </ul>
            </x-card>
        @endif

        @if ($show['team'] && $team->isNotEmpty())
            <x-card title="Who is working on what" icon="users" :padded="false" data-widget="team">
                <x-slot:actions><a href="{{ route('team.index') }}" class="link">Team activity</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @foreach ($team as $row)
                        <li class="px-4 py-2.5">
                            <p class="flex items-center justify-between gap-2"><span class="font-medium">{{ $row->user->name }}</span><span class="text-xs text-muted">{{ $row->open }} open @if ($row->overdue)· <span class="text-danger">{{ $row->overdue }} overdue</span>@endif</span></p>
                            <p class="truncate text-xs text-muted">{{ $row->working_on->isNotEmpty() ? $row->working_on->map(fn ($t) => ($t->project?->code ? $t->project->code.' ' : '').$t->title)->implode(' · ') : 'Nothing in progress' }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-card title="Active projects" icon="rocket" :padded="false" class="xl:col-span-2">
            <x-slot:actions><a href="{{ route('projects.index', ['status' => 'active']) }}" class="link">All active projects</a></x-slot:actions>
            @if ($projects->isEmpty())
                <x-empty-state title="No active projects" icon="rocket">
                    Projects that are Ready To Start, In Progress, Blocked, In Review, in QA or Ready For Launch appear here.
                    Leads, planning and approvals are counted under <a class="link" href="{{ route('projects.index', ['status' => 'pipeline']) }}">Pipeline</a>.
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Project</th><th>Health</th><th>Progress</th><th>Due</th><th>Next action</th></tr></thead>
                        <tbody>
                        @foreach ($projects as $project)
                            <tr>
                                <td>
                                    <a href="{{ route('projects.show', $project) }}" class="font-medium hover:underline">{{ $project->name }}</a>
                                    <p class="text-xs text-muted">{{ $project->code }} · {{ $project->client?->name }}</p>
                                    <x-badge :enum="$project->status" class="mt-1" />
                                </td>
                                <td>@include('partials.health', ['health' => $project->health])</td>
                                <td class="w-36">
                                    @if ($project->progress['percent'] !== null)
                                        <div class="progress" title="{{ $project->progress['basis'] }}"><span style="width: {{ $project->progress['percent'] }}%"></span></div>
                                        <p class="mt-1 text-xs text-muted">{{ $project->progress['percent'] }}%</p>
                                    @else
                                        <span class="text-xs text-faint" title="{{ $project->progress['basis'] }}">Not measurable yet</span>
                                    @endif
                                </td>
                                <td><x-date :value="$project->due_date" overdue /></td>
                                <td class="text-muted">{{ \App\Services\ProjectHealth::nextAction($project) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        @if ($show['myTasks'])
        <x-card title="My open tasks" icon="check-square" :padded="false">
            <x-slot:actions><a href="{{ route('my-work') }}" class="link">My work</a></x-slot:actions>
            <ul class="divide-y divide-line">
                @forelse ($myTasks as $task)
                    <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                        <div class="min-w-0">
                            <a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a>
                            <p class="text-xs text-muted">{{ $task->project?->code }} · <x-date :value="$task->due_date" overdue empty="No due date" /></p>
                        </div>
                        <x-badge :enum="$task->status" />
                    </li>
                @empty
                    <li><x-empty-state title="Nothing assigned to you" icon="check-square" /></li>
                @endforelse
            </ul>
        </x-card>
        @endif

        @if ($show['milestones'])
        <x-card title="Milestones due in the next 14 days" icon="flag" :padded="false">
            <ul class="divide-y divide-line">
                @forelse ($milestones as $milestone)
                    <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                        <div class="min-w-0">
                            <a href="{{ route('projects.milestones', $milestone->project_id) }}" class="font-medium hover:underline">{{ $milestone->name }}</a>
                            <p class="text-xs text-muted">{{ $milestone->project?->code }} · <x-date :value="$milestone->due_date" overdue /></p>
                        </div>
                        <x-badge :enum="$milestone->status" />
                    </li>
                @empty
                    <li><x-empty-state title="No milestones due soon" icon="flag" /></li>
                @endforelse
            </ul>
        </x-card>
        @endif

        @if ($show['blocked'])
        <x-card title="Blocked tasks" icon="lock" :padded="false">
            <ul class="divide-y divide-line">
                @forelse ($blocked as $task)
                    <li class="px-4 py-2.5">
                        <a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a>
                        <p class="text-xs text-muted">{{ $task->project?->code }} · {{ $task->assignee?->name ?? 'Unassigned' }}</p>
                        @if ($task->blocker_reason)<p class="mt-1 text-xs text-danger">{{ \Illuminate\Support\Str::limit($task->blocker_reason, 120) }}</p>@endif
                    </li>
                @empty
                    <li><x-empty-state title="Nothing is blocked" icon="check" /></li>
                @endforelse
            </ul>
        </x-card>
        @endif

        <x-card title="Waiting on clients" icon="hourglass" :padded="false">
            <x-slot:actions><a href="{{ route('client-actions.index') }}" class="link">All</a></x-slot:actions>
            <ul class="divide-y divide-line">
                @forelse ($waiting as $action)
                    <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $action->title }}</p>
                            <p class="text-xs text-muted">{{ $action->client?->name }}@if ($action->project) · {{ $action->project->code }}@endif · {{ $action->type->label() }}</p>
                        </div>
                        <span @class(['badge', 'tone-danger' => $action->daysWaiting() >= 7, 'tone-warning' => $action->daysWaiting() >= 3 && $action->daysWaiting() < 7, 'tone-neutral' => $action->daysWaiting() < 3])>{{ $action->daysWaiting() }}d</span>
                    </li>
                @empty
                    <li><x-empty-state title="Nothing waiting on clients" icon="hourglass" /></li>
                @endforelse
            </ul>
        </x-card>

        <x-card title="Upcoming launches" icon="package" :padded="false">
            <ul class="divide-y divide-line">
                @forelse ($launches as $project)
                    <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                        <div class="min-w-0">
                            <a href="{{ route('projects.show', $project) }}" class="font-medium hover:underline">{{ $project->name }}</a>
                            <p class="text-xs text-muted">{{ $project->client?->name }} · Launch <x-date :value="$project->launch_date" empty="not scheduled" /></p>
                        </div>
                        <x-badge :enum="$project->status" />
                    </li>
                @empty
                    <li><x-empty-state title="No projects in QA or ready for launch" icon="package" /></li>
                @endforelse
            </ul>
        </x-card>

        <x-card title="Recent activity" icon="activity" class="xl:col-span-2">
            @can('view-activity')
                <x-slot:actions><a href="{{ route('activity.index') }}" class="link">Activity log</a></x-slot:actions>
            @endcan
            <ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $activity])</ul>
        </x-card>
    </div>
@endsection
