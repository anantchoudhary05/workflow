@extends('layouts.app')
@section('title', $project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'overview'])

    @php($me = auth()->user())
    @php($canHandOff = $me->can('project-ability', [$project, 'hand-off-projects']))
    @php($canRequestReview = $me->can('project-ability', [$project, 'request-review']))

    <div class="flex flex-col gap-6">
        {{-- At a glance: the four numbers that matter, in one calm strip. --}}
        <div class="stat-strip reveal" style="--d: 1" data-project-glance>
            <div class="stat-cell">
                <p class="stat-label">Health</p>
                <div class="mt-2">@include('partials.health', ['health' => $health])</div>
                @if ($health['reasons'])
                    <ul class="mt-2 space-y-0.5 text-xs text-muted">@foreach ($health['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                @endif
            </div>
            <div class="stat-cell">
                <p class="stat-label">Progress</p>
                @if ($progress['percent'] !== null)
                    <p class="stat-value">{{ $progress['percent'] }}<span class="stat-unit">%</span></p>
                    <div class="progress mt-2"><span style="width: {{ $progress['percent'] }}%"></span></div>
                @else
                    <p class="stat-value text-faint">—</p>
                @endif
                <p class="mt-1.5 text-xs text-muted">{{ $progress['basis'] }}</p>
            </div>
            <div class="stat-cell">
                <p class="stat-label">Delivery date</p>
                <p class="stat-value stat-value-sm"><x-date :value="$project->due_date" :overdue="! $project->isClosed()" empty="Not set" /></p>
                @if ($project->original_due_date && $project->due_date && ! $project->original_due_date->equalTo($project->due_date))
                    <p class="mt-1 text-xs text-warning">Originally {{ $project->original_due_date->format('d M Y') }}</p>
                @endif
                @can('project-ability', [$project, 'set-deadlines'])
                    <button type="button" class="link mt-1 text-xs" data-dialog-open="change-due-date">Change date</button>
                @endcan
            </div>
            <div class="stat-cell stat-cell-accent">
                <p class="stat-label"><x-icon name="sparkles" :size="13" /> Next action</p>
                <p class="mt-2 text-sm font-medium leading-snug">{{ $nextAction }}</p>
            </div>
        </div>

        @if ($project->awaitingHandoffAck())
            <div class="alert tone-warning flex flex-wrap items-start justify-between gap-3 reveal" style="--d: 1">
                <div class="flex items-start gap-2"><x-icon name="briefcase" class="mt-0.5" /><div>
                    <p class="font-medium">Handed to the dev team by {{ $project->handedOffBy?->name ?? 'Marketing' }} · {{ $project->handed_off_at->format('d M Y H:i') }}</p>
                    <p class="whitespace-pre-line">{{ $project->handoff_notes }}</p>
                </div></div>
                @can('project-ability', [$project, 'receive-handoffs'])
                    <form method="POST" action="{{ route('projects.handoff.acknowledge', $project) }}">@csrf<button class="btn btn-primary btn-sm"><x-icon name="check" :size="14" /> Acknowledge handoff</button></form>
                @endcan
            </div>
        @endif

        {{-- The centrepiece: where delivery stands, milestone by milestone. --}}
        @include('projects.partials.milestone-track', ['track' => $track, 'openStop' => $openStop])

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-6 xl:col-span-2 reveal" style="--d: 3">
                @include('projects.partials.my-work-panel', ['todos' => $myTodos, 'myWork' => $myWork])

                @php($resourceTabs = array_filter([
                    'activity' => ['label' => 'Recent activity', 'icon' => 'activity'],
                    'waiting' => ['label' => 'Waiting on client', 'icon' => 'hourglass', 'count' => $waiting->count(), 'alert' => true],
                    'files' => ['label' => 'Project files', 'icon' => 'folder', 'count' => $projectFiles->count()],
                    'documents' => $project->client ? ['label' => 'Client documents', 'icon' => 'paperclip', 'count' => $clientDocuments->count()] : null,
                ]))
                @php($resourceTab = $waiting->isNotEmpty() ? 'waiting' : 'activity')
                <x-tabset name="project-{{ $project->id }}-resources" :tabs="$resourceTabs" :selected="$resourceTab" label="Project activity and resources" id="project-resources">
                    <x-tab-panel tabset="project-{{ $project->id }}-resources" name="activity" :active="$resourceTab === 'activity'">
                        <div class="tab-panel-bar"><span>Latest changes in this project</span><a href="{{ route('projects.activity', $project) }}" class="link">Full history</a></div>
                        <ul class="divide-y divide-line px-4">@include('partials.activity-list', ['logs' => $activity])</ul>
                    </x-tab-panel>
                    <x-tab-panel tabset="project-{{ $project->id }}-resources" name="waiting" :active="$resourceTab === 'waiting'">
                        <div class="tab-panel-bar"><span>Things the client still owes us</span><a href="{{ route('client-actions.index', ['project' => $project->id]) }}" class="link">Waiting room</a></div>
                        <ul class="divide-y divide-line">
                            @forelse ($waiting as $action)
                                <li class="flex items-center justify-between gap-3 px-4 py-2.5"><div><p class="font-medium">{{ $action->title }}</p><p class="text-xs text-muted">{{ $action->type->label() }}</p></div><span class="badge tone-warning">{{ $action->daysWaiting() }}d</span></li>
                            @empty
                                <li class="px-4 py-6 text-center text-muted">Nothing is waiting on the client.</li>
                            @endforelse
                        </ul>
                    </x-tab-panel>
                    <x-tab-panel tabset="project-{{ $project->id }}-resources" name="files" :active="false">
                        <div class="tab-panel-bar"><span>Latest uploads</span><a href="{{ route('projects.files', $project) }}" class="link">All files</a></div>
                        <ul class="divide-y divide-line">
                            @forelse ($projectFiles as $file)
                                <li class="flex items-center justify-between gap-3 px-4 py-2">
                                    <div class="min-w-0"><a class="link truncate font-medium" href="{{ route('files.download', $file) }}">{{ $file->original_name }}</a><p class="text-xs text-muted">{{ $file->category->label() }} · {{ $file->humanSize() }} · {{ $file->uploader?->name }} · {{ $file->created_at?->format('d M Y') }}</p></div>
                                    <a class="btn btn-ghost btn-sm" href="{{ route('files.download', $file) }}" aria-label="Download {{ $file->original_name }}"><x-icon name="download" :size="14" /></a>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-center text-muted">No project files yet. Use the Files tab to upload designs, exports and deliverables.</li>
                            @endforelse
                        </ul>
                    </x-tab-panel>
                    @if ($project->client)
                        <x-tab-panel tabset="project-{{ $project->id }}-resources" name="documents" :active="false">
                            @include('partials.client-documents', ['client' => $project->client, 'documents' => $clientDocuments, 'canUpload' => $canUploadClientDocs])
                        </x-tab-panel>
                    @endif
                </x-tabset>
            </div>

            <div class="flex min-w-0 flex-col gap-6 reveal" style="--d: 4">
                @if ($me->can('update', $project) || $canHandOff || $canRequestReview)
                    <x-card title="Workflow" icon="zap" data-workflow-actions>
                        <div class="flex flex-col gap-2">
                            @can('update', $project)
                                @if ($statusOptions)
                                    <button type="button" class="btn btn-primary" data-dialog-open="change-status"><x-icon name="activity" :size="16" /> Change status</button>
                                @endif
                                <a href="{{ route('projects.edit', $project) }}" class="btn"><x-icon name="edit" :size="16" /> Edit details</a>
                            @endcan
                            @if ($canRequestReview && ! $project->isClosed())
                                <button type="button" class="btn" data-dialog-open="request-review"><x-icon name="eye" :size="16" /> Request manager review</button>
                            @endif
                            @if ($canHandOff && ! $project->isClosed())
                                <button type="button" class="btn" data-dialog-open="hand-off"><x-icon name="rocket" :size="16" /> {{ $project->handed_off_at ? 'Send updated handoff' : 'Send to dev team' }}</button>
                            @endif
                            @if ($project->status === App\Enums\ProjectStatus::Delivered && $me->can('project-ability', [$project, 'close-projects']))
                                <button type="button" class="btn" data-dialog-open="close-project">Close project</button>
                            @elseif ($project->status === App\Enums\ProjectStatus::Closed && $me->can('manage'))
                                <button type="button" class="btn" data-dialog-open="reopen-project">Reopen project</button>
                            @endif
                        </div>
                    </x-card>
                @endif

                @include('projects.partials.completed-log', ['log' => $track['log']])

                @if ($reviewRequests->isNotEmpty() || $canRequestReview)
                    <x-card title="Manager review" icon="badge-check" :padded="false">
                        @canany(['approve-for-preview', 'request-review'])
                            <x-slot:actions><a href="{{ route('reviews.index') }}" class="link">Review queue</a></x-slot:actions>
                        @endcanany
                        <ul class="divide-y divide-line text-sm">
                            @forelse ($reviewRequests as $review)
                                <li class="px-4 py-2.5">
                                    <p class="flex flex-wrap items-center justify-between gap-2"><span class="font-medium">{{ $review->title }}</span><x-badge :enum="$review->status" /></p>
                                    <p class="text-xs text-muted">{{ $review->requester?->name }} · {{ $review->created_at?->format('d M Y') }}@if ($review->decided_at) · decided by {{ $review->decider?->name }} {{ $review->decided_at->format('d M') }}@endif</p>
                                    @if ($review->decision_notes)<p class="mt-1 whitespace-pre-line text-xs">{{ $review->decision_notes }}</p>@endif
                                </li>
                            @empty
                                <li class="px-4 py-4 text-muted">No reviews requested yet. Ask the Manager whether the work is ready for client preview.</li>
                            @endforelse
                        </ul>
                    </x-card>
                @endif

                @php($infoTabs = ['details' => ['label' => 'Details'], 'team' => ['label' => 'Team', 'count' => $project->members->count()], 'history' => ['label' => 'History']])
                <x-tabset name="project-{{ $project->id }}-info" :tabs="$infoTabs" selected="details" label="About this project" id="project-info">
                    <x-tab-panel tabset="project-{{ $project->id }}-info" name="details" :active="true" class="p-4">
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
                            @if ($project->handed_off_at && ! $project->awaitingHandoffAck())
                                <div><dt class="text-muted">Marketing handoff</dt><dd>
                                    Sent by {{ $project->handedOffBy?->name ?? 'Marketing' }} on {{ $project->handed_off_at->format('d M Y') }}; acknowledged by {{ $project->handoffAcknowledgedBy?->name }} on {{ $project->handoff_acknowledged_at?->format('d M Y') }}.
                                    @if ($project->handoff_notes)<span class="mt-1 block whitespace-pre-line text-muted">{{ $project->handoff_notes }}</span>@endif
                                </dd></div>
                            @endif
                        </dl>
                    </x-tab-panel>
                    <x-tab-panel tabset="project-{{ $project->id }}-info" name="team" :active="false">
                        <ul class="divide-y divide-line">
                            @forelse ($project->members as $member)
                                <li class="flex items-center justify-between gap-3 px-4 py-2">
                                    <div class="flex items-center gap-2.5"><span class="avatar">{{ $member->initials() }}</span><div><p class="font-medium">{{ $member->name }}</p><p class="text-xs text-muted">{{ $member->pivot->role ?: $member->role->label() }}</p></div></div>
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
                    </x-tab-panel>
                    <x-tab-panel tabset="project-{{ $project->id }}-info" name="history" :active="false">
                        <h3 class="tab-panel-bar"><span>Status history</span></h3>
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
                        @if ($project->dateChanges->isNotEmpty())
                            <h3 class="tab-panel-bar border-t border-line"><span>Delivery date changes</span></h3>
                            <ul class="divide-y divide-line text-sm">
                                @foreach ($project->dateChanges as $change)
                                    <li class="px-4 py-2">
                                        <p>{{ $change->old_date?->format('d M Y') ?? 'None' }} → <span class="font-medium">{{ $change->new_date?->format('d M Y') ?? 'None' }}</span>@if ($change->delay_source) <span class="badge tone-neutral">{{ str_replace('_', ' ', $change->delay_source) }}</span>@endif</p>
                                        <p class="text-xs text-muted">{{ $change->user?->name }} · {{ $change->created_at?->format('d M Y') }}</p>
                                        <p class="text-xs">{{ $change->reason }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-tab-panel>
                </x-tabset>
            </div>
        </div>
    </div>

    @if ($canRequestReview)
        <x-dialog id="request-review" title="Request manager review" icon="eye">
            <form method="POST" action="{{ route('projects.review-requests.store', $project) }}" class="flex flex-col gap-4">
                @csrf
                <x-field name="title" label="What should be reviewed?" placeholder="e.g. Home and checkout pages – ready for client preview?" required />
                <x-field name="url" type="url" label="Link (staging / preview)" :value="$project->staging_url" />
                <x-field name="notes" type="textarea" label="Notes for the Manager" />
                <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Send for review</button></div>
            </form>
        </x-dialog>
    @endif

    @if ($canHandOff)
        <x-dialog id="hand-off" title="Send to dev team" icon="rocket">
            <form method="POST" action="{{ route('projects.handoff', $project) }}" class="flex flex-col gap-4">
                @csrf
                <p class="text-sm text-muted">Team Leads are notified and can acknowledge the handoff. Make sure the client details, requirements and client documents are in place first.</p>
                <x-field name="handoff_notes" type="textarea" label="Handoff summary" :rows="6" required placeholder="Client contact, key requirements, agreed timeline, meetings held, where the documents are…" />
                <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Send to dev team</button></div>
            </form>
        </x-dialog>
    @endif

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
    @endcan

    @can('project-ability', [$project, 'set-deadlines'])
        <x-dialog id="change-due-date" title="Change delivery date">
            <form method="POST" action="{{ route('projects.due-date', $project) }}" class="flex flex-col gap-4">
                @csrf
                <x-field name="due_date" type="date" label="New delivery date" :value="$project->due_date" />
                <x-field name="delay_source" type="select" label="Cause" :options="['internal' => 'Internal', 'client' => 'Client delay', 'scope_change' => 'Scope change']" blank="Not a delay" />
                <x-field name="reason" type="textarea" label="Reason" required />
                <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Record change</button></div>
            </form>
        </x-dialog>
    @endcan

    @if ($project->status === App\Enums\ProjectStatus::Delivered && auth()->user()->can('project-ability', [$project, 'close-projects']))
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
    @elseif ($project->status === App\Enums\ProjectStatus::Closed && auth()->user()->can('manage'))
            <x-dialog id="reopen-project" title="Reopen project">
                <form method="POST" action="{{ route('projects.reopen', $project) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="reason" type="textarea" label="Why is it being reopened?" required />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Reopen</button></div>
                </form>
            </x-dialog>
    @endif
@endsection
