<?php

namespace App\Http\Controllers;

use App\Services\MyWork;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyWorkController extends Controller
{
    /** The user's open tasks, reviews and follow-ups across all projects, optionally filtered to one project. */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $projectOptions = MyWork::for($user)->projectOptions();
        $projectId = $request->integer('project') ?: null;
        $work = MyWork::for($user, $projectId);

        return view('my-work', [
            'groups' => MyWork::groups($work->tasks()),
            'reviewQueue' => $work->reviewQueue(),
            'followUps' => $work->followUps(),
            'hoursThisWeek' => $work->hoursThisWeek(),
            'projectOptions' => $projectOptions,
            'projectId' => $projectId,
        ]);
    }
}
