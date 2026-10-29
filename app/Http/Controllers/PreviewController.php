<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalOutcome;
use App\Enums\ClientActionType;
use App\Enums\PreviewStatus;
use App\Exceptions\WorkflowException;
use App\Models\Approval;
use App\Models\ClientAction;
use App\Models\Preview;
use App\Models\Project;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PreviewController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('projects.previews', [
            'project' => $project,
            'previews' => $project->previews()->with(['milestone:id,name', 'sharer:id,name', 'approvals'])->latest()->get(),
            'milestones' => $project->milestones()->pluck('name', 'id')->all(),
            'statuses' => PreviewStatus::options(),
            'outcomes' => ApprovalOutcome::options(),
            'canManage' => auth()->user()->can('project-ability', [$project, 'manage-previews']),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'manage-previews']);
        $preview = new Preview($this->validated($request, $project));
        $preview->project_id = $project->id;
        $preview->status = PreviewStatus::Draft;
        $preview->save();
        Activity::log('preview.created', "Preview \"{$preview->title}\" ({$preview->version_label}) added", $preview);

        return back()->with('status', 'Preview added.');
    }

    public function update(Request $request, Preview $preview): RedirectResponse
    {
        $project = $preview->project;
        $this->authorize('project-ability', [$project, 'manage-previews']);
        $data = $this->validated($request, $project);
        $status = PreviewStatus::from($request->validate(['status' => ['required', Rule::enum(PreviewStatus::class)]])['status']);
        if ($status === PreviewStatus::Approved && ! $preview->approvals()->exists()) {
            throw new WorkflowException('Record the client feedback/approval with its source instead of setting Approved directly.');
        }
        DB::transaction(function () use ($preview, $data, $status, $request, $project) {
            $from = $preview->status;
            $preview->fill($data);
            $preview->status = $status;
            if (in_array($status, PreviewStatus::waiting(), true) && ! in_array($from, PreviewStatus::waiting(), true)) {
                $preview->shared_by ??= $request->user()->id;
                $preview->shared_at ??= today();
                ClientAction::forceCreate([
                    'client_id' => $project->client_id, 'project_id' => $project->id, 'type' => ClientActionType::PreviewFeedback,
                    'title' => Str::limit("Feedback on preview {$preview->title} ({$preview->version_label})", 250), 'related_type' => $preview->getMorphClass(), 'related_id' => $preview->id,
                    'requested_at' => today(), 'next_follow_up_at' => $preview->review_due_at ?? today()->addDays((int) config('doc.reminders.client_follow_up_days')),
                    'owner_id' => $request->user()->id, 'status' => 'waiting',
                ]);
            }
            $preview->save();
            Activity::log('preview.updated', "Preview \"{$preview->title}\" is {$status->label()}", $preview, $from !== $status ? ['status' => ['from' => $from->value, 'to' => $status->value]] : []);
        });

        return back()->with('status', 'Preview updated.');
    }

    /** Records client feedback/approval with the source so approvals are evidence-backed (Rule 2). */
    public function feedback(Request $request, Preview $preview): RedirectResponse
    {
        $this->authorize('project-ability', [$preview->project, 'manage-previews']);
        $data = $request->validate([
            'outcome' => ['required', Rule::enum(ApprovalOutcome::class)],
            'decided_by_name' => ['required', 'string', 'max:255'],
            'source_reference' => ['required', 'string', 'max:255'],
            'decided_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $outcome = ApprovalOutcome::from($data['outcome']);
        DB::transaction(function () use ($preview, $data, $outcome, $request) {
            Approval::forceCreate($data + [
                'project_id' => $preview->project_id, 'approvable_type' => $preview->getMorphClass(), 'approvable_id' => $preview->id,
                'version_label' => $preview->version_label, 'recorded_by' => $request->user()->id,
            ]);
            $preview->forceFill([
                'status' => $outcome->isPositive() ? PreviewStatus::Approved : PreviewStatus::ChangesRequired,
                'feedback_summary' => $data['notes'] ?? $preview->feedback_summary,
            ])->save();
            ClientAction::where('related_type', $preview->getMorphClass())->where('related_id', $preview->id)->where('status', 'waiting')
                ->update(['status' => 'received', 'resolved_at' => now(), 'resolution' => $outcome->label()]);
            Activity::log('preview.feedback', "Client feedback on \"{$preview->title}\": {$outcome->label()} ({$data['decided_by_name']})", $preview, ['source' => $data['source_reference']], true);
        });

        return back()->with('status', 'Client feedback recorded.');
    }

    private function validated(Request $request, Project $project): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:500'],
            'version_label' => ['required', 'string', 'max:60'],
            'milestone_id' => ['nullable', Rule::exists('milestones', 'id')->where('project_id', $project->id)],
            'review_due_at' => ['nullable', 'date'],
            'feedback_summary' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
