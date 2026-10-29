@extends('layouts.app')
@section('title', 'Change requests')
@section('content')
    <x-page-header title="Change requests" subtitle="Client changes after scope approval. Open requests are shown unless you pick a status." />
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="status" class="input w-56" data-autosubmit aria-label="Status"><option value="">Open requests</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="classification" class="input w-48" data-autosubmit aria-label="Classification"><option value="">Any classification</option>@foreach ($classifications as $value => $label)<option value="{{ $value }}" @selected(request('classification') === $value)>{{ $label }}</option>@endforeach</select>
    </form>
    <x-card :padded="false">
        @if ($changes->isEmpty())
            <x-empty-state title="No change requests" icon="git-pull-request">Log client change requests from the project's Changes tab.</x-empty-state>
        @else
            @include('change-requests.partials.table')
        @endif
    </x-card>
    <div class="mt-4">{{ $changes->links() }}</div>
@endsection
