{{--
    Project page "My work" panel: the signed-in user's work in THIS project only (same rules as the sidebar My work page,
    via App\Services\MyWork), with a second tab for their private to-dos linked to the project.
    @param Project $project
    @param array $myWork   ['tasks' => Collection, 'reviews' => Collection, 'followUps' => Collection, 'tab' => 'tasks'|'todos']
    @param Collection $todos
--}}
@php($today = today())
@php($taskCount = $myWork['tasks']->count())
@php($tab = $myWork['tab'])
<x-card title="My work" icon="inbox" :padded="false" id="my-work-panel" data-tabs="project-my-work-{{ $project->id }}">
    <x-slot:actions>
        <a href="{{ route('my-work', ['project' => $project->id]) }}" class="link">Open in My work</a>
    </x-slot:actions>

    <div role="tablist" aria-label="My work in this project" class="flex gap-5 border-b border-line px-4">
        <button type="button" role="tab" id="mywork-tab-tasks" class="tab" aria-controls="mywork-panel-tasks" aria-selected="{{ $tab === 'tasks' ? 'true' : 'false' }}" tabindex="{{ $tab === 'tasks' ? 0 : -1 }}" data-tab="tasks">
            Assigned tasks <span class="badge tone-neutral">{{ $taskCount }}</span>
        </button>
        <button type="button" role="tab" id="mywork-tab-todos" class="tab" aria-controls="mywork-panel-todos" aria-selected="{{ $tab === 'todos' ? 'true' : 'false' }}" tabindex="{{ $tab === 'todos' ? 0 : -1 }}" data-tab="todos">
            My to-dos <span class="badge tone-neutral">{{ $todos->reject->isDone()->count() }}</span>
        </button>
    </div>

    <div role="tabpanel" id="mywork-panel-tasks" aria-labelledby="mywork-tab-tasks" tabindex="0" @if ($tab !== 'tasks') hidden @endif data-tab-panel="tasks">
        <ul class="divide-y divide-line" data-project-my-tasks>
            @forelse ($myWork['tasks'] as $task)
                @php($overdue = $task->due_date && $task->due_date->lt($today))
                @php($urgent = $task->priority === App\Enums\Priority::Urgent)
                <li @class(['flex flex-wrap items-start justify-between gap-3 px-4 py-2.5', 'border-l-2 border-danger' => $overdue || $urgent]) data-my-task="{{ $task->id }}">
                    <div class="min-w-0">
                        <a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a>
                        <p class="flex flex-wrap items-center gap-x-2 text-xs text-muted">
                            @if ($overdue)
                                <span class="badge tone-danger">Overdue · {{ $task->due_date->format('d M') }}</span>
                            @elseif ($task->due_date?->isSameDay($today))
                                <span class="badge tone-warning">Due today</span>
                            @else
                                <span>Due <x-date :value="$task->due_date" empty="not set" /></span>
                            @endif
                            @if ($task->reviewer)<span>Reviewer {{ $task->reviewer->name }}</span>@endif
                        </p>
                        <p class="mt-0.5 text-xs text-brand">{{ $task->nextAction() }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-badge :enum="$task->priority" />
                        <x-badge :enum="$task->status" />
                        @if (in_array($task->status, [App\Enums\TaskStatus::Backlog, App\Enums\TaskStatus::Ready], true))
                            @can('changeStatus', $task)
                                <form method="POST" action="{{ route('tasks.status', $task) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="in_progress">
                                    <button class="btn btn-sm" aria-label="Start {{ $task->title }}">Start</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </li>
            @empty
                <li><x-empty-state title="Nothing assigned to you in this project." icon="check-square">Tasks assigned to you here will appear in this list and on My work.</x-empty-state></li>
            @endforelse
        </ul>

        @if ($myWork['reviews']->isNotEmpty())
            <h3 class="border-t border-line bg-subtle px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted">Waiting for my review ({{ $myWork['reviews']->count() }})</h3>
            <ul class="divide-y divide-line" data-project-my-reviews>
                @foreach ($myWork['reviews'] as $task)
                    <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                        <div class="min-w-0"><a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a><p class="text-xs text-muted">by {{ $task->assignee?->name ?? 'Unassigned' }}</p></div>
                        <x-badge :enum="$task->status" />
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($myWork['followUps']->isNotEmpty())
            <h3 class="border-t border-line bg-subtle px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted">My client follow-ups ({{ $myWork['followUps']->count() }})</h3>
            <ul class="divide-y divide-line" data-project-my-followups>
                @foreach ($myWork['followUps'] as $action)
                    <li class="px-4 py-2.5">
                        <p class="font-medium">{{ $action->title }}</p>
                        <p class="text-xs text-muted">Waiting {{ $action->daysWaiting() }}d · next follow-up <x-date :value="$action->next_follow_up_at" overdue empty="not set" /></p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div role="tabpanel" id="mywork-panel-todos" aria-labelledby="mywork-tab-todos" tabindex="0" @if ($tab !== 'todos') hidden @endif data-tab-panel="todos">
        <p class="flex items-center gap-1 px-4 pt-3 text-xs text-muted"><x-icon name="lock" :size="12" /> Only you can see these. <a href="{{ route('todos.index', ['project' => $project->id]) }}" class="link">All my to-dos</a></p>
        @include('partials.todo-list', ['todos' => $todos, 'project' => $project])
    </div>
</x-card>
