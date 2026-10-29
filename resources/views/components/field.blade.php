@props([
    'name', 'label' => null, 'type' => 'text', 'value' => null, 'options' => [], 'placeholder' => null,
    'hint' => null, 'required' => false, 'blank' => null, 'rows' => 3,
])
{{-- "id" is the enclosing <x-dialog> id (null on page forms); old input and errors only apply to the form that was submitted. --}}
@aware(['id' => null])
@php
    // Dotted error key for array-style names like items[0][title].
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $fieldId = 'f-'.str_replace('.', '-', $key).'-'.\Illuminate\Support\Str::random(4);
    $fromThisForm = old('_dialog') === $id;
    $default = $value instanceof \BackedEnum ? $value->value : $value;
    $current = $fromThisForm ? old($key, $default) : $default;
    if ($current instanceof \DateTimeInterface) {
        $current = $current->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d');
    }
    $invalid = $fromThisForm && $errors->has($key);
    $message = $invalid ? $errors->first($key) : null;
@endphp
<div {{ $attributes->only('class') }}>
    @if ($type === 'checkbox')
        <input type="hidden" name="{{ $name }}" value="0">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" id="{{ $fieldId }}" name="{{ $name }}" value="1" @checked($current) {{ $attributes->except('class') }} class="size-4 rounded border-line">
            <span>{{ $label }}</span>
        </label>
    @else
        @if ($label)
            <label for="{{ $fieldId }}" class="label">{{ $label }}@if ($required)<span class="text-danger"> *</span>@endif</label>
        @endif
        @if ($type === 'select')
            <select id="{{ $fieldId }}" name="{{ $name }}" class="input" @required($required) aria-invalid="{{ $invalid ? 'true' : 'false' }}" {{ $attributes->except('class') }}>
                @if ($blank !== null)<option value="">{{ $blank }}</option>@endif
                @foreach ($options as $optValue => $optLabel)
                    <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optLabel }}</option>
                @endforeach
            </select>
        @elseif ($type === 'textarea')
            <textarea id="{{ $fieldId }}" name="{{ $name }}" rows="{{ $rows }}" class="input" placeholder="{{ $placeholder }}" @required($required) aria-invalid="{{ $invalid ? 'true' : 'false' }}" {{ $attributes->except('class') }}>{{ $current }}</textarea>
        @else
            <input id="{{ $fieldId }}" type="{{ $type }}" name="{{ $name }}" @if ($type !== 'password' && $type !== 'file') value="{{ $current }}" @endif
                class="input" placeholder="{{ $placeholder }}" @required($required) aria-invalid="{{ $invalid ? 'true' : 'false' }}" {{ $attributes->except('class') }}>
        @endif
    @endif
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @if ($invalid)<p class="error">{{ $message }}</p>@endif
</div>
