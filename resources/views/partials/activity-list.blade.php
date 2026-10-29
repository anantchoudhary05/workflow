{{-- Expects $logs (ActivityLog collection with user + optional project loaded). --}}
@forelse ($logs as $log)
    <li class="flex gap-3 py-2.5">
        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $log->is_audit ? 'bg-warning' : 'bg-line' }}"></span>
        <div class="min-w-0 flex-1">
            <p>{{ $log->description }}</p>
            <p class="text-xs text-muted">
                {{ $log->user?->name ?? 'System' }}
                @if ($log->relationLoaded('project') && $log->project) · {{ $log->project->code }} @endif
                · <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('d M Y H:i') }}">{{ $log->created_at->diffForHumans() }}</time>
            </p>
            @if ($log->changes)
                <ul class="mt-1 space-y-0.5 text-xs text-muted">
                    @foreach ($log->changes as $field => $change)
                        @if (is_array($change) && array_key_exists('from', $change))
                            <li><span class="font-medium">{{ str_replace('_', ' ', $field) }}</span>: {{ is_array($change['from']) ? json_encode($change['from']) : ($change['from'] ?? '—') }} → {{ is_array($change['to']) ? json_encode($change['to']) : ($change['to'] ?? '—') }}</li>
                        @elseif (! is_array($change))
                            <li><span class="font-medium">{{ str_replace('_', ' ', $field) }}</span>: {{ $change }}</li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </li>
@empty
    <li class="py-6 text-center text-muted">No activity recorded yet.</li>
@endforelse
