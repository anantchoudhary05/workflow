@extends('layouts.app')
@section('title', 'Task board')
@section('content')
    <x-page-header title="Task board" subtitle="Drag a card to move it. Moves follow the same rules as the task page; closed tasks from the last 14 days are shown.">
        <x-slot:actions>
            <a href="{{ route('tasks.index', request()->query()) }}" class="btn"><x-icon name="list" :size="16" /> List</a>
            @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', request()->only('project')) }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New task</a>@endcan
        </x-slot:actions>
    </x-page-header>
    @include('tasks.partials.filters', ['statuses' => null])

    @php($reasons = ['blocked' => 'What is blocking this task?', 'changes_requested' => 'What changes are requested?'])
    <div class="flex gap-3 overflow-x-auto pb-4" data-kanban>
        @foreach (App\Enums\TaskStatus::cases() as $status)
            @php($cards = $columns->get($status->value, collect()))
            <section class="kanban-col flex flex-col rounded-lg bg-subtle p-2" data-column="{{ $status->value }}" @isset($reasons[$status->value]) data-needs-reason="{{ $reasons[$status->value] }}" @endisset aria-label="{{ $status->label() }}">
                <header class="mb-2 flex items-center justify-between px-1">
                    <h2 class="text-sm font-semibold">{{ $status->label() }}</h2>
                    <span class="badge tone-neutral" data-count>{{ $cards->count() }}</span>
                </header>
                <div class="flex min-h-24 flex-1 flex-col gap-2" data-cards>
                    @foreach ($cards as $task)
                        @php($movable = auth()->user()->can('changeStatus', $task))
                        <article class="kanban-card card p-3" data-card data-status-url="{{ route('tasks.status', $task) }}" @if ($movable) draggable="true" @else style="cursor: default" @endif>
                            <a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a>
                            <p class="mt-1 text-xs text-muted">{{ $task->project?->code }}@if ($task->milestone) · {{ $task->milestone->name }}@endif</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                <x-badge :enum="$task->priority" />
                                @if ($task->due_date)<x-date :value="$task->due_date" :overdue="$task->isOpen()" />@endif
                                <span class="ml-auto text-muted">{{ $task->assignee?->name ?? 'Unassigned' }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
