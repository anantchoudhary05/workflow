<?php

namespace App\Http\Controllers;

use App\Enums\ClientActionType;
use App\Models\ClientAction;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The "client waiting room": everything the team is waiting on the client for. */
class ClientActionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'waiting');

        return view('client-actions.index', [
            'actions' => ClientAction::query()->visibleTo($request->user())
                ->with(['client:id,name', 'project:id,code,name', 'owner:id,name'])
                ->where('status', $status)
                ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
                ->orderBy($status === 'waiting' ? 'requested_at' : 'resolved_at', $status === 'waiting' ? 'asc' : 'desc')
                ->paginate(40)->withQueryString(),
            'status' => $status,
            'types' => ClientActionType::options(),
            'projects' => $this->projectOptions(),
            'users' => $this->userOptions(),
            'followUpDays' => (int) config('doc.reminders.client_follow_up_days'),
            'canRecord' => $request->user()->can('record-communication'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('record-communication');
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'type' => ['required', Rule::enum(ClientActionType::class)],
            'title' => ['required', 'string', 'max:255'],
            'requested_at' => ['required', 'date', 'before_or_equal:today'],
            'next_follow_up_at' => ['nullable', 'date', 'after_or_equal:requested_at'],
            'owner_id' => ['nullable', 'exists:users,id'],
        ]);
        $project = $this->visibleProject((int) $data['project_id']);
        $action = new ClientAction($data + ['client_id' => $project->client_id]);
        $action->owner_id ??= $request->user()->id;
        $action->next_follow_up_at ??= Carbon::parse($data['requested_at'])->addDays((int) config('doc.reminders.client_follow_up_days'));
        $action->status = 'waiting';
        $action->save();
        Activity::log('client_action.created', "Waiting on client: {$action->title}", $action);

        return back()->with('status', 'Client action added to the waiting room.');
    }

    public function followUp(Request $request, ClientAction $clientAction): RedirectResponse
    {
        $this->authorize('record-communication');
        $this->authorizeProjectAccess($clientAction->project_id);
        $data = $request->validate([
            'next_follow_up_at' => ['required', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $clientAction->forceFill(['last_follow_up_at' => today(), 'next_follow_up_at' => $data['next_follow_up_at']])->save();
        Activity::log('client_action.follow_up', "Followed up: {$clientAction->title}".(filled($data['note'] ?? null) ? " — {$data['note']}" : ''), $clientAction);

        return back()->with('status', 'Follow-up recorded.');
    }

    public function resolve(Request $request, ClientAction $clientAction): RedirectResponse
    {
        $this->authorize('record-communication');
        $this->authorizeProjectAccess($clientAction->project_id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['received', 'cancelled'])],
            'resolution' => ['required', 'string', 'max:255'],
        ]);
        $clientAction->forceFill(['status' => $data['status'], 'resolution' => $data['resolution'], 'resolved_at' => now()])->save();
        Activity::log('client_action.resolved', "Client action \"{$clientAction->title}\" {$data['status']}: {$data['resolution']}", $clientAction, [], true);

        return back()->with('status', 'Client action closed.');
    }
}
