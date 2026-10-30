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

    /** Marketing onboards new projects; Managers may also create them. */
    public function create(User $user): bool
    {
        return $user->hasAbility('create-projects');
    }

    /** Edit details, status, team. Managers and Team Leads. */
    public function update(User $user, Project $project): bool
    {
        return $user->hasAbility('manage-projects') && $user->canAccessProject($project->id);
    }

    /** Soft delete with a reason (restorable from Trash): administrators only. */
    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }
}
