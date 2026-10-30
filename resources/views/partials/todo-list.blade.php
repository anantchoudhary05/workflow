{{--
    To-do quick-add form and list (private to the signed-in user). Used by the "My to-dos" card and the project "My work" panel.
    @param Collection $todos      the user's to-dos to list
    @param Project|null $project  when set, new to-dos are linked to this project
    @param array $projectOptions  optional project picker for the dashboard quick-add
--}}
@php($project = $project ?? null)
@php($projectOptions = $projectOptions ?? [])
<form method="POST" action="{{ route('todos.store') }}" class="flex flex-wrap items-end gap-2 border-b border-line p-3">
    @csrf
    @if ($project)<input type="hidden" name="project_id" value="{{ $project->id }}">@endif
    <x-field name="title" placeholder="Add a to-do…" required class="min-w-48 flex-[2]" aria-label="To-do title" />
    <x-field name="due_date" type="date" class="w-36" aria-label="Due date" />
    <x-field name="priority" type="select" :options="App\Enums\Priority::options()" value="medium" class="w-28" aria-label="Priority" />
    @if (! $project && $projectOptions)
        <x-field name="project_id" type="select" :options="$projectOptions" blank="No project" class="min-w-40 flex-1" aria-label="Project" />
    @endif
    <button class="btn btn-primary"><x-icon name="plus" :size="16" /> Add</button>
</form>
<ul class="divide-y divide-line">
    @forelse ($todos as $todo)
        <li @class(['flex items-start gap-3 px-4 py-2.5', 'opacity-60' => $todo->isDone()])>
            <form method="POST" action="{{ route('todos.toggle', $todo) }}" class="pt-0.5">
                @csrf
                <button class="btn btn-ghost btn-sm" aria-label="{{ $todo->isDone() ? 'Mark as not done' : 'Mark as done' }}" title="{{ $todo->isDone() ? 'Reopen' : 'Done' }}">
                    <x-icon :name="$todo->isDone() ? 'check-circle' : 'check-square'" :size="16" @class(['text-success' => $todo->isDone()]) />
                </button>
            </form>
            <div class="min-w-0 flex-1">
                <p @class(['font-medium', 'line-through' => $todo->isDone()])>{{ $todo->title }}</p>
                <p class="flex flex-wrap items-center gap-2 text-xs text-muted">
                    @if ($todo->isOverdue())
                        <span class="badge tone-danger">Overdue · {{ $todo->due_date->format('d M') }}</span>
                    @elseif ($todo->isDueToday())
                        <span class="badge tone-warning">Due today</span>
                    @elseif ($todo->due_date)
                        <span>Due {{ $todo->due_date->format('d M Y') }}</span>
                    @endif
                    @if ($todo->priority !== App\Enums\Priority::Medium)<x-badge :enum="$todo->priority" />@endif
                    @if (! $project && $todo->project)<a class="link" href="{{ route('projects.show', $todo->project_id) }}">{{ $todo->project->code }}</a>@endif
                </p>
                @if ($todo->notes)<p class="mt-0.5 whitespace-pre-line text-xs">{{ $todo->notes }}</p>@endif
            </div>
            <form method="POST" action="{{ route('todos.destroy', $todo) }}" data-confirm="Delete this to-do?">
                @csrf @method('DELETE')
                <button class="btn btn-ghost btn-sm" aria-label="Delete to-do"><x-icon name="trash" :size="14" /></button>
            </form>
        </li>
    @empty
        <li><x-empty-state title="No open to-dos" icon="check-square">Add personal reminders here. Nobody else can see them.</x-empty-state></li>
    @endforelse
</ul>
