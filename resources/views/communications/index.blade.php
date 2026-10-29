@extends('layouts.app')
@section('title', 'Communications')
@section('content')
    <x-page-header title="Communications" subtitle="Calls, emails, chats and visits with clients — the record of what was said and agreed.">
        <x-slot:actions>
            @if ($canRecord)
                <button type="button" class="btn btn-primary" data-dialog-open="log-communication"><x-icon name="plus" :size="16" /> Log communication</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="client" class="input w-56" data-autosubmit aria-label="Client"><option value="">All clients</option>@foreach ($clients as $id => $name)<option value="{{ $id }}" @selected(request('client') == $id)>{{ $name }}</option>@endforeach</select>
        <select name="project" class="input w-64" data-autosubmit aria-label="Project"><option value="">All projects</option>@foreach ($projects as $id => $label)<option value="{{ $id }}" @selected(request('project') == $id)>{{ $label }}</option>@endforeach</select>
        <select name="type" class="input w-44" data-autosubmit aria-label="Type"><option value="">All types</option>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach</select>
        @if (request()->hasAny(['client', 'project', 'type']))<a href="{{ route('communications.index') }}" class="btn btn-ghost">Clear</a>@endif
    </form>

    <x-card :padded="false">
        <ul class="divide-y divide-line">
            @forelse ($communications as $communication)
                <li class="flex gap-4 px-4 py-3">
                    <span class="mt-0.5 shrink-0 rounded-full bg-subtle p-2 text-muted"><x-icon name="message" :size="16" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium">{{ $communication->subject }}</p>
                            <x-badge>{{ $communication->type->label() }} · {{ ucfirst($communication->direction) }}</x-badge>
                        </div>
                        <p class="text-xs text-muted">
                            <a class="hover:underline" href="{{ route('clients.show', $communication->client_id) }}">{{ $communication->client->name }}</a>
                            @if ($communication->project) · <a class="hover:underline" href="{{ route('projects.show', $communication->project) }}">{{ $communication->project->code }}</a>@endif
                            · {{ $communication->occurred_at->format('d M Y H:i') }} · {{ $communication->owner?->name }}
                        </p>
                        @if ($communication->summary)<p class="mt-1 whitespace-pre-line">{{ $communication->summary }}</p>@endif
                        @if ($communication->outcome || $communication->next_action)
                            <dl class="mt-1 flex flex-wrap gap-x-6 text-xs">
                                @if ($communication->outcome)<div><dt class="inline text-muted">Outcome:</dt> <dd class="inline">{{ $communication->outcome }}</dd></div>@endif
                                @if ($communication->next_action)<div><dt class="inline text-muted">Next:</dt> <dd class="inline">{{ $communication->next_action }}</dd></div>@endif
                            </dl>
                        @endif
                    </div>
                </li>
            @empty
                <li><x-empty-state title="No communications logged" icon="message">Log calls, emails and meetings so the whole team knows what the client was told.</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>
    <div class="mt-4">{{ $communications->links() }}</div>

    @if ($canRecord)
        @include('communications.partials.form-dialog', ['clientId' => request('client'), 'projectId' => request('project')])
    @endif
@endsection
