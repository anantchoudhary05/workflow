{{-- Expects $tasks; optional $showProject (default true). --}}
@php($showProject = $showProject ?? true)
@if ($tasks->isEmpty())
    <x-empty-state title="No tasks match" icon="check-square" />
@else
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Task</th>@if ($showProject)<th>Project</th>@endif<th>Assignee</th><th>Status</th><th>Priority</th><th>Due</th></tr></thead>
            <tbody>
                @foreach ($tasks as $task)
                    <tr>
                        <td><a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a>@if ($task->milestone)<p class="text-xs text-muted">{{ $task->milestone->name }}</p>@endif</td>
                        @if ($showProject)<td class="text-muted">{{ $task->project?->code }}</td>@endif
                        <td>{{ $task->assignee?->name ?? '—' }}</td>
                        <td><x-badge :enum="$task->status" /></td>
                        <td><x-badge :enum="$task->priority" /></td>
                        <td><x-date :value="$task->due_date" :overdue="$task->isOpen()" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
