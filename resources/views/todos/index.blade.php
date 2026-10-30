@extends('layouts.app')
@section('title', 'My to-dos')
@section('content')
    <x-page-header title="My to-dos" icon="check-square" subtitle="Your private checklist. Nobody else, not even an administrator, can see these.">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap gap-2">
                <span class="badge tone-info">{{ $counts['open'] }} open</span>
                @if ($counts['overdue'])<span class="badge tone-danger">{{ $counts['overdue'] }} overdue</span>@endif
                @if ($counts['today'])<span class="badge tone-warning">{{ $counts['today'] }} due today</span>@endif
            </div>
        </x-slot:meta>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="flex flex-col gap-4 xl:col-span-2">
            <nav class="flex flex-wrap gap-2" aria-label="Filter to-dos">
                @foreach (['open' => 'Open', 'done' => 'Done', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('todos.index', array_filter(['show' => $key, 'project' => request('project')])) }}" @class(['btn btn-sm', 'btn-primary' => $filter === $key])>{{ $label }}</a>
                @endforeach
                @if (request('project'))<a href="{{ route('todos.index', ['show' => $filter]) }}" class="btn btn-ghost btn-sm"><x-icon name="x" :size="14" /> Clear project filter</a>@endif
            </nav>

            <x-card :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($todos as $todo)
                        <li @class(['flex items-start gap-3 px-4 py-3', 'opacity-60' => $todo->isDone()])>
                            <form method="POST" action="{{ route('todos.toggle', $todo) }}">
                                @csrf
                                <button class="btn btn-ghost btn-sm" aria-label="{{ $todo->isDone() ? 'Mark as not done' : 'Mark as done' }}"><x-icon :name="$todo->isDone() ? 'check-circle' : 'check-square'" :size="16" /></button>
                            </form>
                            <div class="min-w-0 flex-1">
                                <p @class(['font-medium', 'line-through' => $todo->isDone()])>{{ $todo->title }}</p>
                                <p class="flex flex-wrap items-center gap-2 text-xs text-muted">
                                    @if ($todo->isOverdue())<span class="badge tone-danger">Overdue · {{ $todo->due_date->format('d M') }}</span>
                                    @elseif ($todo->isDueToday())<span class="badge tone-warning">Due today</span>
                                    @elseif ($todo->due_date)<span>Due {{ $todo->due_date->format('d M Y') }}</span>@endif
                                    <x-badge :enum="$todo->priority" />
                                    @if ($todo->project)<a class="link" href="{{ route('projects.show', $todo->project_id) }}">{{ $todo->project->code }} · {{ $todo->project->name }}</a>@endif
                                    @if ($todo->completed_at)<span>Done {{ $todo->completed_at->format('d M') }}</span>@endif
                                </p>
                                @if ($todo->notes)<p class="mt-1 whitespace-pre-line text-sm">{{ $todo->notes }}</p>@endif
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" data-dialog-open="edit-todo-{{ $todo->id }}" aria-label="Edit to-do"><x-icon name="edit" :size="14" /></button>
                            <form method="POST" action="{{ route('todos.destroy', $todo) }}" data-confirm="Delete this to-do?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="Delete to-do"><x-icon name="trash" :size="14" /></button></form>
                        </li>
                    @empty
                        <li><x-empty-state title="Nothing here" icon="check-square">Add a to-do on the right, from your dashboard, or from any project page.</x-empty-state></li>
                    @endforelse
                </ul>
            </x-card>
            <div>{{ $todos->links() }}</div>
        </div>

        <x-card title="Add a to-do" icon="plus" class="self-start">
            <form method="POST" action="{{ route('todos.store') }}" class="flex flex-col gap-4">
                @csrf
                <x-field name="title" label="Title" required />
                <x-field name="notes" type="textarea" label="Notes" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="due_date" type="date" label="Due date" />
                    <x-field name="priority" type="select" label="Priority" :options="$priorities" value="medium" required />
                </div>
                <x-field name="project_id" type="select" label="Project (optional)" :options="$projects" blank="No project" :value="request('project')" />
                <button class="btn btn-primary"><x-icon name="plus" :size="16" /> Add to-do</button>
            </form>
        </x-card>
    </div>

    @foreach ($todos as $todo)
        <x-dialog id="edit-todo-{{ $todo->id }}" title="Edit to-do" icon="edit">
            <form method="POST" action="{{ route('todos.update', $todo) }}" class="flex flex-col gap-4">
                @csrf @method('PUT')
                <x-field name="title" label="Title" :value="$todo->title" required />
                <x-field name="notes" type="textarea" label="Notes" :value="$todo->notes" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="due_date" type="date" label="Due date" :value="$todo->due_date" />
                    <x-field name="priority" type="select" label="Priority" :options="$priorities" :value="$todo->priority" required />
                </div>
                <x-field name="project_id" type="select" label="Project" :options="$projects + ($todo->project ? [$todo->project_id => $todo->project->code.' · '.$todo->project->name] : [])" blank="No project" :value="$todo->project_id" />
                <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
            </form>
        </x-dialog>
    @endforeach
@endsection
