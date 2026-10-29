@extends('layouts.app')
@section('title', 'Quotations · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'quotations'])
    <div class="mb-4 flex items-center justify-between gap-2">
        <p class="text-sm text-muted">@if ($project->approved_quotation_id) An approved quotation is linked to this project. @else No quotation has been approved yet. @endif</p>
        @can('create', App\Models\Quotation::class)
            <a href="{{ route('projects.quotations.create', $project) }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> New quotation</a>
        @endcan
    </div>
    <x-card :padded="false">
        @if ($quotations->isEmpty())
            <x-empty-state title="No quotations yet" icon="file-text">Quote against an approved scope baseline so the price matches what was agreed.</x-empty-state>
        @else
            @include('quotations.partials.table', ['quotations' => $quotations, 'showProject' => false])
        @endif
    </x-card>
@endsection
