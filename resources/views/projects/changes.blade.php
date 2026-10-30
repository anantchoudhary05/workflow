@extends('layouts.app')
@section('title', 'Changes · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'changes'])
    <div class="mb-4 flex items-center justify-between gap-2">
        <p class="text-sm text-muted">Anything requested after the scope baseline is logged here, classified, and approved before it is built.</p>
        @if ($canLog)<button type="button" class="btn btn-primary" data-dialog-open="new-change"><x-icon name="plus" :size="16" /> Log change request</button>@endif
    </div>
    <x-card :padded="false">
        @if ($changes->isEmpty())
            <x-empty-state title="No change requests" icon="git-pull-request" />
        @else
            @include('change-requests.partials.table', ['showProject' => false])
        @endif
    </x-card>

    @if ($canLog)
        <x-dialog id="new-change" title="Log change request">
            <form method="POST" action="{{ route('projects.changes.store', $project) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-field name="title" label="Title" required class="sm:col-span-2" />
                <x-field name="description" type="textarea" label="What is being asked for" required class="sm:col-span-2" />
                <x-field name="reason" type="textarea" label="Why (client's reason)" class="sm:col-span-2" />
                <x-field name="requested_by" label="Requested by" />
                <x-field name="source" label="Source" placeholder="Email, call, meeting…" />
                <x-field name="received_at" type="date" label="Received" :value="today()" required />
                <x-field name="priority" type="select" label="Priority" :options="$priorities" :value="App\Enums\Priority::Medium" required />
                @can('assess-change-requests')
                    <x-field name="classification" type="select" label="Classification" :options="$classifications" blank="Classify later" />
                    <x-field name="reviewer_id" type="select" label="Reviewer" :options="$users" blank="Unassigned" />
                    <x-field name="classification_rationale" type="textarea" label="Classification rationale" hint="Required when a classification is chosen." class="sm:col-span-2" />
                @endcan
                <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Log request</button></div>
            </form>
        </x-dialog>
    @endif
@endsection
