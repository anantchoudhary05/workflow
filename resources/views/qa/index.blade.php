@extends('layouts.app')
@section('title', 'QA')
@section('content')
    <x-page-header title="QA" subtitle="Unresolved defects across your projects, plus retests and failing test cases." />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form method="GET" class="mb-3">
                <select name="severity" class="input w-48" data-autosubmit aria-label="Severity"><option value="">All severities</option>@foreach ($severities as $value => $label)<option value="{{ $value }}" @selected(request('severity') === $value)>{{ $label }}</option>@endforeach</select>
            </form>
            <x-card title="Unresolved defects" :padded="false">
                @if ($defects->isEmpty())
                    <x-empty-state title="No unresolved defects" icon="bug" />
                @else
                    @include('qa.partials.defects-table')
                @endif
            </x-card>
            <div class="mt-4">{{ $defects->links() }}</div>
        </div>
        <div class="flex flex-col gap-6">
            <x-card title="Awaiting retest" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($retest as $defect)
                        <li class="flex items-center justify-between gap-2 px-4 py-2 text-sm"><a class="hover:underline" href="{{ route('defects.show', $defect) }}">{{ $defect->project->code }} · {{ $defect->code }} {{ $defect->title }}</a><x-badge :enum="$defect->status" /></li>
                    @empty
                        <li class="px-4 py-4 text-muted">Nothing to retest.</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card title="Failing test cases" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($failing as $tc)
                        <li class="flex items-center justify-between gap-2 px-4 py-2 text-sm"><a class="hover:underline" href="{{ route('projects.qa', $tc->project_id) }}">{{ $tc->project->code }} · {{ $tc->code }} {{ $tc->title }}</a><x-badge :enum="$tc->status" /></li>
                    @empty
                        <li class="px-4 py-4 text-muted">No failing test cases.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
@endsection
