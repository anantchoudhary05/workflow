<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\ClientAction;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyWorkController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $tasks = $user->openTasks()->with(['project:id,code,name', 'reviewer:id,name'])->orderBy('due_date')->get();
        $today = today();

        $groups = [
            'Overdue' => $tasks->filter(fn ($t) => $t->due_date && $t->due_date->lt($today)),
            'Due today' => $tasks->filter(fn ($t) => $t->due_date?->isSameDay($today)),
            'Blocked' => $tasks->where('status', TaskStatus::Blocked),
            'Changes requested' => $tasks->where('status', TaskStatus::ChangesRequested),
            'Next 7 days' => $tasks->filter(fn ($t) => $t->due_date && $t->due_date->gt($today) && $t->due_date->lte($today->copy()->addDays(7))),
            'Later / no due date' => $tasks->filter(fn ($t) => ! $t->due_date || $t->due_date->gt($today->copy()->addDays(7))),
        ];

        return view('my-work', [
            'groups' => $groups,
            'reviewQueue' => Task::query()->visibleTo($user)->where('reviewer_id', $user->id)->where('status', TaskStatus::InReview)->with(['project:id,code', 'assignee:id,name'])->get(),
            'followUps' => ClientAction::query()->visibleTo($user)->waiting()->where('owner_id', $user->id)->with(['client:id,name', 'project:id,code'])->orderBy('next_follow_up_at')->get(),
            'hoursThisWeek' => (float) $user->timeEntries()->whereBetween('work_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->sum('hours'),
        ]);
    }
}
