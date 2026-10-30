<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Trash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Administrator-only delete (soft, with a required reason), the Trash page and restore.
 * There is no permanent delete: records stay in the database and can always be restored.
 */
class TrashController extends Controller
{
    public function __construct(private Trash $trash) {}

    public function index(Request $request): View
    {
        Gate::authorize('delete-records');
        $type = array_key_exists($request->query('type'), Trash::TYPES) ? $request->query('type') : 'projects';

        $relations = ['projects' => ['client:id,name'], 'tasks' => ['project:id,code,name,deleted_at']][$type] ?? [];
        $records = $this->trashedQuery($type)->with(['deletedBy:id,name,deleted_at', ...$relations])
            ->latest('deleted_at')->paginate(25)->withQueryString();
        $counts = collect(Trash::TYPES)->map(fn ($class, $key) => $this->trashedQuery($key)->count());

        return view('trash.index', compact('type', 'records', 'counts'));
    }

    public function destroyProject(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('delete-records');
        Gate::authorize('delete', $project);
        $this->trash->delete($project, $request->user(), $this->reason($request));

        return redirect()->route('projects.index')->with('status', "Project {$project->code} deleted. An administrator can restore it from Trash.");
    }

    public function destroyClient(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('delete-records');
        Gate::authorize('delete', $client);
        $this->trash->delete($client, $request->user(), $this->reason($request));

        return redirect()->route('clients.index')->with('status', "Client {$client->name} deleted. An administrator can restore it from Trash.");
    }

    public function destroyTask(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('delete-records');
        Gate::authorize('delete', $task);
        $this->trash->delete($task, $request->user(), $this->reason($request));

        return redirect()->route('projects.tasks', $task->project_id)->with('status', "Task {$task->code} deleted. An administrator can restore it from Trash.");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete-records');
        $this->trash->delete($user, $request->user(), $this->reason($request));

        return redirect()->route('users.index')->with('status', "{$user->realName()} deleted and signed out. Their tasks stay and show them as a deleted user.");
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        Gate::authorize('delete-records');
        abort_unless(array_key_exists($type, Trash::TYPES), 404);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        /** @var Model $record */
        $record = $this->trashedQuery($type)->findOrFail($id);
        $this->trash->restore($record, $request->user(), $note);

        return redirect()->route('trash.index', ['type' => $type])->with('status', 'Restored '.Trash::describe($record).'.');
    }

    private function reason(Request $request): string
    {
        return trim($request->validate(
            ['reason' => ['required', 'string', 'min:5', 'max:1000']],
            ['reason.required' => 'Give a reason for deleting.', 'reason.min' => 'The reason must be at least 5 characters.'],
        )['reason']);
    }

    /** Only trashed rows, and parent-deleted filters lifted so a task of a deleted project is still listed. */
    private function trashedQuery(string $type): Builder
    {
        return Trash::TYPES[$type]::onlyTrashed()->withoutGlobalScopes(['active-project', 'active-client']);
    }
}
