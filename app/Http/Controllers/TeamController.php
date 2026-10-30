<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\TeamWorkload;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('view-team');

        $user = $request->user();

        return view('team.index', [
            'rows' => TeamWorkload::rows($user->accessibleProjectIds()),
            'activity' => $user->can('view-activity')
                ? ActivityLog::query()->visibleTo($user)->with(['user:id,name', 'project:id,code'])->latest('id')->limit(25)->get()
                : collect(),
        ]);
    }
}
