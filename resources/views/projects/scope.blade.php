@extends('layouts.app')
@section('title', 'Scope · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'scope'])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <x-card title="Scope baselines" :padded="false">
                @if ($versions->isEmpty())
                    <x-empty-state title="No approved scope yet" icon="lock">Record the client's approval of the agreed requirements to lock a baseline. Work can't move to Ready to Start without one.</x-empty-state>
                @else
                    <table class="table">
                        <thead><tr><th>Version</th><th>Approved by</th><th>Source</th><th>Approved</th><th class="text-right">Items</th></tr></thead>
                        <tbody>
                            @foreach ($versions as $version)
                                <tr>
                                    <td><a class="link font-medium" href="{{ route('scope.show', $version) }}">v{{ $version->version }}</a>@if ($project->approved_scope_version_id === $version->id) <span class="badge tone-success">Current</span>@endif</td>
                                    <td>{{ $version->approved_by_name }}</td>
                                    <td>{{ $version->approval_source }}</td>
                                    <td>{{ $version->approved_at->format('d M Y') }}</td>
                                    <td class="text-right tabular-nums">{{ count($version->snapshot ?? []) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-card title="Approved requirements">
                @forelse ($approvedRequirements as $requirement)
                    <p class="py-1"><span class="font-mono text-xs text-muted">{{ $requirement->code }}</span> {{ $requirement->title }} <span class="text-xs text-muted">· {{ $requirement->estimated_hours ? $requirement->estimated_hours.' h' : 'no estimate' }}</span></p>
                @empty
                    <p class="text-muted">No approved requirements. <a class="link" href="{{ route('projects.requirements', $project) }}">Review requirements</a> first.</p>
                @endforelse
            </x-card>
        </div>

        @can('manage')
            <x-card title="Record client scope approval">
                @if ($approvedRequirements->isEmpty())
                    <p class="text-muted">Approve at least one requirement first.</p>
                @else
                    <form method="POST" action="{{ route('projects.scope.store', $project) }}" class="flex flex-col gap-4">
                        @csrf
                        <p class="text-sm text-muted">Snapshots the {{ $approvedRequirements->count() }} approved requirement(s) as version {{ ($versions->max('version') ?? 0) + 1 }}.</p>
                        <x-field name="summary" type="textarea" label="Scope summary" required />
                        <x-field name="approved_by_name" label="Approved by (client)" required />
                        <x-field name="approval_source" label="Approval evidence" placeholder="Email 12 Mar, signed SOW…" required />
                        <x-field name="approved_at" type="date" label="Approved on" :value="now()" required />
                        <button class="btn btn-primary">Lock baseline</button>
                    </form>
                @endif
            </x-card>
        @endcan
    </div>
@endsection
