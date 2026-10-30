<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\RequirementCategory;
use App\Enums\RequirementStatus;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Requirement extends Model
{
    use BelongsToProject, SoftDeletes;

    protected $fillable = ['title', 'description', 'category', 'priority', 'acceptance_criteria', 'requested_by', 'owner_id', 'source', 'module', 'estimated_hours', 'notes'];

    protected function casts(): array
    {
        return [
            'category' => RequirementCategory::class,
            'priority' => Priority::class,
            'status' => RequirementStatus::class,
            'estimated_hours' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    /** True once this requirement is part of an approved scope baseline. */
    /** Part of the currently approved scope baseline: changes must go through a change request. */
    public function isBaselined(): bool
    {
        $scope = $this->project?->approvedScope;

        return $scope !== null && collect($scope->snapshot)->contains('id', $this->id);
    }
}
