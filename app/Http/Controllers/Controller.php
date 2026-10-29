<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /** Active users as id => name, for assignment selects. */
    protected function userOptions(): array
    {
        return User::active()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Projects the current user may see, as id => "CODE · Name". */
    protected function projectOptions(bool $openOnly = true): array
    {
        return Project::query()->visibleTo(auth()->user())
            ->when($openOnly, fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled']))
            ->orderBy('code')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->code} · {$p->name}"])->all();
    }

    /** Validates that a submitted project id is visible to the user and returns the project. */
    protected function visibleProject(?int $id): ?Project
    {
        if (! $id) {
            return null;
        }
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return $project;
    }

    /** Record-level guard for project-owned records (null project = organisation-wide record). */
    protected function authorizeProjectAccess(?int $projectId): void
    {
        abort_unless(auth()->user()->canAccessProject($projectId), 403);
    }
}
