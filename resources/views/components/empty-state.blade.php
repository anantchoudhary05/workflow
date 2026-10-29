@props(['title', 'icon' => 'inbox'])
<div {{ $attributes->class('flex flex-col items-center px-6 py-10 text-center') }}>
    <span class="mb-3 grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand ring-8 ring-[var(--c-subtle)]"><x-icon :name="$icon" :size="22" /></span>
    <p class="font-semibold">{{ $title }}</p>
    @if ($slot->isNotEmpty())<div class="mt-1 max-w-md text-sm text-muted">{{ $slot }}</div>@endif
</div>
