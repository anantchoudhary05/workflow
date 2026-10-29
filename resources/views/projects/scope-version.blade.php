@extends('layouts.app')
@section('title', 'Scope v'.$version->version.' · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'scope'])
    <div class="mb-4"><a href="{{ route('projects.scope', $project) }}" class="link">← All baselines</a></div>
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="'Scope version '.$version->version" class="lg:col-span-2" :padded="false">
            <table class="table">
                <thead><tr><th>Code</th><th>Requirement</th><th>Priority</th><th class="text-right">Hours</th></tr></thead>
                <tbody>
                    @foreach ($version->snapshot ?? [] as $item)
                        <tr>
                            <td class="font-mono text-xs">{{ $item['code'] }}</td>
                            <td><p class="font-medium">{{ $item['title'] }}</p>@if (! empty($item['acceptance_criteria']))<p class="whitespace-pre-line text-xs text-muted">{{ $item['acceptance_criteria'] }}</p>@endif</td>
                            <td>{{ App\Enums\Priority::tryFrom($item['priority'] ?? '')?->label() }}</td>
                            <td class="text-right tabular-nums">{{ $item['estimated_hours'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr><td colspan="3" class="font-medium">Total estimate</td><td class="text-right font-medium tabular-nums">{{ collect($version->snapshot)->sum('estimated_hours') }}</td></tr></tfoot>
            </table>
        </x-card>
        <x-card title="Approval">
            <dl class="grid gap-3 text-sm">
                <div><dt class="text-muted">Approved by</dt><dd>{{ $version->approved_by_name }}</dd></div>
                <div><dt class="text-muted">Evidence</dt><dd>{{ $version->approval_source }}</dd></div>
                <div><dt class="text-muted">Approved on</dt><dd>{{ $version->approved_at->format('d M Y') }}</dd></div>
                <div><dt class="text-muted">Recorded by</dt><dd>{{ $version->recorder?->name }} · {{ $version->created_at?->format('d M Y') }}</dd></div>
                <div><dt class="text-muted">Summary</dt><dd class="whitespace-pre-line">{{ $version->summary }}</dd></div>
            </dl>
        </x-card>
    </div>
@endsection
