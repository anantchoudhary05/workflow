<?php

namespace App\Models;

use App\Enums\ChangeClassification;
use App\Enums\ChangeRequestStatus;
use App\Enums\Priority;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeRequest extends Model
{
    use BelongsToProject, SoftDeletes;

    protected $fillable = ['title', 'description', 'reason', 'source', 'received_at', 'requested_by', 'reviewer_id', 'priority', 'classification', 'classification_rationale', 'scope_impact', 'extra_hours', 'cost_impact', 'timeline_impact_days'];

    protected function casts(): array
    {
        return [
            'status' => ChangeRequestStatus::class,
            'classification' => ChangeClassification::class,
            'priority' => Priority::class,
            'received_at' => 'date',
            'internal_reviewed_at' => 'datetime',
            'decided_at' => 'datetime',
            'extra_hours' => 'decimal:2',
            'cost_impact' => 'decimal:2',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')->withTrashed();
    }

    public function internalReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_reviewed_by')->withTrashed();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function milestones(): BelongsToMany
    {
        return $this->belongsToMany(Milestone::class);
    }

    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->latest('decided_at');
    }

    public function needsClientApproval(): bool
    {
        return $this->classification === ChangeClassification::NewScope;
    }

    public function hasRequiredApprovals(): bool
    {
        if (! $this->needsClientApproval()) {
            return true;
        }

        return $this->internal_reviewed_at !== null && $this->decided_at !== null && filled($this->approval_evidence);
    }
}
