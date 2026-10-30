<?php

namespace App\Models;

use App\Enums\Priority;
use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A personal to-do. Private: only its owner may see or change it (see TodoPolicy). */
class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    protected $fillable = ['project_id', 'title', 'notes', 'due_date', 'priority'];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('completed_at');
    }

    /** Open first, then by due date (undated last), then priority. */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderByRaw('completed_at is not null')
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('id');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function isDueToday(): bool
    {
        return ! $this->isDone() && $this->due_date !== null && $this->due_date->isToday();
    }
}
