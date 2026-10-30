<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\Notifier;
use App\Services\TaskWorkflow;
use App\Support\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $tasks = $this->filtered($request)->orderByRaw('due_date is null')->orderBy('due_date')->paginate(50)->withQueryString();

        return view('tasks.index', $this->listData($request) + ['tasks' => $tasks]);
    }

    public function board(Request $request): View
    {
        $tasks = $this->filtered($request, false)->orderBy('position')->orderBy('due_date')->limit(500)->get()->groupBy(fn ($t) => $t->status->value);

        return view('tasks.board', $this->listData($request) + ['columns' => $tasks]);
    }

    public function forProject(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $request->merge(['project' => $project->id]);
        $tasks = $this->filtered($request)->orderByRaw('due_date is null')->orderBy('due_date')->paginate(50)->withQueryString();

        return view('projects.tasks', $this->listData($request) + ['tasks' => $tasks, 'project' => $project]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);
        $project = $this->visibleProject($request->integer('project') ?: null);

        return view('tasks.form', $this->formData(new Task([
            'project_id' => $project?->id,
            'milestone_id' => $request->integer('milestone') ?: null,
            'change_request_id' => $request->integer('change_request') ?: null,
            'priority' => Priority::Medium,
        ])));
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $task = DB::transaction(function () use ($data, $request) {
            $task = new Task(collect($data)->except('status')->all());
            $task->status = TaskStatus::tryFrom((string) ($data['status'] ?? '')) ?? TaskStatus::Backlog;
            $task->creator_id = $request->user()->id;
            $task->save();
            $this->ensureMembers($task);
            Activity::log('task.created', "Task \"{$task->title}\" created in {$task->status->label()}", $task, ['status' => ['from' => null, 'to' => $task->status->value]]);

            return $task;
        });
        app(TaskWorkflow::class)->logAssignmentChanges($task, array_filter(['assignee_id' => $task->assignee_id ? ['from' => null, 'to' => $task->assignee_id] : null]));

        return redirect()->route('tasks.show', $task)->with('status', 'Task created.');
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('view', $task);
        $task->load(['project:id,code,name,client_id', 'milestone:id,name', 'changeRequest:id,number,title', 'assignee:id,name', 'reviewer:id,name', 'creator:id,name', 'comments.user:id,name', 'timeEntries.user:id,name']);

        return view('tasks.show', [
            'task' => $task,
            'transitions' => $task->status->transitions(),
            'history' => $task->project->activities()->where('subject_type', $task->getMorphClass())->where('subject_id', $task->id)->with('user:id,name')->limit(30)->get(),
        ]);
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);

        return view('tasks.form', $this->formData($task));
    }

    public function update(TaskRequest $request, Task $task, TaskWorkflow $workflow): RedirectResponse
    {
        $task->fill($request->validated());
        $diff = Activity::diff($task, ['assignee_id', 'reviewer_id', 'due_date', 'priority', 'title', 'milestone_id']);
        $task->save();
        $this->ensureMembers($task);
        $task->load(['assignee', 'reviewer']);
        $workflow->logAssignmentChanges($task, $diff);
        Activity::log('task.updated', "Task \"{$task->title}\" updated", $task, collect($diff)->except(['assignee_id', 'reviewer_id', 'due_date'])->all());

        return redirect()->route('tasks.show', $task)->with('status', 'Task updated.');
    }

    public function status(Request $request, Task $task, TaskWorkflow $workflow): JsonResponse|RedirectResponse
    {
        $this->authorize('changeStatus', $task);
        $data = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->transition($task, TaskStatus::from($data['status']), $request->user(), $data['reason'] ?? null);

        return $request->expectsJson()
            ? response()->json(['status' => $task->status->value, 'label' => $task->status->label()])
            : back()->with('status', "Task moved to {$task->status->label()}.");
    }

    /** Manager sets a task's priority (including Urgent) from the task page; the assignee is told when it becomes urgent. */
    public function priority(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('setPriority', $task);
        $data = $request->validate(['priority' => ['required', Rule::enum(Priority::class)]]);
        $task->priority = Priority::from($data['priority']);
        $diff = Activity::diff($task, ['priority']);
        if (! $diff) {
            return back();
        }
        $task->save();
        Activity::log('task.priority', "Task \"{$task->title}\" priority set to {$task->priority->label()}", $task, $diff);
        if ($task->priority === Priority::Urgent) {
            Notifier::send($task->assignee, "Urgent: {$task->title}", route('tasks.show', $task), 'warning');
        }

        return back()->with('status', "Priority set to {$task->priority->label()}.");
    }

    public function comment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('comment', $task);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $task->comments()->create(['user_id' => $request->user()->id, 'kind' => 'comment', 'body' => $data['body']]);

        return back()->with('status', 'Comment added.');
    }

    public function logTime(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('logTime', $task);
        $data = $request->validate([
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'description' => ['nullable', 'string', 'max:255'],
            'billable' => ['boolean'],
        ]);
        $entry = $task->timeEntries()->make($data + ['billable' => $request->boolean('billable', true)]);
        $entry->project_id = $task->project_id;
        $entry->user_id = $request->user()->id;
        $entry->save();

        return back()->with('status', 'Time logged.');
    }

    private function filtered(Request $request, bool $hideClosed = true): Builder
    {
        $user = $request->user();

        return Task::query()->visibleTo($user)
            ->with(['project:id,code,name', 'assignee:id,name', 'reviewer:id,name', 'milestone:id,name'])
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('assignee'), fn ($q, $a) => $a === 'none' ? $q->whereNull('assignee_id') : $q->where('assignee_id', $a === 'me' ? $user->id : (int) $a))
            ->when($request->query('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s),
                fn ($q) => $hideClosed ? $q->open() : $q->where(fn ($w) => $w->open()->orWhere('completed_at', '>=', now()->subDays(14))))
            ->when($request->query('due') === 'today', fn ($q) => $q->dueToday())
            ->when($request->query('due') === 'overdue', fn ($q) => $q->overdue())
            ->when($request->query('due') === 'week', fn ($q) => $q->open()->whereBetween('due_date', [today()->toDateString(), today()->addDays(7)->toDateString()]))
            ->when($request->query('q'), fn ($q, $t) => $q->where('title', 'like', "%{$t}%"));
    }

    private function listData(Request $request): array
    {
        return [
            'projects' => $this->projectOptions(),
            'users' => $this->userOptions(),
            'statuses' => TaskStatus::options(),
            'priorities' => Priority::options(),
            'filters' => $request->only(['project', 'assignee', 'status', 'priority', 'due', 'q']),
        ];
    }

    private function formData(Task $task): array
    {
        $project = $task->project_id ? Project::find($task->project_id) : null;

        return [
            'task' => $task,
            'projects' => $this->projectOptions(),
            'milestones' => $project ? $project->milestones()->pluck('name', 'id')->all() : [],
            'changeRequests' => $project ? $project->changeRequests()->whereIn('status', ['approved', 'scheduled', 'in_progress'])->get()->mapWithKeys(fn ($c) => [$c->id => "{$c->number} {$c->title}"])->all() : [],
            'users' => $this->userOptions(),
            'priorities' => Priority::options(),
            'initialStatuses' => collect(TaskRequest::initialStatuses())->mapWithKeys(fn (string $s): array => [$s => TaskStatus::from($s)->label()])->all(),
        ];
    }

    /** Assignees and reviewers need project access to see their work. */
    private function ensureMembers(Task $task): void
    {
        $ids = array_filter([$task->assignee_id, $task->reviewer_id]);
        if ($ids) {
            $task->project->members()->syncWithoutDetaching($ids);
        }
    }
}
