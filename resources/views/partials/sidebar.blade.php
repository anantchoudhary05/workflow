@php
    use App\Models\Quotation;
    $groups = [
        null => [
            ['Dashboard', 'dashboard', 'dashboard', 'dashboard', true],
            ['My work', 'my-work', 'my-work', 'inbox', true],
        ],
        'Delivery' => [
            ['Projects', 'projects.index', 'projects.*', 'folder', true],
            ['Tasks', 'tasks.index', ['tasks.index', 'tasks.create', 'tasks.show', 'tasks.edit'], 'check-square', true],
            ['Task board', 'tasks.board', 'tasks.board', 'kanban', true],
            ['Change requests', 'change-requests.index', 'change-requests.*', 'git-pull-request', true],
            ['QA & defects', 'qa.index', ['qa.*', 'defects.*'], 'bug', true],
            ['Approvals', 'approvals.index', 'approvals.*', 'badge-check', true],
        ],
        'Clients' => [
            ['Clients', 'clients.index', 'clients.*', 'building', true],
            ['Meetings', 'meetings.index', 'meetings.*', 'calendar', true],
            ['Communications', 'communications.index', 'communications.*', 'message', true],
            ['Waiting on client', 'client-actions.index', 'client-actions.*', 'hourglass', true],
            ['Quotations', 'quotations.index', 'quotations.*', 'file-text', auth()->user()->can('viewAny', Quotation::class)],
        ],
        'Insights' => [
            ['Team workload', 'team.index', 'team.*', 'users', auth()->user()->can('view-team')],
            ['Reports', 'reports.index', 'reports.*', 'chart', auth()->user()->can('view-reports')],
            ['Activity log', 'activity.index', 'activity.*', 'activity', auth()->user()->can('view-activity')],
        ],
        'Administration' => [
            ['Users', 'users.index', 'users.*', 'shield', auth()->user()->can('manage')],
        ],
    ];
@endphp
<aside id="sidebar" data-open="false"
       class="no-print fixed inset-y-0 left-0 z-30 flex w-60 shrink-0 -translate-x-full flex-col overflow-hidden bg-[var(--c-sidebar)] bg-[radial-gradient(500px_220px_at_0%_0%,rgb(99_102_241/.22),transparent)] transition-[transform,width] duration-200 data-[open=true]:translate-x-0 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
    <a href="{{ route('dashboard') }}" class="flex h-16 shrink-0 items-center border-b border-white/5 px-4" aria-label="{{ config('app.name') }} — dashboard">
        <x-logo :size="32" />
    </a>
    <nav aria-label="Main" class="sidebar-scroll min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain px-2 py-3">
        @foreach ($groups as $group => $links)
            @php($links = array_filter($links, fn ($l) => $l[4]))
            @continue(! $links)
            @if ($group)<p class="nav-group">{{ $group }}</p>@endif
            @foreach ($links as [$label, $route, $pattern, $icon])
                <a href="{{ route($route) }}" class="nav-link" title="{{ $label }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                    <x-icon :name="$icon" :size="17" /> <span class="nav-text">{{ $label }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>
    <div class="hidden shrink-0 border-t border-white/5 px-2 py-2 lg:block">
        <button type="button" class="nav-link sidebar-toggle w-full" data-sidebar-collapse aria-pressed="false" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <x-icon name="chevron-left" :size="17" class="transition-transform" /> <span class="nav-text">Collapse</span>
        </button>
    </div>
</aside>
<div class="fixed inset-0 z-20 bg-black/40 lg:hidden" data-sidebar-toggle data-sidebar-overlay hidden></div>
