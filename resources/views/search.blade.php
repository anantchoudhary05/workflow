@extends('layouts.app')
@section('title', 'Search')
@section('content')
    <x-page-header title="Search" subtitle="Searches only the records you are allowed to see." />

    <form method="GET" action="{{ route('search') }}" class="mb-6 flex max-w-xl gap-2">
        <input type="search" name="q" value="{{ $term }}" class="input" placeholder="Project, client, task, requirement, CR, defect…" minlength="2" autofocus>
        <button class="btn btn-primary"><x-icon name="search" :size="16" /> Search</button>
    </form>

    @if (mb_strlen($term) < 2)
        <x-card><x-empty-state title="Type at least two characters" icon="search">Tip: press Ctrl/⌘ + K anywhere to jump to search.</x-empty-state></x-card>
    @elseif (empty($results))
        <x-card><x-empty-state title="No matches for “{{ $term }}”" icon="search" /></x-card>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($results as $group => $items)
                <x-card :title="$group" :padded="false">
                    <ul class="divide-y divide-line">
                        @foreach ($items as $item)
                            <li class="px-4 py-2.5"><a href="{{ $item['url'] }}" class="font-medium hover:underline">{{ $item['title'] }}</a><p class="text-xs text-muted">{{ $item['meta'] }}</p></li>
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
