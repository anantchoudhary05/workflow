@extends('layouts.app')
@section('title', 'Previews · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'previews'])
    <div class="mb-4 flex items-center justify-between gap-2">
        <p class="text-sm text-muted">Builds shared with the client for review. Approval is only recorded with its source.</p>
        @if ($canManage)<button type="button" class="btn btn-primary" data-dialog-open="new-preview"><x-icon name="plus" :size="16" /> Add preview</button>@endif
    </div>

    @if ($previews->isEmpty())
        <x-card><x-empty-state title="No previews yet" icon="eye">Add a staging link when a build is ready for client review.</x-empty-state></x-card>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($previews as $preview)
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 font-medium">{{ $preview->title }} <span class="text-xs text-muted">{{ $preview->version_label }}</span> <x-badge :enum="$preview->status" /></p>
                            <p class="mt-1 text-sm text-muted">
                                @if ($preview->milestone){{ $preview->milestone->name }} · @endif
                                @if ($preview->shared_at)Shared {{ $preview->shared_at->format('d M Y') }}@if ($preview->sharer) by {{ $preview->sharer->name }}@endif · @endif
                                Feedback due <x-date :value="$preview->review_due_at" :overdue="in_array($preview->status, App\Enums\PreviewStatus::waiting(), true)" />
                            </p>
                            @if ($preview->url)<a class="link mt-1 inline-flex items-center gap-1 text-sm" href="{{ $preview->url }}" target="_blank" rel="noopener noreferrer">{{ $preview->url }} <x-icon name="external" :size="14" /></a>@endif
                            @if ($preview->feedback_summary)<p class="mt-2 whitespace-pre-line text-sm">{{ $preview->feedback_summary }}</p>@endif
                            @if ($canManage && $preview->internal_notes)<p class="mt-2 whitespace-pre-line text-sm text-muted">{{ $preview->internal_notes }}</p>@endif
                            @foreach ($preview->approvals as $approval)
                                <p class="mt-2 text-xs text-muted"><x-badge :enum="$approval->outcome" /> {{ $approval->decided_by_name }} · {{ $approval->decided_at?->format('d M Y') }} · {{ $approval->source_reference }}</p>
                            @endforeach
                        </div>
                        @if ($canManage)
                            <div class="flex gap-2">
                                <button type="button" class="btn btn-sm" data-dialog-open="pv-feedback-{{ $preview->id }}">Record feedback</button>
                                <button type="button" class="btn btn-sm" data-dialog-open="pv-edit-{{ $preview->id }}"><x-icon name="edit" :size="14" /> Edit</button>
                            </div>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <x-dialog id="new-preview" title="Add preview">
            <form method="POST" action="{{ route('projects.previews.store', $project) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @include('projects.partials.preview-fields', ['preview' => null])
                <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Add preview</button></div>
            </form>
        </x-dialog>
        @foreach ($previews as $preview)
            @php($statusOptions = $preview->approvals->isEmpty() ? collect($statuses)->except(App\Enums\PreviewStatus::Approved->value)->all() : $statuses)
            <x-dialog :id="'pv-edit-'.$preview->id" :title="'Edit preview · '.$preview->title">
                <form method="POST" action="{{ route('previews.update', $preview) }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <input type="hidden" name="_preview" value="{{ $preview->id }}">
                    @include('projects.partials.preview-fields')
                    <x-field name="status" type="select" label="Status" :options="$statusOptions" :value="$preview->status" hint="Moving to Shared or Awaiting feedback creates a client follow-up." required class="sm:col-span-2" />
                    <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
                </form>
            </x-dialog>
            <x-dialog :id="'pv-feedback-'.$preview->id" :title="'Client feedback · '.$preview->title">
                <form method="POST" action="{{ route('previews.feedback', $preview) }}" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="_feedback" value="{{ $preview->id }}">
                    @include('partials.decision-fields')
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Record</button></div>
                </form>
            </x-dialog>
        @endforeach
    @endif
@endsection
