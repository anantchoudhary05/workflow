<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Models\Todo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Personal to-dos. Every query is scoped to the signed-in user and every change goes through TodoPolicy,
 * so nobody (administrators included) can see or edit another person's list. To-dos are not written to
 * the activity log because that log is visible to other people.
 */
class TodoController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = in_array($request->query('show'), ['open', 'done', 'all'], true) ? $request->query('show') : 'open';

        return view('todos.index', [
            'todos' => $user->todos()->with('project:id,code,name')
                ->when($filter === 'open', fn ($q) => $q->whereNull('completed_at'))
                ->when($filter === 'done', fn ($q) => $q->whereNotNull('completed_at'))
                ->when($request->integer('project'), fn ($q, $id) => $q->where('project_id', $id))
                ->ordered()->paginate(30)->withQueryString(),
            'filter' => $filter,
            'projects' => $this->projectOptions(),
            'priorities' => Priority::options(),
            'counts' => [
                'open' => $user->todos()->open()->count(),
                'overdue' => $user->todos()->open()->whereDate('due_date', '<', today())->count(),
                'today' => $user->todos()->open()->whereDate('due_date', today())->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Todo::class);
        $todo = new Todo($this->validated($request));
        $todo->user_id = $request->user()->id;
        $todo->save();

        return back()->with('status', 'To-do added.');
    }

    public function update(Request $request, Todo $todo): RedirectResponse
    {
        $this->authorize('update', $todo);
        $todo->update($this->validated($request));

        return back()->with('status', 'To-do updated.');
    }

    public function toggle(Todo $todo): RedirectResponse
    {
        $this->authorize('update', $todo);
        $todo->completed_at = $todo->completed_at ? null : now();
        $todo->save();

        return back()->with('status', $todo->completed_at ? 'To-do done.' : 'To-do reopened.');
    }

    public function destroy(Todo $todo): RedirectResponse
    {
        $this->authorize('delete', $todo);
        $todo->delete();

        return back()->with('status', 'To-do deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);
        // A to-do may only point at a project the owner can open.
        if (! empty($data['project_id']) && ! $request->user()->canAccessProject((int) $data['project_id'])) {
            abort(403);
        }

        return $data;
    }
}
