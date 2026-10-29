<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use App\Enums\Priority;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    use BelongsToProject, SoftDeletes;

    protected $fillable = ['name', 'description', 'owner_id', 'start_date', 'due_date', 'priority', 'weight', 'acceptance_criteria', 'criteria_met', 'deliverables', 'requires_approval', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return [
            'status' => MilestoneStatus::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'criteria_met' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Milestone::class, 'milestone_dependencies', 'milestone_id', 'depends_on_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->lt(today()) && ! in_array($this->status, [MilestoneStatus::Completed, MilestoneStatus::Cancelled], true);
    }

    public function unmetDependencies(): Collection
    {
        return $this->dependencies->filter(fn ($d) => $d->status !== MilestoneStatus::Completed);
    }
}
