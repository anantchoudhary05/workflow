{{--
    "My to-dos" panel (private to the signed-in user).
    @param Collection $todos      the user's to-dos to list
    @param Project|null $project  when set, new to-dos are linked to this project
    @param array $projectOptions  optional project picker for the dashboard quick-add
--}}
@php($project = $project ?? null)
@php($projectOptions = $projectOptions ?? [])
<x-card title="My to-dos" icon="check-square" :padded="false" id="my-todos">
    <x-slot:actions>
        <span class="inline-flex items-center gap-1 text-xs text-muted"><x-icon name="lock" :size="12" /> Only you can see these</span>
        <a href="{{ route('todos.index', $project ? ['project' => $project->id] : []) }}" class="link">All</a>
    </x-slot:actions>
    @include('partials.todo-list')
</x-card>
