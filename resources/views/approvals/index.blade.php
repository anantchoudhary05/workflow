@extends('layouts.app')
@section('title', 'Approvals')
@section('content')
    <x-page-header title="Approvals" subtitle="Everything waiting for a client decision, and the evidence trail of recorded decisions." />
    @php
        $pending = [
            ['Quotations sent', $quotations, fn ($q) => route('quotations.show', $q), fn ($q) => $q->number.' · '.$q->title, fn ($q) => $q->sent_at],
            ['Change requests', $changes, fn ($c) => route('change-requests.show', $c), fn ($c) => $c->number.' · '.$c->title, fn ($c) => null],
            ['Previews awaiting feedback', $previews, fn ($p) => route('projects.previews', $p->project_id), fn ($p) => $p->title.' ('.$p->version_label.')', fn ($p) => $p->review_due_at],
            ['Milestones awaiting approval', $milestones, fn ($m) => route('projects.milestones', $m->project_id), fn ($m) => $m->name, fn ($m) => $m->due_date],
        ];
    @endphp
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($pending as [$heading, $list, $url, $label, $date])
            @if ($heading !== 'Quotations sent' || auth()->user()->can('view-financials'))
                <x-card :title="$heading.' ('.$list->count().')'" :padded="false">
                    <ul class="divide-y divide-line">
                        @forelse ($list as $row)
                            <li class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                                <a class="min-w-0 truncate hover:underline" href="{{ $url($row) }}"><span class="text-muted">{{ $row->project?->code }}</span> {{ $label($row) }}</a>
                                <x-date :value="$date($row)" :overdue="$heading !== 'Quotations sent'" empty="" />
                            </li>
                        @empty
                            <li class="px-4 py-4 text-muted">Nothing pending.</li>
                        @endforelse
                    </ul>
                </x-card>
            @endif
        @endforeach
    </div>

    <x-card title="Recently recorded decisions" :padded="false" class="mt-6">
        @if ($recent->isEmpty())
            <x-empty-state title="No decisions recorded yet" icon="badge-check" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Date</th><th>Project</th><th>Item</th><th>Outcome</th><th>Decided by</th><th>Evidence</th><th>Recorded by</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $approval)
                            <tr>
                                <td><x-date :value="$approval->decided_at" /></td>
                                <td class="text-muted">{{ $approval->project?->code }}</td>
                                <td>{{ class_basename($approval->approvable_type) }} · {{ $approval->version_label }}</td>
                                <td><x-badge :enum="$approval->outcome" /></td>
                                <td>{{ $approval->decided_by_name }}</td>
                                <td class="text-muted">{{ $approval->source_reference }}</td>
                                <td class="text-muted">{{ $approval->recorder?->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
