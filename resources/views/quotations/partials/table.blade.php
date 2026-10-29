{{-- Expects $quotations and $showProject. --}}
<div class="overflow-x-auto">
    <table class="table">
        <thead><tr><th>Reference</th><th>Title</th>@if ($showProject)<th>Project</th>@endif<th>Status</th><th class="text-right">Total</th><th>Updated</th></tr></thead>
        <tbody>
            @foreach ($quotations as $quotation)
                <tr>
                    <td><a href="{{ route('quotations.show', $quotation) }}" class="link font-mono text-xs">{{ $quotation->reference() }}</a></td>
                    <td>{{ $quotation->title }}</td>
                    @if ($showProject)<td><a class="link" href="{{ route('projects.show', $quotation->project_id) }}">{{ $quotation->project?->code }}</a> <span class="text-muted">{{ $quotation->client?->name }}</span></td>@endif
                    <td><x-badge :enum="$quotation->status" /></td>
                    <td class="text-right tabular-nums">{{ App\Support\Money::format($quotation->total, $quotation->currency) }}</td>
                    <td class="text-muted">{{ $quotation->updated_at?->format('d M Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
