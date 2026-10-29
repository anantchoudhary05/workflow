@extends('layouts.app')
@section('title', $meeting->title)
@section('content')
    <x-page-header :title="$meeting->title" :back="route('meetings.index')">
        <x-slot:meta>
            <p class="mt-1 text-sm text-muted">{{ $meeting->starts_at->format('D d M Y, H:i') }} · {{ $meeting->duration_minutes }} min · {{ $meeting->type->label() }} · {{ ucfirst($meeting->status) }}</p>
        </x-slot:meta>
        <x-slot:actions>@can('record-communication')<a class="btn" href="{{ route('meetings.edit', $meeting) }}"><x-icon name="edit" :size="16" /> Edit</a>@endcan</x-slot:actions>
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            @if ($meeting->agenda)<x-card title="Agenda"><p class="whitespace-pre-line">{{ $meeting->agenda }}</p></x-card>@endif
            <x-card title="Notes">@if ($meeting->notes)<p class="whitespace-pre-line">{{ $meeting->notes }}</p>@else<p class="text-muted">No notes yet.</p>@endif</x-card>
            <x-card title="Decisions" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($meeting->decisions as $decision)
                        <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="whitespace-pre-line">{{ $decision->decision }}</p>
                                <p class="text-xs text-muted">@if ($decision->decided_by)Decided by {{ $decision->decided_by }}@endif @if ($decision->owner) · Owner {{ $decision->owner->name }}@endif</p>
                            </div>
                            @if ($decision->task)
                                <a class="link inline-flex items-center gap-1 text-sm" href="{{ route('tasks.show', $decision->task) }}">Task <x-badge :enum="$decision->task->status" /></a>
                            @elseif ($meeting->project_id)
                                @can('manage')<form method="POST" action="{{ route('meetings.decisions.task', $decision) }}">@csrf<button class="btn btn-sm">Create task</button></form>@endcan
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-4 text-muted">No decisions recorded.</li>
                    @endforelse
                </ul>
                @can('record-communication')
                    <form method="POST" action="{{ route('meetings.decisions.store', $meeting) }}" class="grid gap-3 border-t border-line p-4 sm:grid-cols-2">
                        @csrf
                        <x-field name="decision" type="textarea" label="Record a decision" required class="sm:col-span-2" />
                        <x-field name="decided_by" label="Decided by" placeholder="Client name / team" />
                        <x-field name="owner_id" type="select" label="Owner" :options="$users" blank="None" />
                        <div class="flex justify-end sm:col-span-2"><button class="btn btn-primary">Add decision</button></div>
                    </form>
                @endcan
            </x-card>
        </div>
        <x-card title="Details">
            <dl class="grid gap-3 text-sm">
                @if ($meeting->project)<div><dt class="text-muted">Project</dt><dd><a class="link" href="{{ route('projects.show', $meeting->project) }}">{{ $meeting->project->code }} · {{ $meeting->project->name }}</a></dd></div>@endif
                @if ($meeting->client)<div><dt class="text-muted">Client</dt><dd><a class="link" href="{{ route('clients.show', $meeting->client) }}">{{ $meeting->client->name }}</a></dd></div>@endif
                <div><dt class="text-muted">Organizer</dt><dd>{{ $meeting->organizer?->name }}</dd></div>
                <div><dt class="text-muted">Team</dt><dd>{{ $meeting->participants->pluck('name')->implode(', ') ?: '—' }}</dd></div>
                @if ($meeting->external_participants)<div><dt class="text-muted">External</dt><dd>{{ $meeting->external_participants }}</dd></div>@endif
                <div><dt class="text-muted">Follow-up</dt><dd><x-date :value="$meeting->follow_up_date" /></dd></div>
            </dl>
        </x-card>
    </div>
@endsection
