@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('content')
    <x-page-header :title="$user->exists ? 'Edit '.$user->name : 'New user'" :back="route('users.index')">
        <x-slot:actions>
            @if ($user->exists && ! $user->is(auth()->user()))
                @include('partials.delete-record', ['id' => 'delete-user', 'action' => route('users.destroy', $user), 'label' => $user->name,
                    'note' => 'They are signed out and can no longer sign in. Their tasks, comments and history stay and show them as a deleted user.'])
            @endif
        </x-slot:actions>
    </x-page-header>
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="max-w-2xl">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Name" :value="$user->name" required />
                <x-field name="email" type="email" label="Email" :value="$user->email" required />
                <x-field name="role" type="select" label="Role" :options="App\Enums\Role::options()" :value="$user->role" required />
                <x-field name="job_title" label="Job title" :value="$user->job_title" />
                <x-field name="skills" label="Skills" :value="$user->skills" placeholder="Laravel, React, QA…" />
                <x-field name="weekly_capacity_hours" type="number" label="Weekly capacity (hours)" :value="$user->weekly_capacity_hours" min="0" max="168" step="0.5" />
                <x-field name="password" type="password" label="Password" :hint="$user->exists ? 'Leave blank to keep the current password.' : null" :required="! $user->exists" autocomplete="new-password" />
                <x-field name="password_confirmation" type="password" label="Confirm password" :required="! $user->exists" autocomplete="new-password" />
                <x-field name="is_active" type="checkbox" label="Active (can sign in)" :value="$user->exists ? $user->is_active : true" />
            </div>
        </x-card>
        <x-card title="Which role to choose" icon="shield" class="mt-4" data-role-guide>
            <dl class="grid gap-2 text-sm">
                @foreach (App\Enums\Role::cases() as $role)
                    <div class="flex flex-wrap items-baseline gap-2"><dt class="w-40 shrink-0"><x-badge :enum="$role" /></dt><dd class="flex-1 text-muted">{{ $role->description() }}</dd></div>
                @endforeach
            </dl>
        </x-card>
        <div class="mt-4 flex justify-end gap-2"><a class="btn" href="{{ route('users.index') }}">Cancel</a><button class="btn btn-primary">Save user</button></div>
    </form>
    @if ($user->exists)
        <x-card title="History" class="mt-6 max-w-2xl" data-user-history>
            @if ($history->isEmpty())
                <p class="text-sm text-muted">No history yet.</p>
            @else
                <ul class="divide-y divide-line">@include('partials.activity-list', ['logs' => $history])</ul>
            @endif
        </x-card>
    @endif
@endsection
