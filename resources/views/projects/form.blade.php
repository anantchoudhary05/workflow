@extends('layouts.app')
@section('title', $project->exists ? 'Edit '.$project->name : 'New project')
@section('content')
    <x-page-header :title="$project->exists ? 'Edit project' : 'New project'" :back="$project->exists ? route('projects.show', $project) : route('projects.index')"
        :subtitle="$project->exists ? 'Status and delivery date are changed from the project overview so every change is recorded with a reason.' : null" />

    <form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="flex flex-col gap-6">
        @csrf
        @if ($project->exists) @method('PUT') @endif
        <x-card title="Project">
            <div class="grid gap-4 md:grid-cols-2">
                <x-field name="name" label="Project name" :value="$project->name" required class="md:col-span-2" />
                <x-field name="client_id" type="select" label="Client" :options="$clients" :value="$project->client_id" blank="Select client" required />
                <x-field name="type" type="select" label="Type" :options="$types" :value="$project->type" required />
                <x-field name="manager_id" type="select" label="Project manager" :options="$users" :value="$project->manager_id" blank="Unassigned" />
                <x-field name="priority" type="select" label="Priority" :options="$priorities" :value="$project->priority" required />
                @if ($project->exists)
                    <div>
                        <span class="label">Status</span>
                        <p class="py-2"><x-badge :enum="$project->status" /></p>
                        <p class="hint">Change it from the overview → “Change status” (reason recorded).</p>
                    </div>
                @else
                    <x-field name="status" type="select" label="Initial status" :options="$initialStatuses" :value="$project->status" required
                        hint="Choose “In Progress” or “Ready To Start” for work already underway; it then counts as an active project on the dashboard." />
                @endif
                <x-field name="description" type="textarea" label="Description" :value="$project->description" rows="4" class="md:col-span-2" />
            </div>
        </x-card>

        <x-card title="Dates & effort">
            <div class="grid gap-4 md:grid-cols-4">
                <x-field name="start_date" type="date" label="Start date" :value="$project->start_date" />
                @if ($project->exists)
                    <div><span class="label">Delivery date</span><p class="py-2"><x-date :value="$project->due_date" empty="Not set" /></p><p class="hint">Change it from the overview (reason required).</p></div>
                @else
                    <x-field name="due_date" type="date" label="Delivery date" hint="Becomes the original baseline." />
                @endif
                <x-field name="launch_date" type="date" label="Planned launch" :value="$project->launch_date" />
                <x-field name="estimated_hours" type="number" label="Estimated hours" :value="$project->estimated_hours" min="0" step="0.5" />
            </div>
        </x-card>

        @unless ($project->exists)
        <x-card title="Team">
            <p class="hint mb-3">Members can see this project; the manager is added automatically. Manage the team later from the overview.</p>
            @php($selected = collect(old('members', [])))
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($users as $id => $name)
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="members[]" value="{{ $id }}" class="size-4 rounded border-line" @checked($selected->contains($id))> {{ $name }}</label>
                @endforeach
            </div>
            @error('members')<p class="error">{{ $message }}</p>@enderror
        </x-card>
        @endunless

        <x-card title="Links & notes">
            <div class="grid gap-4 md:grid-cols-3">
                <x-field name="repository_url" type="url" label="Repository URL" :value="$project->repository_url" />
                <x-field name="staging_url" type="url" label="Staging URL" :value="$project->staging_url" />
                <x-field name="production_url" type="url" label="Production URL" :value="$project->production_url" />
                <x-field name="client_summary" type="textarea" label="Client-facing summary" :value="$project->client_summary" class="md:col-span-3" />
                <x-field name="internal_notes" type="textarea" label="Internal notes" :value="$project->internal_notes" hint="Never store passwords, API keys or credentials here." class="md:col-span-3" />
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}" class="btn">Cancel</a>
            <button class="btn btn-primary">{{ $project->exists ? 'Save changes' : 'Create project' }}</button>
        </div>
    </form>
@endsection
