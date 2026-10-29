<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // lists are scoped with Project::visibleTo()
    }

    public function view(User $user, Project $project): bool
    {
        return $user->canAccessProject($project->id);
    }

    public function create(User $user): bool
    {
        return false; // administrators only (Gate::before)
    }

    public function update(User $user, Project $project): bool
    {
        return false;
    }

    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
