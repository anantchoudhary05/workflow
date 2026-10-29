@extends('layouts.app')
@section('title', 'Users')
@section('content')
    <x-page-header title="Users" subtitle="Team accounts. There is no public sign-up; administrators create accounts here.">
        <x-slot:actions><a class="btn btn-primary" href="{{ route('users.create') }}"><x-icon name="plus" :size="16" /> New user</a></x-slot:actions>
    </x-page-header>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" class="input w-64" placeholder="Name or email" aria-label="Search users">
        <select name="role" class="input w-48" data-autosubmit aria-label="Role"><option value="">All roles</option>@foreach (App\Enums\Role::options() as $value => $label)<option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>@endforeach</select>
        <button class="btn">Search</button>
    </form>
    <x-card :padded="false">
        @if ($users->isEmpty())
            <x-empty-state title="No users found" icon="users" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Capacity</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="font-medium">{{ $user->name }}@if ($user->job_title)<p class="text-xs font-normal text-muted">{{ $user->job_title }}</p>@endif</td>
                                <td>{{ $user->email }}</td>
                                <td><x-badge :enum="$user->role" /></td>
                                <td>{{ $user->weekly_capacity_hours !== null ? (float) $user->weekly_capacity_hours.' h/week' : '—' }}</td>
                                <td><x-badge :tone="$user->is_active ? 'success' : 'neutral'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                <td class="text-right"><a class="btn btn-ghost btn-sm" href="{{ route('users.edit', $user) }}"><x-icon name="edit" :size="14" /> Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <div class="mt-4">{{ $users->links() }}</div>
@endsection
