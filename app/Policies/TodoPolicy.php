<?php

namespace App\Policies;

use App\Models\Todo;
use App\Models\User;

/**
 * Personal to-dos are private. Nobody but the owner may see or change one - not even an administrator:
 * AppServiceProvider's Gate::before skips its administrator shortcut for to-dos.
 */
class TodoPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Todo $todo): bool
    {
        return $todo->user_id === $user->id;
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $todo->user_id === $user->id;
    }
}
