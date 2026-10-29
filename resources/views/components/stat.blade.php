@props(['label', 'value', 'tone' => 'brand', 'href' => null, 'hint' => null, 'icon' => null])
{{-- KPI card: tone = brand|violet|warning|info|danger|rose|teal|success|neutral. Zero values are muted. --}}
@php
    $tone = in_array($tone, ['brand', 'violet', 'warning', 'info', 'danger', 'rose', 'teal', 'success', 'neutral'], true) ? $tone : 'brand';
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['kpi', 'kpi-'.$tone, 'kpi-zero' => (string) $value === '0']) }}>
    <div class="flex items-start justify-between gap-3">
        @if ($icon)<span class="kpi-icon"><x-icon :name="$icon" :size="20" /></span>@endif
        @if ($href)<x-icon name="chevron-right" :size="16" class="relative z-[1] text-faint" />@endif
    </div>
    <p class="kpi-value">{{ $value }}</p>
    <div>
        <p class="kpi-label">{{ $label }}</p>
        @if ($hint)<p class="kpi-hint">{{ $hint }}</p>@endif
    </div>
</{{ $tag }}>
