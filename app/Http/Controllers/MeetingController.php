<?php

namespace App\Http\Controllers;

use App\Enums\MeetingType;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Models\Project;
use App\Models\Task;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeetingController extends Controller
{
    public function index(Request $request): View
    {
        $month = $this->monthFromQuery($request->query('month'));
        $base = Meeting::query()->visibleTo($request->user())->with(['client:id,name', 'project:id,code']);

        return view('meetings.index', [
            'month' => $month,
            'calendar' => (clone $base)->whereBetween('starts_at', [$month->startOfMonth(), $month->endOfMonth()])->orderBy('starts_at')->get()
                ->groupBy(fn ($m) => $m->starts_at->toDateString()),
            'upcoming' => (clone $base)->where('starts_at', '>=', now()->startOfDay())->where('status', 'scheduled')->orderBy('starts_at')->limit(20)->get(),
            'recent' => (clone $base)->where('starts_at', '<', now()->startOfDay())->latest('starts_at')->limit(20)->get(),
            'canRecord' => $request->user()->can('record-communication'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('record-communication');

        return view('meetings.form', $this->formData(new Meeting([
            'project_id' => $request->integer('project') ?: null,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'duration_minutes' => 30,
            'status' => 'scheduled',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('record-communication');
        $data = $this->validated($request);
        $meeting = DB::transaction(function () use ($data, $request) {
            $meeting = new Meeting(collect($data)->except('participants')->all());
            $meeting->organizer_id = $request->user()->id;
            $meeting->save();
            $meeting->participants()->sync($data['participants'] ?? []);
            Activity::log('meeting.created', "Meeting \"{$meeting->title}\" scheduled", $meeting);

            return $meeting;
        });

        return redirect()->route('meetings.show', $meeting)->with('status', 'Meeting saved.');
    }

    public function show(Meeting $meeting): View
    {
        $this->authorizeProjectAccess($meeting->project_id);
        $meeting->load(['client:id,name', 'project:id,code,name', 'organizer:id,name', 'participants:id,name', 'decisions.owner:id,name', 'decisions.task:id,title,status']);

        return view('meetings.show', ['meeting' => $meeting, 'users' => $this->userOptions()]);
    }

    public function edit(Meeting $meeting): View
    {
        $this->authorize('record-communication');
        $this->authorizeProjectAccess($meeting->project_id);

        return view('meetings.form', $this->formData($meeting));
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('record-communication');
        $this->authorizeProjectAccess($meeting->project_id);
        $data = $this->validated($request);
        $meeting->update(collect($data)->except('participants')->all());
        $meeting->participants()->sync($data['participants'] ?? []);
        Activity::log('meeting.updated', "Meeting \"{$meeting->title}\" updated", $meeting);

        return redirect()->route('meetings.show', $meeting)->with('status', 'Meeting updated.');
    }

    public function storeDecision(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('record-communication');
        $this->authorizeProjectAccess($meeting->project_id);
        $data = $request->validate([
            'decision' => ['required', 'string', 'max:5000'],
            'decided_by' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'exists:users,id,deleted_at,NULL'],
        ]);
        $decision = $meeting->decisions()->make($data);
        $decision->recorded_by = $request->user()->id;
        $decision->save();
        Activity::log('meeting.decision', "Decision recorded in \"{$meeting->title}\": ".str($data['decision'])->limit(80), $meeting, [], true);

        return back()->with('status', 'Decision recorded.');
    }

    /** Turns a meeting decision into a tracked task (Team Leads and Managers, project meetings only). */
    public function decisionToTask(Request $request, MeetingDecision $decision): RedirectResponse
    {
        $this->authorize('manage-tasks');
        $meeting = $decision->meeting;
        abort_unless($meeting->project_id, 422, 'Only project meetings can create tasks.');
        abort_unless($request->user()->canAccessProject($meeting->project_id), 403);
        if ($decision->task_id) {
            return redirect()->route('tasks.show', $decision->task_id);
        }
        $task = DB::transaction(function () use ($decision, $meeting, $request) {
            $task = new Task([
                'project_id' => $meeting->project_id,
                'title' => str($decision->decision)->limit(180)->toString(),
                'description' => "From meeting \"{$meeting->title}\" on {$meeting->starts_at->format('d M Y')}.\n\n{$decision->decision}",
                'assignee_id' => $decision->owner_id,
                'priority' => 'medium',
            ]);
            $task->status = TaskStatus::Backlog;
            $task->creator_id = $request->user()->id;
            $task->save();
            $decision->update(['task_id' => $task->id]);
            Activity::log('task.created', "Task \"{$task->title}\" created from a meeting decision", $task);

            return $task;
        });

        return redirect()->route('tasks.edit', $task)->with('status', 'Task created from decision. Review the details.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(MeetingType::class)],
            'client_id' => ['nullable', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'external_participants' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled'])],
            'follow_up_date' => ['nullable', 'date'],
            'participants' => ['array'],
            'participants.*' => ['exists:users,id,deleted_at,NULL'],
        ]);
        $this->authorizeProjectAccess(isset($data['project_id']) ? (int) $data['project_id'] : null);
        if (! empty($data['project_id']) && empty($data['client_id'])) {
            $data['client_id'] = Project::find($data['project_id'])->client_id;
        }

        return $data;
    }

    private function formData(Meeting $meeting): array
    {
        return [
            'meeting' => $meeting,
            'types' => MeetingType::options(),
            'clients' => Client::where('status', '!=', 'archived')->visibleTo(auth()->user())->orderBy('name')->pluck('name', 'id')->all(),
            'projects' => $this->projectOptions(),
            'users' => $this->userOptions(),
        ];
    }

    /**
     * Parse ?month=YYYY-MM. Anything else (missing, malformed, out of range) falls back to the current month.
     */
    private function monthFromQuery(mixed $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], 1, (int) $m[1]) && (int) $m[1] >= 2000 && (int) $m[1] <= 2100) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1)->startOfDay();
        }

        return CarbonImmutable::today()->startOfMonth();
    }
}
