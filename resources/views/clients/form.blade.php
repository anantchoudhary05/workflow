@extends('layouts.app')
@section('title', $client->exists ? 'Edit '.$client->name : 'New client')
@section('content')
    <x-page-header :title="$client->exists ? 'Edit client' : 'New client'" :back="$client->exists ? route('clients.show', $client) : route('clients.index')" />

    @if (session('duplicates'))
        <div class="alert tone-warning mb-4">
            <p class="font-medium">Possible duplicate clients found:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach (session('duplicates') as $duplicate)
                    <li><a class="link" href="{{ route('clients.show', $duplicate['id']) }}">{{ $duplicate['code'] }} · {{ $duplicate['name'] }}</a>@if (! empty($duplicate['email'])) ({{ $duplicate['email'] }})@endif</li>
                @endforeach
            </ul>
            <p class="mt-1">Tick “Create anyway” below if this really is a different client.</p>
        </div>
    @endif

    <form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}">
        @csrf
        @if ($client->exists) @method('PUT') @endif
        <x-card>
            <div class="grid gap-4 md:grid-cols-2">
                <x-field name="name" label="Client name" :value="$client->name" required />
                <x-field name="company_name" label="Company" :value="$client->company_name" />
                <x-field name="primary_contact" label="Primary contact" :value="$client->primary_contact" />
                <x-field name="email" type="email" label="Email" :value="$client->email" />
                <x-field name="phone" label="Phone" :value="$client->phone" />
                <x-field name="website" type="url" label="Website" :value="$client->website" placeholder="https://" />
                <x-field name="status" type="select" label="Status" :options="App\Enums\ClientStatus::options()" :value="$client->status" required />
                <x-field name="owner_id" type="select" label="Account owner" :options="$users" :value="$client->owner_id" blank="No owner" />
                <x-field name="source" label="Source" :value="$client->source" placeholder="Referral, website, repeat…" />
                <x-field name="billing_address" type="textarea" label="Billing address" :value="$client->billing_address" class="md:col-span-2" />
                <x-field name="notes" type="textarea" label="Internal notes" :value="$client->notes" hint="Do not store passwords or credentials here." class="md:col-span-2" />
                @if (session('duplicates'))
                    <x-field name="confirm_duplicate" type="checkbox" label="Create anyway — this is a different client" class="md:col-span-2" />
                @endif
            </div>
        </x-card>
        <div class="mt-4 flex justify-end gap-2">
            <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="btn">Cancel</a>
            <button class="btn btn-primary">{{ $client->exists ? 'Save changes' : 'Create client' }}</button>
        </div>
    </form>
@endsection
