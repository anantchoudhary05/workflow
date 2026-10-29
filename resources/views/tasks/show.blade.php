@extends('layouts.app')
@section('title', $task->title)
@section('content')
    <x-page-header :title="$task->title" :back="route('projects.tasks', $task->project_id)">
        <x-slot:meta>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                <x-badge :enum="$task->status" /> <x-badge :enum="$task->priority" />
                <a class="link" href="{{ route('projects.show', $task->project_id) }}">{{ $task->project->code }} · {{ $task->project->name }}</a>
            </p>
        </x-slot:meta>
        <x-slot:actions>
            @can('changeStatus', $task)
                @if ($transitions)<button type="button" class="btn btn-primary" data-dialog-open="task-status">Move task</button>@endif
            @endcan
            @can('update', $task)<a href="{{ route('tasks.edit', $task) }}" class="btn"><x-icon name="edit" :size="16" /> Edit</a>@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            @if ($task->blocker_reason)
                <div class="alert tone-danger"><p class="font-medium">Blocked</p><p>{{ $task->blocker_reason }}</p></div>
            @endif
            <x-card title="Details">
                <p class="whitespace-pre-line">{{ $task->description ?: 'No description.' }}</p>
                @if ($task->acceptance_criteria)<p class="label mt-4">Acceptance criteria</p><p class="whitespace-pre-line">{{ $task->acceptance_criteria }}</p>@endif
                @if ($task->internal_notes)<p class="label mt-4">Internal notes</p><p class="whitespace-pre-line text-muted">{{ $task->internal_notes }}</p>@endif
            </x-card>

            <x-card title="Comments" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($task->comments as $comment)
                        <li class="px-4 py-3"><p class="text-xs text-muted"><span class="font-medium text-ink">{{ $comment->user?->name }}</span> · {{ $comment->created_at?->diffForHumans() }}</p><p class="mt-1 whitespace-pre-line">{{ $comment->body }}</p></li>
                    @empty
                        <li class="px-4 py-4 text-muted">No comments yet.</li>
                    @endforelse
                </ul>
                @can('comment', $task)
                    <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="flex flex-col gap-2 border-t border-line p-4">
                        @csrf
                        <x-field name="body" type="textarea" label="Add a comment" required />
                        <div class="flex justify-end"><button class="btn btn-primary">Comment</button></div>
                    </form>
                @endcan
            </x-card>

            <x-card title="History"><ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $history])</ul></x-card>
        </div>

        <div class="flex flex-col gap-6">
            <x-card>
                <p class="text-xs font-medium uppercase tracking-wide text-muted">Next action</p>
                <p class="mt-1 font-medium">{{ $task->nextAction() }}</p>
            </x-card>
            <x-card title="Overview">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <dt class="text-muted">Assignee</dt><dd>{{ $task->assignee?->name ?? 'Unassigned' }}</dd>
                    <dt class="text-muted">Reviewer</dt><dd>{{ $task->reviewer?->name ?? '—' }}</dd>
                    <dt class="text-muted">Start</dt><dd><x-date :value="$task->start_date" /></dd>
                    <dt class="text-muted">Due</dt><dd><x-date :value="$task->due_date" :overdue="$task->isOpen()" /></dd>
                    <dt class="text-muted">Milestone</dt><dd>{{ $task->milestone?->name ?? '—' }}</dd>
                    <dt class="text-muted">Change request</dt><dd>@if ($task->changeRequest)<a class="link" href="{{ route('change-requests.show', $task->changeRequest) }}">{{ $task->changeRequest->number }}</a>@else — @endif</dd>
                    <dt class="text-muted">Estimate</dt><dd>{{ $task->estimated_hours ? (float) $task->estimated_hours.' h' : '—' }}</dd>
                    <dt class="text-muted">Logged</dt><dd>{{ (float) $task->timeEntries->sum('hours') }} h</dd>
                    @if ($task->tags)<dt class="text-muted">Tags</dt><dd>{{ $task->tags }}</dd>@endif
                    <dt class="text-muted">Created</dt><dd>{{ $task->creator?->name }} · {{ $task->created_at?->format('d M Y') }}</dd>
                    @if ($task->completed_at)<dt class="text-muted">Completed</dt><dd>{{ $task->completed_at->format('d M Y') }}</dd>@endif
                </dl>
            </x-card>

            <x-card title="Time" :padded="false">
                <ul class="divide-y divide-line text-sm">
                    @forelse ($task->timeEntries as $entry)
                        <li class="flex justify-between gap-2 px-4 py-2"><span>{{ $entry->work_date->format('d M') }} · {{ $entry->user?->name }}@if ($entry->description)<span class="block text-xs text-muted">{{ $entry->description }}</span>@endif</span><span class="tabular-nums">{{ (float) $entry->hours }} h @unless ($entry->billable)<span class="text-xs text-muted">(non-billable)</span>@endunless</span></li>
                    @empty
                        <li class="px-4 py-3 text-muted">No time logged.</li>
                    @endforelse
                </ul>
                @can('logTime', $task)
                    <form method="POST" action="{{ route('tasks.time.store', $task) }}" class="grid grid-cols-2 gap-2 border-t border-line p-4">
                        @csrf
                        <x-field name="work_date" type="date" label="Date" :value="today()" required />
                        <x-field name="hours" type="number" label="Hours" min="0.25" max="24" step="0.25" required />
                        <x-field name="description" label="What was done" class="col-span-2" />
                        <x-field name="billable" type="checkbox" label="Billable" :value="true" class="col-span-2" />
                        <button class="btn col-span-2">Log time</button>
                    </form>
                @endcan
            </x-card>
        </div>
    </div>

    @can('changeStatus', $task)
        @if ($transitions)
            <x-dialog id="task-status" title="Move task">
                <form method="POST" action="{{ route('tasks.status', $task) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="status" type="select" label="New status" :options="collect($transitions)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" required />
                    <x-field name="reason" type="textarea" label="Reason / note" hint="Required when blocking a task or requesting changes; otherwise added as a comment." />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Move</button></div>
                </form>
            </x-dialog>
        @endif
    @endcan
@endsection
