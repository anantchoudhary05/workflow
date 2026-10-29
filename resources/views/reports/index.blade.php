@extends('layouts.app')
@section('title', 'Reports')
@section('content')
    @php($ranged = in_array($key, ['time-by-project', 'change-requests'], true))
    <x-page-header title="Reports" subtitle="Live reports scoped to the projects you can see. Export any report as CSV.">
        <x-slot:actions>
            <a class="btn" href="{{ route('reports.export', array_filter(['report' => $key, 'from' => $ranged ? $from : null, 'to' => $ranged ? $to : null])) }}"><x-icon name="download" :size="16" /> Export CSV</a>
        </x-slot:actions>
    </x-page-header>
    <nav class="mb-4 flex flex-wrap items-center gap-4 border-b border-line" aria-label="Reports">
        @foreach ($available as $reportKey => $label)
            <a href="{{ route('reports.index', ['report' => $reportKey]) }}" class="tab" @if ($reportKey === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @if ($ranged)
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="report" value="{{ $key }}">
            <x-field name="from" type="date" label="From" :value="$from" />
            <x-field name="to" type="date" label="To" :value="$to" />
            <button class="btn">Apply</button>
        </form>
    @endif
    <x-card :title="$report['title']" :padded="false">
        @if (empty($report['rows']))
            <x-empty-state title="No data for this report" icon="chart" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr>@foreach ($report['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
