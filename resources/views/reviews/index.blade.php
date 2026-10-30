@extends('layouts.app')
@section('title', 'Review queue')
@section('content')
    <x-page-header title="Review queue" icon="badge-check" subtitle="Team Leads ask whether work is ready for client preview; the Manager answers “Ready for client preview” or “Needs corrections”.">
        <x-slot:meta><div class="mt-2"><span class="badge tone-warning">{{ $pending->count() }} awaiting review</span></div></x-slot:meta>
    </x-page-header>

    <div class="flex flex-col gap-6">
        <x-card title="Awaiting manager review" :padded="false">
            <ul class="divide-y divide-line">
                @forelse ($pending as $review)
                    <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2"><a class="font-medium hover:underline" href="{{ route('projects.show', $review->project_id) }}">{{ $review->project?->code }}</a><span class="font-medium">{{ $review->title }}</span>@if ($review->project?->priority === App\Enums\Priority::Urgent)<x-badge :enum="$review->project->priority" />@endif</p>
                            <p class="text-xs text-muted">Requested by {{ $review->requester?->name }} · {{ $review->created_at?->diffForHumans() }}</p>
                            @if ($review->url)<p class="text-xs"><a class="link" href="{{ $review->url }}" target="_blank" rel="noopener noreferrer">{{ $review->url }}</a></p>@endif
                            @if ($review->notes)<p class="mt-1 whitespace-pre-line text-sm">{{ $review->notes }}</p>@endif
                        </div>
                        @if ($canDecide)
                            <button type="button" class="btn btn-primary btn-sm" data-dialog-open="decide-review-{{ $review->id }}">Decide</button>
                        @else
                            <x-badge :enum="$review->status" />
                        @endif
                    </li>
                @empty
                    <li><x-empty-state title="Nothing waiting for review" icon="badge-check" /></li>
                @endforelse
            </ul>
        </x-card>

        <x-card title="Recent decisions" :padded="false">
            <ul class="divide-y divide-line">
                @forelse ($decided as $review)
                    <li class="px-4 py-2.5">
                        <p class="flex flex-wrap items-center justify-between gap-2"><span><a class="link" href="{{ route('projects.show', $review->project_id) }}">{{ $review->project?->code }}</a> · {{ $review->title }}</span><x-badge :enum="$review->status" /></p>
                        <p class="text-xs text-muted">{{ $review->decider?->name }} · {{ $review->decided_at?->format('d M Y H:i') }}</p>
                        @if ($review->decision_notes)<p class="mt-1 whitespace-pre-line text-sm">{{ $review->decision_notes }}</p>@endif
                    </li>
                @empty
                    <li class="px-4 py-4 text-muted">No decisions yet.</li>
                @endforelse
            </ul>
        </x-card>
    </div>

    @if ($canDecide)
        @foreach ($pending as $review)
            <x-dialog id="decide-review-{{ $review->id }}" title="Review: {{ $review->title }}" icon="badge-check">
                <form method="POST" action="{{ route('review-requests.decide', $review) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="status" type="select" label="Decision" :options="[App\Enums\ReviewRequestStatus::ReadyForPreview->value => 'Ready for client preview', App\Enums\ReviewRequestStatus::NeedsCorrections->value => 'Needs corrections']" required />
                    <x-field name="decision_notes" type="textarea" label="Notes / corrections" :rows="5" hint="Required when corrections are needed." />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Record decision</button></div>
                </form>
            </x-dialog>
        @endforeach
    @endif
@endsection
