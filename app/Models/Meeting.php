<?php

namespace App\Models;

use App\Enums\MeetingType;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use BelongsToProject, SoftDeletes;

    /** Also hidden while its client is deleted. */
    public const SCOPED_TO_CLIENT = true;

    public const STATUSES = ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $fillable = ['client_id', 'project_id', 'title', 'type', 'starts_at', 'duration_minutes', 'external_participants', 'agenda', 'notes', 'status', 'follow_up_date'];

    protected function casts(): array
    {
        return ['type' => MeetingType::class, 'starts_at' => 'datetime', 'follow_up_date' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id')->withTrashed();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class)->latest();
    }

    /** Meetings without a project are visible only to users who can see all projects. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->accessibleProjectIds();

        return $ids === null ? $query : $query->whereIn('project_id', $ids ?: [0]);
    }
}
