{{-- One completed-log entry. @param object $entry --}}
<li @class(['timeline-item', 'is-milestone' => $entry->type === 'milestone']) data-log-entry="{{ $entry->type }}-{{ $entry->id }}">
    <span class="timeline-dot" aria-hidden="true"><x-icon :name="$entry->type === 'milestone' ? 'flag' : 'check'" :size="11" /></span>
    <div class="min-w-0 flex-1">
        <p class="text-sm leading-snug">
            <span class="sr-only">{{ $entry->type === 'milestone' ? 'Milestone' : 'Task' }} completed:</span>
            <a href="{{ $entry->url }}" class="font-medium hover:underline">{{ $entry->title }}</a>
            @if ($entry->type === 'milestone')<span class="badge tone-success ml-1">Milestone</span>@endif
        </p>
        <p class="mt-0.5 text-xs text-muted">
            {{ $entry->by ?? 'Someone' }} ·
            <time datetime="{{ $entry->at->toIso8601String() }}" title="{{ $entry->at->format('d M Y H:i') }}">{{ $entry->at->format('d M, H:i') }}</time>
            @if ($entry->milestone)<span class="text-faint"> · {{ $entry->milestone }}</span>@endif
        </p>
    </div>
</li>
