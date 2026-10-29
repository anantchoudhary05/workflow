<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AppAlert;

class Notifier
{
    /** Notify a user unless it is the actor or the same dedupe key was already sent. */
    public static function send(?User $user, string $title, string $url, string $kind = 'info', ?string $dedupeKey = null): void
    {
        if (! $user || ! $user->is_active || $user->id === auth()->id()) {
            return;
        }
        if ($dedupeKey && $user->notifications()->where('dedupe_key', $dedupeKey)->exists()) {
            return;
        }
        $user->notify(new AppAlert($title, $url, $kind, $dedupeKey));
    }
}
