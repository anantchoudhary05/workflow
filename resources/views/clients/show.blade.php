@extends('layouts.app')
@section('title', $client->name)
@section('content')
    <x-page-header :title="$client->name" :back="route('clients.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted">
                <x-badge :enum="$client->status" /> <span>{{ $client->code }}</span>
                @if ($client->company_name)<span>· {{ $client->company_name }}</span>@endif
                @if ($client->owner)<span>· Owner {{ $client->owner->name }}</span>@endif
                @if ($createdBy)<span data-created-by>· Created by {{ $createdBy }}</span>@endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $client)
                <a href="{{ route('clients.edit', $client) }}" class="btn"><x-icon name="edit" :size="16" /> Edit</a>
                @if ($client->status !== App\Enums\ClientStatus::Archived)
                    <form method="POST" action="{{ route('clients.archive', $client) }}" data-confirm="Archive {{ $client->name }}? It will be hidden from active lists.">@csrf<button class="btn">Archive</button></form>
                @endif
            @endcan
            @can('create', App\Models\Project::class)
                <a href="{{ route('projects.create', ['client' => $client->id]) }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New project</a>
            @endcan
            @include('partials.delete-record', ['id' => 'delete-client', 'action' => route('clients.destroy', $client), 'label' => 'client '.$client->name,
                'note' => $projects->isNotEmpty() ? 'This client still has projects. Delete or move them first; the delete will be refused until then.' : null])
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="flex flex-col gap-6 xl:col-span-2">
            @include('partials.client-documents', ['client' => $client, 'documents' => $documents, 'canUpload' => $canUploadDocuments])

            <x-card title="Projects" :padded="false">
                @if ($projects->isEmpty())
                    <x-empty-state title="No projects yet" icon="folder" />
                @else
                    <table class="table">
                        <thead><tr><th>Project</th><th>Status</th><th>Manager</th><th>Due</th></tr></thead>
                        <tbody>
                        @foreach ($projects as $project)
                            <tr>
                                <td><a href="{{ route('projects.show', $project) }}" class="font-medium hover:underline">{{ $project->name }}</a><p class="text-xs text-muted">{{ $project->code }}</p></td>
                                <td><x-badge :enum="$project->status" /></td>
                                <td>{{ $project->manager?->name ?? '—' }}</td>
                                <td><x-date :value="$project->due_date" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-card title="Recent communications" :padded="false">
                <x-slot:actions><a href="{{ route('communications.index', ['client' => $client->id]) }}" class="link">All / log new</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($communications as $communication)
                        <li class="px-4 py-2.5">
                            <p class="font-medium">{{ $communication->subject }}</p>
                            <p class="text-xs text-muted">{{ $communication->type->label() }} · {{ ucfirst($communication->direction) }} · {{ $communication->occurred_at->format('d M Y H:i') }} · {{ $communication->owner?->name }}@if ($communication->project) · {{ $communication->project->code }}@endif</p>
                            @if ($communication->summary)<p class="mt-1 text-muted">{{ \Illuminate\Support\Str::limit($communication->summary, 200) }}</p>@endif
                        </li>
                    @empty
                        <li><x-empty-state title="No communications logged" icon="message" /></li>
                    @endforelse
                </ul>
            </x-card>

            <x-card title="Waiting on this client" :padded="false">
                <x-slot:actions><a href="{{ route('client-actions.index') }}" class="link">Waiting room</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @forelse ($actions as $action)
                        <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div><p class="font-medium">{{ $action->title }}</p><p class="text-xs text-muted">{{ $action->type->label() }}@if ($action->project) · {{ $action->project->code }}@endif · requested <x-date :value="$action->requested_at" /></p></div>
                            @if ($action->status === 'waiting')<span class="badge tone-warning">Waiting {{ $action->daysWaiting() }}d</span>@else<span class="badge tone-neutral">{{ ucfirst($action->status) }}</span>@endif
                        </li>
                    @empty
                        <li><x-empty-state title="Nothing requested from this client" icon="hourglass" /></li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="flex flex-col gap-6">
            <x-card title="Details">
                <dl class="grid gap-3 text-sm">
                    <div><dt class="text-muted">Primary contact</dt><dd>{{ $client->primary_contact ?? '—' }}</dd></div>
                    <div><dt class="text-muted">Email</dt><dd>@if ($client->email)<a class="link" href="mailto:{{ $client->email }}">{{ $client->email }}</a>@else — @endif</dd></div>
                    <div><dt class="text-muted">Phone</dt><dd>{{ $client->phone ?? '—' }}</dd></div>
                    <div><dt class="text-muted">Website</dt><dd>@if ($client->website)<a class="link" href="{{ $client->website }}" target="_blank" rel="noopener noreferrer">{{ $client->website }}</a>@else — @endif</dd></div>
                    <div><dt class="text-muted">Source</dt><dd>{{ $client->source ?? '—' }}</dd></div>
                    <div><dt class="text-muted">Last contacted</dt><dd><x-date :value="$client->last_contacted_at" empty="Never" /></dd></div>
                    @if ($client->billing_address)<div><dt class="text-muted">Billing address</dt><dd class="whitespace-pre-line">{{ $client->billing_address }}</dd></div>@endif
                    @if ($client->notes)<div><dt class="text-muted">Notes</dt><dd class="whitespace-pre-line">{{ $client->notes }}</dd></div>@endif
                </dl>
            </x-card>

            <x-card title="Contacts" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($client->contacts as $contact)
                        <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div class="min-w-0"><p class="font-medium">{{ $contact->name }}</p><p class="text-xs text-muted">{{ collect([$contact->role_title, $contact->email, $contact->phone])->filter()->implode(' · ') }}</p></div>
                            @can('update', $client)
                                <form method="POST" action="{{ route('clients.contacts.destroy', $contact) }}" data-confirm="Remove {{ $contact->name }}?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="Remove contact"><x-icon name="trash" :size="14" /></button></form>
                            @endcan
                        </li>
                    @empty
                        <li class="px-4 py-4 text-muted">No additional contacts.</li>
                    @endforelse
                </ul>
                @can('update', $client)
                    <form method="POST" action="{{ route('clients.contacts.store', $client) }}" class="grid gap-2 border-t border-line p-4">
                        @csrf
                        <x-field name="name" label="Add contact" placeholder="Name" required />
                        <div class="grid grid-cols-2 gap-2"><x-field name="email" type="email" placeholder="Email" /><x-field name="phone" placeholder="Phone" /></div>
                        <x-field name="role_title" placeholder="Role, e.g. Marketing lead" />
                        <button class="btn btn-sm justify-self-end">Add contact</button>
                    </form>
                @endcan
            </x-card>

            <x-card title="Meetings" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($meetings as $meeting)
                        <li class="px-4 py-2.5"><a href="{{ route('meetings.show', $meeting) }}" class="font-medium hover:underline">{{ $meeting->title }}</a><p class="text-xs text-muted">{{ $meeting->starts_at->format('d M Y H:i') }} · {{ ucfirst(str_replace('_', ' ', $meeting->status)) }}</p></li>
                    @empty
                        <li class="px-4 py-4 text-muted">No meetings recorded.</li>
                    @endforelse
                </ul>
            </x-card>

            @can('view-financials')
                <x-card title="Quotations" :padded="false">
                    <ul class="divide-y divide-line">
                        @forelse ($quotations as $quotation)
                            <li class="flex items-start justify-between gap-3 px-4 py-2.5">
                                <div><a href="{{ route('quotations.show', $quotation) }}" class="font-medium hover:underline">{{ $quotation->reference() }}</a><p class="text-xs text-muted">{{ $quotation->title }} · {{ App\Support\Money::format($quotation->total, $quotation->currency) }}</p></div>
                                <x-badge :enum="$quotation->status" />
                            </li>
                        @empty
                            <li class="px-4 py-4 text-muted">No quotations.</li>
                        @endforelse
                    </ul>
                </x-card>
            @endcan

            <x-card title="History" data-client-history>
                @if ($history->isEmpty())
                    <p class="text-sm text-muted">No history yet.</p>
                @else
                    <ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $history])</ul>
                @endif
            </x-card>
        </div>
    </div>
@endsection
