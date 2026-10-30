<?php

namespace App\Models\Concerns;

use App\Models\Project;
use App\Models\Scopes\ActiveParentScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToProject
{
    /**
     * Records of a soft-deleted project (or, for client-level records, a soft-deleted client) are hidden
     * everywhere and reappear when the project or client is restored.
     */
    public static function bootBelongsToProject(): void
    {
        static::addGlobalScope('active-project', new ActiveParentScope('project_id', 'projects'));
        if (defined(static::class.'::SCOPED_TO_CLIENT')) {
            static::addGlobalScope('active-client', new ActiveParentScope('client_id', 'clients'));
        }
    }

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
        // Count every record ever created in the project (trashed or hidden too) so codes are never reused.
        $count = static::query()->withoutGlobalScopes()->where('project_id', $projectId)->count();

        return sprintf('%s-%03d', $prefix, $count + 1);
    }
}
