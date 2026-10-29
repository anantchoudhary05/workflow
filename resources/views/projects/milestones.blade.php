@extends('layouts.app')
@section('title', 'Milestones · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'milestones'])
    @php($allNames = $milestones->pluck('name', 'id'))

    <div class="mb-4 flex items-center justify-between gap-2">
        <p class="text-sm text-muted">Milestone weights drive project progress. Dependencies and approvals are enforced before completion.</p>
        @can('manage')<button type="button" class="btn btn-primary" data-dialog-open="new-milestone"><x-icon name="plus" :size="16" /> Add milestone</button>@endcan
    </div>

    @if ($milestones->isEmpty())
        <x-card><x-empty-state title="No milestones yet" icon="flag">Break delivery into milestones with dates, owners and acceptance criteria.</x-empty-state></x-card>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($milestones as $milestone)
                @php($pct = $milestone->tasks_count ? (int) round($milestone->done_tasks_count / $milestone->tasks_count * 100) : null)
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $milestone->name }} <x-badge :enum="$milestone->status" /> @if ($milestone->requires_approval)<span class="badge tone-info">Needs approval</span>@endif</p>
                            <p class="mt-1 text-sm text-muted">
                                {{ $milestone->owner?->name ?? 'No owner' }} ·
                                Due <x-date :value="$milestone->due_date" :overdue="$milestone->isOverdue()" empty="not set" /> ·
                                Weight {{ $milestone->weight }} ·
                                {{ $milestone->done_tasks_count }}/{{ $milestone->tasks_count }} tasks done
                            </p>
                            @if ($milestone->dependencies->isNotEmpty())
                                <p class="mt-1 text-xs text-muted">Depends on:
                                    @foreach ($milestone->dependencies as $dependency)
                                        <span @class(['text-success' => $dependency->status === App\Enums\MilestoneStatus::Completed, 'text-warning' => $dependency->status !== App\Enums\MilestoneStatus::Completed])>{{ $dependency->name }}</span>@if (! $loop->last), @endif
                                    @endforeach
                                </p>
                            @endif
                            @if ($milestone->acceptance_criteria)
                                <p class="mt-2 text-xs"><span class="font-medium">Acceptance:</span> {{ Illuminate\Support\Str::limit($milestone->acceptance_criteria, 200) }} @if ($milestone->criteria_met)<span class="text-success">✓ met</span>@endif</p>
                            @endif
                            @if ($milestone->override_reason)<p class="mt-1 text-xs text-warning">Override: {{ $milestone->override_reason }}</p>@endif
                        </div>
                        @can('manage')
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm" data-dialog-open="ms-status-{{ $milestone->id }}">Status</button>
                                @if ($milestone->requires_approval)<button type="button" class="btn btn-sm" data-dialog-open="ms-approval-{{ $milestone->id }}">Record approval</button>@endif
                                <button type="button" class="btn btn-sm" data-dialog-open="ms-edit-{{ $milestone->id }}"><x-icon name="edit" :size="14" /> Edit</button>
                            </div>
                        @endcan
                    </div>
                    @if ($pct !== null)<div class="progress mt-3"><span style="width: {{ $pct }}%"></span></div>@endif
                </x-card>
            @endforeach
        </div>
    @endif

    @can('manage')
        <x-dialog id="new-milestone" title="Add milestone">
            <form method="POST" action="{{ route('projects.milestones.store', $project) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @include('projects.partials.milestone-fields', ['milestone' => new App\Models\Milestone, 'others' => $allNames->all(), 'dialog' => 'new-milestone'])
                <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Add milestone</button></div>
            </form>
        </x-dialog>

        @foreach ($milestones as $milestone)
            <x-dialog :id="'ms-edit-'.$milestone->id" :title="'Edit '.$milestone->name">
                <form method="POST" action="{{ route('milestones.update', $milestone) }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <input type="hidden" name="_milestone" value="{{ $milestone->id }}">
                    @include('projects.partials.milestone-fields', ['others' => $allNames->except($milestone->id)->all(), 'dialog' => 'ms-edit-'.$milestone->id])
                    <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
                </form>
            </x-dialog>

            <x-dialog :id="'ms-status-'.$milestone->id" :title="'Status of '.$milestone->name">
                <form method="POST" action="{{ route('milestones.status', $milestone) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="status" type="select" label="Status" :options="$statuses" :value="$milestone->status" required />
                    @if (auth()->user()->isAdmin())
                        <x-field name="override_reason" label="Override reason" hint="Only fill this to bypass dependency, criteria or approval checks. It is audited." />
                    @endif
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Update</button></div>
                </form>
            </x-dialog>

            @if ($milestone->requires_approval)
                <x-dialog :id="'ms-approval-'.$milestone->id" :title="'Client approval · '.$milestone->name">
                    <form method="POST" action="{{ route('approvals.milestone', $milestone) }}" class="flex flex-col gap-4">
                        @csrf
                        <x-field name="outcome" type="select" label="Outcome" :options="App\Enums\ApprovalOutcome::options()" required />
                        <x-field name="decided_by_name" label="Decided by (client contact)" required />
                        <x-field name="source_reference" label="Evidence" placeholder="Email, call notes, signed document…" required />
                        <x-field name="decided_at" type="date" label="Decision date" :value="now()" required />
                        <x-field name="notes" type="textarea" label="Notes / conditions" />
                        <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Record</button></div>
                    </form>
                </x-dialog>
            @endif
        @endforeach
    @endcan
@endsection
