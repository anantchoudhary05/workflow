@extends('layouts.app')
@section('title', 'Activity · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'activity'])
    <div class="mb-4 flex gap-2">
        <a href="{{ route('projects.activity', $project) }}" @class(['btn btn-sm', 'btn-primary' => ! request()->boolean('audit')])>All activity</a>
        <a href="{{ route('projects.activity', [$project, 'audit' => 1]) }}" @class(['btn btn-sm', 'btn-primary' => request()->boolean('audit')])>Audit trail only</a>
    </div>
    <x-card><ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $logs])</ul></x-card>
    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
