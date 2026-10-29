<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    <script>try { if (localStorage.getItem('workflow.sidebar') === 'collapsed') document.documentElement.dataset.sidebar = 'collapsed'; } catch (e) {}</script>
    @include('partials.assets')
</head>
<body class="min-h-screen font-sans antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-surface focus:p-2">Skip to content</a>
<div class="flex min-h-screen">
    @include('partials.sidebar')

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="no-print sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-line bg-surface/90 px-4 backdrop-blur">
            <button type="button" class="btn btn-ghost btn-sm lg:hidden" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation">
                <x-icon name="menu" />
            </button>
            <form action="{{ route('search') }}" method="GET" role="search" class="relative max-w-md flex-1">
                <label for="global-search" class="sr-only">Search</label>
                <x-icon name="search" :size="16" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-faint" />
                <input id="global-search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Search projects, tasks, clients…" class="input pl-8 pr-14">
                <kbd class="pointer-events-none absolute right-2 top-1/2 hidden -translate-y-1/2 rounded border border-line px-1.5 text-[11px] text-faint sm:block">Ctrl K</kbd>
            </form>
            <div class="ml-auto flex items-center gap-1">
                <a href="{{ route('notifications.index') }}" class="btn btn-ghost btn-sm relative" aria-label="Notifications{{ $unreadCount ? " ($unreadCount unread)" : '' }}">
                    <x-icon name="bell" />
                    @if ($unreadCount)
                        <span class="absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-danger px-1 text-center text-[10px] font-semibold leading-4 text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                    @endif
                </a>
                <div class="relative" data-dropdown>
                    <button type="button" class="btn btn-ghost btn-sm" data-dropdown-trigger aria-haspopup="true" aria-expanded="false">
                        <span class="grid size-7 place-items-center rounded-full bg-brand-soft text-xs font-semibold text-brand">{{ auth()->user()->initials() }}</span>
                        <span class="hidden text-left leading-tight sm:block"><span class="block text-sm">{{ auth()->user()->name }}</span><span class="block text-[11px] text-muted">{{ auth()->user()->role->label() }}</span></span>
                        <x-icon name="chevron-down" :size="14" />
                    </button>
                    <div class="dropdown-menu" data-dropdown-menu hidden>
                        <a href="{{ route('my-work') }}">My work</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="mx-auto w-full max-w-[1400px] flex-1 p-4 sm:p-6">
            @if ($errors->has('workflow'))
                <div class="alert tone-danger mb-4 flex items-start gap-2" role="alert"><x-icon name="alert" class="mt-0.5" /><span>{{ $errors->first('workflow') }}</span></div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

<div id="toasts" class="no-print fixed bottom-4 right-4 z-50 flex flex-col gap-2" aria-live="polite">
    @if (session('status'))<div class="toast toast-success" role="status" data-toast><x-icon name="check-circle" :size="16" class="mt-0.5" /><span>{{ session('status') }}</span></div>@endif
</div>

<dialog id="confirm-dialog" class="modal max-w-sm">
    <form method="dialog" class="p-5">
        <p class="font-medium" data-confirm-message>Are you sure?</p>
        <div class="mt-5 flex justify-end gap-2">
            <button value="cancel" class="btn">Cancel</button>
            <button value="ok" class="btn btn-primary">Confirm</button>
        </div>
    </form>
</dialog>
</body>
</html>
