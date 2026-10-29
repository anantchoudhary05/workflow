<?php

namespace App\Http\Controllers;

use App\Services\TeamWorkload;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('view-team');

        return view('team.index', ['rows' => TeamWorkload::rows($request->user()->accessibleProjectIds())]);
    }
}
