{{-- Expects $changes; optional $showProject (default true). --}}
@php($showProject = $showProject ?? true)
<div class="overflow-x-auto">
    <table class="table">
        <thead><tr><th>Number</th><th>Title</th>@if ($showProject)<th>Project</th>@endif<th>Classification</th><th>Status</th><th>Received</th><th>Reviewer</th></tr></thead>
        <tbody>
            @foreach ($changes as $change)
                <tr>
                    <td><a class="link font-mono text-xs" href="{{ route('change-requests.show', $change) }}">{{ $change->number }}</a></td>
                    <td class="font-medium">{{ $change->title }}</td>
                    @if ($showProject)<td class="text-muted">{{ $change->project?->code }}</td>@endif
                    <td>@if ($change->classification)<x-badge :enum="$change->classification" />@else <span class="text-warning">Unclassified</span>@endif</td>
                    <td><x-badge :enum="$change->status" /></td>
                    <td><x-date :value="$change->received_at" /></td>
                    <td>{{ $change->reviewer?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
