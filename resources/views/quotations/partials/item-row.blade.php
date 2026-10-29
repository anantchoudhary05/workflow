{{-- One line item row. Expects $index and $item (array). --}}
<div class="grid grid-cols-12 items-start gap-2 rounded-md border border-line p-3" data-repeater-row>
    <div class="col-span-12 md:col-span-5">
        <label class="label text-xs" for="items-{{ $index }}-description">Description</label>
        <input class="input" id="items-{{ $index }}-description" name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" required maxlength="500">
        <input class="input mt-2 text-xs" name="items[{{ $index }}][notes]" value="{{ $item['notes'] ?? '' }}" placeholder="Line notes (optional)" aria-label="Line notes" maxlength="500">
    </div>
    <div class="col-span-6 md:col-span-2">
        <label class="label text-xs" for="items-{{ $index }}-category">Category</label>
        <input class="input" id="items-{{ $index }}-category" name="items[{{ $index }}][category]" value="{{ $item['category'] ?? '' }}" placeholder="Design, Dev…" maxlength="40">
    </div>
    <div class="col-span-3 md:col-span-1">
        <label class="label text-xs" for="items-{{ $index }}-quantity">Qty</label>
        <input class="input" type="number" step="0.01" min="0.01" id="items-{{ $index }}-quantity" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" required>
    </div>
    <div class="col-span-3 md:col-span-1">
        <label class="label text-xs" for="items-{{ $index }}-unit">Unit</label>
        <input class="input" id="items-{{ $index }}-unit" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? 'item' }}" maxlength="30">
    </div>
    <div class="col-span-6 md:col-span-2">
        <label class="label text-xs" for="items-{{ $index }}-unit_price">Unit price</label>
        <input class="input" type="number" step="0.01" min="0" id="items-{{ $index }}-unit_price" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" required>
    </div>
    <div class="col-span-6 flex items-end justify-end md:col-span-1">
        <button type="button" class="btn btn-ghost btn-sm mt-6" data-repeater-remove aria-label="Remove line"><x-icon name="x" :size="14" /></button>
    </div>
    <div class="col-span-4 md:col-span-2">
        <label class="label text-xs" for="items-{{ $index }}-hours">Est. hours</label>
        <input class="input" type="number" step="0.25" min="0" id="items-{{ $index }}-hours" name="items[{{ $index }}][hours]" value="{{ $item['hours'] ?? '' }}">
    </div>
    <div class="col-span-4 md:col-span-2">
        <label class="label text-xs" for="items-{{ $index }}-cost_rate">Internal cost / h</label>
        <input class="input" type="number" step="0.01" min="0" id="items-{{ $index }}-cost_rate" name="items[{{ $index }}][cost_rate]" value="{{ $item['cost_rate'] ?? '' }}">
    </div>
    <label class="col-span-4 mt-6 flex items-center gap-2 text-sm md:col-span-3">
        <input type="hidden" name="items[{{ $index }}][is_optional]" value="0">
        <input type="checkbox" name="items[{{ $index }}][is_optional]" value="1" @checked(! empty($item['is_optional']))> Optional item
    </label>
</div>
