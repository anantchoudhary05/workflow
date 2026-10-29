@extends('layouts.app')
@section('title', $project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'overview'])

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="flex flex-col gap-6 xl:col-span-2">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted">Health</p>
                    <div class="mt-2">@include('partials.health', ['health' => $health])</div>
                    @if ($health['reasons'])<ul class="mt-2 list-disc pl-4 text-xs text-muted">@foreach ($health['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif
                </div>
                <div class="card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted">Progress</p>
                    @if ($progress['percent'] !== null)
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $progress['percent'] }}%</p>
                        <div class="progress mt-1"><span style="width: {{ $progress['percent'] }}%"></span></div>
                    @else
                        <p class="mt-1 text-2xl font-semibold text-faint">—</p>
                    @endif
                    <p class="mt-1 text-xs text-muted">{{ $progress['basis'] }}</p>
                </div>
                <div class="card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted">Delivery date</p>
                    <p class="mt-1 text-lg font-semibold"><x-date :value="$project->due_date" :overdue="! $project->isClosed()" empty="Not set" /></p>
                    @if ($project->original_due_date && $project->due_date && ! $project->original_due_date->equalTo($project->due_date))
                        <p class="text-xs text-warning">Originally {{ $project->original_due_date->format('d M Y') }}</p>
                    @endif
                    @can('update', $project)
                        <button type="button" class="link mt-1 text-xs" data-dialog-open="change-due-date">Change date</button>
                    @endcan
                </div>
            </div>

            <div class="alert tone-info flex items-start gap-2"><x-icon name="info" class="mt-0.5" /><div><p class="font-medium">Next action</p><p>{{ $nextAction }}</p></div></div>

            <x-card title="Milestones" :padded="false">
                <x-slot:actions><a href="{{ route('projects.milestones', $project) }}" class="link">Manage</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($milestones as $milestone)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <div><p class="font-medium">{{ $milestone->name }}</p><p class="text-xs text-muted">Due <x-date :value="$milestone->due_date" :overdue="$milestone->status !== App\Enums\MilestoneStatus::Completed" empty="not set" /></p></div>
                            <x-badge :enum="$milestone->status" />
                        </li>
                    @empty
                        <li><x-empty-state title="No milestones planned" icon="flag" /></li>
                    @endforelse
                </ul>
            </x-card>

            <x-card title="Waiting on client" :padded="false">
                <x-slot:actions><a href="{{ route('client-actions.index', ['project' => $project->id]) }}" class="link">Waiting room</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($waiting as $action)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5"><div><p class="font-medium">{{ $action->title }}</p><p class="text-xs text-muted">{{ $action->type->label() }}</p></div><span class="badge tone-warning">{{ $action->daysWaiting() }}d</span></li>
                    @empty
                        <li class="px-4 py-4 text-muted">Nothing is waiting on the client.</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card title="Recent activity">
                <x-slot:actions><a href="{{ route('projects.activity', $project) }}" class="link">Full history</a></x-slot:actions>
                <ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $activity])</ul>
            </x-card>
        </div>

        <div class="flex flex-col gap-6">
            @can('update', $project)
                <x-card title="Workflow">
                    <div class="flex flex-col gap-2">
                        @if ($statusOptions)
                            <button type="button" class="btn btn-primary" data-dialog-open="change-status"><x-icon name="activity" :size="16" /> Change status</button>
                        @endif
                        <a href="{{ route('projects.edit', $project) }}" class="btn"><x-icon name="edit" :size="16" /> Edit details</a>
                        @if ($project->status === App\Enums\ProjectStatus::Delivered)
                            <button type="button" class="btn" data-dialog-open="close-project">Close project</button>
                        @elseif ($project->status === App\Enums\ProjectStatus::Closed)
                            <button type="button" class="btn" data-dialog-open="reopen-project">Reopen project</button>
                        @endif
                    </div>
                </x-card>
            @endcan

            <x-card title="Details">
                <dl class="grid gap-3 text-sm">
                    <div><dt class="text-muted">Client</dt><dd><a class="link" href="{{ route('clients.show', $project->client_id) }}">{{ $project->client?->name }}</a></dd></div>
                    <div><dt class="text-muted">Manager</dt><dd>{{ $project->manager?->name ?? 'Unassigned' }}</dd></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><dt class="text-muted">Type</dt><dd>{{ $project->type->label() }}</dd></div>
                        <div><dt class="text-muted">Priority</dt><dd><x-badge :enum="$project->priority" /></dd></div>
                        <div><dt class="text-muted">Start</dt><dd><x-date :value="$project->start_date" /></dd></div>
                        <div><dt class="text-muted">Launch</dt><dd><x-date :value="$project->launch_date" /></dd></div>
                        <div><dt class="text-muted">Estimate</dt><dd>{{ $project->estimated_hours ? $project->estimated_hours.' h' : '—' }}</dd></div>
                        <div><dt class="text-muted">Handover</dt><dd>{{ $project->handover_status?->label() ?? '—' }}</dd></div>
                    </div>
                    <div><dt class="text-muted">Approved scope</dt><dd>@if ($project->approvedScope)<a class="link" href="{{ route('scope.show', $project->approvedScope) }}">Version {{ $project->approvedScope->version }}</a>@else <span class="text-warning">Not baselined</span>@endif</dd></div>
                    @foreach (['repository_url' => 'Repository', 'staging_url' => 'Staging', 'production_url' => 'Production'] as $field => $label)
                        @if ($project->{$field})<div><dt class="text-muted">{{ $label }}</dt><dd class="truncate"><a class="link" href="{{ $project->{$field} }}" target="_blank" rel="noopener noreferrer">{{ $project->{$field} }}</a></dd></div>@endif
                    @endforeach
                    @if ($project->description)<div><dt class="text-muted">Description</dt><dd class="whitespace-pre-line">{{ $project->description }}</dd></div>@endif
                    @if ($project->closed_at)<div><dt class="text-muted">Closed</dt><dd>{{ $project->closed_at->format('d M Y') }} by {{ $project->closer?->name }}@if ($project->closure_notes)<span class="block whitespace-pre-line text-muted">{{ $project->closure_notes }}</span>@endif</dd></div>@endif
                </dl>
            </x-card>

            <x-card title="Team" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($project->members as $member)
                        <li class="flex items-center justify-between gap-3 px-4 py-2">
                            <div><p class="font-medium">{{ $member->name }}</p><p class="text-xs text-muted">{{ $member->pivot->role ?: $member->role->label() }}</p></div>
                            @can('update', $project)
                                <form method="POST" action="{{ route('projects.members.destroy', [$project, $member]) }}" data-confirm="Remove {{ $member->name }} from this project?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="Remove {{ $member->name }}"><x-icon name="x" :size="14" /></button></form>
                            @endcan
                        </li>
                    @empty
                        <li class="px-4 py-4 text-muted">No team members yet.</li>
                    @endforelse
                </ul>
                @can('update', $project)
                    <form method="POST" action="{{ route('projects.members.store', $project) }}" class="flex flex-wrap items-end gap-2 border-t border-line p-4">
                        @csrf
                        <x-field name="user_id" type="select" label="Add member" :options="array_diff_key($users, $project->members->pluck('name', 'id')->all())" blank="Select person" required class="min-w-40 flex-1" />
                        <x-field name="role" placeholder="Role (optional)" class="w-32" />
                        <button class="btn">Add</button>
                    </form>
                @endcan
            </x-card>

            <x-card title="Status history" :padded="false">
                <ul class="divide-y divide-line text-sm">
                    @forelse ($project->statusHistory as $history)
                        <li class="px-4 py-2">
                            <p>{{ $history->from_status?->label() ?? 'Created' }} → <span class="font-medium">{{ $history->to_status->label() }}</span>@if ($history->is_override) <span class="badge tone-warning">Override</span>@endif</p>
                            <p class="text-xs text-muted">{{ $history->user?->name }} · {{ $history->created_at?->format('d M Y H:i') }}</p>
                            @if ($history->reason)<p class="text-xs">{{ $history->reason }}</p>@endif
                        </li>
                    @empty
                        <li class="px-4 py-4 text-muted">No status changes yet.</li>
                    @endforelse
                </ul>
            </x-card>

            @if ($project->dateChanges->isNotEmpty())
                <x-card title="Delivery date changes" :padded="false">
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($project->dateChanges as $change)
                            <li class="px-4 py-2">
                                <p>{{ $change->old_date?->format('d M Y') ?? 'None' }} → <span class="font-medium">{{ $change->new_date?->format('d M Y') ?? 'None' }}</span>@if ($change->delay_source) <span class="badge tone-neutral">{{ str_replace('_', ' ', $change->delay_source) }}</span>@endif</p>
                                <p class="text-xs text-muted">{{ $change->user?->name }} · {{ $change->created_at?->format('d M Y') }}</p>
                                <p class="text-xs">{{ $change->reason }}</p>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>

    @can('update', $project)
        <x-dialog id="change-status" title="Change project status">
            <form method="POST" action="{{ route('projects.status', $project) }}" class="flex flex-col gap-4">
                @csrf
                <p class="text-sm text-muted">Current status: <x-badge :enum="$project->status" /></p>
                <x-field name="status" type="select" label="New status" :options="$statusOptions" required />
                <x-field name="reason" type="textarea" label="Reason" hint="Required for Blocked, On Hold, Cancelled and any override." />
                @if (auth()->user()->isAdmin())
                    <x-field name="override" type="checkbox" label="Administrator override (allows statuses marked “admin override” and skips gate checks; reason required and audited)" />
                @endif
                <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Update status</button></div>
            </form>
        </x-dialog>

        <x-dialog id="change-due-date" title="Change delivery date">
            <form method="POST" action="{{ route('projects.due-date', $project) }}" class="flex flex-col gap-4">
                @csrf
                <x-field name="due_date" type="date" label="New delivery date" :value="$project->due_date" />
                <x-field name="delay_source" type="select" label="Cause" :options="['internal' => 'Internal', 'client' => 'Client delay', 'scope_change' => 'Scope change']" blank="Not a delay" />
                <x-field name="reason" type="textarea" label="Reason" required />
                <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Record change</button></div>
            </form>
        </x-dialog>

        @if ($project->status === App\Enums\ProjectStatus::Delivered)
            <x-dialog id="close-project" title="Close project">
                <form method="POST" action="{{ route('projects.close', $project) }}" class="flex flex-col gap-4">
                    @csrf
                    <p class="text-muted">Closing requires completed release and handover checklists and recorded client acceptance.</p>
                    <x-field name="closure_notes" type="textarea" label="Closure notes" />
                    @if (auth()->user()->isAdmin())
                        <x-field name="override" type="checkbox" label="Close with an exception (administrator)" />
                        <x-field name="override_reason" label="Exception reason" />
                    @endif
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Close project</button></div>
                </form>
            </x-dialog>
        @elseif ($project->status === App\Enums\ProjectStatus::Closed)
            <x-dialog id="reopen-project" title="Reopen project">
                <form method="POST" action="{{ route('projects.reopen', $project) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="reason" type="textarea" label="Why is it being reopened?" required />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Reopen</button></div>
                </form>
            </x-dialog>
        @endif
    @endcan
@endsection
