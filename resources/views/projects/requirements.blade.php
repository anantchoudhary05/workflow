@extends('layouts.app')
@section('title', 'Requirements · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'requirements'])

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <form method="GET" class="flex flex-wrap gap-2">
            <select name="status" class="input w-52" data-autosubmit aria-label="Status"><option value="">All statuses</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
            <select name="category" class="input w-44" data-autosubmit aria-label="Category"><option value="">All categories</option>@foreach ($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select>
        </form>
        <div class="ml-auto flex gap-2">
            <a href="{{ route('projects.scope', $project) }}" class="btn">Scope baselines</a>
            <button type="button" class="btn btn-primary" data-dialog-open="new-requirement"><x-icon name="plus" :size="16" /> Add requirement</button>
        </div>
    </div>

    @if ($project->approvedScope)
        <p class="mb-4 text-sm text-muted"><x-icon name="lock" :size="14" class="inline" /> {{ count($baselined) }} requirement(s) are locked in scope version {{ $project->approvedScope->version }}. Changing them needs a <a class="link" href="{{ route('projects.changes', $project) }}">change request</a>.</p>
    @endif

    <x-card :padded="false">
        @if ($requirements->isEmpty())
            <x-empty-state title="No requirements recorded" icon="list">Capture what the client needs, with acceptance criteria, before estimating and quoting.</x-empty-state>
        @else
            <ul class="divide-y divide-line">
                @foreach ($requirements as $requirement)
                    @php($locked = in_array($requirement->id, $baselined, true))
                    <li id="req-{{ $requirement->id }}">
                        <details class="group">
                            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 px-4 py-3 hover:bg-subtle">
                                <span class="w-20 shrink-0 font-mono text-xs text-muted">{{ $requirement->code }}</span>
                                <span class="min-w-0 flex-1 font-medium">{{ $requirement->title }}@if ($locked) <x-icon name="lock" :size="13" class="inline text-faint" />@endif</span>
                                <span class="text-xs text-muted">{{ $requirement->category->label() }}</span>
                                <x-badge :enum="$requirement->priority" />
                                <x-badge :enum="$requirement->status" />
                            </summary>
                            <div class="grid gap-4 border-t border-line bg-subtle/50 px-4 py-4 md:grid-cols-3">
                                <div class="md:col-span-2 flex flex-col gap-3">
                                    @if ($requirement->description)<div><p class="label">Description</p><p class="whitespace-pre-line">{{ $requirement->description }}</p></div>@endif
                                    <div><p class="label">Acceptance criteria</p><p class="whitespace-pre-line">{{ $requirement->acceptance_criteria ?: 'Not defined yet.' }}</p></div>
                                    @if ($requirement->decision_notes)<div><p class="label">Decision notes</p><p class="whitespace-pre-line">{{ $requirement->decision_notes }}</p><p class="text-xs text-muted">{{ $requirement->decider?->name }} · {{ $requirement->decided_at?->format('d M Y') }}</p></div>@endif
                                    @if ($requirement->notes)<div><p class="label">Notes</p><p class="whitespace-pre-line">{{ $requirement->notes }}</p></div>@endif
                                </div>
                                <div class="flex flex-col gap-3">
                                    <dl class="grid grid-cols-2 gap-2 text-sm">
                                        <dt class="text-muted">Owner</dt><dd>{{ $requirement->owner?->name ?? '—' }}</dd>
                                        <dt class="text-muted">Estimate</dt><dd>{{ $requirement->estimated_hours ? $requirement->estimated_hours.' h' : '—' }}</dd>
                                        <dt class="text-muted">Module</dt><dd>{{ $requirement->module ?? '—' }}</dd>
                                        <dt class="text-muted">Requested by</dt><dd>{{ $requirement->requested_by ?? '—' }}</dd>
                                    </dl>
                                    <div class="flex flex-wrap gap-2">
                                        @unless ($locked)
                                            <button type="button" class="btn btn-sm" data-dialog-open="edit-req-{{ $requirement->id }}"><x-icon name="edit" :size="14" /> Edit</button>
                                        @endunless
                                        @can('manage')
                                            <button type="button" class="btn btn-sm" data-dialog-open="decide-req-{{ $requirement->id }}">Change status</button>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-dialog id="new-requirement" title="Add requirement">
        <form method="POST" action="{{ route('projects.requirements.store', $project) }}" class="grid gap-4 sm:grid-cols-2">
            @csrf
            @include('projects.partials.requirement-fields', ['requirement' => new App\Models\Requirement])
            <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Add requirement</button></div>
        </form>
    </x-dialog>

    @foreach ($requirements as $requirement)
        @unless (in_array($requirement->id, $baselined, true))
            <x-dialog :id="'edit-req-'.$requirement->id" :title="'Edit '.$requirement->code">
                <form method="POST" action="{{ route('requirements.update', $requirement) }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_requirement" value="{{ $requirement->id }}">
                    @include('projects.partials.requirement-fields')
                    <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
                </form>
            </x-dialog>
        @endunless
        @can('manage')
            <x-dialog :id="'decide-req-'.$requirement->id" :title="'Status of '.$requirement->code">
                <form method="POST" action="{{ route('requirements.decide', $requirement) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="status" type="select" label="Status" :options="$statuses" :value="$requirement->status" required />
                    <x-field name="decision_notes" type="textarea" label="Decision notes" hint="Required when rejecting, deferring or asking for clarification." />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save decision</button></div>
                </form>
            </x-dialog>
        @endcan
    @endforeach
@endsection
