@extends('layouts.app')
@section('title', $defect->code.' · '.$defect->title)
@section('content')
    <x-page-header :title="$defect->code.' · '.$defect->title" :back="route('projects.qa', $defect->project_id)">
        <x-slot:meta>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted"><x-badge :enum="$defect->severity" /> <x-badge :enum="$defect->status" /> <a class="link" href="{{ route('projects.show', $defect->project_id) }}">{{ $defect->project->code }} · {{ $defect->project->name }}</a></p>
        </x-slot:meta>
        <x-slot:actions>@if ($allowed)<button type="button" class="btn btn-primary" data-dialog-open="defect-status">Update status</button>@endif</x-slot:actions>
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <x-card title="Details">
                @if ($defect->description)<p class="whitespace-pre-line">{{ $defect->description }}</p>@endif
                @if ($defect->steps)<p class="label mt-4">Steps to reproduce</p><p class="whitespace-pre-line">{{ $defect->steps }}</p>@endif
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><p class="label">Expected</p><p class="whitespace-pre-line">{{ $defect->expected_behavior ?: '—' }}</p></div>
                    <div><p class="label">Actual</p><p class="whitespace-pre-line">{{ $defect->actual_behavior ?: '—' }}</p></div>
                </div>
                @if ($defect->resolution_notes)<p class="label mt-4">Resolution notes</p><p class="whitespace-pre-line">{{ $defect->resolution_notes }}</p>@endif
                @if ($defect->file)<a class="link mt-4 inline-flex items-center gap-1 text-sm" href="{{ route('files.download', $defect->file) }}"><x-icon name="paperclip" :size="14" /> {{ $defect->file->original_name }}</a>@endif
            </x-card>
            <x-card title="History"><ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $history])</ul></x-card>
        </div>
        <x-card title="Overview">
            <dl class="grid gap-3 text-sm">
                <div><dt class="text-muted">Priority</dt><dd><x-badge :enum="$defect->priority" /></dd></div>
                <div><dt class="text-muted">Assignee</dt><dd>{{ $defect->assignee?->name ?? '—' }}</dd></div>
                <div><dt class="text-muted">Reported by</dt><dd>{{ $defect->reporter?->name }} · {{ $defect->created_at->format('d M Y') }}</dd></div>
                @if ($defect->testCase)<div><dt class="text-muted">Test case</dt><dd>{{ $defect->testCase->code }} {{ $defect->testCase->title }}</dd></div>@endif
                @if ($defect->resolved_at)<div><dt class="text-muted">Resolved</dt><dd>{{ $defect->resolved_at->format('d M Y') }}</dd></div>@endif
                @if ($defect->verified_at)<div><dt class="text-muted">Verified</dt><dd>{{ $defect->verifier?->name }} · {{ $defect->verified_at->format('d M Y') }}</dd></div>@endif
            </dl>
        </x-card>
    </div>
    @if ($allowed)
        <x-dialog id="defect-status" title="Update status">
            <form method="POST" action="{{ route('defects.status', $defect) }}" class="flex flex-col gap-4">
                @csrf
                <x-field name="status" type="select" label="New status" :options="collect($allowed)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" required />
                <x-field name="resolution_notes" type="textarea" label="Notes" hint="Required for Won't fix and Reopened." />
                @can('manage-qa')<x-field name="assignee_id" type="select" label="Reassign to" :options="$users" :value="$defect->assignee_id" blank="Keep current" />@endcan
                <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Update</button></div>
            </form>
        </x-dialog>
    @endif
@endsection
