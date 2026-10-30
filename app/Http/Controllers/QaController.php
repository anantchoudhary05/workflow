<?php

namespace App\Http\Controllers;

use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Enums\Priority;
use App\Enums\TestCaseStatus;
use App\Exceptions\WorkflowException;
use App\Models\Defect;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QaController extends Controller
{
    /** Cross-project QA overview: unresolved defects and pending retests. */
    public function overview(Request $request): View
    {
        $user = $request->user();

        return view('qa.index', [
            'defects' => Defect::visibleTo($user)->unresolved()->with(['project:id,code,name', 'assignee:id,name'])
                ->when($request->query('severity'), fn ($q, $s) => $q->where('severity', $s))
                ->orderByRaw("case severity when 'critical' then 0 when 'major' then 1 when 'minor' then 2 else 3 end")
                ->latest()->paginate(40)->withQueryString(),
            'retest' => Defect::visibleTo($user)->whereIn('status', [DefectStatus::Fixed, DefectStatus::ReadyForRetest])->with('project:id,code')->limit(20)->get(),
            'failing' => TestCase::visibleTo($user)->whereIn('status', [TestCaseStatus::Failed, TestCaseStatus::NeedsRetest])->with('project:id,code')->limit(20)->get(),
            'severities' => DefectSeverity::options(),
        ]);
    }

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('projects.qa', [
            'project' => $project,
            'testCases' => $project->testCases()->with(['requirement:id,code', 'tester:id,name'])->orderBy('code')->get(),
            'defects' => $project->defects()->with('assignee:id,name')->orderByRaw("case status when 'verified' then 2 when 'wont_fix' then 2 else 0 end")->latest()->get(),
            'requirements' => $project->requirements()->get()->mapWithKeys(fn ($r) => [$r->id => "{$r->code} {$r->title}"])->all(),
            'milestones' => $project->milestones()->pluck('name', 'id')->all(),
            'severities' => DefectSeverity::options(),
            'priorities' => Priority::options(),
            'testStatuses' => TestCaseStatus::options(),
            'users' => $this->userOptions(),
            'canQa' => auth()->user()->can('project-ability', [$project, 'manage-qa']),
        ]);
    }

    public function storeTestCase(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'manage-qa']);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'requirement_id' => ['nullable', Rule::exists('requirements', 'id')->where('project_id', $project->id)],
            'milestone_id' => ['nullable', Rule::exists('milestones', 'id')->where('project_id', $project->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'preconditions' => ['nullable', 'string', 'max:5000'],
            'steps' => ['required', 'string', 'max:10000'],
            'expected_result' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $tc = new TestCase($data);
        $tc->project_id = $project->id;
        $tc->code = TestCase::nextCode($project->id, 'TC');
        $tc->status = TestCaseStatus::NotRun;
        $tc->save();
        Activity::log('test_case.created', "Test case {$tc->code} added", $tc);

        return back()->with('status', "Test case {$tc->code} added.");
    }

    public function recordResult(Request $request, TestCase $testCase): RedirectResponse
    {
        $this->authorize('project-ability', [$testCase->project, 'manage-qa']);
        $data = $request->validate([
            'status' => ['required', Rule::enum(TestCaseStatus::class)],
            'actual_result' => [Rule::requiredIf($request->input('status') === 'failed'), 'nullable', 'string', 'max:5000'],
        ]);
        $from = $testCase->status;
        $testCase->forceFill($data + ['tester_id' => $request->user()->id, 'executed_at' => now()])->save();
        Activity::log('test_case.result', "{$testCase->code} {$from->label()} → {$testCase->status->label()}", $testCase, ['status' => ['from' => $from->value, 'to' => $testCase->status->value]], true);

        return back()->with('status', "Result recorded for {$testCase->code}.");
    }

    public function storeDefect(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'manage-qa']);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'test_case_id' => ['nullable', Rule::exists('test_cases', 'id')->where('project_id', $project->id)],
            'description' => ['nullable', 'string', 'max:10000'],
            'severity' => ['required', Rule::enum(DefectSeverity::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'steps' => ['nullable', 'string', 'max:10000'],
            'expected_behavior' => ['nullable', 'string', 'max:5000'],
            'actual_behavior' => ['nullable', 'string', 'max:5000'],
            'assignee_id' => ['nullable', 'exists:users,id,deleted_at,NULL'],
        ]);
        $defect = new Defect($data);
        $defect->project_id = $project->id;
        $defect->code = Defect::nextCode($project->id, 'DEF');
        $defect->status = DefectStatus::Open;
        $defect->reporter_id = $request->user()->id;
        $defect->save();
        if ($defect->assignee_id) {
            $project->members()->syncWithoutDetaching([$defect->assignee_id]);
        }
        Activity::log('defect.created', "{$defect->code} reported ({$defect->severity->label()}): {$defect->title}", $defect, [], true);
        $url = route('defects.show', $defect);
        Notifier::send($defect->assignee, "Defect assigned: {$defect->code} {$defect->title}", $url, 'warning');
        if ($defect->severity === DefectSeverity::Critical) {
            Notifier::send($project->manager, "Critical defect on {$project->code}: {$defect->title}", $url, 'danger');
        }

        return back()->with('status', "{$defect->code} reported.");
    }

    public function showDefect(Defect $defect): View
    {
        $this->authorizeProjectAccess($defect->project_id);
        $defect->load(['project:id,code,name', 'testCase:id,code,title', 'assignee:id,name', 'reporter:id,name', 'verifier:id,name', 'file']);
        $user = auth()->user();

        return view('qa.defect', [
            'defect' => $defect,
            'history' => $defect->project->activities()->where('subject_type', $defect->getMorphClass())->where('subject_id', $defect->id)->with('user:id,name')->get(),
            'allowed' => $this->allowedStatuses($defect, $user),
            'users' => $this->userOptions(),
        ]);
    }

    public function defectStatus(Request $request, Defect $defect): RedirectResponse
    {
        $this->authorizeProjectAccess($defect->project_id);
        $data = $request->validate([
            'status' => ['required', Rule::enum(DefectStatus::class)],
            'resolution_notes' => [Rule::requiredIf(in_array($request->input('status'), ['wont_fix', 'reopened'], true)), 'nullable', 'string', 'max:5000'],
            'assignee_id' => ['nullable', 'exists:users,id,deleted_at,NULL'],
        ]);
        $to = DefectStatus::from($data['status']);
        $user = $request->user();
        if (! in_array($to, $this->allowedStatuses($defect, $user), true)) {
            throw new WorkflowException("You cannot move {$defect->code} to {$to->label()}.");
        }
        $from = $defect->status;
        $defect->status = $to;
        if (filled($data['resolution_notes'] ?? null)) {
            $defect->resolution_notes = $data['resolution_notes'];
        }
        if ($user->can('manage-qa') && array_key_exists('assignee_id', $data) && $data['assignee_id']) {
            $defect->assignee_id = $data['assignee_id'];
        }
        $defect->resolved_at = in_array($to, [DefectStatus::Fixed, DefectStatus::ReadyForRetest, DefectStatus::Verified, DefectStatus::WontFix], true) ? ($defect->resolved_at ?? now()) : null;
        if ($to === DefectStatus::Verified) {
            $defect->verified_by = $user->id;
            $defect->verified_at = now();
        }
        $defect->save();
        Activity::log('defect.status', "{$defect->code} {$from->label()} → {$to->label()}", $defect,
            ['status' => ['from' => $from->value, 'to' => $to->value], 'notes' => $data['resolution_notes'] ?? null], true);
        if ($to === DefectStatus::ReadyForRetest) {
            Notifier::send($defect->reporter, "Ready for retest: {$defect->code} {$defect->title}", route('defects.show', $defect), 'review');
        }

        return back()->with('status', "{$defect->code} is now {$to->label()}.");
    }

    /** Developers move their own defects through the fix flow; QA verifies, reopens or closes. */
    private function allowedStatuses(Defect $defect, User $user): array
    {
        if ($user->can('manage-qa')) {
            return array_values(array_filter(DefectStatus::cases(), fn ($s) => $s !== $defect->status));
        }
        if ($defect->assignee_id === $user->id && $user->can('work-tasks')) {
            return array_values(array_filter([DefectStatus::InProgress, DefectStatus::Fixed, DefectStatus::ReadyForRetest], fn ($s) => $s !== $defect->status && in_array($defect->status, DefectStatus::unresolved(), true)));
        }

        return [];
    }
}
