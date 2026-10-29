{{-- Project workspace header + tab bar. Expects $project and $active (tab key). --}}
@php
    $tabs = array_filter([
        'overview' => ['Overview', route('projects.show', $project)],
        'requirements' => ['Requirements', route('projects.requirements', $project)],
        'scope' => ['Scope', route('projects.scope', $project)],
        'quotations' => auth()->user()->can('view-financials') ? ['Quotations', route('projects.quotations', $project)] : null,
        'milestones' => ['Milestones', route('projects.milestones', $project)],
        'tasks' => ['Tasks', route('projects.tasks', $project)],
        'changes' => ['Changes', route('projects.changes', $project)],
        'previews' => ['Previews', route('projects.previews', $project)],
        'qa' => ['QA', route('projects.qa', $project)],
        'files' => ['Files', route('projects.files', $project)],
        'release' => ['Release', route('projects.checklist', [$project, 'release'])],
        'handover' => ['Handover', route('projects.checklist', [$project, 'handover'])],
        'activity' => ['Activity', route('projects.activity', $project)],
    ]);
@endphp
<div class="mb-6">
    <a href="{{ route('projects.index') }}" class="mb-1 inline-flex items-center gap-1 text-xs text-muted hover:text-ink"><x-icon name="chevron-left" :size="14" /> Projects</a>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <h1 class="text-xl font-semibold tracking-tight">{{ $project->name }}</h1>
        <x-badge :enum="$project->status" />
    </div>
    <p class="mt-0.5 text-sm text-muted">{{ $project->code }} · {{ $project->client?->name }} · {{ $project->status->stage() }}</p>
    <nav class="no-print mt-4 flex gap-5 overflow-x-auto border-b border-line" aria-label="Project sections">
        @foreach ($tabs as $key => [$label, $url])
            <a href="{{ $url }}" class="tab" @if ($active === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</div>
