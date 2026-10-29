@extends('layouts.app')
@section('title', 'QA · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'qa'])
    @php
        $passed = $testCases->where('status', App\Enums\TestCaseStatus::Passed)->count();
        $applicable = $testCases->where('status', '!=', App\Enums\TestCaseStatus::NotApplicable)->count();
        $openDefects = $defects->filter(fn ($d) => in_array($d->status, App\Enums\DefectStatus::unresolved(), true));
    @endphp
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Test cases passed" :value="$passed.' / '.$applicable" tone="success" icon="check-circle" />
        <x-stat label="Open defects" :value="$openDefects->count()" tone="warning" icon="bug" />
        <x-stat label="Open critical" :value="$openDefects->where('severity', App\Enums\DefectSeverity::Critical)->count()" tone="danger" icon="alert-triangle" />
    </div>

    <x-card title="Test cases" :padded="false">
        <x-slot:actions>@if ($canQa)<button type="button" class="btn btn-sm" data-dialog-open="new-test-case"><x-icon name="plus" :size="14" /> Test case</button>@endif</x-slot:actions>
        @if ($testCases->isEmpty())
            <x-empty-state title="No test cases" icon="check-square">Write test cases against the approved requirements.</x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Code</th><th>Title</th><th>Requirement</th><th>Status</th><th>Last run</th>@if ($canQa)<th></th>@endif</tr></thead>
                    <tbody>
                        @foreach ($testCases as $tc)
                            <tr>
                                <td class="font-mono text-xs">{{ $tc->code }}</td>
                                <td><details><summary class="cursor-pointer font-medium">{{ $tc->title }}</summary>
                                    <div class="mt-2 grid gap-2 text-sm">
                                        @if ($tc->preconditions)<p><span class="text-muted">Preconditions:</span> {{ $tc->preconditions }}</p>@endif
                                        <p class="whitespace-pre-line"><span class="text-muted">Steps:</span> {{ $tc->steps }}</p>
                                        <p><span class="text-muted">Expected:</span> {{ $tc->expected_result }}</p>
                                        @if ($tc->actual_result)<p class="text-danger"><span class="text-muted">Actual:</span> {{ $tc->actual_result }}</p>@endif
                                    </div></details></td>
                                <td class="text-muted">{{ $tc->requirement?->code ?? '—' }}</td>
                                <td><x-badge :enum="$tc->status" /></td>
                                <td class="text-xs text-muted">{{ $tc->executed_at?->format('d M Y') }} {{ $tc->tester?->name }}</td>
                                @if ($canQa)<td class="text-right"><button type="button" class="btn btn-sm" data-dialog-open="tc-result-{{ $tc->id }}">Record result</button></td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card title="Defects" :padded="false" class="mt-6">
        <x-slot:actions>@if ($canQa)<button type="button" class="btn btn-sm" data-dialog-open="new-defect"><x-icon name="plus" :size="14" /> Defect</button>@endif</x-slot:actions>
        @if ($defects->isEmpty())
            <x-empty-state title="No defects reported" icon="bug" />
        @else
            @include('qa.partials.defects-table', ['showProject' => false])
        @endif
    </x-card>

    @if ($canQa)
        <x-dialog id="new-test-case" title="Add test case">
            <form method="POST" action="{{ route('projects.test-cases.store', $project) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-field name="title" label="Title" required class="sm:col-span-2" />
                <x-field name="requirement_id" type="select" label="Requirement" :options="$requirements" blank="None" />
                <x-field name="milestone_id" type="select" label="Milestone" :options="$milestones" blank="None" />
                <x-field name="preconditions" type="textarea" label="Preconditions" class="sm:col-span-2" />
                <x-field name="steps" type="textarea" label="Steps" :rows="4" required class="sm:col-span-2" />
                <x-field name="expected_result" type="textarea" label="Expected result" required class="sm:col-span-2" />
                <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Add</button></div>
            </form>
        </x-dialog>
        <x-dialog id="new-defect" title="Report defect">
            <form method="POST" action="{{ route('projects.defects.store', $project) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-field name="title" label="Title" required class="sm:col-span-2" />
                <x-field name="severity" type="select" label="Severity" :options="$severities" required />
                <x-field name="priority" type="select" label="Priority" :options="$priorities" :value="App\Enums\Priority::Medium" required />
                <x-field name="test_case_id" type="select" label="Found by test case" :options="$testCases->mapWithKeys(fn ($t) => [$t->id => $t->code.' '.$t->title])->all()" blank="None" />
                <x-field name="assignee_id" type="select" label="Assign to" :options="$users" blank="Unassigned" />
                <x-field name="steps" type="textarea" label="Steps to reproduce" class="sm:col-span-2" />
                <x-field name="expected_behavior" type="textarea" label="Expected" />
                <x-field name="actual_behavior" type="textarea" label="Actual" />
                <x-field name="description" type="textarea" label="Notes" class="sm:col-span-2" />
                <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Report</button></div>
            </form>
        </x-dialog>
        @foreach ($testCases as $tc)
            <x-dialog :id="'tc-result-'.$tc->id" :title="$tc->code.' result'">
                <form method="POST" action="{{ route('test-cases.result', $tc) }}" class="flex flex-col gap-4">
                    @csrf
                    <x-field name="status" type="select" label="Result" :options="$testStatuses" :value="$tc->status" required />
                    <x-field name="actual_result" type="textarea" label="Actual result" hint="Required when the test failed." />
                    <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
                </form>
            </x-dialog>
        @endforeach
    @endif
@endsection
