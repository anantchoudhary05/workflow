@props(['size' => 32, 'wordmark' => true, 'tone' => 'light'])
{{-- Workflow brand: a gradient tile with three connected nodes (a flow), plus the "Workflow" wordmark. --}}
@php($gid = 'wf-'.substr(md5(uniqid('', true)), 0, 6))
<span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false" class="shrink-0">
        <defs>
            <linearGradient id="{{ $gid }}" x1="2" y1="2" x2="30" y2="30" gradientUnits="userSpaceOnUse">
                <stop stop-color="#6366F1"/><stop offset=".55" stop-color="#7C3AED"/><stop offset="1" stop-color="#C026D3"/>
            </linearGradient>
        </defs>
        <rect x="1" y="1" width="30" height="30" rx="8" fill="url(#{{ $gid }})"/>
        <path d="M8.5 11.5 12.5 21l3.5-7.5 3.5 7.5 4-9.5" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="8.5" cy="11.5" r="2" fill="#fff"/><circle cx="23.5" cy="11.5" r="2" fill="#fff"/><circle cx="16" cy="13.5" r="1.6" fill="#E0E7FF"/>
    </svg>
    @if ($wordmark)
        <span @class(['brand-text leading-tight', 'text-white' => $tone === 'light', 'text-ink' => $tone === 'dark'])>
            <span class="brand-word block text-[15px]">Work<span>flow</span></span>
            <span @class(['block text-[10.5px] font-medium tracking-wide', 'text-indigo-200/80' => $tone === 'light', 'text-muted' => $tone === 'dark'])>Development Operations</span>
        </span>
    @endif
</span>
