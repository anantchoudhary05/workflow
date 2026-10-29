<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'project_id', 'client_id', 'subject_type', 'subject_id', 'event', 'description', 'changes', 'is_audit'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'is_audit' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Append-only: audit history cannot be edited or removed through the application.
        static::updating(fn () => throw new LogicException('Activity log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Activity log entries are immutable.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        $ids = $user->accessibleProjectIds();

        return $ids === null ? $q : $q->whereIn('project_id', $ids ?: [0]);
    }
}
