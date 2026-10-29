@props(['id', 'title', 'open' => false, 'icon' => null])
{{--
    Native modal. Open with <button data-dialog-open="{{ $id }}">.
    Every form inside gets a hidden "_dialog" input, so after a failed submit only the dialog that was
    submitted re-opens and only its fields show the old input and validation errors.
--}}
@php
    $submitted = old('_dialog') === $id;
    $shouldOpen = $open || ($submitted && $errors->any());
    $body = preg_replace('/<form\b[^>]*>/i', '$0<input type="hidden" name="_dialog" value="'.e($id).'">', (string) $slot);
@endphp
<dialog id="{{ $id }}" class="modal" aria-labelledby="{{ $id }}-title" @if ($shouldOpen) data-open @endif>
    <div class="modal-head">
        <h2 id="{{ $id }}-title" class="flex items-center gap-2 font-semibold">
            @if ($icon)<span class="modal-icon"><x-icon :name="$icon" :size="16" /></span>@endif
            {{ $title }}
        </h2>
        <button type="button" class="btn btn-ghost btn-sm" data-dialog-close aria-label="Close"><x-icon name="x" :size="16" /></button>
    </div>
    <div class="max-h-[75vh] overflow-y-auto px-5 py-4">
        @if ($submitted && $errors->has('workflow'))
            <div class="alert tone-danger mb-4 flex items-start gap-2" role="alert"><x-icon name="alert" :size="16" class="mt-0.5 shrink-0" /><span>{{ $errors->first('workflow') }}</span></div>
        @endif
        {!! $body !!}
    </div>
</dialog>
