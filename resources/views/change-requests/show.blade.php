@extends('layouts.app')
@section('title', $cr->number.' · '.$cr->title)
@section('content')
    @php
        $S = App\Enums\ChangeRequestStatus::class;
        $assessable = ! in_array($cr->status, [$S::Approved, $S::Scheduled, $S::InProgress, $S::Implemented, $S::Verified, $S::Rejected, $S::Cancelled], true);
        $statusTargets = collect($transitions)->reject(fn ($s) => $cr->status === $S::AwaitingClientApproval && in_array($s, [$S::Approved, $S::Rejected], true));
    @endphp
    <x-page-header :title="$cr->number.' · '.$cr->title" :back="route('projects.changes', $cr->project)">
        <x-slot:meta>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                <x-badge :enum="$cr->status" />
                @if ($cr->classification)<x-badge :enum="$cr->classification" />@else <span class="badge tone-warning">Unclassified</span>@endif
                <a class="link" href="{{ route('projects.show', $cr->project) }}">{{ $cr->project->code }} · {{ $cr->project->name }}</a>
            </p>
        </x-slot:meta>
        <x-slot:actions>
            @can('manage')
                @if ($cr->status === $S::AwaitingInternalReview && ! $cr->internal_reviewed_at)<button type="button" class="btn" data-dialog-open="cr-review">Record internal review</button>@endif
                @if ($cr->status === $S::AwaitingClientApproval)<button type="button" class="btn btn-primary" data-dialog-open="cr-decision">Record client decision</button>@endif
                @if ($statusTargets->isNotEmpty() || in_array($cr->status, [$S::Approved, $S::UnderAssessment, $S::AwaitingInternalReview], true))<button type="button" class="btn" data-dialog-open="cr-status">Change status</button>@endif
                @if (in_array($cr->status, [$S::Approved, $S::Scheduled, $S::InProgress], true))
                    <a class="btn" href="{{ route('tasks.create', ['project' => $cr->project_id, 'change_request' => $cr->id]) }}"><x-icon name="plus" :size="16" /> Task</a>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <x-card title="Request">
                <p class="whitespace-pre-line">{{ $cr->description }}</p>
                @if ($cr->reason)<p class="label mt-4">Reason</p><p class="whitespace-pre-line">{{ $cr->reason }}</p>@endif
                <p class="mt-4 text-xs text-muted">Received {{ $cr->received_at?->format('d M Y') }}@if ($cr->requested_by) from {{ $cr->requested_by }}@endif @if ($cr->source) via {{ $cr->source }}@endif</p>
            </x-card>

            <x-card title="Assessment">
                @if ($cr->classification)
                    <p class="text-sm text-muted">{{ $cr->classification->guidance() }}</p>
                    @if ($cr->classification_rationale)<p class="mt-2 whitespace-pre-line">{{ $cr->classification_rationale }}</p>@endif
                @else
                    <p class="text-muted">Not classified yet.</p>
                @endif
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div><dt class="text-muted">Extra hours</dt><dd>{{ $cr->extra_hours !== null ? (float) $cr->extra_hours : '—' }}</dd></div>
                    @can('view-financials')<div><dt class="text-muted">Cost impact</dt><dd>{{ $cr->cost_impact !== null ? App\Support\Money::format($cr->cost_impact, config('doc.currency')) : '—' }}</dd></div>@endcan
                    <div><dt class="text-muted">Timeline impact</dt><dd>{{ $cr->timeline_impact_days !== null ? $cr->timeline_impact_days.' days' : '—' }}</dd></div>
                    <div><dt class="text-muted">Priority</dt><dd><x-badge :enum="$cr->priority" /></dd></div>
                </dl>
                @if ($cr->scope_impact)<p class="label mt-4">Scope impact</p><p class="whitespace-pre-line">{{ $cr->scope_impact }}</p>@endif
                @if ($cr->milestones->isNotEmpty())<p class="mt-3 text-sm"><span class="text-muted">Milestones:</span> {{ $cr->milestones->pluck('name')->implode(', ') }}</p>@endif
                @if ($cr->requirements->isNotEmpty())<p class="mt-1 text-sm"><span class="text-muted">Requirements:</span> {{ $cr->requirements->map(fn ($r) => $r->code)->implode(', ') }}</p>@endif
            </x-card>

            @can('manage')
                @if ($assessable)
                    <x-card title="Update assessment">
                        <form method="POST" action="{{ route('change-requests.update', $cr) }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf @method('PUT')
                            <x-field name="title" label="Title" :value="$cr->title" required class="sm:col-span-2" />
                            <x-field name="description" type="textarea" label="Description" :value="$cr->description" required class="sm:col-span-2" />
                            <input type="hidden" name="received_at" value="{{ $cr->received_at?->toDateString() }}">
                            <input type="hidden" name="reason" value="{{ $cr->reason }}"><input type="hidden" name="source" value="{{ $cr->source }}"><input type="hidden" name="requested_by" value="{{ $cr->requested_by }}">
                            <x-field name="classification" type="select" label="Classification" :options="$classifications" :value="$cr->classification" blank="Not classified" />
                            <x-field name="priority" type="select" label="Priority" :options="$priorities" :value="$cr->priority" required />
                            <x-field name="classification_rationale" type="textarea" label="Classification rationale" :value="$cr->classification_rationale" class="sm:col-span-2" />
                            <x-field name="scope_impact" type="textarea" label="Scope impact" :value="$cr->scope_impact" class="sm:col-span-2" />
                            <x-field name="extra_hours" type="number" label="Extra hours" :value="$cr->extra_hours" min="0" step="0.5" />
                            <x-field name="cost_impact" type="number" label="Cost impact" :value="$cr->cost_impact" min="0" step="0.01" />
                            <x-field name="timeline_impact_days" type="number" label="Timeline impact (days)" :value="$cr->timeline_impact_days" min="-365" max="365" />
                            <x-field name="reviewer_id" type="select" label="Reviewer" :options="$users" :value="$cr->reviewer_id" blank="Unassigned" />
                            @if ($milestones)
                                <fieldset><legend class="label">Affected milestones</legend>@foreach ($milestones as $id => $name)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="milestones[]" value="{{ $id }}" @checked($cr->milestones->contains('id', $id))> {{ $name }}</label>@endforeach</fieldset>
                            @endif
                            @if ($requirements)
                                <fieldset><legend class="label">Affected requirements</legend><div class="max-h-40 overflow-y-auto">@foreach ($requirements as $id => $label)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requirements[]" value="{{ $id }}" @checked($cr->requirements->contains('id', $id))> {{ $label }}</label>@endforeach</div></fieldset>
                            @endif
                            <p class="hint sm:col-span-2">Changing the classification clears any previous internal review.</p>
                            <div class="flex justify-end sm:col-span-2"><button class="btn btn-primary">Save assessment</button></div>
                        </form>
                    </x-card>
                @endif
            @endcan

            <x-card title="Tasks" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($cr->tasks as $task)
                        <li class="flex items-center justify-between gap-2 px-4 py-2"><a class="hover:underline" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a><span class="flex items-center gap-2 text-xs text-muted">{{ $task->assignee?->name }} <x-badge :enum="$task->status" /></span></li>
                    @empty
                        <li class="px-4 py-4 text-muted">No tasks linked.</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card title="History"><ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $history])</ul></x-card>
        </div>

        <div class="flex flex-col gap-6">
            <x-card title="Approvals">
                <dl class="grid gap-3 text-sm">
                    <div><dt class="text-muted">Internal review</dt><dd>@if ($cr->internal_reviewed_at){{ $cr->internalReviewer?->name }} · {{ $cr->internal_reviewed_at->format('d M Y') }}@else <span class="text-muted">Not recorded</span>@endif</dd></div>
                    <div><dt class="text-muted">Client approval</dt><dd>{{ $cr->needsClientApproval() ? 'Required (new scope)' : 'Not required' }}</dd></div>
                    @if ($cr->decided_at)<div><dt class="text-muted">Decision</dt><dd>{{ $cr->decided_at->format('d M Y') }} · recorded by {{ $cr->decider?->name }}@if ($cr->approval_evidence)<span class="block text-muted">{{ $cr->approval_evidence }}</span>@endif @if ($cr->decision_notes)<span class="block whitespace-pre-line">{{ $cr->decision_notes }}</span>@endif</dd></div>@endif
                    @if ($cr->override_reason)<div><dt class="text-warning">Override</dt><dd>{{ $cr->override_reason }}</dd></div>@endif
                </dl>
                @foreach ($cr->approvals as $approval)
                    <div class="mt-3 rounded-md border border-line p-3 text-sm">
                        <x-badge :enum="$approval->outcome" /> {{ $approval->decided_by_name }} · {{ $approval->decided_at?->format('d M Y') }}
                        <p class="text-xs text-muted">{{ $approval->source_reference }}</p>
                        @if ($approval->notes)<p class="text-xs">{{ $approval->notes }}</p>@endif
                    </div>
                @endforeach
            </x-card>
            <x-card title="People">
                <dl class="grid gap-2 text-sm">
                    <div><dt class="text-muted">Reviewer</dt><dd>{{ $cr->reviewer?->name ?? '—' }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>

    @can('manage')
        <x-dialog id="cr-status" title="Change status">
            <form method="POST" action="{{ route('change-requests.status', $cr) }}" class="flex flex-col gap-4">
                @csrf
                @php($targets = $statusTargets->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                @if (auth()->user()->isAdmin() && ! in_array($cr->status, [$S::Rejected, $S::Verified, $S::Cancelled, $S::Scheduled, $S::InProgress, $S::Implemented], true))
                    @php($targets = $targets->union([$S::Scheduled->value => $S::Scheduled->label().' (override if approvals missing)']))
                @endif
                <x-field name="status" type="select" label="New status" :options="$targets->all()" required />
                <x-field name="reason" type="textarea" label="Reason" hint="Required for Rejected, Deferred, Cancelled and overrides." />
                @if (auth()->user()->isAdmin())
                    <x-field name="override" type="checkbox" label="Administrator override (schedule without required approvals; reason required)" />
                @endif
                <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Update</button></div>
            </form>
        </x-dialog>

        @if ($cr->status === $S::AwaitingInternalReview && ! $cr->internal_reviewed_at)
            <x-dialog id="cr-review" title="Record internal review">
                <form method="POST" action="{{ route('change-requests.internal-review', $cr) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="notes" type="textarea" label="Review notes" hint="Who reviewed the estimate and impact, and what was agreed." required />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Record review</button></div>
                </form>
            </x-dialog>
        @endif

        @if ($cr->status === $S::AwaitingClientApproval)
            <x-dialog id="cr-decision" title="Record client decision">
                <form method="POST" action="{{ route('change-requests.client-decision', $cr) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="outcome" type="select" label="Outcome" :options="$outcomes" required />
                    <x-field name="decided_by_name" label="Decided by (client contact)" required />
                    <x-field name="source_reference" label="Evidence" placeholder="Email 3 Apr, signed change order…" required />
                    <x-field name="decided_at" type="date" label="Decision date" :value="now()" required />
                    <x-field name="notes" type="textarea" label="Notes" hint="Required when rejected." />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Record decision</button></div>
                </form>
            </x-dialog>
        @endif
    @endcan
@endsection
