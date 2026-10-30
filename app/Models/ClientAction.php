<?php

namespace App\Models;

use App\Enums\ClientActionType;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClientAction extends Model
{
    use BelongsToProject;

    /** Also hidden while its client is deleted. */
    public const SCOPED_TO_CLIENT = true;

    public const STATUSES = ['waiting' => 'Waiting on client', 'received' => 'Received', 'cancelled' => 'Cancelled'];

    protected $fillable = ['client_id', 'project_id', 'type', 'title', 'requested_at', 'next_follow_up_at', 'owner_id'];

    protected function casts(): array
    {
        return [
            'type' => ClientActionType::class,
            'requested_at' => 'date',
            'last_follow_up_at' => 'date',
            'next_follow_up_at' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeWaiting(Builder $q): Builder
    {
        return $q->where('status', 'waiting');
    }

    public function daysWaiting(): int
    {
        return (int) $this->requested_at->diffInDays(today());
    }
}
