@extends('layouts.app')
@section('title', 'Meetings')
@section('content')
    <x-page-header title="Meetings" subtitle="Client and internal meetings with their decisions.">
        <x-slot:actions>@if ($canRecord)<a class="btn btn-primary" href="{{ route('meetings.create') }}"><x-icon name="plus" :size="16" /> New meeting</a>@endif</x-slot:actions>
    </x-page-header>
    @php
        $gridStart = $month->startOfMonth()->startOfWeek(Carbon\CarbonInterface::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(Carbon\CarbonInterface::SUNDAY);
        $days = Carbon\CarbonPeriod::create($gridStart, $gridEnd);
    @endphp
    <x-card :padded="false">
        <x-slot:actions>
            <a class="btn btn-ghost btn-sm" href="{{ route('meetings.index', ['month' => $month->subMonth()->format('Y-m')]) }}" aria-label="Previous month"><x-icon name="chevron-left" :size="16" /></a>
            <span class="font-medium">{{ $month->format('F Y') }}</span>
            <a class="btn btn-ghost btn-sm" href="{{ route('meetings.index', ['month' => $month->addMonth()->format('Y-m')]) }}" aria-label="Next month"><x-icon name="chevron-right" :size="16" /></a>
            @unless ($month->isSameMonth(today()))<a class="btn btn-sm" href="{{ route('meetings.index') }}">Today</a>@endunless
        </x-slot:actions>
        <div class="grid grid-cols-7 border-b border-line text-center text-xs font-medium text-muted">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)<div class="py-2">{{ $dayName }}</div>@endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach ($days as $day)
                @php($items = $calendar->get($day->toDateString(), collect()))
                <div @class(['min-h-24 border-b border-r border-line p-1.5 text-xs', 'bg-subtle text-faint' => ! $day->isSameMonth($month)])>
                    <p @class(['mb-1 text-right', 'font-semibold text-brand' => $day->isToday()])>{{ $day->day }}</p>
                    @foreach ($items as $meeting)
                        <a href="{{ route('meetings.show', $meeting) }}" @class(['mb-1 block truncate rounded bg-brand-soft px-1 py-0.5 text-ink hover:underline', 'line-through opacity-60' => $meeting->status === 'cancelled']) title="{{ $meeting->title }}">{{ $meeting->starts_at->format('H:i') }} {{ $meeting->title }}</a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </x-card>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach (['Upcoming' => $upcoming, 'Recent' => $recent] as $heading => $list)
            <x-card :title="$heading" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($list as $meeting)
                        <li class="px-4 py-2.5">
                            <a class="font-medium hover:underline" href="{{ route('meetings.show', $meeting) }}">{{ $meeting->title }}</a>
                            <p class="text-xs text-muted">{{ $meeting->starts_at->format('D d M Y, H:i') }} · {{ $meeting->type->label() }}@if ($meeting->project) · {{ $meeting->project->code }}@elseif ($meeting->client) · {{ $meeting->client->name }}@endif · {{ ucfirst($meeting->status) }}</p>
                        </li>
                    @empty
                        <li class="px-4 py-4 text-muted">No meetings.</li>
                    @endforelse
                </ul>
            </x-card>
        @endforeach
    </div>
@endsection
