@extends('layouts.app')
@section('title', 'Quotations')
@section('content')
    <x-page-header title="Quotations" subtitle="Every quotation version across the projects you can access." />
    <nav class="mb-4 flex flex-wrap items-center gap-4 border-b border-line" aria-label="Quotation status">
        <a href="{{ route('quotations.index') }}" class="tab" @if (! request('status')) aria-current="page" @endif>All</a>
        @foreach ($statuses as $value => $label)
            <a href="{{ route('quotations.index', ['status' => $value]) }}" class="tab" @if (request('status') === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <x-card :padded="false">
        @if ($quotations->isEmpty())
            <x-empty-state title="No quotations found" icon="file-text">Create quotations from a project's Quotations tab.</x-empty-state>
        @else
            @include('quotations.partials.table', ['showProject' => true])
        @endif
    </x-card>
    <div class="mt-4">{{ $quotations->links() }}</div>
@endsection
