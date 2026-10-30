{{--
    Milestone journey: the centre of the project overview. One stop per milestone in plan order; the line leading into
    each stop fills with that milestone's completed / total tasks. Activating a stop opens its detail panel listing the
    tasks completed in it (open tasks collapsed below). Data comes from App\Services\MilestoneTrack.
    @param Project $project
    @param array $track
    @param ?string $openStop  key of the stop whose panel is open on load (?milestone=…)
--}}
@php($stops = $track['stops'])
@php($overall = $track['overall'])
@php($unplanned = $track['unplanned'])
@php($canManage = auth()->user()->can('project-ability', [$project, 'manage-milestones']))
@php($stateLabels = ['done' => 'Completed', 'current' => 'You are here', 'upcoming' => 'Upcoming'])
<section id="milestone-journey" class="ms-journey reveal" data-ms-journey aria-labelledby="ms-journey-title" style="--d: 2">
    <header class="ms-head">
        <div>
            <p class="eyebrow">Delivery journey</p>
            <h2 id="ms-journey-title" class="ms-title">Milestones</h2>
            @if ($stops->isNotEmpty())
                <p class="ms-summary" data-ms-overall="{{ $overall['percent'] }}">
                    <strong>{{ $overall['milestonesDone'] }} of {{ $overall['milestones'] }}</strong> milestones complete
                    <span aria-hidden="true">·</span> <strong>{{ $overall['done'] }}/{{ $overall['total'] }}</strong> tasks done
                </p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if ($stops->isNotEmpty())
                <div class="ms-overall" role="img" aria-label="{{ $overall['percent'] }}% of the project's tasks are done" style="--p: {{ $overall['percent'] }}">
                    <span class="ms-overall-value">{{ $overall['percent'] }}<small>%</small></span>
                </div>
            @endif
            <a href="{{ route('projects.milestones', $project) }}" class="btn btn-ghost btn-sm">{{ $canManage ? 'Manage' : 'All milestones' }} <x-icon name="chevron-right" :size="14" /></a>
        </div>
    </header>

    @if ($stops->isEmpty())
        <div class="ms-empty" data-ms-empty>
            <div class="ms-empty-art" aria-hidden="true"><span></span><i></i><span></span><i></i><span></span></div>
            <p class="font-semibold">Map the journey for this project</p>
            <p class="mt-1 max-w-md text-sm text-muted">Milestones turn the plan into stops on a track. As tasks are completed the line fills and glows toward the next milestone.</p>
            @if ($canManage)
                <a href="{{ route('projects.milestones', $project) }}#new-milestone" class="btn btn-primary mt-4" data-ms-add><x-icon name="plus" :size="16" /> Add the first milestone</a>
            @endif
            @if ($unplanned)
                <p class="mt-3 text-xs text-muted">{{ $unplanned->done }}/{{ $unplanned->total }} tasks done so far without a milestone.</p>
            @endif
        </div>
    @else
        <div class="ms-scroller">
            <ol class="ms-track" role="list">
                <li class="ms-origin" aria-hidden="true">
                    <span class="ms-origin-dot"></span>
                    <span class="ms-origin-label">Start<br><span class="text-faint">{{ $project->start_date?->format('d M') ?? 'Kick-off' }}</span></span>
                </li>
                @foreach ($stops as $stop)
                    @php($m = $stop->milestone)
                    <li @class(['ms-step', 'is-'.$stop->state, 'is-overdue' => $stop->overdue]) style="--p: {{ $stop->fill }}; --i: {{ $loop->index }}" data-ms-stop="{{ $stop->key }}" data-state="{{ $stop->state }}">
                        <div class="ms-seg" aria-hidden="true">
                            <span class="ms-seg-fill"></span>
                            @if ($stop->state === 'current' && $stop->fill > 0 && $stop->fill < 100)<span class="ms-seg-head"></span>@endif
                        </div>
                        <div class="ms-stop">
                            <button type="button" class="ms-node" id="ms-node-{{ $stop->key }}" data-ms-toggle="{{ $stop->key }}"
                                aria-expanded="{{ $openStop === $stop->key ? 'true' : 'false' }}" aria-controls="ms-panel-{{ $stop->key }}"
                                aria-label="{{ $m->name }}: {{ $stateLabels[$stop->state] }}{{ $stop->overdue ? ', overdue' : '' }}, {{ $stop->done }} of {{ $stop->total }} tasks done. Show details">
                                <span class="ms-dot">
                                    @if ($stop->state === 'done')
                                        <x-icon name="check" :size="20" />
                                    @elseif ($stop->state === 'current')
                                        <span class="ms-dot-pct">{{ $stop->percent }}<small>%</small></span>
                                    @else
                                        <span class="ms-dot-num">{{ $loop->iteration }}</span>
                                    @endif
                                </span>
                            </button>
                            <div class="ms-meta">
                                <p class="ms-state">
                                    @if ($stop->overdue)
                                        <span class="ms-flag ms-flag-danger"><x-icon name="clock" :size="11" /> Overdue</span>
                                    @elseif ($stop->state === 'current')
                                        <span class="ms-flag ms-flag-brand"><span class="ms-here-dot"></span> You are here · {{ $stop->percent }}%</span>
                                    @elseif ($stop->state === 'done')
                                        <span class="ms-flag ms-flag-success"><x-icon name="check" :size="11" /> Completed</span>
                                    @else
                                        <span class="ms-flag">Upcoming</span>
                                    @endif
                                </p>
                                <p class="ms-name" title="{{ $m->name }}">{{ $m->name }}</p>
                                <p class="ms-sub">
                                    @if ($stop->state === 'done' && $m->completed_at)
                                        Done {{ $m->completed_at->format('d M') }}
                                    @elseif ($m->due_date)
                                        Due <time datetime="{{ $m->due_date->toDateString() }}" @class(['text-danger font-medium' => $stop->overdue])>{{ $m->due_date->format('d M') }}</time>
                                    @else
                                        No due date
                                    @endif
                                </p>
                                <p class="ms-count" data-ms-count="{{ $stop->done }}/{{ $stop->total }}">{{ $stop->done }}/{{ $stop->total }} tasks done</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        @if ($unplanned || $track['cancelled'])
            <div class="ms-extras">
                @if ($unplanned)
                    <button type="button" class="ms-chip" id="ms-node-unplanned" data-ms-toggle="unplanned" aria-expanded="{{ $openStop === 'unplanned' ? 'true' : 'false' }}" aria-controls="ms-panel-unplanned">
                        <x-icon name="layers" :size="14" /> Unplanned work <span class="ms-chip-count">{{ $unplanned->done }}/{{ $unplanned->total }} done</span>
                        <span class="ms-chip-bar" style="--p: {{ $unplanned->percent }}" aria-hidden="true"><span></span></span>
                    </button>
                @endif
                @if ($track['cancelled'])
                    <span class="text-xs text-faint">{{ $track['cancelled'] }} cancelled {{ \Illuminate\Support\Str::plural('milestone', $track['cancelled']) }} not shown</span>
                @endif
            </div>
        @endif
    @endif

    @if ($stops->isNotEmpty())
        <div class="ms-details" data-ms-details>
            @foreach ($unplanned ? $stops->concat([$unplanned]) : $stops as $stop)
                @php($isMilestone = $stop->key !== 'unplanned')
                <div id="ms-panel-{{ $stop->key }}" class="ms-panel" role="region" aria-labelledby="ms-panel-title-{{ $stop->key }}" data-ms-panel="{{ $stop->key }}" tabindex="-1" @if ($openStop !== $stop->key) hidden @endif>
                    <div class="ms-panel-head">
                        <div class="min-w-0">
                            <p class="eyebrow">{{ $isMilestone ? 'Milestone '.($stop->index + 1) : 'Tasks without a milestone' }}</p>
                            <h3 id="ms-panel-title-{{ $stop->key }}" class="ms-panel-title">{{ $stop->name }}</h3>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted">
                                @if ($isMilestone)
                                    <x-badge :enum="$stop->milestone->status" />
                                    @if ($stop->milestone->due_date)<span>Due <x-date :value="$stop->milestone->due_date" :overdue="$stop->overdue" /></span>@endif
                                    @if ($stop->milestone->completed_at)<span>· Completed {{ $stop->milestone->completed_at->format('d M Y') }}</span>@endif
                                @endif
                                <span>{{ $stop->done }} of {{ $stop->total }} tasks done</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="ms-panel-progress" aria-hidden="true"><span style="width: {{ $stop->percent }}%"></span></div>
                            <span class="text-sm font-semibold tabular-nums">{{ $stop->percent }}%</span>
                            <button type="button" class="btn btn-ghost btn-sm" data-ms-close aria-label="Close {{ $stop->name }} details"><x-icon name="x" :size="16" /></button>
                        </div>
                    </div>

                    <div class="ms-panel-body">
                        <h4 class="ms-list-title"><x-icon name="check-circle" :size="15" class="text-success" /> Completed in this {{ $isMilestone ? 'milestone' : 'group' }} <span class="badge tone-success">{{ $stop->completedTasks->count() }}</span></h4>
                        @if ($stop->completedTasks->isEmpty())
                            <p class="ms-none">No tasks completed here yet{{ $stop->total ? '.' : ' — no tasks are linked to this milestone.' }}</p>
                        @else
                            <ul class="ms-done-list" data-ms-completed>
                                @foreach ($stop->completedTasks as $task)
                                    <li class="ms-done-item" data-ms-task="{{ $task->id }}">
                                        <span class="ms-done-check" aria-hidden="true"><x-icon name="check" :size="12" /></span>
                                        <a href="{{ route('tasks.show', $task) }}" class="ms-done-title">{{ $task->title }}</a>
                                        @if ($task->assignee)
                                            <span class="avatar" title="{{ $task->assignee->name }}" aria-label="Assignee {{ $task->assignee->name }}">{{ $task->assignee->initials() }}</span>
                                        @else
                                            <span class="avatar avatar-empty" title="Unassigned" aria-label="Unassigned">—</span>
                                        @endif
                                        <time class="ms-done-date" datetime="{{ $task->completed_at?->toIso8601String() }}">{{ $task->completed_at?->format('d M Y') ?? 'Done' }}</time>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($stop->openTasks->isNotEmpty())
                            <button type="button" class="ms-open-toggle" data-disclosure aria-expanded="false" aria-controls="ms-open-{{ $stop->key }}">
                                <x-icon name="chevron-down" :size="14" /> {{ $stop->openTasks->count() }} still open
                            </button>
                            <ul id="ms-open-{{ $stop->key }}" class="ms-open-list" data-ms-open hidden>
                                @foreach ($stop->openTasks as $task)
                                    <li class="ms-open-item" data-ms-open-task="{{ $task->id }}">
                                        <a href="{{ route('tasks.show', $task) }}" class="min-w-0 flex-1 truncate hover:underline">{{ $task->title }}</a>
                                        <x-badge :enum="$task->status" />
                                        @if ($task->assignee)<span class="avatar avatar-sm" title="{{ $task->assignee->name }}">{{ $task->assignee->initials() }}</span>@endif
                                        <span class="w-20 text-right text-xs text-muted"><x-date :value="$task->due_date" overdue empty="No date" /></span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
