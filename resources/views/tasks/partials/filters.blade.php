{{-- Task filter bar. Expects $filters, $projects, $users, $statuses, $priorities; optional $lockProject. --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input w-56" placeholder="Search tasks" aria-label="Search tasks">
    @empty($lockProject)
        <select name="project" class="input w-56" data-autosubmit aria-label="Project"><option value="">All projects</option>@foreach ($projects as $id => $label)<option value="{{ $id }}" @selected(($filters['project'] ?? '') == $id)>{{ $label }}</option>@endforeach</select>
    @endempty
    <select name="assignee" class="input w-44" data-autosubmit aria-label="Assignee">
        <option value="">Anyone</option><option value="me" @selected(($filters['assignee'] ?? '') === 'me')>Me</option>
        @foreach ($users as $id => $name)<option value="{{ $id }}" @selected(($filters['assignee'] ?? '') == $id)>{{ $name }}</option>@endforeach
    </select>
    @isset($statuses)
        <select name="status" class="input w-44" data-autosubmit aria-label="Status"><option value="">Open tasks</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
    @endisset
    <select name="priority" class="input w-36" data-autosubmit aria-label="Priority"><option value="">Any priority</option>@foreach ($priorities as $value => $label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
    <select name="due" class="input w-36" data-autosubmit aria-label="Due"><option value="">Any due date</option>@foreach (['today' => 'Due today', 'week' => 'Next 7 days', 'overdue' => 'Overdue'] as $value => $label)<option value="{{ $value }}" @selected(($filters['due'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
    <button class="btn">Filter</button>
    @if (collect($filters)->except(empty($lockProject) ? [] : ['project'])->filter()->isNotEmpty())<a href="{{ url()->current() }}" class="btn btn-ghost">Clear</a>@endif
</form>
