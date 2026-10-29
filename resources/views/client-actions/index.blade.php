@extends('layouts.app')
@section('title', 'Waiting on client')
@section('content')
    <x-page-header title="Waiting on client" subtitle="Content, credentials, feedback and approvals the team needs from clients. Overdue follow-ups trigger reminders.">
        <x-slot:actions>
            @if ($canRecord)
                <button type="button" class="btn btn-primary" data-dialog-open="add-action"><x-icon name="plus" :size="16" /> Add item</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-4 border-b border-line">
        @foreach (['waiting' => 'Waiting', 'received' => 'Received', 'cancelled' => 'Cancelled'] as $value => $label)
            <a href="{{ route('client-actions.index', ['status' => $value] + request()->only('project')) }}" class="tab" @if ($status === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        <form method="GET" class="mb-2 ml-auto flex gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <select name="project" class="input w-64" data-autosubmit aria-label="Filter by project">
                <option value="">All projects</option>
                @foreach ($projects as $id => $label)<option value="{{ $id }}" @selected(request('project') == $id)>{{ $label }}</option>@endforeach
            </select>
        </form>
    </div>

    <x-card :padded="false">
        @if ($actions->isEmpty())
            <x-empty-state :title="$status === 'waiting' ? 'Nothing is waiting on clients' : 'No '.$status.' items'" icon="hourglass" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Item</th><th>Client / project</th><th>Owner</th><th>Requested</th>@if ($status === 'waiting')<th>Waiting</th><th>Next follow-up</th><th></th>@else<th>Closed</th><th>Resolution</th>@endif</tr></thead>
                    <tbody>
                    @foreach ($actions as $action)
                        <tr>
                            <td><p class="font-medium">{{ $action->title }}</p><p class="text-xs text-muted">{{ $action->type->label() }}</p></td>
                            <td>{{ $action->client->name }}@if ($action->project)<p class="text-xs text-muted"><a class="hover:underline" href="{{ route('projects.show', $action->project) }}">{{ $action->project->code }}</a></p>@endif</td>
                            <td>{{ $action->owner?->name ?? '—' }}</td>
                            <td><x-date :value="$action->requested_at" /></td>
                            @if ($status === 'waiting')
                                <td><span @class(['badge', 'tone-danger' => $action->daysWaiting() >= $followUpDays * 2, 'tone-warning' => $action->daysWaiting() >= $followUpDays && $action->daysWaiting() < $followUpDays * 2, 'tone-neutral' => $action->daysWaiting() < $followUpDays])>{{ $action->daysWaiting() }} days</span></td>
                                <td><x-date :value="$action->next_follow_up_at" overdue empty="not set" /></td>
                                <td class="text-right">
                                    @if ($canRecord)
                                        <div class="flex justify-end gap-1">
                                            <button type="button" class="btn btn-sm" data-dialog-open="follow-up-{{ $action->id }}">Follow up</button>
                                            <button type="button" class="btn btn-sm" data-dialog-open="resolve-{{ $action->id }}">Close</button>
                                        </div>
                                    @endif
                                </td>
                            @else
                                <td><x-date :value="$action->resolved_at" /></td>
                                <td class="text-muted">{{ $action->resolution }}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <div class="mt-4">{{ $actions->links() }}</div>

    @if ($canRecord && $status === 'waiting')
        @foreach ($actions as $action)
            <x-dialog :id="'follow-up-'.$action->id" :title="'Follow up: '.$action->title">
                <form method="POST" action="{{ route('client-actions.follow-up', $action) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="next_follow_up_at" type="date" label="Next follow-up" :value="today()->addDays($followUpDays)" required />
                    <x-field name="note" label="Note" placeholder="e.g. Called, promised by Friday" />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save follow-up</button></div>
                </form>
            </x-dialog>
            <x-dialog :id="'resolve-'.$action->id" :title="'Close: '.$action->title">
                <form method="POST" action="{{ route('client-actions.resolve', $action) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="status" type="select" label="Outcome" :options="['received' => 'Received from client', 'cancelled' => 'No longer needed']" required />
                    <x-field name="resolution" label="Resolution" placeholder="What was received, or why it is no longer needed" required />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Close item</button></div>
                </form>
            </x-dialog>
        @endforeach
    @endif

    @if ($canRecord)
        <x-dialog id="add-action" title="Add item waiting on client">
            <form method="POST" action="{{ route('client-actions.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-field name="project_id" type="select" label="Project" :options="$projects" blank="Select project" required class="sm:col-span-2" />
                <x-field name="title" label="What are we waiting for?" required class="sm:col-span-2" placeholder="e.g. Final homepage copy" />
                <x-field name="type" type="select" label="Type" :options="$types" required />
                <x-field name="owner_id" type="select" label="Owner" :options="$users" :value="auth()->id()" />
                <x-field name="requested_at" type="date" label="Requested on" :value="today()" required />
                <x-field name="next_follow_up_at" type="date" label="Next follow-up" :hint="'Defaults to '.$followUpDays.' days after request.'" />
                <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Add item</button></div>
            </form>
        </x-dialog>
    @endif
@endsection
