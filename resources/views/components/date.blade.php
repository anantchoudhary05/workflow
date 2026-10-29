@props(['value', 'overdue' => false, 'empty' => '—'])
{{-- Date with optional overdue highlight. --}}
@if ($value)
    <time datetime="{{ $value->toDateString() }}" @class(['whitespace-nowrap', 'font-medium text-danger' => $overdue && $value->lt(today())])>{{ $value->format('d M Y') }}</time>
@else
    <span class="text-faint">{{ $empty }}</span>
@endif
