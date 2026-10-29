<?php

namespace App\Http\Controllers;

use App\Enums\CommunicationType;
use App\Models\Client;
use App\Models\Communication;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('communications.index', [
            'communications' => Communication::query()->visibleTo($user)
                ->with(['client:id,name', 'project:id,code', 'owner:id,name'])
                ->when($request->query('client'), fn ($q, $c) => $q->where('client_id', $c))
                ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
                ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
                ->latest('occurred_at')->paginate(40)->withQueryString(),
            'clients' => Client::visibleTo($user)->orderBy('name')->pluck('name', 'id')->all(),
            'projects' => $this->projectOptions(false),
            'types' => CommunicationType::options(),
            'canRecord' => $user->can('record-communication'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('record-communication');
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('client_id', $request->input('client_id'))],
            'type' => ['required', Rule::enum(CommunicationType::class)],
            'direction' => ['required', Rule::in(['inbound', 'outbound'])],
            'subject' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'outcome' => ['nullable', 'string', 'max:255'],
            'next_action' => ['nullable', 'string', 'max:255'],
        ]);
        $this->authorizeProjectAccess(isset($data['project_id']) ? (int) $data['project_id'] : null);
        $communication = new Communication($data);
        $communication->owner_id = $request->user()->id;
        $communication->save();
        Client::whereKey($data['client_id'])->update(['last_contacted_at' => $data['occurred_at']]);
        Activity::log('communication.logged', "{$communication->type->label()} logged: {$communication->subject}", $communication);

        return back()->with('status', 'Communication logged.');
    }
}
