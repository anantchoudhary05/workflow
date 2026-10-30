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

    {{--
        Calm layout: the four most important numbers in one strip, the rest as quiet chips; lists are grouped into two
        tabbed cards ("Needs attention" and "Coming up") instead of a wall of cards. Every widget is still rendered on the
        server for the roles that may see it; tabs only choose which list is visible.
    --}}
    @php
        $primaryKpis = array_slice($kpis, 0, 4, true);
        $moreKpis = array_slice($kpis, 4, null, true);
    @endphp
    <div class="reveal" style="--d: 1">
        <div class="stat-strip" data-dashboard-kpis>
            @foreach ($primaryKpis as $key => $kpi)
                <a href="{{ $kpi['url'] }}" @class(['stat-cell', 'tone-kpi-'.$kpi['tone'], 'stat-zero' => (string) $kpi['value'] === '0']) data-kpi="{{ $key }}">
                    <span class="flex items-center gap-2.5"><span class="stat-icon"><x-icon :name="$kpi['icon']" :size="16" /></span><span class="stat-label">{{ $kpi['label'] }}</span></span>
                    <span class="stat-value">{{ $kpi['value'] }}</span>
                    <span class="stat-hint mt-1 text-xs text-muted">{{ $kpi['hint'] }}</span>
                    <x-icon name="chevron-right" :size="16" class="stat-go" />
                </a>
            @endforeach
        </div>
        @if ($moreKpis)
            <div class="kpi-chips mt-3" aria-label="More numbers">
                @foreach ($moreKpis as $key => $kpi)
                    <a href="{{ $kpi['url'] }}" class="kpi-chip tone-kpi-{{ $kpi['tone'] }}" data-kpi="{{ $key }}" title="{{ $kpi['hint'] }}">
                        <span class="kpi-chip-dot" aria-hidden="true"></span><strong>{{ $kpi['value'] }}</strong> {{ $kpi['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @php
        $attention = array_filter([
            'urgent' => $show['urgent'] ? ['label' => 'Urgent', 'icon' => 'zap', 'count' => $urgentProjects->count() + $urgentTasks->count(), 'alert' => true] : null,
            'reviews' => $show['reviews'] ? ['label' => 'Reviews', 'icon' => 'eye', 'count' => $reviewQueue->count()] : null,
            'handoffs' => $show['handoffs'] ? ['label' => 'Handoffs', 'icon' => 'briefcase', 'count' => $handoffs->count(), 'alert' => true] : null,
            'my-handoffs' => $show['myHandoffs'] ? ['label' => 'Sent to dev', 'icon' => 'briefcase', 'count' => $myHandoffs->count()] : null,
            'unassigned' => $show['unassigned'] ? ['label' => 'Unassigned', 'icon' => 'user-check', 'count' => $unassigned->count()] : null,
            'blocked' => $show['blocked'] ? ['label' => 'Blocked', 'icon' => 'lock', 'count' => $blocked->count(), 'alert' => true] : null,
            'waiting' => ['label' => 'Waiting on clients', 'icon' => 'hourglass', 'count' => $waiting->count()],
        ]);
        $comingUp = array_filter([
            'my-tasks' => $show['myTasks'] ? ['label' => 'My tasks', 'icon' => 'check-square', 'count' => $myTasks->count()] : null,
            'milestones' => $show['milestones'] ? ['label' => 'Milestones', 'icon' => 'flag', 'count' => $milestones->count()] : null,
            'launches' => ['label' => 'Launches', 'icon' => 'package', 'count' => $launches->count()],
            'pipeline' => $show['pipeline'] ? ['label' => 'Pipeline', 'icon' => 'target', 'count' => $pipeline->count()] : null,
            'client-documents' => $show['clientDocuments'] ? ['label' => 'Client documents', 'icon' => 'paperclip', 'count' => $clientDocuments->count()] : null,
            'team' => $show['team'] && $team->isNotEmpty() ? ['label' => 'Team', 'icon' => 'users', 'count' => $team->count()] : null,
        ]);
        $firstBusy = fn (array $tabs) => collect($tabs)->search(fn ($t) => ($t['count'] ?? 0) > 0) ?: array_key_first($tabs);
        $attentionTab = $firstBusy($attention);
        $comingTab = $firstBusy($comingUp);
    @endphp

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <x-card title="Active projects" icon="rocket" :padded="false" class="reveal" style="--d: 2">
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

            <x-tabset name="dashboard-attention" :tabs="$attention" :selected="$attentionTab" title="Needs attention" icon="zap" class="reveal" style="--d: 3">
                @if ($show['urgent'])
                    <x-tab-panel tabset="dashboard-attention" name="urgent" :active="$attentionTab === 'urgent'" data-widget="urgent">
                        <div class="tab-panel-bar"><span>Urgent projects and tasks</span><a href="{{ route('tasks.index', ['priority' => 'urgent']) }}" class="link">All urgent</a></div>
                        <ul class="divide-y divide-line border-t border-line-soft">
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
                    </x-tab-panel>
                @endif
                @if ($show['reviews'])
                    <x-tab-panel tabset="dashboard-attention" name="reviews" :active="$attentionTab === 'reviews'" data-widget="reviews">
                        <div class="tab-panel-bar"><span>{{ auth()->user()->can('approve-for-preview') ? 'Review queue' : 'My review requests' }}</span><a href="{{ route('reviews.index') }}" class="link">Open queue</a></div>
                        <ul class="divide-y divide-line border-t border-line-soft">
                            @forelse ($reviewQueue as $review)
                                <li class="px-4 py-2.5">
                                    <p class="flex items-start justify-between gap-2"><a class="font-medium hover:underline" href="{{ route('projects.show', $review->project_id) }}">{{ $review->title }}</a><x-badge :enum="$review->status" /></p>
                                    <p class="text-xs text-muted">{{ $review->project?->code }} · {{ $review->requester?->name }} · {{ $review->created_at?->diffForHumans() }}</p>
                                </li>
                            @empty
                                <li><x-empty-state title="No review requests" icon="eye" /></li>
                            @endforelse
                        </ul>
                    </x-tab-panel>
                @endif
                @if ($show['handoffs'])
                    <x-tab-panel tabset="dashboard-attention" name="handoffs" :active="$attentionTab === 'handoffs'" data-widget="handoffs">
                        <div id="handoffs">
                            <div class="tab-panel-bar"><span>New from Marketing</span></div>
                            <ul class="divide-y divide-line border-t border-line-soft">
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
                        </div>
                    </x-tab-panel>
                @endif
                @if ($show['myHandoffs'])
                    <x-tab-panel tabset="dashboard-attention" name="my-handoffs" :active="$attentionTab === 'my-handoffs'" data-widget="my-handoffs">
                        <div @if (! $show['handoffs']) id="handoffs" @endif>
                            <div class="tab-panel-bar"><span>Sent to the dev team</span></div>
                            <ul class="divide-y divide-line border-t border-line-soft">
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
                        </div>
                    </x-tab-panel>
                @endif
                @if ($show['unassigned'])
                    <x-tab-panel tabset="dashboard-attention" name="unassigned" :active="$attentionTab === 'unassigned'" data-widget="unassigned">
                        <div class="tab-panel-bar"><span>Unassigned tasks</span><a href="{{ route('tasks.index', ['assignee' => 'none']) }}" class="link">All</a></div>
                        <ul class="divide-y divide-line border-t border-line-soft">
                            @forelse ($unassigned as $task)
                                <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                                    <div class="min-w-0"><a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a><p class="text-xs text-muted">{{ $task->project?->code }} · <x-date :value="$task->due_date" overdue empty="no due date" /></p></div>
                                    <x-badge :enum="$task->priority" />
                                </li>
                            @empty
                                <li><x-empty-state title="Every open task has an owner" icon="user-check" /></li>
                            @endforelse
                        </ul>
                    </x-tab-panel>
                @endif
                @if ($show['blocked'])
                    <x-tab-panel tabset="dashboard-attention" name="blocked" :active="$attentionTab === 'blocked'" data-widget="blocked">
                        <div class="tab-panel-bar"><span>Blocked tasks</span></div>
                        <ul class="divide-y divide-line border-t border-line-soft">
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
                    </x-tab-panel>
                @endif
                <x-tab-panel tabset="dashboard-attention" name="waiting" :active="$attentionTab === 'waiting'" data-widget="waiting">
                    <div class="tab-panel-bar"><span>Waiting on clients</span><a href="{{ route('client-actions.index') }}" class="link">All</a></div>
                    <ul class="divide-y divide-line border-t border-line-soft">
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
                        </x-tab-panel>
                    </x-tabset>

                    <x-tabset name="dashboard-upcoming" :tabs="$comingUp" :selected="$comingTab" title="Coming up" icon="calendar" class="reveal" style="--d: 4">
                        @if ($show['myTasks'])
                            <x-tab-panel tabset="dashboard-upcoming" name="my-tasks" :active="$comingTab === 'my-tasks'" data-widget="my-tasks">
                                <div class="tab-panel-bar"><span>My open tasks</span><a href="{{ route('my-work') }}" class="link">My work</a></div>
                <ul class="divide-y divide-line border-t border-line-soft">
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
                            </x-tab-panel>
                        @endif
                        @if ($show['milestones'])
                            <x-tab-panel tabset="dashboard-upcoming" name="milestones" :active="$comingTab === 'milestones'" data-widget="milestones">
                                <div class="tab-panel-bar"><span>Milestones due in the next 14 days</span></div>
                <ul class="divide-y divide-line border-t border-line-soft">
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
                            </x-tab-panel>
                        @endif
                        <x-tab-panel tabset="dashboard-upcoming" name="launches" :active="$comingTab === 'launches'" data-widget="launches">
                            <div class="tab-panel-bar"><span>Upcoming launches</span></div>
                    <ul class="divide-y divide-line border-t border-line-soft">
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
                        </x-tab-panel>
                        @if ($show['pipeline'])
                            <x-tab-panel tabset="dashboard-upcoming" name="pipeline" :active="$comingTab === 'pipeline'" data-widget="pipeline">
                                <div class="tab-panel-bar"><span>Pipeline (leads &amp; planning)</span><a href="{{ route('projects.index', ['status' => 'pipeline']) }}" class="link">All</a></div>
                    <ul class="divide-y divide-line border-t border-line-soft">
                        @forelse ($pipeline as $project)
                            <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                                <div class="min-w-0"><a href="{{ route('projects.show', $project) }}" class="font-medium hover:underline">{{ $project->name }}</a><p class="text-xs text-muted">{{ $project->client?->name }}@if ($project->handed_off_at) · handed off @endif</p></div>
                                <x-badge :enum="$project->status" />
                            </li>
                        @empty
                            <li><x-empty-state title="No leads or projects in planning" icon="target" /></li>
                        @endforelse
                    </ul>
                    </x-tab-panel>
                @endif
                @if ($show['clientDocuments'])
                    <x-tab-panel tabset="dashboard-upcoming" name="client-documents" :active="$comingTab === 'client-documents'" data-widget="client-documents">
                        <div class="tab-panel-bar"><span>Recent client documents</span></div>
                        <ul class="divide-y divide-line border-t border-line-soft">
                            @forelse ($clientDocuments as $document)
                                <li class="px-4 py-2.5">
                                    <a class="font-medium hover:underline" href="{{ route('clients.show', $document->client_id) }}#client-documents">{{ $document->original_name }}</a>
                                    <p class="text-xs text-muted">{{ $document->client?->name }} · {{ $document->category->label() }} · {{ $document->created_at?->diffForHumans() }}</p>
                                </li>
                            @empty
                                <li><x-empty-state title="No client documents yet" icon="paperclip">Upload briefs and contracts from a client page.</x-empty-state></li>
                            @endforelse
                        </ul>
                    </x-tab-panel>
                @endif
                @if ($show['team'] && $team->isNotEmpty())
                    <x-tab-panel tabset="dashboard-upcoming" name="team" :active="$comingTab === 'team'" data-widget="team">
                        <div class="tab-panel-bar"><span>Who is working on what</span><a href="{{ route('team.index') }}" class="link">Team activity</a></div>
                        <ul class="divide-y divide-line border-t border-line-soft">
                            @foreach ($team as $row)
                                <li class="px-4 py-2.5">
                                    <p class="flex items-center justify-between gap-2"><span class="font-medium">{{ $row->user->name }}</span><span class="text-xs text-muted">{{ $row->open }} open @if ($row->overdue)· <span class="text-danger">{{ $row->overdue }} overdue</span>@endif</span></p>
                                    <p class="truncate text-xs text-muted">{{ $row->working_on->isNotEmpty() ? $row->working_on->map(fn ($t) => ($t->project?->code ? $t->project->code.' ' : '').$t->title)->implode(' · ') : 'Nothing in progress' }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </x-tab-panel>
                @endif
            </x-tabset>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <div class="reveal" style="--d: 2">
                @include('partials.todo-panel', ['todos' => $todos, 'projectOptions' => $todoProjects])
            </div>
            <x-card title="Recent activity" icon="activity" class="reveal" style="--d: 3">
                @can('view-activity')
                    <x-slot:actions><a href="{{ route('activity.index') }}" class="link">Activity log</a></x-slot:actions>
                @endcan
                <ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $activity])</ul>
            </x-card>
        </div>
    </div>
@endsection
