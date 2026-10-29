<?php

namespace App\Policies;

use App\Models\ProjectFile;
use App\Models\User;

class ProjectFilePolicy
{
    public function view(User $user, ProjectFile $file): bool
    {
        if (! $user->canAccessProject($file->project_id)) {
            return false;
        }

        // Quotation documents follow the financial permission.
        return $file->category->value !== 'quotation' || $user->hasAbility('view-financials');
    }

    public function delete(User $user, ProjectFile $file): bool
    {
        return $file->uploaded_by === $user->id && $user->canAccessProject($file->project_id);
    }
}
