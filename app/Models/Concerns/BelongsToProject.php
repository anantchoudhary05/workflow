<?php

namespace App\Models\Concerns;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToProject
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /** Restrict to records in projects the user may access. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->accessibleProjectIds();

        return $ids === null ? $query : $query->whereIn($this->qualifyColumn('project_id'), $ids ?: [0]);
    }

    /** Next sequential code within a project, e.g. REQ-007. */
    public static function nextCode(int $projectId, string $prefix): string
    {
        $count = static::withTrashedIfAvailable()->where('project_id', $projectId)->count();

        return sprintf('%s-%03d', $prefix, $count + 1);
    }

    protected static function withTrashedIfAvailable(): Builder
    {
        $query = static::query();

        return method_exists(static::class, 'bootSoftDeletes') ? $query->withTrashed() : $query;
    }
}
