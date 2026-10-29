@php($money = fn ($v) => App\Support\Money::format($v, $quotation->currency))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation {{ $quotation->reference() }} · {{ config('app.name') }}</title>
    @include('partials.assets')
</head>
<body class="bg-surface font-sans text-ink antialiased">
<main class="mx-auto max-w-3xl p-8">
    <div class="no-print mb-6 flex justify-end gap-2">
        <a href="{{ route('quotations.show', $quotation) }}" class="btn">Back</a>
        <button type="button" class="btn btn-primary" data-print>Print / Save as PDF</button>
    </div>
    <header class="mb-8 flex items-start justify-between gap-6">
        <div>
            <p class="text-xl font-semibold">{{ config('app.name') }}</p>
            <p class="mt-6 text-sm text-muted">Prepared for</p>
            <p class="font-medium">{{ $quotation->client?->name }}</p>
            <p class="text-sm">{{ $quotation->project->name }}</p>
        </div>
        <div class="text-right">
            <p class="text-2xl font-semibold">Quotation</p>
            <p class="font-mono text-sm">{{ $quotation->reference() }}</p>
            <p class="mt-2 text-sm">Date: {{ ($quotation->sent_at ?? $quotation->updated_at)?->format('d M Y') }}</p>
            @if ($quotation->valid_until)<p class="text-sm">Valid until: {{ $quotation->valid_until->format('d M Y') }}</p>@endif
            @if ($quotation->isLocked() === false)<p class="mt-2 badge tone-warning">Draft</p>@endif
        </div>
    </header>

    <h1 class="mb-2 text-lg font-semibold">{{ $quotation->title }}</h1>
    @if ($quotation->scope_summary)<p class="mb-6 whitespace-pre-line text-sm">{{ $quotation->scope_summary }}</p>@endif

    <table class="table mb-6">
        <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
            @foreach ($quotation->items->where('is_optional', false) as $item)
                <tr><td>{{ $item->description }}@if ($item->notes)<span class="block text-xs text-muted">{{ $item->notes }}</span>@endif</td><td class="text-right tabular-nums">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} {{ $item->unit }}</td><td class="text-right tabular-nums">{{ $money($item->unit_price) }}</td><td class="text-right tabular-nums">{{ $money($item->line_total) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="text-right">Subtotal</td><td class="text-right tabular-nums">{{ $money($quotation->subtotal) }}</td></tr>
            @if ((float) $quotation->discount_amount)<tr><td colspan="3" class="text-right">Discount</td><td class="text-right tabular-nums">− {{ $money($quotation->discount_amount) }}</td></tr>@endif
            @if ((float) $quotation->tax_rate)<tr><td colspan="3" class="text-right">Tax ({{ (float) $quotation->tax_rate }}%)</td><td class="text-right tabular-nums">{{ $money($quotation->tax_amount) }}</td></tr>@endif
            <tr><td colspan="3" class="text-right font-semibold">Total</td><td class="text-right font-semibold tabular-nums">{{ $money($quotation->total) }}</td></tr>
        </tfoot>
    </table>

    @if ($quotation->items->contains('is_optional', true))
        <h2 class="mb-2 font-semibold">Optional items</h2>
        <table class="table mb-6">
            <tbody>
                @foreach ($quotation->items->where('is_optional', true) as $item)
                    <tr><td>{{ $item->description }}</td><td class="text-right tabular-nums">{{ $money($item->line_total) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="grid gap-4 text-sm">
        @foreach (['delivery_period' => 'Delivery period', 'payment_terms' => 'Payment terms', 'assumptions' => 'Assumptions', 'exclusions' => 'Exclusions'] as $field => $label)
            @if ($quotation->{$field})<div><p class="font-semibold">{{ $label }}</p><p class="whitespace-pre-line">{{ $quotation->{$field} }}</p></div>@endif
        @endforeach
    </div>
</main>
</body>
</html>
