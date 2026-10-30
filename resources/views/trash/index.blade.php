@extends('layouts.app')
@section('title', 'Trash')
@section('content')
    <x-page-header title="Trash" icon="trash"
        subtitle="Deleted projects, clients, tasks and users, with who deleted them and why. Nothing here is erased; restore puts a record back exactly as it was." />

    <nav class="mb-4 flex gap-5 overflow-x-auto border-b border-line" aria-label="Deleted record types">
        @foreach (['projects' => 'Projects', 'clients' => 'Clients', 'tasks' => 'Tasks', 'users' => 'Users'] as $key => $label)
            <a href="{{ route('trash.index', ['type' => $key]) }}" class="tab" @if ($type === $key) aria-current="page" @endif>
                {{ $label }} <span class="ml-1 text-xs text-muted tabular-nums">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padded="false">
        @if ($records->isEmpty())
            <x-empty-state title="Nothing deleted here" icon="trash" />
        @else
            <div class="overflow-x-auto">
                <table class="table" data-trash-table>
                    <thead><tr><th>{{ str($type)->singular()->title() }}</th><th>Deleted by</th><th>Deleted at</th><th>Reason</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($records as $record)
                        <tr data-trash-row="{{ $record->id }}">
                            <td class="font-medium">
                                @if ($type === 'projects') {{ $record->name }}<p class="text-xs font-normal text-muted">{{ $record->code }} · {{ $record->client?->name }}</p>
                                @elseif ($type === 'clients') {{ $record->name }}<p class="text-xs font-normal text-muted">{{ $record->code }}@if ($record->company_name) · {{ $record->company_name }}@endif</p>
                                @elseif ($type === 'tasks') {{ $record->title }}<p class="text-xs font-normal text-muted">{{ $record->code }} · {{ $record->project?->code }} {{ $record->project?->name }}@if ($record->project?->trashed()) <span class="text-danger">(project deleted)</span>@endif</p>
                                @else {{ $record->realName() }}<p class="text-xs font-normal text-muted">{{ $record->email }} · {{ $record->role->label() }}</p>
                                @endif
                            </td>
                            <td>{{ $record->deletedBy?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $record->deleted_at->format('d M Y H:i T') }}</td>
                            <td class="max-w-md whitespace-pre-line text-sm">{{ $record->deletion_reason ?? '—' }}</td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm" data-dialog-open="restore-{{ $type }}-{{ $record->id }}"><x-icon name="check" :size="14" /> Restore</button>
                                <x-dialog :id="'restore-'.$type.'-'.$record->id" title="Restore {{ $type === 'users' ? $record->realName() : ($record->name ?? $record->title) }}?" icon="check">
                                    <form method="POST" action="{{ route('trash.restore', [$type, $record->id]) }}" class="grid gap-4 text-left">
                                        @csrf
                                        <p class="text-sm text-muted">It comes back everywhere it was before{{ ['projects' => ', together with its tasks and other records', 'users' => ' and can sign in again (if the account is active)'][$type] ?? '' }}. The restore is written to the history.</p>
                                        <x-field name="note" type="textarea" label="Restore note (optional)" rows="2" maxlength="1000" placeholder="e.g. Deleted by mistake" />
                                        <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Restore</button></div>
                                    </form>
                                </x-dialog>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <div class="mt-4">{{ $records->links() }}</div>
    <p class="mt-4 text-xs text-muted">There is no permanent delete. Restore order: a client before its projects, a project before its tasks.</p>
@endsection
