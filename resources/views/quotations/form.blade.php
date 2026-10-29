@extends('layouts.app')
@section('title', $quotation->exists ? 'Edit '.$quotation->reference() : 'New quotation')
@section('content')
    <x-page-header :title="$quotation->exists ? 'Edit '.$quotation->reference() : 'New quotation'" :subtitle="$project->code.' · '.$project->name"
        :back="$quotation->exists ? route('quotations.show', $quotation) : route('projects.quotations', $project)" />

    <form method="POST" action="{{ $quotation->exists ? route('quotations.update', $quotation) : route('projects.quotations.store', $project) }}" class="flex flex-col gap-6">
        @csrf
        @if ($quotation->exists) @method('PUT') @endif

        <x-card title="Quotation">
            <div class="grid gap-4 md:grid-cols-3">
                <x-field name="title" label="Title" :value="$quotation->title" required class="md:col-span-2" />
                <x-field name="scope_version_id" type="select" label="Scope baseline" :options="$scopes" :value="$quotation->scope_version_id" blank="Not linked" />
                <x-field name="currency" label="Currency" :value="$quotation->currency" required maxlength="3" />
                <x-field name="valid_until" type="date" label="Valid until" :value="$quotation->valid_until" />
                <x-field name="delivery_period" label="Delivery period" :value="$quotation->delivery_period" placeholder="6 weeks" />
                <x-field name="scope_summary" type="textarea" label="Scope summary" :value="$quotation->scope_summary" class="md:col-span-3" />
            </div>
        </x-card>

        <x-card title="Line items">
            <div data-repeater class="flex flex-col gap-3">
                <div data-repeater-rows class="flex flex-col gap-3">
                    @foreach (array_values($items) as $index => $item)
                        @include('quotations.partials.item-row', ['index' => $index, 'item' => $item])
                    @endforeach
                </div>
                <template data-repeater-template>@include('quotations.partials.item-row', ['index' => '__INDEX__', 'item' => []])</template>
                @error('items')<p class="error">{{ $message }}</p>@enderror
                @if ($errors->has('items.*'))<p class="error">Check the line items: {{ $errors->first('items.*') }}</p>@endif
                <div><button type="button" class="btn" data-repeater-add><x-icon name="plus" :size="16" /> Add line</button></div>
                <p class="hint">Totals, tax and internal margin are calculated on save. Optional items are shown separately and excluded from the total.</p>
            </div>
        </x-card>

        <x-card title="Commercial terms">
            <div class="grid gap-4 md:grid-cols-2">
                <x-field name="discount_amount" type="number" label="Discount (amount)" :value="$quotation->discount_amount" min="0" step="0.01" />
                <x-field name="tax_rate" type="number" label="Tax rate (%)" :value="$quotation->tax_rate" min="0" max="100" step="0.01" />
                <x-field name="payment_terms" label="Payment terms" :value="$quotation->payment_terms" placeholder="50% advance, 50% on delivery" class="md:col-span-2" />
                <x-field name="assumptions" type="textarea" label="Assumptions" :value="$quotation->assumptions" />
                <x-field name="exclusions" type="textarea" label="Exclusions" :value="$quotation->exclusions" />
                <x-field name="notes" type="textarea" label="Internal notes" :value="$quotation->notes" hint="Not shown on the printable quotation." class="md:col-span-2" />
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ $quotation->exists ? route('quotations.show', $quotation) : route('projects.quotations', $project) }}" class="btn">Cancel</a>
            <button class="btn btn-primary">{{ $quotation->exists ? 'Save changes' : 'Save draft' }}</button>
        </div>
    </form>
@endsection
