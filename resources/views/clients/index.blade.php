@extends('layouts.app')
@section('title', 'Clients')
@section('content')
    <x-page-header title="Clients" subtitle="Everyone you deliver for, with their projects and open requests.">
        <x-slot:actions>
            @can('create', App\Models\Client::class)
                <a href="{{ route('clients.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New client</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" class="input w-72" placeholder="Name, company, email or code" aria-label="Search clients">
        <select name="status" class="input w-44" data-autosubmit aria-label="Status"><option value="">All statuses</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <button class="btn">Filter</button>
        @if (request()->hasAny(['q', 'status']))<a href="{{ route('clients.index') }}" class="btn btn-ghost">Clear</a>@endif
    </form>

    <x-card :padded="false">
        @if ($clients->isEmpty())
            <x-empty-state title="No clients found" icon="building" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Client</th><th>Contact</th><th>Status</th><th>Owner</th><th class="text-right">Active projects</th><th class="text-right">Waiting on them</th><th>Last contact</th></tr></thead>
                    <tbody>
                    @foreach ($clients as $client)
                        <tr>
                            <td><a href="{{ route('clients.show', $client) }}" class="font-medium hover:underline">{{ $client->name }}</a><p class="text-xs text-muted">{{ $client->code }}@if ($client->company_name) · {{ $client->company_name }}@endif</p></td>
                            <td>{{ $client->primary_contact ?? '—' }}@if ($client->email)<p class="text-xs text-muted">{{ $client->email }}</p>@endif</td>
                            <td><x-badge :enum="$client->status" /></td>
                            <td>{{ $client->owner?->name ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $client->active_projects_count }}</td>
                            <td class="text-right tabular-nums">@if ($client->waiting_count)<span class="badge tone-warning">{{ $client->waiting_count }}</span>@else 0 @endif</td>
                            <td><x-date :value="$client->last_contacted_at" empty="Never" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <div class="mt-4">{{ $clients->links() }}</div>
@endsection
