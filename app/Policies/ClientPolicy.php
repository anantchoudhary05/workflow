<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // non-privileged users see only clients of their projects
    }

    public function view(User $user, Client $client): bool
    {
        return $user->hasAbility('view-clients')
            || $client->projects()->whereIn('id', $user->accessibleProjectIds() ?? [])->exists();
    }

    /** Marketing onboards clients; Managers may also. */
    public function create(User $user): bool
    {
        return $user->hasAbility('manage-clients');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasAbility('manage-clients');
    }

    /** Soft delete with a reason (restorable from Trash): administrators only. */
    public function delete(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }
}
