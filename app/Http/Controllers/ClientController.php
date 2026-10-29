<?php

namespace App\Http\Controllers;

use App\Enums\ClientStatus;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = Client::query()->visibleTo($request->user())
            ->search($request->query('q'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with('owner:id,name')
            ->withCount(['projects as active_projects_count' => fn ($q) => $q->active()])
            ->withCount(['clientActions as waiting_count' => fn ($q) => $q->where('status', 'waiting')])
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('clients.index', ['clients' => $clients, 'statuses' => ClientStatus::options()]);
    }

    public function create(): View
    {
        $this->authorize('create', Client::class);

        return view('clients.form', ['client' => new Client(['status' => ClientStatus::Lead]), 'users' => $this->userOptions()]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $duplicates = Client::possibleDuplicates($data);
        if ($duplicates->isNotEmpty() && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->with('duplicates', $duplicates->map(fn (Client $c): array => $c->only(['id', 'code', 'name', 'email']))->values()->all());
        }
        $client = Client::create($data);
        Activity::log('client.created', "Client {$client->name} created", $client);

        return redirect()->route('clients.show', $client)->with('status', 'Client created.');
    }

    public function show(Request $request, Client $client): View
    {
        $this->authorize('view', $client);
        $user = $request->user();
        $client->load(['owner:id,name', 'contacts']);

        return view('clients.show', [
            'client' => $client,
            'projects' => $client->projects()->visibleTo($user)->with('manager:id,name')->latest()->get(),
            'communications' => $client->communications()->visibleTo($user)->with(['owner:id,name', 'project:id,code'])->latest('occurred_at')->limit(15)->get(),
            'actions' => $client->clientActions()->visibleTo($user)->with('project:id,code')->latest('requested_at')->limit(15)->get(),
            'meetings' => $client->meetings()->visibleTo($user)->latest('starts_at')->limit(10)->get(),
            'quotations' => $user->can('view-financials') ? $client->quotations()->visibleTo($user)->latest()->limit(10)->get() : collect(),
        ]);
    }

    public function edit(Client $client): View
    {
        $this->authorize('update', $client);

        return view('clients.form', ['client' => $client, 'users' => $this->userOptions()]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->fill($request->validated());
        $changes = Activity::diff($client, ['name', 'status', 'email', 'owner_id']);
        $client->save();
        Activity::log('client.updated', "Client {$client->name} updated", $client, $changes, (bool) $changes);

        return redirect()->route('clients.show', $client)->with('status', 'Client updated.');
    }

    /** Archiving keeps history intact; clients are never hard-deleted from the UI. */
    public function archive(Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $client->update(['status' => ClientStatus::Archived]);
        Activity::log('client.archived', "Client {$client->name} archived", $client, [], true);

        return redirect()->route('clients.index')->with('status', 'Client archived.');
    }

    public function storeContact(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $client->contacts()->create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_title' => ['nullable', 'string', 'max:120'],
        ]));

        return back()->with('status', 'Contact added.');
    }

    public function destroyContact(ClientContact $contact): RedirectResponse
    {
        $this->authorize('update', $contact->client);
        $contact->delete();

        return back()->with('status', 'Contact removed.');
    }
}
