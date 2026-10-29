@extends('layouts.app')
@section('title', $quotation->reference())
@section('content')
    @php($money = fn ($v) => App\Support\Money::format($v, $quotation->currency))
    <x-page-header :title="$quotation->reference().' · '.$quotation->title" :subtitle="$quotation->project->code.' · '.$quotation->client?->name" :back="route('projects.quotations', $quotation->project)">
        <x-slot:actions>
            <x-badge :enum="$quotation->status" />
            <a href="{{ route('quotations.print', $quotation) }}" class="btn" target="_blank"><x-icon name="printer" :size="16" /> Print</a>
            @can('update', $quotation)
                @if (! $quotation->isLocked())
                    <a href="{{ route('quotations.edit', $quotation) }}" class="btn"><x-icon name="edit" :size="16" /> Edit</a>
                @endif
                @if ($quotation->status === App\Enums\QuotationStatus::Draft)
                    <form method="POST" action="{{ route('quotations.submit', $quotation) }}">@csrf<button class="btn btn-primary">Submit for review</button></form>
                @elseif ($quotation->status === App\Enums\QuotationStatus::InternalReview)
                    <form method="POST" action="{{ route('quotations.send', $quotation) }}" data-confirm="Mark as sent to the client? It will be locked and a follow-up will be created.">@csrf<button class="btn btn-primary">Mark as sent</button></form>
                @elseif ($quotation->status === App\Enums\QuotationStatus::Sent)
                    <button type="button" class="btn btn-primary" data-dialog-open="decide-quotation">Record client decision</button>
                @endif
                @if ($quotation->isLocked() && $quotation->status !== App\Enums\QuotationStatus::Superseded)
                    <form method="POST" action="{{ route('quotations.new-version', $quotation) }}">@csrf<button class="btn">New version</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <x-card title="Line items" :padded="false">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit price</th><th class="text-right">Total</th><th class="text-right">Hours</th><th class="text-right">Cost</th></tr></thead>
                        <tbody>
                            @forelse ($quotation->items as $item)
                                <tr @class(['text-muted' => $item->is_optional])>
                                    <td><p class="font-medium">{{ $item->description }}@if ($item->is_optional) <span class="badge tone-neutral">Optional</span>@endif</p><p class="text-xs text-muted">{{ $item->category }}@if ($item->notes) · {{ $item->notes }}@endif</p></td>
                                    <td class="text-right tabular-nums">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} {{ $item->unit }}</td>
                                    <td class="text-right tabular-nums">{{ $money($item->unit_price) }}</td>
                                    <td class="text-right tabular-nums">{{ $money($item->line_total) }}</td>
                                    <td class="text-right tabular-nums">{{ (float) $item->hours ?: '—' }}</td>
                                    <td class="text-right tabular-nums">{{ (float) $item->internal_cost ? $money($item->internal_cost) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No line items.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            @foreach (['scope_summary' => 'Scope', 'assumptions' => 'Assumptions', 'exclusions' => 'Exclusions', 'notes' => 'Internal notes'] as $field => $label)
                @if ($quotation->{$field})<x-card :title="$label"><p class="whitespace-pre-line">{{ $quotation->{$field} }}</p></x-card>@endif
            @endforeach
        </div>

        <div class="flex flex-col gap-6">
            <x-card title="Totals">
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-muted">Subtotal</dt><dd class="text-right tabular-nums">{{ $money($quotation->subtotal) }}</dd>
                    <dt class="text-muted">Discount</dt><dd class="text-right tabular-nums">− {{ $money($quotation->discount_amount) }}</dd>
                    <dt class="text-muted">Tax ({{ (float) $quotation->tax_rate }}%)</dt><dd class="text-right tabular-nums">{{ $money($quotation->tax_amount) }}</dd>
                    <dt class="font-semibold">Total</dt><dd class="text-right text-lg font-semibold tabular-nums">{{ $money($quotation->total) }}</dd>
                    @if ((float) $quotation->optional_total)<dt class="text-muted">Optional items</dt><dd class="text-right tabular-nums">{{ $money($quotation->optional_total) }}</dd>@endif
                </dl>
                <dl class="mt-4 grid grid-cols-2 gap-2 border-t border-line pt-4 text-sm">
                    <dt class="text-muted">Estimated hours</dt><dd class="text-right tabular-nums">{{ (float) $quotation->estimated_hours }}</dd>
                    <dt class="text-muted">Internal cost</dt><dd class="text-right tabular-nums">{{ $money($quotation->internal_cost_total) }}</dd>
                    <dt class="text-muted">Margin</dt><dd @class(['text-right tabular-nums', 'text-danger' => $quotation->margin() !== null && $quotation->margin() < 20])>{{ $quotation->margin() !== null ? $quotation->margin().'%' : '—' }}</dd>
                </dl>
            </x-card>

            <x-card title="Details">
                <dl class="grid gap-3 text-sm">
                    <div><dt class="text-muted">Scope baseline</dt><dd>@if ($quotation->scopeVersion)<a class="link" href="{{ route('scope.show', $quotation->scopeVersion) }}">v{{ $quotation->scopeVersion->version }}</a>@else <span class="text-warning">Not linked</span>@endif</dd></div>
                    <div><dt class="text-muted">Valid until</dt><dd><x-date :value="$quotation->valid_until" :overdue="$quotation->status === App\Enums\QuotationStatus::Sent" /></dd></div>
                    <div><dt class="text-muted">Delivery period</dt><dd>{{ $quotation->delivery_period ?? '—' }}</dd></div>
                    <div><dt class="text-muted">Payment terms</dt><dd>{{ $quotation->payment_terms ?? '—' }}</dd></div>
                    <div><dt class="text-muted">Created by</dt><dd>{{ $quotation->creator?->name }} · {{ $quotation->created_at?->format('d M Y') }}</dd></div>
                    @if ($quotation->sent_at)<div><dt class="text-muted">Sent</dt><dd>{{ $quotation->sent_at->format('d M Y') }}</dd></div>@endif
                    @if ($quotation->decided_at)<div><dt class="text-muted">Decision</dt><dd>{{ $quotation->status->label() }} on {{ $quotation->decided_at->format('d M Y') }} (recorded by {{ $quotation->decider?->name }})<span class="block text-muted">{{ $quotation->decision_source }}</span>@if ($quotation->decision_notes)<span class="block whitespace-pre-line">{{ $quotation->decision_notes }}</span>@endif</dd></div>@endif
                </dl>
            </x-card>

            <x-card title="Versions" :padded="false">
                <ul class="divide-y divide-line text-sm">
                    @foreach ($versions as $version)
                        <li @class(['flex items-center justify-between gap-2 px-4 py-2', 'bg-subtle' => $version->id === $quotation->id])>
                            <a class="link" href="{{ route('quotations.show', $version->id) }}">v{{ $version->version }}</a>
                            <x-badge :enum="$version->status" />
                            <span class="tabular-nums">{{ $money($version->total) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    </div>

    @can('update', $quotation)
        @if ($quotation->status === App\Enums\QuotationStatus::Sent)
            <x-dialog id="decide-quotation" title="Record client decision">
                <form method="POST" action="{{ route('quotations.decide', $quotation) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="outcome" type="select" label="Outcome" :options="collect($outcomes)->except(App\Enums\ApprovalOutcome::ChangesRequested->value)->all()" required />
                    <x-field name="decided_by_name" label="Decided by (client contact)" required />
                    <x-field name="source_reference" label="Evidence" placeholder="Email 14 Mar, signed PDF…" required />
                    <x-field name="decided_at" type="date" label="Decision date" :value="now()" required />
                    <x-field name="notes" type="textarea" label="Notes / conditions" />
                    <p class="hint">To request changes, record a rejection with notes and create a new version.</p>
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Record decision</button></div>
                </form>
            </x-dialog>
        @endif
    @endcan
@endsection
