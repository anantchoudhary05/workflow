{{-- Expects $defects; optional $showProject (default true). --}}
@php($showProject = $showProject ?? true)
<div class="overflow-x-auto">
    <table class="table">
        <thead><tr><th>Code</th><th>Title</th>@if ($showProject)<th>Project</th>@endif<th>Severity</th><th>Status</th><th>Assignee</th></tr></thead>
        <tbody>
            @foreach ($defects as $defect)
                <tr>
                    <td><a class="link font-mono text-xs" href="{{ route('defects.show', $defect) }}">{{ $defect->code }}</a></td>
                    <td class="font-medium">{{ $defect->title }}</td>
                    @if ($showProject)<td class="text-muted">{{ $defect->project?->code }}</td>@endif
                    <td><x-badge :enum="$defect->severity" /></td>
                    <td><x-badge :enum="$defect->status" /></td>
                    <td>{{ $defect->assignee?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
