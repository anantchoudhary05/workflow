<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToProject, HasFactory, SoftDeletes;

    // status, blocker_reason, completed_at are changed via TaskWorkflow only.
    protected $fillable = ['project_id', 'milestone_id', 'change_request_id', 'title', 'description', 'assignee_id', 'reviewer_id', 'priority', 'start_date', 'due_date', 'estimated_hours', 'tags', 'acceptance_criteria', 'internal_notes'];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Task $t) => $t->status ??= TaskStatus::Backlog);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class)->withTrashed();
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', TaskStatus::open());
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->whereDate('due_date', '<', today());
    }

    public function scopeDueToday(Builder $q): Builder
    {
        return $q->open()->whereDate('due_date', today());
    }

    public function isOpen(): bool
    {
        return in_array($this->status, TaskStatus::open(), true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date && $this->due_date->lt(today());
    }

    public function nextAction(): string
    {
        return match ($this->status) {
            TaskStatus::Backlog => 'Groom and move to Ready',
            TaskStatus::Ready => 'Start work',
            TaskStatus::InProgress => 'Finish and submit for review',
            TaskStatus::Blocked => 'Resolve blocker',
            TaskStatus::InReview => 'Review by '.($this->reviewer?->name ?? 'reviewer'),
            TaskStatus::ChangesRequested => 'Address review feedback',
            default => '—',
        };
    }
}
