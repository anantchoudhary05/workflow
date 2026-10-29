@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
    <x-page-header title="Notifications">
        <x-slot:actions>
            @if ($notifications->contains(fn ($n) => $n->read_at === null))
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn">Mark all as read</button></form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <ul class="divide-y divide-line">
            @forelse ($notifications as $notification)
                <li @class(['flex items-start gap-3 px-4 py-3', 'bg-brand-soft' => ! $notification->read_at])>
                    <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-brand' => ! $notification->read_at, 'bg-line' => $notification->read_at])></span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('notifications.open', $notification->id) }}" class="font-medium hover:underline">{{ $notification->data['title'] ?? 'Notification' }}</a>
                        <p class="text-xs text-muted">{{ str_replace('-', ' ', $notification->data['kind'] ?? 'update') }} · {{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </li>
            @empty
                <li><x-empty-state title="You have no notifications" icon="bell">Assignments, review requests and reminders will appear here.</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>
    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
