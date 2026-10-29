<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Defect;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Requirement;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Global search across records the user is allowed to see. */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse|View
    {
        $term = trim((string) $request->query('q'));
        $user = $request->user();
        $results = [];

        if (mb_strlen($term) >= 2) {
            $like = "%{$term}%";
            $results = [
                'Projects' => Project::query()->visibleTo($user)->search($term)->limit(6)->get()
                    ->map(fn ($p) => ['title' => "{$p->code} · {$p->name}", 'meta' => $p->status->label(), 'url' => route('projects.show', $p)]),
                'Clients' => Client::query()->visibleTo($user)->search($term)->limit(6)->get()
                    ->map(fn ($c) => ['title' => $c->name, 'meta' => $c->company_name ?? $c->code, 'url' => route('clients.show', $c)]),
                'Tasks' => Task::query()->visibleTo($user)->where('title', 'like', $like)->with('project:id,code')->limit(8)->get()
                    ->map(fn ($t) => ['title' => $t->title, 'meta' => $t->project->code.' · '.$t->status->label(), 'url' => route('tasks.show', $t)]),
                'Requirements' => Requirement::query()->visibleTo($user)->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('code', 'like', $like))->with('project:id,code')->limit(6)->get()
                    ->map(fn ($r) => ['title' => "{$r->code} {$r->title}", 'meta' => $r->project->code, 'url' => route('projects.requirements', $r->project_id).'#req-'.$r->id]),
                'Change requests' => ChangeRequest::query()->visibleTo($user)->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('number', 'like', $like))->with('project:id,code')->limit(6)->get()
                    ->map(fn ($c) => ['title' => "{$c->number} {$c->title}", 'meta' => $c->project->code.' · '.$c->status->label(), 'url' => route('change-requests.show', $c)]),
                'Defects' => Defect::query()->visibleTo($user)->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('code', 'like', $like))->with('project:id,code')->limit(6)->get()
                    ->map(fn ($d) => ['title' => "{$d->code} {$d->title}", 'meta' => $d->project->code.' · '.$d->status->label(), 'url' => route('defects.show', $d)]),
                'Meetings' => Meeting::query()->visibleTo($user)->where('title', 'like', $like)->limit(6)->get()
                    ->map(fn ($m) => ['title' => $m->title, 'meta' => $m->starts_at->format('d M Y H:i'), 'url' => route('meetings.show', $m)]),
            ];
            if ($user->can('view-financials')) {
                $results['Quotations'] = Quotation::query()->visibleTo($user)->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like))->limit(6)->get()
                    ->map(fn ($q) => ['title' => $q->reference().' '.$q->title, 'meta' => $q->status->label(), 'url' => route('quotations.show', $q)]);
            }
            $results = array_filter($results, fn ($group) => $group->isNotEmpty());
        }

        if ($request->expectsJson()) {
            return response()->json(['results' => $results]);
        }

        return view('search', ['term' => $term, 'results' => $results]);
    }
}
