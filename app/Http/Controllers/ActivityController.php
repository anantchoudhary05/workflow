<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('view-activity');
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return view('activity.index', [
            'logs' => ActivityLog::query()->visibleTo($request->user())->with(['user:id,name', 'project:id,code'])
                ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
                ->when($request->query('user'), fn ($q, $u) => $q->where('user_id', $u))
                ->when($request->boolean('audit'), fn ($q) => $q->where('is_audit', true))
                ->when($request->query('event'), fn ($q, $e) => $q->where('event', 'like', "{$e}%"))
                ->when($request->query('from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
                ->when($request->query('to'), fn ($q, $d) => $q->where('created_at', '<', Carbon::parse($d)->addDay()))
                ->latest('id')->paginate(50)->withQueryString(),
            'projects' => $this->projectOptions(false),
            'users' => $this->userOptions(),
        ]);
    }
}
