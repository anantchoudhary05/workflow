@extends('layouts.app')
@section('title', 'Tasks')
@section('content')
    <x-page-header title="Tasks" subtitle="Open work across your projects.">
        <x-slot:actions>
            <a href="{{ route('tasks.board', request()->except('status', 'page')) }}" class="btn"><x-icon name="kanban" :size="16" /> Board</a>
            @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', request()->only('project')) }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New task</a>@endcan
        </x-slot:actions>
    </x-page-header>
    @include('tasks.partials.filters')
    <x-card :padded="false">@include('tasks.partials.table')</x-card>
    <div class="mt-4">{{ $tasks->links() }}</div>
@endsection
