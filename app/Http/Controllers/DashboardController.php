<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Services\DashboardMetrics;
use App\Services\TeamWorkload;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** One dashboard, assembled per role: each role sees its own KPIs and work queues plus its private to-dos. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $metrics = new DashboardMetrics($user);
        $projects = $metrics->activeProjects();
        $role = $user->role;
        $isDelivery = ! in_array($role, [Role::Marketing, Role::Management], true);
        $show = [
            'urgent' => $role !== Role::Marketing,
            'reviews' => $user->can('approve-for-preview') || $user->can('request-review'),
            'handoffs' => $user->can('receive-handoffs'),
            'myHandoffs' => $user->can('hand-off-projects'),
            'unassigned' => $user->can('manage-tasks'),
            'pipeline' => in_array($role, [Role::Marketing, Role::Manager, Role::Admin], true),
            'clientDocuments' => in_array($role, [Role::Marketing, Role::Admin], true),
            'team' => in_array($role, [Role::Management, Role::Manager], true),
            'myTasks' => $isDelivery,
            'milestones' => $role !== Role::Marketing,
            'blocked' => $isDelivery,
        ];

        return view('dashboard', [
            'kpis' => $metrics->kpisForRole($projects),
            'projects' => $projects,
            'show' => $show,
            'milestones' => $show['milestones'] ? $metrics->upcomingMilestones() : collect(),
            'blocked' => $show['blocked'] ? $metrics->blockedTasks() : collect(),
            'waiting' => $metrics->clientWaiting(),
            'launches' => $metrics->upcomingLaunches(),
            'myTasks' => $show['myTasks'] ? $metrics->myTasks() : collect(),
            'todos' => $metrics->myTodos(),
            'todoProjects' => $this->projectOptions(),
            'urgentTasks' => $show['urgent'] ? $metrics->urgentTasks() : collect(),
            'urgentProjects' => $show['urgent'] ? $metrics->urgentProjects() : collect(),
            'reviewQueue' => $show['reviews'] ? $metrics->reviewQueue() : collect(),
            'handoffs' => $show['handoffs'] ? $metrics->pendingHandoffs() : collect(),
            'myHandoffs' => $show['myHandoffs'] ? $metrics->myHandoffs() : collect(),
            'unassigned' => $show['unassigned'] ? $metrics->unassignedTasks() : collect(),
            'pipeline' => $show['pipeline'] ? $metrics->pipelineProjects() : collect(),
            'clientDocuments' => $show['clientDocuments'] ? $metrics->recentClientDocuments() : collect(),
            'team' => $show['team'] && $user->can('view-team') ? TeamWorkload::rows($user->accessibleProjectIds())->filter(fn ($r) => $r->open > 0)->take(8) : collect(),
            'activity' => ActivityLog::query()->visibleTo($user)->where('event', 'not like', 'auth.%')->with('user:id,name')->latest('id')->limit(10)->get(),
        ]);
    }
}
