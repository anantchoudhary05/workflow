@extends('layouts.app')
@section('title', 'Tasks · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'tasks'])
    <div class="flex flex-wrap items-start justify-between gap-2">
        @include('tasks.partials.filters', ['lockProject' => true])
        <div class="flex gap-2">
            <a href="{{ route('tasks.board', ['project' => $project->id]) }}" class="btn"><x-icon name="kanban" :size="16" /> Board</a>
            @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', ['project' => $project->id]) }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New task</a>@endcan
        </div>
    </div>
    <x-card :padded="false">@include('tasks.partials.table', ['showProject' => false])</x-card>
    <div class="mt-4">{{ $tasks->links() }}</div>
@endsection
