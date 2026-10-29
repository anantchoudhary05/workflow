@extends('layouts.app')
@section('title', ucfirst($type).' · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => $type])
    @php($isHandover = $type === 'handover')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card :title="$isHandover ? 'Handover checklist' : 'Release readiness checklist'" :padded="false">
                <x-slot:actions>
                    @if ($items->isNotEmpty())<x-badge :tone="$complete ? 'success' : 'warning'">{{ $complete ? 'All mandatory items satisfied' : 'Incomplete' }}</x-badge>@endif
                </x-slot:actions>
                @if ($items->isEmpty())
                    <x-empty-state :title="'No '.$type.' checklist yet'" icon="check-square">
                        @can('manage')
                            <form method="POST" action="{{ route('projects.checklist.generate', [$project, $type]) }}" class="mt-3">@csrf<button class="btn btn-primary">Create from standard template</button></form>
                        @else
                            An administrator creates this checklist from the standard template.
                        @endcan
                    </x-empty-state>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($items as $item)
                            <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="flex items-center gap-2 font-medium">
                                        <x-icon :name="$item->isSatisfied() ? 'check' : 'clock'" :size="16" :class="$item->isSatisfied() ? 'text-success' : 'text-faint'" />
                                        {{ $item->label }} @if ($item->is_mandatory)<span class="text-xs text-danger">Required</span>@endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-muted">
                                        {{ match ($item->status) { 'done' => 'Done', 'not_applicable' => 'Not applicable', default => 'Pending' } }}
                                        @if ($item->responsible) · Owner {{ $item->responsible->name }}@endif
                                        @if ($item->completer) · {{ $item->completer->name }} {{ $item->completed_at?->format('d M Y') }}@endif
                                        @if ($item->evidence) · {{ $item->evidence }}@endif
                                    </p>
                                </div>
                                @if ($canEdit)<button type="button" class="btn btn-sm" data-dialog-open="item-{{ $item->id }}">Update</button>@endif
                            </li>
                        @endforeach
                    </ul>
                    @can('manage')
                        <form method="POST" action="{{ route('projects.checklist.store', [$project, $type]) }}" class="flex flex-wrap items-end gap-3 border-t border-line p-4">
                            @csrf
                            <x-field name="label" label="Add item" class="min-w-60 flex-1" required />
                            <x-field name="is_mandatory" type="checkbox" label="Required" />
                            <button class="btn">Add</button>
                        </form>
                    @endcan
                @endif
            </x-card>
        </div>

        <div class="flex flex-col gap-6">
            @if ($isHandover)
                <x-card title="Handover">
                    <p class="text-sm">Status: @if ($project->handover_status)<x-badge :enum="$project->handover_status" />@else <span class="text-muted">Not started</span>@endif</p>
                    @can('manage')
                        @if ($project->status === App\Enums\ProjectStatus::Delivered)
                            <form method="POST" action="{{ route('projects.handover-status', $project) }}" class="mt-4 flex flex-col gap-3">
                                @csrf
                                <x-field name="handover_status" type="select" label="Set handover status" :options="$handoverStatuses" :value="$project->handover_status" required />
                                <x-field name="reason" label="Note" />
                                <button class="btn">Update handover</button>
                            </form>
                            <p class="hint mt-3">Accepted requires a recorded client acceptance. Close the project from the <a class="link" href="{{ route('projects.show', $project) }}">overview</a>.</p>
                        @else
                            <p class="hint mt-3">Handover starts once the project is marked Delivered.</p>
                        @endif
                    @endcan
                </x-card>
                <x-card title="Client acceptance">
                    @forelse ($acceptances as $approval)
                        <div class="mb-3 text-sm">
                            <x-badge :enum="$approval->outcome" /> {{ $approval->decided_by_name }} · {{ $approval->decided_at?->format('d M Y') }}
                            <p class="text-xs text-muted">{{ $approval->source_reference }} · recorded by {{ $approval->recorder?->name }}</p>
                            @if ($approval->notes)<p class="text-xs">{{ $approval->notes }}</p>@endif
                        </div>
                    @empty
                        <p class="text-sm text-muted">No acceptance recorded.</p>
                    @endforelse
                    @can('manage')<button type="button" class="btn btn-sm mt-2" data-dialog-open="acceptance">Record acceptance</button>@endcan
                </x-card>
            @else
                <x-card title="About this checklist"><p class="text-sm text-muted">Mandatory items must be done before the project can be closed. QA reviewers can update these items.</p></x-card>
            @endif
        </div>
    </div>

    @if ($canEdit)
        @foreach ($items as $item)
            <x-dialog :id="'item-'.$item->id" :title="$item->label">
                <form method="POST" action="{{ route('checklist.update', $item) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="status" type="select" label="Status" :options="$item->is_mandatory ? ['pending' => 'Pending', 'done' => 'Done'] : ['pending' => 'Pending', 'done' => 'Done', 'not_applicable' => 'Not applicable']" :value="$item->status" required />
                    <x-field name="evidence" label="Evidence" :value="$item->evidence" placeholder="Link, document name, test run…" />
                    <x-field name="responsible_id" type="select" label="Owner" :options="$users" :value="$item->responsible_id" blank="Unassigned" />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
                </form>
            </x-dialog>
        @endforeach
    @endif
    @if ($isHandover)
        @can('manage')
            <x-dialog id="acceptance" title="Record final acceptance">
                <form method="POST" action="{{ route('projects.acceptance', $project) }}" class="flex flex-col gap-4">
                    @csrf
                    @include('partials.decision-fields')
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Record</button></div>
                </form>
            </x-dialog>
        @endcan
    @endif
@endsection
