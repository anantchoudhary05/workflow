<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalOutcome;
use App\Enums\HandoverStatus;
use App\Models\ChecklistItem;
use App\Models\Project;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Release-readiness (QA) and handover checklists. */
class ChecklistController extends Controller
{
    public function index(Project $project, string $type): View
    {
        $this->authorize('view', $project);
        $items = $project->checklistItems()->where('type', $type)->with(['responsible:id,name', 'completer:id,name'])->orderBy('sort_order')->get();

        return view('projects.checklist', [
            'project' => $project,
            'type' => $type,
            'items' => $items,
            'canEdit' => $this->canEdit($project, $type),
            'users' => $this->userOptions(),
            'complete' => $items->isNotEmpty() && $items->every(fn ($i) => ! $i->is_mandatory || $i->isSatisfied()),
            'acceptances' => $type === 'handover' ? $project->approvals()->with('recorder:id,name')->get() : collect(),
            'handoverStatuses' => collect(HandoverStatus::cases())->reject(fn ($s) => $s === HandoverStatus::Closed)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'outcomes' => collect([ApprovalOutcome::Approved, ApprovalOutcome::ApprovedWithConditions, ApprovalOutcome::Rejected])->mapWithKeys(fn ($o) => [$o->value => $o->label()])->all(),
        ]);
    }

    public function generate(Project $project, string $type): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'manage-release']);
        if ($project->checklistItems()->where('type', $type)->exists()) {
            return back()->withErrors(['workflow' => 'This checklist already exists.']);
        }
        $order = 0;
        foreach (config("doc.checklists.$type") as $label => $mandatory) {
            $item = new ChecklistItem(['type' => $type, 'label' => $label, 'is_mandatory' => $mandatory, 'sort_order' => $order++]);
            $item->project_id = $project->id;
            $item->status = 'pending';
            $item->save();
        }
        Activity::log('checklist.generated', ucfirst($type).' checklist created', $project);

        return back()->with('status', 'Checklist created from the standard template.');
    }

    public function store(Request $request, Project $project, string $type): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'manage-release']);
        $data = $request->validate(['label' => ['required', 'string', 'max:255'], 'is_mandatory' => ['boolean']]);
        $item = new ChecklistItem($data + ['type' => $type, 'is_mandatory' => $request->boolean('is_mandatory'), 'sort_order' => (int) $project->checklistItems()->where('type', $type)->max('sort_order') + 1]);
        $item->project_id = $project->id;
        $item->status = 'pending';
        $item->save();

        return back()->with('status', 'Checklist item added.');
    }

    public function update(Request $request, ChecklistItem $item): RedirectResponse
    {
        abort_unless($this->canEdit($item->project, $item->type), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'done', 'not_applicable'])],
            'evidence' => ['nullable', 'string', 'max:255'],
            'responsible_id' => ['nullable', 'exists:users,id,deleted_at,NULL'],
        ]);
        if ($data['status'] === 'not_applicable' && $item->is_mandatory) {
            return back()->withErrors(['workflow' => 'Mandatory items cannot be marked not applicable.']);
        }
        $from = $item->status;
        $item->forceFill([
            'status' => $data['status'],
            'evidence' => $data['evidence'] ?? $item->evidence,
            'responsible_id' => $data['responsible_id'] ?? $item->responsible_id,
            'completed_by' => $data['status'] === 'pending' ? null : $request->user()->id,
            'completed_at' => $data['status'] === 'pending' ? null : now(),
        ])->save();
        if ($from !== $item->status) {
            Activity::log('checklist.item', ucfirst($item->type)." checklist: \"{$item->label}\" {$from} → {$item->status}", $item->project, ['item' => $item->label, 'evidence' => $item->evidence], true);
        }

        return back()->with('status', 'Checklist updated.');
    }

    private function canEdit(Project $project, string $type): bool
    {
        $user = auth()->user();
        if ($user->can('project-ability', [$project, 'manage-release'])) {
            return true;
        }

        return $type === 'release' && $user->can('project-ability', [$project, 'update-release-checklist']);
    }
}
