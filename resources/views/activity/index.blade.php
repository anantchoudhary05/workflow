@extends('layouts.app')
@section('title', 'Activity & audit')
@section('content')
    <x-page-header title="Activity & audit" subtitle="Every recorded change. Audit entries mark approvals, overrides, status changes and account changes." />
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <x-field name="project" type="select" label="Project" :options="$projects" :value="request('project')" blank="All projects" />
        <x-field name="user" type="select" label="Person" :options="$users" :value="request('user')" blank="Anyone" />
        <x-field name="event" label="Event prefix" :value="request('event')" placeholder="task, quotation…" />
        <x-field name="from" type="date" label="From" :value="request('from')" />
        <x-field name="to" type="date" label="To" :value="request('to')" />
        <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="audit" value="1" @checked(request()->boolean('audit'))> Audit only</label>
        <button class="btn">Filter</button>
        @if (request()->hasAny(['project', 'user', 'event', 'from', 'to', 'audit']))<a class="btn btn-ghost" href="{{ route('activity.index') }}">Clear</a>@endif
    </form>
    <x-card>
        <ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $logs])</ul>
    </x-card>
    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
