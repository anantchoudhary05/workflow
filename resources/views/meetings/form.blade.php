@extends('layouts.app')
@section('title', $meeting->exists ? 'Edit meeting' : 'New meeting')
@section('content')
    <x-page-header :title="$meeting->exists ? 'Edit meeting' : 'New meeting'" :back="$meeting->exists ? route('meetings.show', $meeting) : route('meetings.index')" />
    <form method="POST" action="{{ $meeting->exists ? route('meetings.update', $meeting) : route('meetings.store') }}" class="max-w-3xl">
        @csrf
        @if ($meeting->exists) @method('PUT') @endif
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="title" label="Title" :value="$meeting->title" required class="sm:col-span-2" />
                <x-field name="type" type="select" label="Type" :options="$types" :value="$meeting->type" required />
                <x-field name="status" type="select" label="Status" :options="['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled']" :value="$meeting->status" required />
                <x-field name="starts_at" type="datetime-local" label="Starts" :value="$meeting->starts_at" required />
                <x-field name="duration_minutes" type="number" label="Duration (minutes)" :value="$meeting->duration_minutes" min="5" max="600" required />
                <x-field name="project_id" type="select" label="Project" :options="$projects" :value="$meeting->project_id" blank="None" />
                <x-field name="client_id" type="select" label="Client" :options="$clients" :value="$meeting->client_id" blank="From project / none" />
                @php($selected = array_map('intval', old('participants', $meeting->exists ? $meeting->participants->pluck('id')->all() : [auth()->id()])))
                <fieldset class="sm:col-span-2">
                    <legend class="label">Team participants</legend>
                    <div class="grid gap-1 sm:grid-cols-3">
                        @foreach ($users as $id => $name)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="participants[]" value="{{ $id }}" @checked(in_array($id, $selected, true))> {{ $name }}</label>@endforeach
                    </div>
                </fieldset>
                <x-field name="external_participants" label="Client / external participants" :value="$meeting->external_participants" class="sm:col-span-2" />
                <x-field name="agenda" type="textarea" label="Agenda" :value="$meeting->agenda" class="sm:col-span-2" />
                <x-field name="notes" type="textarea" label="Notes" :value="$meeting->notes" :rows="6" class="sm:col-span-2" />
                <x-field name="follow_up_date" type="date" label="Follow-up date" :value="$meeting->follow_up_date" />
            </div>
        </x-card>
        <div class="mt-4 flex justify-end gap-2">
            <a class="btn" href="{{ $meeting->exists ? route('meetings.show', $meeting) : route('meetings.index') }}">Cancel</a>
            <button class="btn btn-primary">Save meeting</button>
        </div>
    </form>
@endsection
