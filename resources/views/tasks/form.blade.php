@extends('layouts.app')
@section('title', $task->exists ? 'Edit task' : 'New task')
@section('content')
    <x-page-header :title="$task->exists ? 'Edit task' : 'New task'" :back="$task->exists ? route('tasks.show', $task) : route('tasks.index')" />

    @if (! $task->exists && ! $task->project_id)
        <x-card title="Choose a project" class="max-w-xl">
            <form method="GET" action="{{ route('tasks.create') }}" class="flex items-end gap-2">
                <x-field name="project" type="select" label="Project" :options="$projects" blank="Select project" required class="flex-1" />
                <button class="btn btn-primary">Continue</button>
            </form>
            <p class="hint mt-2">Milestones and change requests are loaded for the selected project.</p>
        </x-card>
    @else
        <form method="POST" action="{{ $task->exists ? route('tasks.update', $task) : route('tasks.store') }}" class="flex max-w-4xl flex-col gap-6">
            @csrf
            @if ($task->exists) @method('PUT') @else <input type="hidden" name="project_id" value="{{ $task->project_id }}"> @endif
            <x-card title="Task">
                <p class="mb-4 text-sm text-muted">Project: <span class="font-medium text-ink">{{ $projects[$task->project_id] ?? 'Closed project' }}</span>@unless ($task->exists) · <a class="link" href="{{ route('tasks.create') }}">change</a>@endunless</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="title" label="Title" :value="$task->title" required class="sm:col-span-2" />
                    <x-field name="milestone_id" type="select" label="Milestone" :options="$milestones" :value="$task->milestone_id" blank="None" />
                    <x-field name="change_request_id" type="select" label="Change request" :options="$changeRequests" :value="$task->change_request_id" blank="None" />
                    <x-field name="description" type="textarea" label="Description" :value="$task->description" rows="5" class="sm:col-span-2" />
                    <x-field name="acceptance_criteria" type="textarea" label="Acceptance criteria" :value="$task->acceptance_criteria" class="sm:col-span-2" />
                </div>
            </x-card>
            <x-card title="Assignment & schedule">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="assignee_id" type="select" label="Assignee" :options="$users" :value="$task->assignee_id" blank="Unassigned" />
                    <x-field name="reviewer_id" type="select" label="Reviewer" :options="$users" :value="$task->reviewer_id" blank="No review" />
                    <x-field name="priority" type="select" label="Priority" :options="$priorities" :value="$task->priority" required />
                    @unless ($task->exists)
                        <x-field name="status" type="select" label="Initial status" :options="$initialStatuses" value="backlog" hint="Review and Done are reached through the workflow." />
                    @endunless
                    <x-field name="start_date" type="date" label="Start" :value="$task->start_date" />
                    <x-field name="due_date" type="date" label="Due" :value="$task->due_date" />
                    <x-field name="estimated_hours" type="number" label="Estimate (hours)" :value="$task->estimated_hours" min="0" step="0.25" />
                    <x-field name="tags" label="Tags" :value="$task->tags" placeholder="frontend, api" class="sm:col-span-3" />
                    <x-field name="internal_notes" type="textarea" label="Internal notes" :value="$task->internal_notes" class="sm:col-span-3" />
                </div>
                <p class="hint mt-2">Assignees and reviewers are added to the project team automatically.</p>
            </x-card>
            <div class="flex justify-end gap-2">
                <a href="{{ $task->exists ? route('tasks.show', $task) : route('tasks.index') }}" class="btn">Cancel</a>
                <button class="btn btn-primary">{{ $task->exists ? 'Save changes' : 'Create task' }}</button>
            </div>
        </form>
    @endif
@endsection
