<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $metrics = new DashboardMetrics($user);
        $projects = $metrics->activeProjects();

        return view('dashboard', [
            'kpis' => $metrics->kpis($projects),
            'projects' => $projects,
            'milestones' => $metrics->upcomingMilestones(),
            'blocked' => $metrics->blockedTasks(),
            'waiting' => $metrics->clientWaiting(),
            'launches' => $metrics->upcomingLaunches(),
            'myTasks' => $metrics->myTasks(),
            'activity' => ActivityLog::query()->visibleTo($user)->where('event', 'not like', 'auth.%')->with('user:id,name')->latest('id')->limit(10)->get(),
        ]);
    }
}
