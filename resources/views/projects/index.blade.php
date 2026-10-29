@extends('layouts.app')
@section('title', 'Projects')
@section('content')
    <x-page-header title="Projects" subtitle="Health and progress are calculated from milestones, tasks, dates and blockers — never entered by hand.">
        <x-slot:actions>
            @can('create', App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New project</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" class="input w-64" placeholder="Name or code" aria-label="Search projects">
        <select name="status" class="input w-52" data-autosubmit aria-label="Status"><option value="">All open projects</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="manager" class="input w-48" data-autosubmit aria-label="Manager"><option value="">Any manager</option>@foreach ($managers as $id => $name)<option value="{{ $id }}" @selected(request('manager') == $id)>{{ $name }}</option>@endforeach</select>
        <select name="health" class="input w-48" data-autosubmit aria-label="Health"><option value="">Any health</option><option value="attention" @selected(request('health') === 'attention')>Needs attention</option></select>
        <button class="btn">Filter</button>
        @if (request()->hasAny(['q', 'status', 'manager', 'health', 'client']))<a href="{{ route('projects.index') }}" class="btn btn-ghost">Clear</a>@endif
    </form>

    <x-card :padded="false">
        @if ($projects->isEmpty())
            <x-empty-state title="No projects match" icon="folder" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Project</th><th>Status</th><th>Health</th><th>Progress</th><th>Manager</th><th>Due</th></tr></thead>
                    <tbody>
                    @foreach ($projects as $project)
                        <tr>
                            <td><a href="{{ route('projects.show', $project) }}" class="font-medium hover:underline">{{ $project->name }}</a><p class="text-xs text-muted">{{ $project->code }} · {{ $project->client?->name }}</p></td>
                            <td><x-badge :enum="$project->status" /><p class="mt-0.5 text-xs text-muted">{{ $project->status->stage() }}</p></td>
                            <td>@include('partials.health', ['health' => $project->health])</td>
                            <td class="w-36">
                                @if ($project->progress['percent'] !== null)
                                    <div class="progress" title="{{ $project->progress['basis'] }}"><span style="width: {{ $project->progress['percent'] }}%"></span></div>
                                    <p class="mt-1 text-xs text-muted">{{ $project->progress['percent'] }}%</p>
                                @else
                                    <span class="text-xs text-faint">{{ $project->progress['basis'] }}</span>
                                @endif
                            </td>
                            <td>{{ $project->manager?->name ?? '—' }}</td>
                            <td><x-date :value="$project->due_date" :overdue="! $project->isClosed()" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
