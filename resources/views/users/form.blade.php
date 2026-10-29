@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('content')
    <x-page-header :title="$user->exists ? 'Edit '.$user->name : 'New user'" :back="route('users.index')" />
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
        <div class="mt-4 flex justify-end gap-2"><a class="btn" href="{{ route('users.index') }}">Cancel</a><button class="btn btn-primary">Save user</button></div>
    </form>
@endsection
