<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/** Standard database channel that also stores a dedupe key so reminders are not repeated. */
class DedupedDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification): array
    {
        return parent::buildPayload($notifiable, $notification) + [
            'dedupe_key' => $notification->dedupeKey ?? null,
        ];
    }
}
