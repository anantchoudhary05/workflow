@extends('layouts.app')
@section('title', 'Team workload')
@section('content')
    <x-page-header title="Team workload" subtitle="Open assigned tasks and remaining estimates against one week of capacity." />
    <x-card :padded="false">
        @if ($rows->isEmpty())
            <x-empty-state title="No active team members" icon="users" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Person</th><th>Open</th><th>In progress</th><th>In review</th><th>Blocked</th><th>Overdue</th><th>Due this week</th><th>Est. remaining</th><th>Logged this week</th><th class="w-48">Load</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($tone = $row->load === null ? 'neutral' : ($row->load > 100 ? 'danger' : ($row->load >= 80 ? 'warning' : 'success')))
                            <tr>
                                <td><a class="font-medium hover:underline" href="{{ route('tasks.index', ['assignee' => $row->user->id]) }}">{{ $row->user->name }}</a><p class="text-xs text-muted">{{ $row->user->job_title ?? $row->user->role->label() }}</p></td>
                                <td>{{ $row->open }}</td>
                                <td>{{ $row->in_progress }}</td>
                                <td>{{ $row->in_review }}</td>
                                <td @class(['font-medium text-danger' => $row->blocked > 0])>{{ $row->blocked }}</td>
                                <td @class(['font-medium text-danger' => $row->overdue > 0])>{{ $row->overdue }}</td>
                                <td>{{ $row->due_this_week }}</td>
                                <td>{{ $row->estimated_remaining }} h</td>
                                <td>{{ $row->logged_this_week }} h</td>
                                <td>
                                    @if ($row->load === null)
                                        <span class="text-xs text-muted">Capacity not set</span>
                                    @else
                                        <div class="flex items-center gap-2"><div class="progress flex-1"><span @class(['bg-danger' => $tone === 'danger', 'bg-warning' => $tone === 'warning', 'bg-success' => $tone === 'success']) style="width: {{ min(100, $row->load) }}%"></span></div><x-badge :tone="$tone">{{ $row->load }}%</x-badge></div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
