@extends('layouts.app')
@section('title', 'Team activity')
@section('content')
    <x-page-header title="Team activity" icon="users" subtitle="Who is working on what, open work and remaining estimates against one week of capacity." />
    <x-card :padded="false">
        @if ($rows->isEmpty())
            <x-empty-state title="No active team members" icon="users" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Person</th><th>Working on</th><th>Open</th><th>In progress</th><th>In review</th><th>Blocked</th><th>Overdue</th><th>Due this week</th><th>Est. remaining</th><th>Logged this week</th><th class="w-48">Load</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($tone = $row->load === null ? 'neutral' : ($row->load > 100 ? 'danger' : ($row->load >= 80 ? 'warning' : 'success')))
                            <tr>
                                <td><a class="font-medium hover:underline" href="{{ route('tasks.index', ['assignee' => $row->user->id]) }}">{{ $row->user->name }}</a><p class="text-xs text-muted">{{ $row->user->job_title ?? $row->user->role->label() }}</p><p class="text-xs text-faint">{{ $row->last_active ? 'Active '.$row->last_active->diffForHumans() : 'No recorded activity' }}</p></td>
                                <td class="max-w-64">
                                    @forelse ($row->working_on as $task)
                                        <a class="block truncate text-xs hover:underline" href="{{ route('tasks.show', $task->id) }}" title="{{ $task->title }}"><span class="text-muted">{{ $task->project?->code }}</span> {{ $task->title }}</a>
                                    @empty
                                        <span class="text-xs text-muted">—</span>
                                    @endforelse
                                </td>
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

    @if ($activity->isNotEmpty())
        <x-card title="Recent team activity" icon="activity" class="mt-6">
            <x-slot:actions><a href="{{ route('activity.index') }}" class="link">Full activity log</a></x-slot:actions>
            <ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $activity])</ul>
        </x-card>
    @endif
@endsection
