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

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Client $client): bool
    {
        return false;
    }

    public function delete(User $user, Client $client): bool
    {
        return false;
    }
}
