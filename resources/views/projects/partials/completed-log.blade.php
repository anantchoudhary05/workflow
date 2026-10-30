{{--
    Compact timeline of recently completed tasks and milestones in this project (who and when). The first few entries
    show; the rest sit behind "Show more".
    @param Project $project
    @param Collection $log  entries from App\Services\MilestoneTrack (type, title, at, by, milestone, url)
--}}
@php($visible = 5)
<x-card title="Completed log" icon="check-circle" :padded="false" id="completed-log" data-completed-log>
    <x-slot:actions><span class="text-xs text-muted">{{ $log->count() }} recent</span></x-slot:actions>
    @if ($log->isEmpty())
        <x-empty-state title="Nothing completed yet" icon="check-circle">Finished tasks and milestones will appear here as a timeline.</x-empty-state>
    @else
        <ol class="timeline" data-timeline>
            @foreach ($log->take($visible) as $entry)
                @include('projects.partials.completed-log-entry', ['entry' => $entry])
            @endforeach
        </ol>
        @if ($log->count() > $visible)
            <ol class="timeline timeline-more" id="completed-log-more" hidden>
                @foreach ($log->slice($visible) as $entry)
                    @include('projects.partials.completed-log-entry', ['entry' => $entry])
                @endforeach
            </ol>
            <div class="px-4 pb-3">
                <button type="button" class="btn btn-ghost btn-sm w-full" data-disclosure data-label-closed="Show {{ $log->count() - $visible }} more" data-label-open="Show less" aria-expanded="false" aria-controls="completed-log-more">
                    <x-icon name="chevron-down" :size="14" /> <span data-disclosure-label>Show {{ $log->count() - $visible }} more</span>
                </button>
            </div>
        @endif
    @endif
</x-card>
