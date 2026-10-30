<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Marketing → dev team handoff. Marketing confirms the client details, requirements and documents are
 * in place; every active Team Lead (and the project manager) is notified, and a Team Lead acknowledges.
 */
class HandoffController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'hand-off-projects']);
        $data = $request->validate(['handoff_notes' => ['required', 'string', 'max:5000']]);
        $project->handed_off_at = now();
        $project->handed_off_by = $request->user()->id;
        $project->handoff_notes = $data['handoff_notes'];
        $project->handoff_acknowledged_at = null;
        $project->handoff_acknowledged_by = null;
        $project->save();
        Activity::log('project.handoff', "Handed to the dev team by {$request->user()->name}", $project, [], true);

        $recipients = User::active()->where('role', Role::TeamLead)->get();
        if ($project->manager) {
            $recipients->push($project->manager);
        }
        foreach ($recipients->unique('id') as $user) {
            Notifier::send($user, "New handoff: {$project->code} · {$project->name}", route('projects.show', $project), 'info');
        }

        return back()->with('status', 'Sent to the dev team. Team Leads have been notified.');
    }

    public function acknowledge(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'receive-handoffs']);
        abort_unless($project->awaitingHandoffAck(), 422, 'There is no handoff waiting for acknowledgement.');
        $project->handoff_acknowledged_at = now();
        $project->handoff_acknowledged_by = $request->user()->id;
        $project->save();
        Activity::log('project.handoff.ack', "Handoff acknowledged by {$request->user()->name}", $project);
        Notifier::send($project->handedOffBy, "Dev team picked up {$project->code} · {$project->name}", route('projects.show', $project), 'success');

        return back()->with('status', 'Handoff acknowledged.');
    }
}
