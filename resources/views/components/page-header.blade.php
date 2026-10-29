@props(['title', 'subtitle' => null, 'back' => null, 'icon' => null])
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-1 inline-flex items-center gap-1 text-xs text-muted hover:text-ink"><x-icon name="chevron-left" :size="14" /> Back</a>
        @endif
        <h1 class="flex items-center gap-2.5 text-xl font-semibold tracking-tight">@if ($icon)<span class="grid size-9 place-items-center rounded-xl bg-brand-soft text-brand"><x-icon :name="$icon" :size="18" /></span>@endif<span class="min-w-0">{{ $title }}</span></h1>
        @if ($subtitle)<p class="mt-1 text-muted">{{ $subtitle }}</p>@endif
        {{ $meta ?? '' }}
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
