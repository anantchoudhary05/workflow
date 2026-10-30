@extends('layouts.app')
@section('title', 'My work')
@section('content')
    <x-page-header title="My work" subtitle="Your open tasks, reviews waiting on you, and client follow-ups you own.">
        <x-slot:meta><span class="badge tone-info">{{ rtrim(rtrim(number_format($hoursThisWeek, 2), '0'), '.') }} h logged this week{{ $projectId ? ' on this project' : '' }}</span></x-slot:meta>
    </x-page-header>

    <form method="GET" action="{{ route('my-work') }}" class="mb-4 flex flex-wrap items-center gap-2" data-mywork-filter>
        <label for="mywork-project" class="text-sm text-muted">Project</label>
        <select id="mywork-project" name="project" class="input w-72" data-autosubmit>
            <option value="">All projects</option>
            @foreach ($projectOptions as $value => $label)<option value="{{ $value }}" @selected($projectId === $value)>{{ $label }}</option>@endforeach
        </select>
        <noscript><button class="btn">Filter</button></noscript>
        @if ($projectId)
            <a href="{{ route('my-work') }}" class="link text-sm">Show all projects</a>
            @isset($projectOptions[$projectId])<a href="{{ route('projects.show', $projectId) }}" class="link text-sm">Open project</a>@endisset
        @endif
    </form>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="flex flex-col gap-6 xl:col-span-2">
            @php($hasTasks = collect($groups)->contains(fn ($g) => $g->isNotEmpty()))
            @unless ($hasTasks)
                <x-card><x-empty-state :title="$projectId ? 'Nothing assigned to you in this project' : 'No open tasks assigned to you'" icon="check-square">New assignments will appear here.</x-empty-state></x-card>
            @endunless
            @foreach ($groups as $label => $tasks)
                @continue($tasks->isEmpty())
                <x-card :title="$label.' ('.$tasks->count().')'" :padded="false">
                    <ul class="divide-y divide-line">
                        @foreach ($tasks as $task)
                            <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-2.5" data-mywork-task>
                                <div class="min-w-0">
                                    <a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a>
                                    <p class="text-xs text-muted"><a class="link" href="{{ route('projects.show', $task->project_id) }}">{{ $task->project->code }} · {{ $task->project->name }}</a> · Due <x-date :value="$task->due_date" overdue empty="not set" />@if ($task->reviewer) · Reviewer {{ $task->reviewer->name }}@endif</p>
                                    <p class="mt-0.5 text-xs text-brand">{{ $task->nextAction() }}</p>
                                </div>
                                <div class="flex items-center gap-2"><x-badge :enum="$task->priority" /><x-badge :enum="$task->status" /></div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        </div>

        <div class="flex flex-col gap-6">
            <x-card title="Waiting for my review" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($reviewQueue as $task)
                        <li class="px-4 py-2.5">
                            <a href="{{ route('tasks.show', $task) }}" class="font-medium hover:underline">{{ $task->title }}</a>
                            <p class="text-xs text-muted">{{ $task->project->code }} · {{ $task->project->name }} · by {{ $task->assignee?->name ?? 'Unassigned' }}</p>
                        </li>
                    @empty
                        <li><x-empty-state title="No reviews waiting" icon="badge-check" /></li>
                    @endforelse
                </ul>
            </x-card>

            <x-card title="My client follow-ups" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($followUps as $action)
                        <li class="px-4 py-2.5">
                            <p class="font-medium">{{ $action->title }}</p>
                            <p class="text-xs text-muted">{{ $action->client->name }}@if ($action->project) · {{ $action->project->code }}@endif · waiting {{ $action->daysWaiting() }}d · next follow-up <x-date :value="$action->next_follow_up_at" overdue empty="not set" /></p>
                        </li>
                    @empty
                        <li><x-empty-state title="No follow-ups owned by you" icon="hourglass" /></li>
                    @endforelse
                </ul>
                @if ($followUps->isNotEmpty())
                    <div class="border-t border-line px-4 py-2 text-right"><a class="link text-sm" href="{{ route('client-actions.index') }}">Manage follow-ups</a></div>
                @endif
            </x-card>
        </div>
    </div>
@endsection
